import apiClient from './apiClient';

const drawingService = {
    async list(params = {}) {
        const response = await apiClient.get('/drawings', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/drawings/${id}`);
        return response.data;
    },
    async create(formData) {
        const response = await apiClient.post('/drawings', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async update(id, data) {
        const response = await apiClient.put(`/drawings/${id}`, data);
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/drawings/${id}`);
        return response.data;
    },
    getDownloadUrl(id) {
        return `/api/drawings/${id}/download`;
    },
};

export default drawingService;
