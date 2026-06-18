import apiClient from './apiClient';

const leaveService = {
    // ── Leave Requests ──
    async list(params = {}) {
        const response = await apiClient.get('/leaves', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/leaves/${id}`);
        return response.data;
    },
    async apply(formData) {
        const response = await apiClient.post('/leaves', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async approve(id) {
        const response = await apiClient.post(`/leaves/${id}/approve`);
        return response.data;
    },
    async reject(id, data = {}) {
        const response = await apiClient.post(`/leaves/${id}/reject`, data);
        return response.data;
    },
    async cancel(id) {
        const response = await apiClient.post(`/leaves/${id}/cancel`);
        return response.data;
    },
    async balance(params = {}) {
        const response = await apiClient.get('/leaves/balance', { params });
        return response.data;
    },

    // ── Leave Types ──
    async listTypes() {
        const response = await apiClient.get('/leave-types');
        return response.data;
    },
    async createType(data) {
        const response = await apiClient.post('/leave-types', data);
        return response.data;
    },
    async updateType(id, data) {
        const response = await apiClient.put(`/leave-types/${id}`, data);
        return response.data;
    },
    async deleteType(id) {
        const response = await apiClient.delete(`/leave-types/${id}`);
        return response.data;
    },
};

export default leaveService;
