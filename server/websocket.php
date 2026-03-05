<?php
/**
 * Simple WebSocket Server for P2P Communication
 * Fixed version with proper message handling
 * 
 * Run with: php server/websocket.php
 */

class P2PWebSocketServer
{
    private string $host;
    private int $port;
    private $socket;
    private array $clients = [];
    private array $connections = []; // user_id => connection key
    
    public function __construct(string $host = '127.0.0.1', int $port = 8081)
    {
        $this->host = $host;
        $this->port = $port;
    }
    
    public function start(): void
    {
        // Create socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, $this->host, $this->port);
        socket_listen($this->socket);
        
        echo "P2P WebSocket Server started on ws://{$this->host}:{$this->port}\n";
        echo "Waiting for connections...\n";
        
        $this->run();
    }
    
    private function run(): void
    {
        while (true) {
            // Get all sockets to watch
            $read = [$this->socket];
            foreach ($this->clients as $client) {
                $read[] = $client['socket'];
            }
            
            // Wait for activity
            $write = null;
            $except = null;
            socket_select($read, $write, $except, 0, 100000);
            
            // Check for new connections
            if (in_array($this->socket, $read)) {
                $newClient = @socket_accept($this->socket);
                if ($newClient !== false) {
                    $this->clients[] = [
                        'socket' => $newClient,
                        'handshake' => false,
                        'user_id' => null,
                        'peer_id' => null
                    ];
                    
                    // Remove server socket from read list
                    $key = array_search($this->socket, $read);
                    unset($read[$key]);
                    
                    echo "New connection accepted\n";
                }
            }
            
            // Process each client
            foreach ($this->clients as $key => $client) {
                if (in_array($client['socket'], $read)) {
                    $data = @socket_read($client['socket'], 8192);
                    
                    if ($data === false || $data === '') {
                        $this->handleDisconnect($key);
                        continue;
                    }
                    
                    if (!$client['handshake']) {
                        $this->performHandshake($this->clients[$key], $data);
                    } else {
                        $this->handleMessage($key, $data);
                    }
                }
            }
        }
    }
    
    private function performHandshake(array &$client, string $data): void
    {
        // Parse headers
        $headers = [];
        $lines = explode("\r\n", $data);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }
        
        if (!isset($headers['sec-websocket-key'])) {
            return;
        }
        
        // Generate accept key
        $key = $headers['sec-websocket-key'];
        $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        // Send handshake response
        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n";
        $response .= "\r\n";
        
        socket_write($client['socket'], $response, strlen($response));
        $client['handshake'] = true;
        
