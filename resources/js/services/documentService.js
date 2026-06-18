import apiClient from './apiClient';

const documentService = {
    async list(params = {}) {
        const response = await apiClient.get('/documents', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/documents/${id}`);
        return response.data;
    },
    async create(formData) {
        const response = await apiClient.post('/documents', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async update(id, data) {
        const response = await apiClient.put(`/documents/${id}`, data);
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/documents/${id}`);
        return response.data;
    },
    getDownloadUrl(id) {
        return `/api/documents/${id}/download`;
    },
};

export default documentService;
