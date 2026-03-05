<?php
/**
 * User Model
 * Handles user-related database operations
 */

namespace P2P\Models;

use P2P\Core\Database;

class User
{
    private ?Database $db = null;
    private int $id;
    private string $username;
    private string $email;
    private ?string $passwordHash;
    private ?string $displayName;
    private ?string $avatar;
    private ?string $publicKey;
    private bool $isOnline;
    private ?string $lastSeen;
    private string $createdAt;
    private string $updatedAt;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create new user
     */
    public function create(string $username, string $email, string $password): bool
    {
        $this->username = $username;
        $this->email = $email;
        $this->passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $this->displayName = $username;
        $this->isOnline = false;

        $sql = "INSERT INTO users (username, email, password_hash, display_name) VALUES (?, ?, ?, ?)";
        $this->db->query($sql, [$this->username, $this->email, $this->passwordHash, $this->displayName]);
        
        $this->id = (int)$this->db->lastInsertId();
        return true;
    }

    /**
     * Find user by ID
     */
    public function findById(int $id): ?User
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        $result = $this->db->fetch($sql, [$id]);
        
        if ($result) {
            return $this->hydrate($result);
        }
        return null;
    }

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?User
    {
        $sql = "SELECT * FROM users WHERE username = ?";
        $result = $this->db->fetch($sql, [$username]);
        
        if ($result) {
            return $this->hydrate($result);
        }
        return null;
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        $sql = "SELECT * FROM users WHERE email = ?";
        $result = $this->db->fetch($sql, [$email]);
        
        if ($result) {
            return $this->hydrate($result);
        }
        return null;
    }

    /**
     * Get all online users
     */
    public function getOnlineUsers(): array
    {
        $sql = "SELECT id, username, display_name, avatar FROM users WHERE is_online = 1";
        return $this->db->fetchAll($sql);
    }

    /**
     * Get all users (for discovery)
     */
    public function getAllUsers(): array
    {
        $sql = "SELECT id, username, display_name, avatar, is_online, last_seen FROM users ORDER BY username";
        return $this->db->fetchAll($sql);
    }

    /**
     * Search users by username
     */
    public function search(string $query): array
    {
        $sql = "SELECT id, username, display_name, avatar, is_online FROM users WHERE username LIKE ? OR display_name LIKE ? LIMIT 20";
        $searchTerm = "%{$query}%";
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm]);
    }

    /**
     * Update user status
     */
    public function setOnlineStatus(int $userId, bool $isOnline): bool
    {
        $sql = "UPDATE users SET is_online = ?, last_seen = ? WHERE id = ?";
        $lastSeen = $isOnline ? null : date('Y-m-d H:i:s');
        $this->db->query($sql, [$isOnline ? 1 : 0, $lastSeen, $userId]);
        return true;
    }

    /**
     * Update user profile
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $allowedFields = ['display_name', 'avatar', 'public_key'];
        $updates = [];
        $values = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $values[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $this->db->query($sql, $values);
        return true;
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /**
     * Hydrate object from database result
     */
    private function hydrate(array $data): User
    {
        $user = new self();
        $user->id = (int)$data['id'];
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->passwordHash = $data['password_hash'];
        $user->displayName = $data['display_name'];
        $user->avatar = $data['avatar'];
        $user->publicKey = $data['public_key'];
        $user->isOnline = (bool)$data['is_online'];
        $user->lastSeen = $data['last_seen'];
        $user->createdAt = $data['created_at'];
        $user->updatedAt = $data['updated_at'];
        return $user;
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function getEmail(): string { return $this->email; }
    public function getPasswordHash(): ?string { return $this->passwordHash; }
    public function getDisplayName(): ?string { return $this->displayName; }
    public function getAvatar(): ?string { return $this->avatar; }
    public function getPublicKey(): ?string { return $this->publicKey; }
    public function isOnline(): bool { return $this->isOnline; }
    public function getLastSeen(): ?string { return $this->lastSeen; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    // Setters
    public function setId(int $id): void { $this->id = $id; }
    public function setUsername(string $username): void { $this->username = $username; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function setPasswordHash(string $hash): void { $this->passwordHash = $hash; }
    public function setDisplayName(?string $name): void { $this->displayName = $name; }
    public function setAvatar(?string $avatar): void { $this->avatar = $avatar; }
    public function setPublicKey(?string $key): void { $this->publicKey = $key; }
}