        echo "Client handshake completed\n";
    }
    
    private function handleMessage(int $key, string $data): void
    {
        $messages = $this->decodeMessages($data);
        
        foreach ($messages as $message) {
            if (empty($message)) continue;
            
            $client = &$this->clients[$key];
            $jsonData = json_decode($message, true);
            
            if (!$jsonData || !isset($jsonData['type'])) {
                continue;
            }
            
            echo "Received: " . $jsonData['type'] . "\n";
            
            switch ($jsonData['type']) {
                case 'register':
                    $this->handleRegister($key, $jsonData);
                    break;
                case 'offer':
                case 'answer':
                case 'ice-candidate':
                    $this->handleSignaling($key, $jsonData);
                    break;
                case 'message':
                    $this->handleChatMessage($key, $jsonData);
                    break;
                case 'typing':
                    $this->handleTyping($key, $jsonData);
                    break;
                case 'ping':
                    $this->sendFrame($client['socket'], json_encode(['type' => 'pong']));
                    break;
            }
        }
    }
    
    private function handleRegister(int $key, array $data): void
    {
        $userId = $data['user_id'] ?? 0;
        $peerId = $data['peer_id'] ?? uniqid('peer_');
        
        if ($userId) {
            $this->clients[$key]['user_id'] = $userId;
            $this->clients[$key]['peer_id'] = $peerId;
            $this->connections[$userId] = $key;
            
            // Send registration success
            $this->sendFrame($this->clients[$key]['socket'], json_encode([
                'type' => 'registered',
                'peer_id' => $peerId,
                'user_id' => $userId
            ]));
            
            $this->broadcastUserStatus($userId, true);
            
            echo "User {$userId} registered with peer ID {$peerId}\n";
        }
    }
    
    private function handleSignaling(int $key, array $data): void
    {
        $targetUserId = $data['target_user_id'] ?? 0;
        
        if ($targetUserId && isset($this->connections[$targetUserId])) {
            $targetKey = $this->connections[$targetUserId];
            
            if (isset($this->clients[$targetKey])) {
                $this->sendFrame($this->clients[$targetKey]['socket'], json_encode([
                    'type' => $data['type'],
                    'sender_user_id' => $this->clients[$key]['user_id'],
                    'sender_peer_id' => $this->clients[$key]['peer_id'],
                    'sdp' => $data['sdp'] ?? null,
                    'candidate' => $data['candidate'] ?? null,
                    'connection_id' => $data['connection_id'] ?? null
                ]));
                
                echo "Forwarded {$data['type']} to user {$targetUserId}\n";
            }
        }
    }
    
    private function handleChatMessage(int $key, array $data): void
    {
        $targetUserId = $data['target_user_id'] ?? 0;
        
        echo "Chat message to user {$targetUserId}\n";
        
        if ($targetUserId && isset($this->connections[$targetUserId])) {
            $targetKey = $this->connections[$targetUserId];
            
            if (isset($this->clients[$targetKey])) {
                $this->sendFrame($this->clients[$targetKey]['socket'], json_encode([
                    'type' => 'message',
                    'sender_user_id' => $this->clients[$key]['user_id'],
                    'sender_peer_id' => $this->clients[$key]['peer_id'],
                    'content' => $data['content'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
                
                // Confirm delivery
                $this->sendFrame($this->clients[$key]['socket'], json_encode([
                    'type' => 'message_sent',
                    'target_user_id' => $targetUserId
                ]));
                
                echo "Message sent to user {$targetUserId}\n";
            }
        }
    }
    
    private function handleTyping(int $key, array $data): void
    {
        $targetUserId = $data['target_user_id'] ?? 0;
        
        if ($targetUserId && isset($this->connections[$targetUserId])) {
            $targetKey = $this->connections[$targetUserId];
            
            if (isset($this->clients[$targetKey])) {
                $this->sendFrame($this->clients[$targetKey]['socket'], json_encode([
                    'type' => 'typing',
                    'sender_user_id' => $this->clients[$key]['user_id'],
                    'is_typing' => $data['is_typing'] ?? true
                ]));
            }
        }
    }
    
    private function broadcastUserStatus(int $userId, bool $isOnline): void
    {
        $message = json_encode([
            'type' => 'user_status',
            'user_id' => $userId,
            'is_online' => $isOnline
        ]);
        
        foreach ($this->clients as $client) {
            if ($client['user_id'] != $userId && $client['handshake']) {
                $this->sendFrame($client['socket'], $message);
            }
        }
    }
    
    private function handleDisconnect(int $key): void
    {
        $userId = $this->clients[$key]['user_id'] ?? null;
        
        if ($userId && isset($this->connections[$userId])) {
            unset($this->connections[$userId]);
            $this->broadcastUserStatus($userId, false);
            echo "User {$userId} disconnected\n";
        }
        
        @socket_close($this->clients[$key]['socket']);
        unset($this->clients[$key]);
        
        echo "Connection closed\n";
    }
    
    private function decodeMessages(string $data): array
    {
        $messages = [];
        $offset = 0;
        $dataLength = strlen($data);
        
        while ($offset < $dataLength) {
            if ($dataLength < 2) break;
            
            $firstByte = ord($data[$offset]);
            $secondByte = isset($data[$offset + 1]) ? ord($data[$offset + 1]) : 0;
            
            // Check if it's a text frame (0x81)
            if (($firstByte & 0x0F) !== 0x01) {
                $offset++;
                continue;
            }
            
            $isMasked = ($secondByte & 0x80) !== 0;
            $length = $secondByte & 127;
            
            if ($length === 126) {
                if ($dataLength < 4) break;
                $length = (ord($data[$offset + 2]) << 8) | ord($data[$offset + 3]);
                $maskStart = 4;
                $payloadStart = 8;
            } elseif ($length === 127) {
                if ($dataLength < 10) break;
                $length = 0;
                for ($i = 0; $i < 8; $i++) {
                    $length = ($length << 8) | ord($data[$offset + 2 + $i]);
                }
                $maskStart = 10;
                $payloadStart = 14;
            } else {
                $maskStart = 2;
                $payloadStart = 6;
            }
            
            if ($dataLength < $payloadStart + $length) break;
            
            $payload = substr($data, $payloadStart, $length);
            
            if ($isMasked) {
                $mask = substr($data, $offset + $maskStart, 4);
                $message = '';
                for ($i = 0; $i < strlen($payload); $i++) {
                    $message .= $payload[$i] ^ $mask[$i % 4];
                }
            } else {
                $message = $payload;
            }
            
            $messages[] = $message;
            $offset = $payloadStart + $length;
        }
        
        return $messages;
    }
    
    private function sendFrame($socket, string $message): void
    {
        // Create text frame
        $length = strlen($message);
        
        if ($length <= 125) {
            $frame = chr(0x81) . chr($length) . $message;
        } elseif ($length <= 65535) {
            $frame = chr(0x81) . chr(126) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF) . $message;
        } else {
            $frame = chr(0x81) . chr(127);
            for ($i = 7; $i >= 0; $i--) {
                $frame .= chr(($length >> (8 * $i)) & 0xFF);
            }
            $frame .= $message;
        }
        
        @socket_write($socket, $frame, strlen($frame));
    }
}

// Start the server
$server = new P2PWebSocketServer('127.0.0.1', 8081);
$server->start();
