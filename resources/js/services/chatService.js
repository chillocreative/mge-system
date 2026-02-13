import apiClient from './apiClient';

const chatService = {
    // ── Rooms ──
    async getRooms() {
        const response = await apiClient.get('/chat/rooms');
        return response.data;
    },
    async getOrCreatePrivateRoom(userId) {
        const response = await apiClient.post('/chat/rooms/private', { user_id: userId });
        return response.data;
    },
    async createGroupRoom(data) {
        const response = await apiClient.post('/chat/rooms/group', data);
        return response.data;
    },

    // ── Messages ──
    async getMessages(roomId, params = {}) {
        const response = await apiClient.get(`/chat/rooms/${roomId}/messages`, { params });
        return response.data;
    },
    async sendMessage(roomId, body) {
        const response = await apiClient.post(`/chat/rooms/${roomId}/messages`, { body });
        return response.data;
    },
    async sendFile(roomId, formData) {
        const response = await apiClient.post(`/chat/rooms/${roomId}/files`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },

    // ── Read Status ──
    async markRead(roomId) {
        const response = await apiClient.post(`/chat/rooms/${roomId}/read`);
        return response.data;
    },
    async getUnreadCount() {
        const response = await apiClient.get('/chat/unread-count');
        return response.data;
    },
};

export default chatService;
