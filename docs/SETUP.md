# P2P Network Application - Complete Setup Guide

## Overview

This is a complete P2P (Peer-to-Peer) web application built with PHP that enables:
- User registration and authentication
- Real-time messaging via WebSocket
- P2P file sharing via WebRTC
- Peer discovery and connections

---

## Prerequisites

- **XAMPP** (or any Apache/MySQL/PHP stack)
- **PHP 8.2+** with sockets extension enabled
- **MySQL 5.7+**
- **Web browser** with WebRTC support (Chrome, Firefox, Edge)

---

## Step-by-Step Installation

### Step 1: Enable PHP Sockets Extension

1. Open php.ini file:
   - Location: `C:\xampp\php\php.ini`

2. Find and enable the sockets extension:
   ```ini
   ;extension=sockets
   ```
   Change to:
   ```ini
   extension=sockets
   ```

3. Save the file

### Step 2: Setup MySQL Database

1. Open phpMyAdmin (http://localhost/phpmyadmin)

2. Create a new database:
   - Name: `p2p_network`
   - Collation: `utf8mb4_unicode_ci`

3. Click on the database, then go to "Import" tab

4. Import the database schema:
   - Select file: `config/database.sql`
   - Click "Go"

### Step 3: Configure Database Credentials

Edit `config/config.php`:

```php
'database' => [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'p2p_network',
    'username' => 'root',        // Your MySQL username
    'password' => '',            // Your MySQL password
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
],
```

### Step 4: Create Required Directories

Create these folders in the project root:
```
p2p/
├── logs/          (empty directory)
└── uploads/       (empty directory)
```

---

## Running the Application

You need to run **TWO** separate servers:

### Server 1: WebSocket Server (Real-time)

Open a terminal and run:

```bash
c:\xampp\php\php.exe c:\xampp\htdocs\test\kilo\p2p\server\websocket.php
```

Expected output:
```
P2P WebSocket Server started on ws://127.0.0.1:8081
Waiting for connections...
```

Keep this terminal open!

---

### Server 2: PHP Development Server (Web Interface)

Open a **NEW** terminal and run:

```bash
c:\xampp\php\php.exe -S localhost:8080 -t c:\xampp\htdocs\test\kilo\p2p\public
```

Or if using XAMPP:

1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf`

2. Add:
```apache
<VirtualHost *:8080>
    DocumentRoot "C:/xampp/htdocs/test/kilo/p2p/public"
    ServerName localhost
    <Directory "C:/xampp/htdocs/test/kilo/p2p/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

3. Start Apache from XAMPP Control Panel

4. Open: http://localhost:8080

---

## How to Use the Application

### 1. Register/Login

- Open http://localhost:8080 in your browser
- Use sample accounts:
  - Username: `admin`, `alice`, or `bob`
  - Password: `password`

Or register a new account by clicking "Register" tab

### 2. Connect with Peers

1. After login, you'll see the peer list
2. Click "Connect" on any user to send a connection request
3. The other user needs to accept (if using different accounts)

### 3. Send Messages

1. Click on a connected peer to open chat
2. Type message and press Enter or click Send
3. Messages are delivered in real-time via WebSocket

### 4. Establish P2P Connection

1. In a chat window, click "🔗 P2P" button
2. This creates a direct peer-to-peer connection using WebRTC
3. Once connected, you can share files directly

---

## Testing with Multiple Users

### Option A: Different Browser Windows
1. Open http://localhost:8080 in Chrome (login as Alice)
2. Open http://localhost:8080 in Firefox (login as Bob)
3. Connect and chat between them

### Option B: Incognito Mode
1. Login as one user in normal window
2. Open incognito window and login as another user

---

## Project Structure

```
p2p/
├── config/
│   ├── config.php          # Application configuration
│   └── database.sql        # MySQL database schema
├── public/
│   ├── index.php           # Main entry point
│   ├── index.html          # Frontend HTML
│   ├── css/styles.css      # Styling
│   ├── js/app.js           # Frontend JavaScript
│   └── autoload.php        # Class autoloader
├── server/
│   └── websocket.php       # WebSocket server
├── src/
│   ├── Auth/               # Authentication (JWT)
│   ├── Controllers/       # API endpoints
│   ├── Core/              # Core utilities
│   └── Models/            # Database models
├── logs/                  # Application logs
└── uploads/               # File uploads
```

---

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/auth/register` | POST | Register new user |
| `/api/auth/login` | POST | User login |
| `/api/auth/logout` | POST | User logout |
| `/api/users/me` | GET | Get current user |
| `/api/users/search?q=text` | GET | Search users |
| `/api/peers` | GET | Get connected peers |
| `/api/peers/discover` | GET | Discover peers |
| `/api/peers/connect` | POST | Request connection |
| `/api/messages/conversations` | GET | Get conversations |
| `/api/messages/chat/{id}` | GET | Get chat messages |
| `/api/messages/send` | POST | Send message |

---

## Troubleshooting

### Issue: "socket_create() not found"
**Solution:** Enable PHP sockets extension in php.ini (see Step 1)

### Issue: Database connection error
**Solution:** Check database credentials in `config/config.php`

### Issue: WebSocket connection failed
**Solution:** 
- Make sure WebSocket server is running
- Check firewall allows port 8081

### Issue: Messages not sending
**Solution:**
- Check browser console (F12) for JavaScript errors
- Make sure both users are connected to WebSocket server

### Issue: P2P connection failed
**Solution:**
- Use HTTPS in production (required for some WebRTC features)
- Check STUN server configuration

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                     P2P Network Architecture                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────┐     ┌──────────┐     ┌──────────┐          │
│  │  User A  │     │ WebSocket│     │  User B  │          │
│  │ (Browser)│◄───►│  Server  │◄───►│ (Browser)│          │
│  └────┬─────┘     └──────────┘     └────┬─────┘          │
│       │                                   │                │
│       │         ┌──────────┐              │                │
│       │         │   MySQL  │              │                │
│       └────────►│ Database │◄─────────────┘                │
│                 └──────────┘                               │
│                                                             │
│  ┌─────────────────────────────────────────────┐           │
│  │              WebRTC P2P Connection          │           │
│  │  (Direct connection for file transfer)      │           │
│  └─────────────────────────────────────────────┘           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Security Notes

- Change the JWT secret in production: `config/config.php`
- Use HTTPS in production
- Implement rate limiting
- Add input validation
- Use prepared statements (already implemented)

---

## Credits

Built with:
- PHP 8.2
- MySQL
- WebSocket (PHP Sockets)
- WebRTC API
