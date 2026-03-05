<?php
/**
 * API Controller
 * Handles all REST API endpoints
 */

namespace P2P\Controllers;

use P2P\Auth\Authenticator;
use P2P\Core\Config;
use P2P\Core\Router;
use P2P\Core\Database;
use P2P\Models\User;
use P2P\Models\Message;
use P2P\Models\Peer;

class ApiController
{
    private Router $router;
    private Authenticator $auth;
    private array $currentUser = [];

    public function __construct()
    {
        $this->router = new Router();
        $this->auth = new Authenticator();
        
        // Initialize database
        Config::init();
        Database::getInstance();
        
        // Apply auth middleware
        $this->router->addMiddleware([$this, 'authMiddleware']);
        
        $this->registerRoutes();
    }

    /**
     * Auth middleware
     */
    public function authMiddleware(string $method, string $path): bool
    {
        // Public routes
        $publicRoutes = [
            '/api/auth/register',
            '/api/auth/login',
            '/api/users/search',
        ];

        foreach ($publicRoutes as $route) {
            if (strpos($path, $route) === 0) {
                return true;
            }
        }

        // Check auth for other routes
        $token = Router::getAuthHeader();
        $token = str_replace('Bearer ', '', $token ?? '');
        
        $user = $this->auth->getCurrentUser($token);
        
        if ($user) {
            $this->currentUser = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
            ];
            return true;
        }

