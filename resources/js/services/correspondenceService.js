import apiClient from './apiClient';

const correspondenceService = {
    async list(params = {}) {
        const response = await apiClient.get('/correspondence', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/correspondence/${id}`);
        return response.data;
    },
    async create(formData) {
        const response = await apiClient.post('/correspondence', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async update(id, formData) {
        // Laravel handles PUT with multipart via _method spoofing on a POST request
        formData.append('_method', 'PUT');
        const response = await apiClient.post(`/correspondence/${id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/correspondence/${id}`);
        return response.data;
    },
    async uploadFiles(id, formData) {
        const response = await apiClient.post(`/correspondence/${id}/files`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    getFileDownloadUrl(fileId) {
        return `/api/correspondence/files/${fileId}/download`;
    },
    async downloadFile(fileId, fileName) {
        const response = await apiClient.get(`/correspondence/files/${fileId}/download`, {
            responseType: 'blob',
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', fileName || `file-${fileId}`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    },
};

export default correspondenceService;
