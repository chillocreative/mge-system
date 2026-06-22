import apiClient from './apiClient';

const memoService = {
    async list(params = {}) {
        const res = await apiClient.get('/memos', { params });
        return res.data;
    },
    async get(id) {
        const res = await apiClient.get(`/memos/${id}`);
        return res.data;
    },
    async send(data) {
        const res = await apiClient.post('/memos/send', data);
        return res.data;
    },
    async markAsRead(id) {
        const res = await apiClient.patch(`/memos/${id}/read`);
        return res.data;
    },
    async unreadCount() {
        const res = await apiClient.get('/memos/unread-count');
        return res.data;
    },
};

export default memoService;
