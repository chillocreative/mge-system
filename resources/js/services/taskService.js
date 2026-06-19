import apiClient from './apiClient';

const taskService = {
    async list(params = {}) {
        const response = await apiClient.get('/tasks', { params });
        return response.data;
    },

    async get(id) {
        const response = await apiClient.get(`/tasks/${id}`);
        return response.data;
    },

    async create(formData) {
        const response = await apiClient.post('/tasks', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },

    async update(id, data) {
        const response = await apiClient.put(`/tasks/${id}`, data);
        return response.data;
    },

    async delete(id) {
        const response = await apiClient.delete(`/tasks/${id}`);
        return response.data;
    },

    attachmentDownloadUrl(id) {
        return `/api/tasks/attachments/${id}/download`;
    },
};

export default taskService;
