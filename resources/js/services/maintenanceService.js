import apiClient from './apiClient';

const maintenanceService = {
    async list(params = {}) {
        const response = await apiClient.get('/maintenance', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/maintenance/${id}`);
        return response.data;
    },
    async create(data) {
        const response = await apiClient.post('/maintenance', data);
        return response.data;
    },
    async update(id, data) {
        const response = await apiClient.put(`/maintenance/${id}`, data);
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/maintenance/${id}`);
        return response.data;
    },
    async upcoming(days = 30) {
        const response = await apiClient.get('/maintenance/upcoming', { params: { days } });
        return response.data;
    },
};

export default maintenanceService;
