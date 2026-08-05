import apiClient from './apiClient';

const contractService = {
    async list(params = {}) {
        const response = await apiClient.get('/project-contracts', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/project-contracts/${id}`);
        return response.data;
    },
    async create(formData) {
        const response = await apiClient.post('/project-contracts', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async update(id, formData) {
        // Use POST + _method override so multipart file uploads work on update
        formData.append('_method', 'PUT');
        const response = await apiClient.post(`/project-contracts/${id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async delete(id) {
        const response = await apiClient.delete(`/project-contracts/${id}`);
        return response.data;
    },
    async uploadFiles(id, formData) {
        const response = await apiClient.post(`/project-contracts/${id}/files`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    getFileDownloadUrl(fileId) {
        return `/api/project-contracts/files/${fileId}/download`;
    },
    async deleteFile(fileId) {
        const response = await apiClient.delete(`/project-contracts/files/${fileId}`);
        return response.data;
    },
    async listBoqItems(contractId) {
        const response = await apiClient.get(`/project-contracts/${contractId}/boq-items`);
        return response.data;
    },
    async addBoqItem(contractId, data) {
        const response = await apiClient.post(`/project-contracts/${contractId}/boq-items`, data);
        return response.data;
    },
    async removeBoqItem(itemId) {
        const response = await apiClient.delete(`/project-contracts/boq-items/${itemId}`);
        return response.data;
    },
};

export default contractService;
