import apiClient from './apiClient';

const trainingService = {
    // ── Records ──
    async records(params = {}) {
        const response = await apiClient.get('/training/records', { params });
        return response.data;
    },
    async createRecord(data) {
        const response = await apiClient.post('/training/records', data);
        return response.data;
    },
    async updateRecord(id, data) {
        const response = await apiClient.put(`/training/records/${id}`, data);
        return response.data;
    },
    async deleteRecord(id) {
        const response = await apiClient.delete(`/training/records/${id}`);
        return response.data;
    },

    // ── Overview ──
    async overview() {
        const response = await apiClient.get('/training/overview');
        return response.data;
    },

    // ── Requests ──
    async requests(params = {}) {
        const response = await apiClient.get('/training/requests', { params });
        return response.data;
    },
    async createRequest(data) {
        const response = await apiClient.post('/training/requests', data);
        return response.data;
    },
    async approveRequest(id, data = {}) {
        const response = await apiClient.post(`/training/requests/${id}/approve`, data);
        return response.data;
    },
    async rejectRequest(id, data = {}) {
        const response = await apiClient.post(`/training/requests/${id}/reject`, data);
        return response.data;
    },

    // ── Self-service ──
    async my() {
        const response = await apiClient.get('/training/my');
        return response.data;
    },
};

export default trainingService;
