import apiClient from './apiClient';

const notificationService = {
    async list(params = {}) {
        const response = await apiClient.get('/notifications', { params });
        return response.data;
    },
    async unreadCount() {
        const response = await apiClient.get('/notifications/unread-count');
        return response.data;
    },
    async markAsRead(id) {
        const response = await apiClient.patch(`/notifications/${id}/read`);
        return response.data;
    },
    async markAllAsRead() {
        const response = await apiClient.post('/notifications/mark-all-read');
        return response.data;
    },
};

export default notificationService;
