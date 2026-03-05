<?php
/**
 * Authenticator
 * Handles user authentication and session management
 */

namespace P2P\Auth;

use P2P\Core\Config;
use P2P\Core\Database;
use P2P\Models\User;

class Authenticator
{
    private ?User $user = null;
    private ?array $tokenPayload = null;

    /**
     * Register new user
     */
    public function register(string $username, string $email, string $password): array
    {
        $userModel = new User();

        // Check if username exists
        if ($userModel->findByUsername($username)) {
            return ['success' => false, 'error' => 'Username already exists'];
        }

        // Check if email exists
        if ($userModel->findByEmail($email)) {
            return ['success' => false, 'error' => 'Email already exists'];
        }

        // Validate password
        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters'];
        }

        // Create user
        try {
            $userModel->create($username, $email, $password);
            
            // Generate token
            $token = $this->generateToken($userModel->getId(), $username, $email);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $userModel->getId(),
                    'username' => $username,
                    'email' => $email,
                ],
                'token' => $token
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Login user
     */
    public function login(string $username, string $password): array
    {
        $userModel = new User();

        // Find user by username or email
        $user = $userModel->findByUsername($username);
        if (!$user) {
            $user = $userModel->findByEmail($username);
        }

        if (!$user) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        // Verify password
        if (!$user->verifyPassword($password)) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        // Update online status
        $userModel->setOnlineStatus($user->getId(), true);

        // Generate token
        $token = $this->generateToken($user->getId(), $user->getUsername(), $user->getEmail());

        // Save session
        $this->saveSession($user->getId(), $token);

        return [
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'display_name' => $user->getDisplayName(),
                'avatar' => $user->getAvatar(),
            ],
            'token' => $token
        ];
    }

    /**
     * Logout user
     */
    public function logout(int $userId): bool
    {
        $userModel = new User();
        $userModel->setOnlineStatus($userId, false);

        // Delete sessions
        $db = Database::getInstance();
        $db->query("DELETE FROM sessions WHERE user_id = ?", [$userId]);
        
        // Remove from online peers
        $db->query("DELETE FROM online_peers WHERE user_id = ?", [$userId]);

        return true;
    }

    /**
     * Validate token
     */
    public function validateToken(?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        JWT::init();
        $payload = JWT::decode($token);

        if (!$payload) {
            return null;
        }

        // Check if session exists
        $db = Database::getInstance();
        $session = $db->fetch("SELECT * FROM sessions WHERE token = ? AND expires_at > NOW()", [$token]);

        if (!$session) {
            return null;
        }

        $this->tokenPayload = $payload;
        return $payload;
    }

    /**
     * Get current user
     */
    public function getCurrentUser(?string $token): ?User
    {
        $payload = $this->validateToken($token);
        
        if (!$payload) {
            return null;
        }

        $userModel = new User();
        $this->user = $userModel->findById($payload['user_id'] ?? 0);
        
        return $this->user;
    }

    /**
     * Generate JWT token
     */
    private function generateToken(int $userId, string $username, string $email): string
    {
        JWT::init();
        
        return JWT::encode([
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
        ]);
    }

    /**
     * Save session to database
     */
    private function saveSession(int $userId, string $token): void
    {
        $db = Database::getInstance();
        
        // Delete old sessions
        $db->query("DELETE FROM sessions WHERE user_id = ?", [$userId]);
        
        // Calculate expiry
        $expiry = date('Y-m-d H:i:s', time() + Config::get('auth.jwt_expiry', 86400));
        
        // Insert new session
        $db->query(
            "INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)",
            [
                $userId,
                $token,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $expiry
            ]
        );
    }

    /**
     * Refresh token
     */
    public function refreshToken(string $oldToken): ?string
    {
        $payload = $this->validateToken($oldToken);
        
        if (!$payload) {
            return null;
        }

        return $this->generateToken($payload['user_id'], $payload['username'], $payload['email']);
    }
}
