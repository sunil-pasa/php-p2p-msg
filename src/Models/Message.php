<?php
/**
 * Message Model
 * Handles message-related database operations
 */

namespace P2P\Models;

use P2P\Core\Database;

class Message
{
    private ?Database $db = null;
    private int $id;
    private int $senderId;
    private int $receiverId;
    private string $content;
    private string $messageType;
    private ?string $filePath;
    private ?string $fileName;
    private ?int $fileSize;
    private bool $isRead;
    private string $createdAt;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->messageType = 'text';
        $this->isRead = false;
    }

    /**
     * Send a message
     */
    public function send(int $senderId, int $receiverId, string $content, string $type = 'text', array $fileData = null): int
    {
        $this->senderId = $senderId;
        $this->receiverId = $receiverId;
        $this->content = $content;
        $this->messageType = $type;

        if ($fileData) {
            $this->filePath = $fileData['path'] ?? null;
            $this->fileName = $fileData['name'] ?? null;
            $this->fileSize = $fileData['size'] ?? null;
        }

        $sql = "INSERT INTO messages (sender_id, receiver_id, content, message_type, file_path, file_name, file_size) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $this->senderId,
            $this->receiverId,
            $this->content,
            $this->messageType,
            $this->filePath,
            $this->fileName,
            $this->fileSize
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Get conversation between two users
     */
    public function getConversation(int $userId, int $otherUserId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT m.*, 
                u1.username as sender_username, u1.display_name as sender_display_name,
                u2.username as receiver_username, u2.display_name as receiver_display_name
                FROM messages m
                JOIN users u1 ON m.sender_id = u1.id
                JOIN users u2 ON m.receiver_id = u2.id
                WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($sql, [$userId, $otherUserId, $otherUserId, $userId, $limit, $offset]);
    }

    /**
     * Get all conversations for a user
     */
    public function getConversations(int $userId): array
    {
        $sql = "SELECT m.*, 
                u.id as other_user_id,
                u.username as other_username,
                u.display_name as other_display_name,
                u.avatar as other_avatar,
                u.is_online as other_online,
                (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
                FROM messages m
                JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
                WHERE m.sender_id = ? OR m.receiver_id = ?
                GROUP BY u.id
                ORDER BY m.created_at DESC";
        
        return $this->db->fetchAll($sql, [$userId, $userId, $userId, $userId]);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(int $receiverId, int $senderId): bool
    {
        $sql = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0";
        $this->db->query($sql, [$receiverId, $senderId]);
        return true;
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount(int $userId): int
    {
        $sql = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
        $result = $this->db->fetch($sql, [$userId]);
        return (int)$result['count'];
    }

    /**
     * Delete a message
     */
    public function delete(int $messageId, int $userId): bool
    {
        $sql = "DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)";
        $this->db->query($sql, [$messageId, $userId, $userId]);
        return true;
    }

    /**
     * Save ICE candidate
     */
    public function saveIceCandidate(string $connectionId, int $userId, string $candidate, ?string $sdpMid = null, ?int $sdpMidLineIndex = null): bool
    {
        $sql = "INSERT INTO ice_candidates (connection_id, user_id, candidate, sdp_mid, sdp_mid_line_index) VALUES (?, ?, ?, ?, ?)";
        $this->db->query($sql, [$connectionId, $userId, $candidate, $sdpMid, $sdpMidLineIndex]);
        return true;
    }

    /**
     * Get ICE candidates for a connection
     */
    public function getIceCandidates(string $connectionId, int $userId): array
    {
        $sql = "SELECT * FROM ice_candidates WHERE connection_id = ? AND user_id != ? ORDER BY created_at ASC";
        return $this->db->fetchAll($sql, [$connectionId, $userId]);
    }

    /**
     * Delete ICE candidates
     */
    public function clearIceCandidates(string $connectionId): bool
    {
        $sql = "DELETE FROM ice_candidates WHERE connection_id = ?";
        $this->db->query($sql, [$connectionId]);
        return true;
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getSenderId(): int { return $this->senderId; }
    public function getReceiverId(): int { return $this->receiverId; }
    public function getContent(): string { return $this->content; }
    public function getMessageType(): string { return $this->messageType; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function getFileName(): ?string { return $this->fileName; }
    public function getFileSize(): ?int { return $this->fileSize; }
    public function isRead(): bool { return $this->isRead; }
    public function getCreatedAt(): string { return $this->createdAt; }
}
