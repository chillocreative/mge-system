import apiClient from './apiClient';

const meetingService = {
    async list(params = {}) {
        const response = await apiClient.get('/meetings', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/meetings/${id}`);
        return response.data;
    },
    async create(formData) {
        const response = await apiClient.post('/meetings', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async update(id, formData) {
        // Use POST + _method override so multipart file uploads work on update
        formData.append('_method', 'PUT');
        const response = await apiClient.post(`/meetings/${id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async delete(id) {
        const response = await apiClient.delete(`/meetings/${id}`);
        return response.data;
    },
    async uploadFiles(id, formData) {
        const response = await apiClient.post(`/meetings/${id}/files`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async listEmployees() {
        const response = await apiClient.get('/meetings/employees');
        return response.data;
    },
    getFileDownloadUrl(fileId) {
        return `/api/meetings/files/${fileId}/download`;
    },
};

export default meetingService;
