<?php
/**
 * Peer Model
 * Handles peer connections and P2P-related database operations
 */

namespace P2P\Models;

use P2P\Core\Database;

class Peer
{
    private ?Database $db = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Create connection request
     */
    public function requestConnection(int $fromUserId, int $toUserId): bool
    {
        // Check if connection already exists
        $existing = $this->db->fetch(
            "SELECT id FROM peer_connections WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?)",
            [$fromUserId, $toUserId, $toUserId, $fromUserId]
        );

        if ($existing) {
            return false;
        }

        $this->db->query(
            "INSERT INTO peer_connections (user_one_id, user_two_id, status) VALUES (?, ?, 'pending')",
            [$fromUserId, $toUserId]
        );

        return true;
    }

    /**
     * Accept connection request
     */
    public function acceptConnection(int $fromUserId, int $toUserId): bool
    {
        $sql = "UPDATE peer_connections SET status = 'accepted' 
                WHERE ((user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?)) 
                AND status = 'pending'";
        
        $this->db->query($sql, [$fromUserId, $toUserId, $toUserId, $fromUserId]);
        return true;
    }

    /**
     * Reject connection request
     */
    public function rejectConnection(int $fromUserId, int $toUserId): bool
    {
        $sql = "UPDATE peer_connections SET status = 'rejected' 
                WHERE ((user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?))";
        
        $this->db->query($sql, [$fromUserId, $toUserId, $toUserId, $fromUserId]);
        return true;
    }

    /**
     * Block user
     */
    public function blockUser(int $fromUserId, int $toUserId): bool
    {
        // Remove existing connection first
        $this->removeConnection($fromUserId, $toUserId);
        
        // Add block
        $this->db->query(
            "INSERT INTO peer_connections (user_one_id, user_two_id, status) VALUES (?, ?, 'blocked')",
            [$fromUserId, $toUserId]
        );
        
        return true;
    }

    /**
     * Remove connection
     */
    public function removeConnection(int $fromUserId, int $toUserId): bool
    {
        $sql = "DELETE FROM peer_connections WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?)";
        $this->db->query($sql, [$fromUserId, $toUserId, $toUserId, $fromUserId]);
        return true;
    }

    /**
     * Get connection status between two users
     */
    public function getConnectionStatus(int $userId, int $otherUserId): ?string
    {
        $sql = "SELECT status FROM peer_connections 
                WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?)";
        
        $result = $this->db->fetch($sql, [$userId, $otherUserId, $otherUserId, $userId]);
        return $result ? $result['status'] : null;
    }

    /**
     * Get all connections for a user
     */
    public function getConnections(int $userId): array
    {
        $sql = "SELECT u.id, u.username, u.display_name, u.avatar, u.is_online, pc.status, pc.created_at
                FROM peer_connections pc
                JOIN users u ON (CASE WHEN pc.user_one_id = ? THEN pc.user_two_id ELSE pc.user_one_id END) = u.id
                WHERE (pc.user_one_id = ? OR pc.user_two_id = ?) AND pc.status = 'accepted'";
        
        return $this->db->fetchAll($sql, [$userId, $userId, $userId]);
    }

    /**
     * Get pending connection requests
     */
    public function getPendingRequests(int $userId): array
    {
        $sql = "SELECT u.id, u.username, u.display_name, u.avatar, pc.created_at
                FROM peer_connections pc
                JOIN users u ON pc.user_one_id = u.id
                WHERE pc.user_two_id = ? AND pc.status = 'pending'";
        
        return $this->db->fetchAll($sql, [$userId]);
    }

    /**
     * Get all users (for discovery)
     */
    public function discoverPeers(int $userId): array
    {
        $sql = "SELECT u.id, u.username, u.display_name, u.avatar, u.is_online, 
                (SELECT status FROM peer_connections pc 
                 WHERE (pc.user_one_id = ? AND pc.user_two_id = u.id) 
                 OR (pc.user_one_id = u.id AND pc.user_two_id = ?) 
                 LIMIT 1) as connection_status
                FROM users u
                WHERE u.id != ?
                ORDER BY u.is_online DESC, u.username ASC
                LIMIT 50";
        
        return $this->db->fetchAll($sql, [$userId, $userId, $userId]);
    }

    /**
     * Register online peer
     */
    public function registerOnlinePeer(int $userId, string $socketId, string $peerId): bool
    {
        // First, remove any existing registration
        $this->db->query("DELETE FROM online_peers WHERE user_id = ?", [$userId]);
        
        // Insert new peer
        $this->db->query(
            "INSERT INTO online_peers (user_id, socket_id, peer_id) VALUES (?, ?, ?)",
            [$userId, $socketId, $peerId]
        );
        
        return true;
    }

    /**
     * Unregister online peer
     */
    public function unregisterOnlinePeer(int $userId): bool
    {
        $this->db->query("DELETE FROM online_peers WHERE user_id = ?", [$userId]);
        return true;
    }

    /**
     * Get all online peers
     */
    public function getOnlinePeers(): array
    {
        $sql = "SELECT op.user_id, op.socket_id, op.peer_id, u.username, u.display_name, u.avatar
                FROM online_peers op
                JOIN users u ON op.user_id = u.id";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Get peer by socket ID
     */
    public function getPeerBySocketId(string $socketId): ?array
    {
        $sql = "SELECT op.*, u.username, u.display_name
                FROM online_peers op
                JOIN users u ON op.user_id = u.id
                WHERE op.socket_id = ?";
        
        $result = $this->db->fetch($sql, [$socketId]);
        return $result ?: null;
    }

    /**
     * Get peer by user ID
     */
    public function getPeerByUserId(int $userId): ?array
    {
        $sql = "SELECT op.*, u.username, u.display_name
                FROM online_peers op
                JOIN users u ON op.user_id = u.id
                WHERE op.user_id = ?";
        
        $result = $this->db->fetch($sql, [$userId]);
        return $result ?: null;
    }

    /**
     * Update peer socket ID
     */
    public function updatePeerSocket(int $userId, string $socketId): bool
    {
        $this->db->query("UPDATE online_peers SET socket_id = ? WHERE user_id = ?", [$socketId, $userId]);
        return true;
    }
}