        return false;
    }

    /**
     * Register all API routes
     */
    private function registerRoutes(): void
    {
        // Auth routes
        $this->router->post('/api/auth/register', [$this, 'register']);
        $this->router->post('/api/auth/login', [$this, 'login']);
        $this->router->post('/api/auth/logout', [$this, 'logout']);
        
        // User routes
        $this->router->get('/api/users/me', [$this, 'getCurrentUser']);
        $this->router->put('/api/users/me', [$this, 'updateProfile']);
        $this->router->get('/api/users/search', [$this, 'searchUsers']);
        $this->router->get('/api/users/online', [$this, 'getOnlineUsers']);
        
        // Peer routes
        $this->router->get('/api/peers', [$this, 'getPeers']);
        $this->router->get('/api/peers/discover', [$this, 'discoverPeers']);
        $this->router->post('/api/peers/connect', [$this, 'requestConnection']);
        $this->router->post('/api/peers/accept', [$this, 'acceptConnection']);
        $this->router->post('/api/peers/reject', [$this, 'rejectConnection']);
        $this->router->delete('/api/peers/remove', [$this, 'removeConnection']);
        
        // Message routes
        $this->router->get('/api/messages/conversations', [$this, 'getConversations']);
        $this->router->get('/api/messages/chat/{id}', [$this, 'getChat']);
        $this->router->post('/api/messages/send', [$this, 'sendMessage']);
        $this->router->post('/api/messages/mark-read', [$this, 'markAsRead']);
        
        // ICE candidates (for WebRTC)
        $this->router->post('/api/ice/candidates', [$this, 'saveIceCandidates']);
        $this->router->get('/api/ice/candidates/{connectionId}', [$this, 'getIceCandidates']);
        
        // WebSocket info
        $this->router->get('/api/ws/config', [$this, 'getWebSocketConfig']);
    }

    /**
     * Run the router
     */
    public function run(): void
    {
        $this->router->dispatch(Router::getMethod(), Router::getPath());
    }

    // ==================== AUTH ENDPOINTS ====================

    /**
     * Register new user
     */
    public function register(): void
    {
        $data = Router::getJsonBody();
        
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $this->router->jsonResponse(['error' => 'Missing required fields'], 400);
            return;
        }

        $result = $this->auth->register($username, $email, $password);
        
        if ($result['success']) {
            $this->router->jsonResponse($result, 201);
        } else {
            $this->router->jsonResponse($result, 400);
        }
    }

    /**
     * User login
     */
    public function login(): void
    {
        $data = Router::getJsonBody();
        
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->router->jsonResponse(['error' => 'Missing credentials'], 400);
            return;
        }

        $result = $this->auth->login($username, $password);
        
        if ($result['success']) {
            $this->router->jsonResponse($result);
        } else {
            $this->router->jsonResponse($result, 401);
        }
    }

    /**
     * User logout
     */
    public function logout(): void
    {
        $this->auth->logout($this->currentUser['id']);
        $this->router->jsonResponse(['success' => true]);
    }

    // ==================== USER ENDPOINTS ====================

    /**
     * Get current user
     */
    public function getCurrentUser(): void
    {
        $userModel = new User();
        $user = $userModel->findById($this->currentUser['id']);
        
        if ($user) {
            $this->router->jsonResponse([
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'display_name' => $user->getDisplayName(),
                'avatar' => $user->getAvatar(),
                'is_online' => $user->isOnline(),
            ]);
        } else {
            $this->router->jsonResponse(['error' => 'User not found'], 404);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(): void
    {
        $data = Router::getJsonBody();
        $userModel = new User();
        
        $result = $userModel->updateProfile($this->currentUser['id'], $data);
        
        if ($result) {
            $this->router->jsonResponse(['success' => true]);
        } else {
            $this->router->jsonResponse(['error' => 'Update failed'], 400);
        }
    }

    /**
     * Search users
     */
    public function searchUsers(): void
    {
        $query = $_GET['q'] ?? '';
        
        if (strlen($query) < 2) {
            $this->router->jsonResponse(['error' => 'Query too short'], 400);
            return;
        }

        $userModel = new User();
        $users = $userModel->search($query);
        
        $this->router->jsonResponse(['users' => $users]);
    }

    /**
     * Get online users
     */
    public function getOnlineUsers(): void
    {
        $userModel = new User();
        $users = $userModel->getOnlineUsers();
        $this->router->jsonResponse(['users' => $users]);
    }

    // ==================== PEER ENDPOINTS ====================

    /**
     * Get user's connections
     */
    public function getPeers(): void
    {
        $peerModel = new Peer();
        $connections = $peerModel->getConnections($this->currentUser['id']);
        $this->router->jsonResponse(['peers' => $connections]);
    }

    /**
     * Discover peers
     */
    public function discoverPeers(): void
    {
        $peerModel = new Peer();
        $peers = $peerModel->discoverPeers($this->currentUser['id']);
        $this->router->jsonResponse(['peers' => $peers]);
    }

    /**
     * Request connection to peer
     */
    public function requestConnection(): void
    {
        $data = Router::getJsonBody();
        $targetUserId = $data['user_id'] ?? 0;
        
        if (!$targetUserId) {
            $this->router->jsonResponse(['error' => 'User ID required'], 400);
            return;
        }

        $peerModel = new Peer();
        $result = $peerModel->requestConnection($this->currentUser['id'], $targetUserId);
        
        if ($result) {
            $this->router->jsonResponse(['success' => true, 'message' => 'Connection request sent']);
        } else {
            $this->router->jsonResponse(['error' => 'Connection already exists'], 400);
        }
    }

    /**
     * Accept connection
     */
    public function acceptConnection(): void
    {
        $data = Router::getJsonBody();
        $fromUserId = $data['user_id'] ?? 0;
        
        $peerModel = new Peer();
        $peerModel->acceptConnection($fromUserId, $this->currentUser['id']);
        
        $this->router->jsonResponse(['success' => true]);
    }

    /**
     * Reject connection
     */
    public function rejectConnection(): void
    {
        $data = Router::getJsonBody();
        $fromUserId = $data['user_id'] ?? 0;
        
        $peerModel = new Peer();
        $peerModel->rejectConnection($fromUserId, $this->currentUser['id']);
        
        $this->router->jsonResponse(['success' => true]);
    }

    /**
     * Remove connection
     */
    public function removeConnection(): void
    {
        $data = Router::getJsonBody();
        $userId = $data['user_id'] ?? 0;
        
        $peerModel = new Peer();
        $peerModel->removeConnection($this->currentUser['id'], $userId);
        
        $this->router->jsonResponse(['success' => true]);
    }

    // ==================== MESSAGE ENDPOINTS ====================

    /**
     * Get all conversations
     */
    public function getConversations(): void
    {
        $messageModel = new Message();
        $conversations = $messageModel->getConversations($this->currentUser['id']);
        $this->router->jsonResponse(['conversations' => $conversations]);
    }

    /**
     * Get chat messages
     */
    public function getChat(): void
    {
        $path = Router::getPath();
        preg_match('/\/api\/messages\/chat\/(\d+)/', $path, $matches);
        $otherUserId = $matches[1] ?? 0;
        
        if (!$otherUserId) {
            $this->router->jsonResponse(['error' => 'User ID required'], 400);
            return;
        }

        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $messageModel = new Message();
        $messages = $messageModel->getConversation($this->currentUser['id'], $otherUserId, $limit, $offset);
        
        $this->router->jsonResponse(['messages' => array_reverse($messages)]);
    }

    /**
     * Send message
     */
    public function sendMessage(): void
    {
        $data = Router::getJsonBody();
        $receiverId = $data['receiver_id'] ?? 0;
        $content = $data['content'] ?? '';
        
        if (!$receiverId || empty($content)) {
            $this->router->jsonResponse(['error' => 'Missing required fields'], 400);
            return;
        }

        $messageModel = new Message();
        $messageId = $messageModel->send($this->currentUser['id'], $receiverId, $content);
        
        $this->router->jsonResponse(['success' => true, 'message_id' => $messageId]);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(): void
    {
        $data = Router::getJsonBody();
        $senderId = $data['sender_id'] ?? 0;
        
        $messageModel = new Message();
        $messageModel->markAsRead($this->currentUser['id'], $senderId);
        
        $this->router->jsonResponse(['success' => true]);
    }

    // ==================== ICE CANDIDATE ENDPOINTS ====================

    /**
     * Save ICE candidates
     */
    public function saveIceCandidates(): void
    {
        $data = Router::getJsonBody();
        
        $connectionId = $data['connection_id'] ?? '';
        $candidates = $data['candidates'] ?? [];
        
        if (empty($connectionId) || empty($candidates)) {
            $this->router->jsonResponse(['error' => 'Missing data'], 400);
            return;
        }

        $messageModel = new Message();
        
        foreach ($candidates as $candidate) {
            $messageModel->saveIceCandidate(
                $connectionId,
                $this->currentUser['id'],
                $candidate['candidate'] ?? '',
                $candidate['sdpMid'] ?? null,
                $candidate['sdpMidLineIndex'] ?? null
            );
        }
        
        $this->router->jsonResponse(['success' => true]);
    }

    /**
     * Get ICE candidates
     */
    public function getIceCandidates(): void
    {
        $path = Router::getPath();
        preg_match('/\/api\/ice\/candidates\/([^\/]+)/', $path, $matches);
        $connectionId = $matches[1] ?? '';
        
        if (empty($connectionId)) {
            $this->router->jsonResponse(['error' => 'Connection ID required'], 400);
            return;
        }

        $messageModel = new Message();
        $candidates = $messageModel->getIceCandidates($connectionId, $this->currentUser['id']);
        
        $this->router->jsonResponse(['candidates' => $candidates]);
    }

    // ==================== WEBSOCKET CONFIG ====================

    /**
     * Get WebSocket configuration
     */
    public function getWebSocketConfig(): void
    {
        $config = Config::all();
        $wsConfig = $config['websocket'] ?? [];
        
        $this->router->jsonResponse([
            'ws_url' => "ws://{$wsConfig['host']}:{$wsConfig['port']}",
            'stun_servers' => $config['p2p']['stun_servers'] ?? [],
            'turn_servers' => $config['p2p']['turn_servers'] ?? [],
        ]);
    }
}
