import apiClient from './apiClient';

const projectInvoiceService = {
    async list(params = {}) {
        const response = await apiClient.get('/project-invoices', { params });
        return response.data;
    },
    async summary(params = {}) {
        const response = await apiClient.get('/project-invoices/summary', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/project-invoices/${id}`);
        return response.data;
    },
    async create(formData) {
        const response = await apiClient.post('/project-invoices', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async update(id, formData) {
        // Spoof PUT so multipart/form-data file uploads are parsed by PHP.
        formData.append('_method', 'PUT');
        const response = await apiClient.post(`/project-invoices/${id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/project-invoices/${id}`);
        return response.data;
    },
    async uploadFiles(id, formData) {
        const response = await apiClient.post(`/project-invoices/${id}/files`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async deleteFile(fileId) {
        const response = await apiClient.delete(`/project-invoices/files/${fileId}`);
        return response.data;
    },
    getFileDownloadUrl(fileId) {
        return `/api/project-invoices/files/${fileId}/download`;
    },
};

export default projectInvoiceService;
