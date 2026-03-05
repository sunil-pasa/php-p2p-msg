/**
 * P2P Network - Frontend JavaScript
 * Handles authentication, WebSocket, WebRTC, and UI interactions
 */

class P2PApp {
    constructor() {
        this.apiUrl = '/api';
        this.wsUrl = 'ws://127.0.0.1:8081';
        this.token = localStorage.getItem('p2p_token');
        this.user = null;
        this.ws = null;
        this.peerConnection = null;
        this.dataChannel = null;
        this.currentChatUser = null;
        this.iceServers = [];
        
        this.init();
    }

    async init() {
        // Check if user is logged in
        if (this.token) {
            await this.checkAuth();
        }

        this.setupEventListeners();
        this.setupTabs();
    }

    // ==================== AUTH ====================

    async checkAuth() {
        try {
            const response = await fetch(`${this.apiUrl}/users/me`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            
            if (response.ok) {
                this.user = await response.json();
                this.showMainView();
                this.connectWebSocket();
                this.loadPeers();
                this.loadConversations();
            } else {
                this.logout();
            }
        } catch (error) {
            console.error('Auth check failed:', error);
            this.logout();
        }
    }

    async login(username, password) {
        try {
            const response = await fetch(`${this.apiUrl}/auth/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.token = data.token;
                this.user = data.user;
                localStorage.setItem('p2p_token', this.token);
                this.showMainView();
                this.connectWebSocket();
                this.loadPeers();
                this.loadConversations();
                return true;
            } else {
                return data.error || 'Login failed';
            }
        } catch (error) {
            return 'Network error';
        }
    }

    async register(username, email, password) {
        try {
            const response = await fetch(`${this.apiUrl}/auth/register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, email, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.token = data.token;
                this.user = data.user;
                localStorage.setItem('p2p_token', this.token);
                this.showMainView();
                this.connectWebSocket();
                this.loadPeers();
                this.loadConversations();
                return true;
            } else {
                return data.error || 'Registration failed';
            }
        } catch (error) {
            return 'Network error';
        }
    }

    logout() {
        if (this.ws) {
            this.ws.close();
        }
        
        fetch(`${this.apiUrl}/auth/logout`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${this.token}` }
        });
        
        this.token = null;
        this.user = null;
        localStorage.removeItem('p2p_token');
        this.showAuthView();
    }

    // ==================== WEBSOCKET ====================

    connectWebSocket() {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            return;
        }

        this.ws = new WebSocket(this.wsUrl);

        this.ws.onopen = () => {
            console.log('WebSocket connected');
            // Register user
            this.ws.send(JSON.stringify({
                type: 'register',
                user_id: this.user.id,
                peer_id: this.generatePeerId()
            }));
        };

        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleWebSocketMessage(data);
        };

        this.ws.onclose = () => {
            console.log('WebSocket disconnected');
            // Reconnect after 3 seconds
            setTimeout(() => this.connectWebSocket(), 3000);
        };

        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
        };
    }

    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'registered':
                console.log('Registered with WebSocket server');
                break;
            
            case 'user_status':
                this.updateUserStatus(data.user_id, data.is_online);
                break;
            
            case 'message':
                this.receiveMessage(data);
                break;
            
            case 'typing':
                this.showTypingIndicator(data.sender_user_id, data.is_typing);
                break;
            
            case 'offer':
                this.handleOffer(data);
                break;
            
            case 'answer':
                this.handleAnswer(data);
                break;
            
            case 'ice-candidate':
                this.handleIceCandidate(data);
                break;
        }
    }

    sendMessage(targetUserId, content) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                type: 'message',
                target_user_id: targetUserId,
                sender_user_id: this.user.id,
                content: content
            }));
        }
    }

    sendTypingStatus(targetUserId, isTyping) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                type: 'typing',
                target_user_id: targetUserId,
                sender_user_id: this.user.id,
                is_typing: isTyping
            }));
        }
    }

    // ==================== WEBRTC ====================

    async initWebRTC() {
        // Get ICE servers configuration
        try {
            const response = await fetch(`${this.apiUrl}/ws/config`);
            const config = await response.json();
            this.iceServers = [
                { urls: config.stun_servers || ['stun:stun.l.google.com:19302'] }
            ];
        } catch (error) {
            console.error('Failed to get ICE config:', error);
            this.iceServers = [{ urls: ['stun:stun.l.google.com:19302'] }];
        }
    }

    async startP2PConnection(targetUserId) {
        await this.initWebRTC();

        const configuration = {
            iceServers: this.iceServers
        };

        this.peerConnection = new RTCPeerConnection(configuration);

        // Handle ICE candidates
        this.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignaling({
                    type: 'ice-candidate',
                    target_user_id: targetUserId,
                    candidate: event.candidate,
                    sender_user_id: this.user.id
                });
            }
        };

        // Handle connection state changes
        this.peerConnection.onconnectionstatechange = () => {
            console.log('Connection state:', this.peerConnection.connectionState);
            this.updateP2PStatus(this.peerConnection.connectionState);
        };

        // Handle incoming data channel
        this.peerConnection.ondatachannel = (event) => {
            this.setupDataChannel(event.channel);
        };

        // Create data channel
        this.dataChannel = this.peerConnection.createDataChannel('data', {
            ordered: true
        });
        this.setupDataChannel(this.dataChannel);

        // Create and send offer
        const offer = await this.peerConnection.createOffer();
        await this.peerConnection.setLocalDescription(offer);

        this.sendSignaling({
            type: 'offer',
            target_user_id: targetUserId,
            sdp: this.peerConnection.localDescription,
            sender_user_id: this.user.id
        });

        this.updateP2PStatus('connecting');
    }

    setupDataChannel(channel) {
        channel.onopen = () => {
            console.log('Data channel opened');
            this.updateP2PStatus('connected');
        };

        channel.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleP2PData(data);
        };

        channel.onclose = () => {
            console.log('Data channel closed');
            this.updateP2PStatus('disconnected');
        };
    }

    async handleOffer(data) {
        await this.initWebRTC();

        const configuration = {
            iceServers: this.iceServers
        };

        this.peerConnection = new RTCPeerConnection(configuration);

        this.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignaling({
                    type: 'ice-candidate',
                    target_user_id: data.sender_user_id,
                    candidate: event.candidate,
                    sender_user_id: this.user.id
                });
            }
        };

        this.peerConnection.ondatachannel = (event) => {
            this.setupDataChannel(event.channel);
        };

        await this.peerConnection.setRemoteDescription(new RTCSessionDescription(data.sdp));

        const answer = await this.peerConnection.createAnswer();
        await this.peerConnection.setLocalDescription(answer);

        this.sendSignaling({
            type: 'answer',
            target_user_id: data.sender_user_id,
            sdp: this.peerConnection.localDescription,
            sender_user_id: this.user.id
        });
    }

    async handleAnswer(data) {
        if (this.peerConnection) {
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription(data.sdp));
        }
    }

    async handleIceCandidate(data) {
        if (this.peerConnection && data.candidate) {
            await this.peerConnection.addIceCandidate(new RTCIceCandidate(data.candidate));
        }
    }

    sendSignaling(data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        }
    }

    sendP2PData(data) {
        if (this.dataChannel && this.dataChannel.readyState === 'open') {
            this.dataChannel.send(JSON.stringify(data));
        }
    }

    handleP2PData(data) {
        switch (data.type) {
            case 'file':
                this.receiveFile(data);
                break;
            case 'message':
                this.receiveP2PMessage(data);
                break;
        }
    }

    // ==================== FILE TRANSFER ====================

    sendFile(file) {
        if (this.dataChannel && this.dataChannel.readyState === 'open') {
            // For small files, send directly
            if (file.size < 16 * 1024) { // 16KB
                const reader = new FileReader();
                reader.onload = () => {
                    this.sendP2PData({
                        type: 'file',
                        name: file.name,
                        size: file.size,
                        data: reader.result
                    });
                };
                reader.readAsDataURL(file);
            } else {
                // For larger files, use chunked transfer
                this.sendFileChunked(file);
            }
        }
    }

    async sendFileChunked(file) {
        const chunkSize = 16 * 1024;
        const chunks = Math.ceil(file.size / chunkSize);
        let offset = 0;

        for (let i = 0; i < chunks; i++) {
            const chunk = file.slice(offset, offset + chunkSize);
            const buffer = await chunk.arrayBuffer();
            
            this.sendP2PData({
                type: 'file-chunk',
                name: file.name,
                size: file.size,
                chunk: Array.from(new Uint8Array(buffer)),
                chunkIndex: i,
                totalChunks: chunks
            });

            offset += chunkSize;
            
            // Update progress
            const progress = ((i + 1) / chunks) * 100;
            this.updateFileTransferProgress(progress);
        }
    }

    receiveFile(data) {
        // Handle received file data
        const blob = this.base64ToBlob(data.data, data.type);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = data.name;
        a.click();
        URL.revokeObjectURL(url);
    }

    base64ToBlob(base64, mimeType) {
        const byteCharacters = atob(base64.split(',')[1]);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        return new Blob([byteArray], { type: mimeType });
    }

    // ==================== API CALLS ====================

    async loadPeers() {
        try {
            const response = await fetch(`${this.apiUrl}/peers/discover`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            const data = await response.json();
            this.renderPeers(data.peers || []);
        } catch (error) {
            console.error('Failed to load peers:', error);
        }
    }

    async loadConversations() {
        try {
            const response = await fetch(`${this.apiUrl}/messages/conversations`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            const data = await response.json();
            this.renderConversations(data.conversations || []);
        } catch (error) {
            console.error('Failed to load conversations:', error);
        }
    }

    async loadChat(userId) {
        try {
            const response = await fetch(`${this.apiUrl}/messages/chat/${userId}`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            const data = await response.json();
            this.renderMessages(data.messages || []);
            
            // Mark as read
            await fetch(`${this.apiUrl}/messages/mark-read`, {
                method: 'POST',
                headers: { 
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ sender_id: userId })
            });
        } catch (error) {
            console.error('Failed to load chat:', error);
        }
    }

    async requestConnection(userId) {
        try {
            await fetch(`${this.apiUrl}/peers/connect`, {
                method: 'POST',
                headers: { 
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId })
            });
            this.loadPeers();
        } catch (error) {
            console.error('Failed to request connection:', error);
        }
    }

    // ==================== UI ====================

    showAuthView() {
        document.getElementById('auth-view').classList.remove('hidden');
        document.getElementById('main-view').classList.add('hidden');
    }

    showMainView() {
        document.getElementById('auth-view').classList.add('hidden');
        document.getElementById('main-view').classList.remove('hidden');
    }

    renderPeers(peers) {
        const container = document.getElementById('peers-container');
        container.innerHTML = peers.map(peer => `
            <div class="peer-item" data-user-id="${peer.id}">
                <div class="avatar">${peer.display_name?.[0] || peer.username[0]}</div>
                <div class="peer-info">
                    <span class="peer-name">${peer.display_name || peer.username}</span>
                    <span class="peer-status ${peer.is_online ? 'online' : 'offline'}">
                        ${peer.is_online ? 'Online' : 'Offline'}
                    </span>
                </div>
                <div class="peer-actions">
                    ${peer.connection_status === null ? 
                        `<button class="btn-connect" data-id="${peer.id}">Connect</button>` :
                        peer.connection_status === 'pending' ?
                        '<span>Pending</span>' :
                        peer.connection_status === 'accepted' ?
                        '<button class="btn-chat" data-id="${peer.id}">Chat</button>' :
                        '<button class="btn-reconnect" data-id="${peer.id}">Reconnect</button>'
                    }
                </div>
            </div>
        `).join('');

        // Add click handlers
        container.querySelectorAll('.btn-connect, .btn-chat, .btn-reconnect').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const userId = parseInt(e.target.dataset.id);
                this.requestConnection(userId);
            });
        });

        container.querySelectorAll('.peer-item').forEach(item => {
            item.addEventListener('click', () => {
                const userId = parseInt(item.dataset.userId);
                this.openChat(userId, peers.find(p => p.id === userId));
            });
        });
    }

    renderConversations(conversations) {
        const container = document.getElementById('conversations-container');
        container.innerHTML = conversations.map(conv => `
            <div class="conversation-item" data-user-id="${conv.other_user_id}">
                <div class="avatar">${conv.other_display_name?.[0] || conv.other_username[0]}</div>
                <div class="conv-info">
                    <span class="conv-name">${conv.other_display_name || conv.other_username}</span>
                    <span class="conv-preview">${conv.content || ''}</span>
                </div>
                ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
            </div>
        `).join('');

        container.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', () => {
                const userId = parseInt(item.dataset.userId);
                const conv = conversations.find(c => c.other_user_id === userId);
                this.openChat(userId, conv);
            });
        });
    }

    renderMessages(messages) {
        const container = document.getElementById('chat-messages');
        container.innerHTML = messages.map(msg => `
            <div class="message ${msg.sender_id === this.user.id ? 'sent' : 'received'}">
                <div class="message-content">${this.escapeHtml(msg.content)}</div>
                <div class="message-time">${new Date(msg.created_at).toLocaleTimeString()}</div>
            </div>
        `).join('');
        container.scrollTop = container.scrollHeight;
    }

    receiveMessage(data) {
        if (this.currentChatUser && data.sender_user_id === this.currentChatUser.id) {
            // Add to current chat
            const container = document.getElementById('chat-messages');
            container.innerHTML += `
                <div class="message received">
                    <div class="message-content">${this.escapeHtml(data.content)}</div>
                    <div class="message-time">${new Date(data.timestamp).toLocaleTimeString()}</div>
                </div>
            `;
            container.scrollTop = container.scrollHeight;
        }
        
        // Reload conversations to update preview
        this.loadConversations();
    }

    receiveP2PMessage(data) {
        // Handle P2P direct message
        console.log('P2P Message:', data);
    }

    openChat(userId, user) {
        this.currentChatUser = { id: userId, ...user };
        
        document.getElementById('no-chat').classList.add('hidden');
        document.getElementById('chat-interface').classList.remove('hidden');
        
        document.getElementById('chat-peer-name').textContent = user.display_name || user.username;
        document.getElementById('chat-peer-status').textContent = user.is_online ? 'Online' : 'Offline';
        
        this.loadChat(userId);
    }

    updateUserStatus(userId, isOnline) {
        // Update peer list
        const peerItems = document.querySelectorAll('.peer-item');
        peerItems.forEach(item => {
            if (parseInt(item.dataset.userId) === userId) {
                const statusEl = item.querySelector('.peer-status');
                statusEl.textContent = isOnline ? 'Online' : 'Offline';
                statusEl.className = `peer-status ${isOnline ? 'online' : 'offline'}`;
            }
        });
        
        // Update conversation list
        const convItems = document.querySelectorAll('.conversation-item');
        convItems.forEach(item => {
            if (parseInt(item.dataset.userId) === userId) {
                const statusEl = item.querySelector('.conv-info .peer-status');
                if (statusEl) {
                    statusEl.textContent = isOnline ? 'Online' : 'Offline';
                }
            }
        });
    }

    showTypingIndicator(userId, isTyping) {
        if (this.currentChatUser && userId === this.currentChatUser.id) {
            const indicator = document.getElementById('typing-indicator');
            if (!indicator) {
                const el = document.createElement('div');
                el.id = 'typing-indicator';
                el.className = 'typing-indicator';
                el.textContent = 'Typing...';
                document.getElementById('chat-messages').appendChild(el);
            }
            
            if (!isTyping) {
                indicator?.remove();
            }
        }
    }

    updateP2PStatus(state) {
        const statusEl = document.getElementById('p2p-status');
        statusEl.classList.remove('hidden');
        
        const statusText = statusEl.querySelector('.status-text');
        const progressBar = statusEl.querySelector('.progress-bar');
        
        const states = {
            'connecting': 'Establishing P2P connection...',
            'connected': 'P2P Connected!',
            'disconnected': 'P2P Disconnected',
            'failed': 'P2P Connection Failed'
        };
        
        statusText.textContent = states[state] || state;
        
        if (state === 'connected') {
            setTimeout(() => statusEl.classList.add('hidden'), 2000);
        }
    }

    updateFileTransferProgress(progress) {
        const transferEl = document.getElementById('file-transfer');
        transferEl.classList.remove('hidden');
        transferEl.querySelector('.file-progress').textContent = `${Math.round(progress)}%`;
        transferEl.querySelector('.progress').style.width = `${progress}%`;
        
        if (progress >= 100) {
            setTimeout(() => transferEl.classList.add('hidden'), 2000);
        }
    }

    // ==================== EVENT LISTENERS ====================

    setupEventListeners() {
        // Auth forms
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = e.target.username.value;
            const password = e.target.password.value;
            const error = await this.login(username, password);
            
            if (error) {
                document.getElementById('auth-error').textContent = error;
                document.getElementById('auth-error').classList.remove('hidden');
            }
        });

        document.getElementById('register-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = e.target.username.value;
            const email = e.target.email.value;
            const password = e.target.password.value;
            const error = await this.register(username, email, password);
            
            if (error) {
                document.getElementById('auth-error').textContent = error;
                document.getElementById('auth-error').classList.remove('hidden');
            }
        });

        // Logout
        document.getElementById('logout-btn').addEventListener('click', () => this.logout());

        // Message form
        document.getElementById('message-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const input = document.getElementById('message-input');
            const content = input.value.trim();
            
            if (content && this.currentChatUser) {
                // Send via WebSocket for real-time
                this.sendMessage(this.currentChatUser.id, content);
                
                // Add to UI
                const container = document.getElementById('chat-messages');
                container.innerHTML += `
                    <div class="message sent">
                        <div class="message-content">${this.escapeHtml(content)}</div>
                        <div class="message-time">${new Date().toLocaleTimeString()}</div>
                    </div>
                `;
                container.scrollTop = container.scrollHeight;
                
                input.value = '';
            }
        });

        // Typing indicator
        let typingTimeout;
        document.getElementById('message-input').addEventListener('input', () => {
            if (this.currentChatUser) {
                this.sendTypingStatus(this.currentChatUser.id, true);
                
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    this.sendTypingStatus(this.currentChatUser.id, false);
                }, 1000);
            }
        });

        // User search
        document.getElementById('user-search').addEventListener('input', async (e) => {
            const query = e.target.value;
            if (query.length >= 2) {
                try {
                    const response = await fetch(`${this.apiUrl}/users/search?q=${encodeURIComponent(query)}`, {
                        headers: { 'Authorization': `Bearer ${this.token}` }
                    });
                    const data = await response.json();
                    this.renderPeers(data.users || []);
                } catch (error) {
                    console.error('Search failed:', error);
                }
            } else if (query.length === 0) {
                this.loadPeers();
            }
        });

        // P2P connection
        document.getElementById('start-p2p-btn').addEventListener('click', () => {
            if (this.currentChatUser) {
                this.startP2PConnection(this.currentChatUser.id);
            }
        });

        // File attachment
        document.getElementById('attach-btn').addEventListener('click', () => {
            document.getElementById('file-input').click();
        });

        document.getElementById('file-input').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && this.dataChannel?.readyState === 'open') {
                this.sendFile(file);
            }
        });
    }

    setupTabs() {
        // Auth tabs
        document.querySelectorAll('#auth-view .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#auth-view .tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                const tab = btn.dataset.tab;
                if (tab === 'login') {
                    document.getElementById('login-form').classList.remove('hidden');
                    document.getElementById('register-form').classList.add('hidden');
                } else {
                    document.getElementById('login-form').classList.add('hidden');
                    document.getElementById('register-form').classList.remove('hidden');
                }
            });
        });

        // Sidebar tabs
        document.querySelectorAll('.sidebar-tabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.sidebar-tabs .tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                const tab = btn.dataset.tab;
                document.getElementById('peers-list').classList.toggle('active', tab === 'peers');
                document.getElementById('messages-list').classList.toggle('active', tab === 'messages');
            });
        });
    }

    // ==================== UTILITIES ====================

    generatePeerId() {
        return 'peer_' + Math.random().toString(36).substr(2, 9) + Date.now();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize app
const app = new P2PApp();
