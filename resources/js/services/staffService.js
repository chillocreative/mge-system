import apiClient from './apiClient';

const staffService = {
    async list(params = {}) {
        const response = await apiClient.get('/employees', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/employees/${id}`);
        return response.data;
    },
    async create(formData) {
        const response = await apiClient.post('/employees', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async update(id, formData) {
        // Use POST + method spoofing so multipart/form-data (photo) is parsed by PHP
        formData.append('_method', 'PUT');
        const response = await apiClient.post(`/employees/${id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async delete(id) {
        const response = await apiClient.delete(`/employees/${id}`);
        return response.data;
    },
    getPhotoUrl(id) {
        return `/api/employees/${id}/photo`;
    },
};

export default staffService;
