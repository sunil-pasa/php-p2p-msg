# P2P Network Application

A peer-to-peer web application built with PHP, WebSocket, and WebRTC for real-time communication, messaging, and file sharing between users.

## Features

- **User Authentication**: Registration and login with JWT tokens
- **Peer Discovery**: Find and connect with other users
- **Real-time Messaging**: Instant messaging via WebSocket
- **P2P Connections**: Direct peer-to-peer connections using WebRTC
- **File Transfer**: Share files directly between peers
- **Online Status**: Real-time online/offline indicators
- **Responsive Design**: Works on desktop and mobile devices

## Architecture

```
P2P Network
├── config/           # Configuration files
├── public/           # Frontend (HTML, CSS, JS)
├── server/           # WebSocket server
├── src/
│   ├── Auth/        # Authentication (JWT, Authenticator)
│   ├── Controllers/  # API controllers
│   ├── Core/        # Core classes (Config, Database, Router)
│   └── Models/      # Database models
├── vendor/           # Composer dependencies
└── logs/            # Application logs
```

## Technology Stack

- **Backend**: PHP 8.2+
- **Database**: MySQL
- **Real-time**: WebSocket (Ratchet)
- **P2P Protocol**: WebRTC
- **Authentication**: JWT

## Installation

### Prerequisites

- PHP 8.2 or higher
- MySQL 5.7+
- Composer
- Web browser with WebRTC support

### Step 1: Clone and Install Dependencies

```bash
cd c:/xampp/htdocs/test/kilo/p2p
composer install
```

### Step 2: Configure Database

1. Create a MySQL database named `p2p_network`
2. Import the schema from `config/database.sql`
3. Update database credentials in `config/config.php`

```php
'database' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'p2p_network',
    'username' => 'root',
    'password' => 'your-password',
],
```

### Step 3: Start the WebSocket Server

In a separate terminal:

```bash
php server/websocket.php
```

Or use composer:

```bash
composer run ws
```

The WebSocket server will start on `ws://127.0.0.1:8081`

### Step 4: Start PHP Development Server

```bash
php -S localhost:8080 -t public
```

Or use XAMPP's Apache - point your document root to the `public` folder.

## Usage

1. Open `http://localhost:8080` in your browser
2. Register a new account or login
3. Discover peers in the sidebar
4. Click "Connect" to send a connection request
5. Once connected, start chatting
6. Click "P2P" to establish a direct peer-to-peer connection for file transfer

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout

### Users
- `GET /api/users/me` - Get current user
- `PUT /api/users/me` - Update profile
- `GET /api/users/search?q=query` - Search users
- `GET /api/users/online` - Get online users

### Peers
- `GET /api/peers` - Get connected peers
- `GET /api/peers/discover` - Discover peers
- `POST /api/peers/connect` - Request connection
- `POST /api/peers/accept` - Accept connection
- `POST /api/peers/reject` - Reject connection
- `DELETE /api/peers/remove` - Remove connection

### Messages
- `GET /api/messages/conversations` - Get all conversations
- `GET /api/messages/chat/{id}` - Get chat messages
- `POST /api/messages/send` - Send message
- `POST /api/messages/mark-read` - Mark as read

### WebRTC
- `POST /api/ice/candidates` - Save ICE candidates
- `GET /api/ice/candidates/{connectionId}` - Get ICE candidates
- `GET /api/ws/config` - Get WebSocket and ICE configuration

## WebRTC Flow

1. User A clicks "P2P" to initiate connection
2. Browser creates RTCPeerConnection with STUN servers
3. Creates an offer (SDP) and sends via WebSocket to User B
4. User B receives offer, creates answer, sends back
5. Both peers exchange ICE candidates
6. Direct P2P data channel established
7. Files/messages can now be sent directly

## Security Considerations

- JWT tokens with configurable expiry
- Password hashing with bcrypt
- CORS protection
- Input validation and sanitization
- Prepared statements for SQL queries
- HTTPS recommended for production

## Troubleshooting

### WebSocket Connection Failed
- Ensure the WebSocket server is running
- Check firewall settings for port 8081

### Database Connection Error
- Verify MySQL credentials in config.php
- Ensure database exists and tables are created

### WebRTC Not Working
- Use HTTPS in production (required for some browser features)
- Check STUN/TURN server configuration
- Ensure browser supports WebRTC

## License

MIT License

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
