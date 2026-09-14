import apiClient from './apiClient';

const qcService = {
    async listRecords(params = {}) {
        const response = await apiClient.get('/qc-records', { params });
        return response.data;
    },
    async getRecord(id) {
        const response = await apiClient.get(`/qc-records/${id}`);
        return response.data;
    },
    async createRecord(formData) {
        const response = await apiClient.post('/qc-records', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async updateRecord(id, formData) {
        // Use POST + _method override so multipart file uploads work on update
        formData.append('_method', 'PUT');
        const response = await apiClient.post(`/qc-records/${id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async deleteRecord(id) {
        const response = await apiClient.delete(`/qc-records/${id}`);
        return response.data;
    },
    async listProjects() {
        const response = await apiClient.get('/projects?per_page=100');
        return response.data;
    },
    getAttachmentUrl(record) {
        if (!record?.attachment_path) return null;
        return `/storage/${record.attachment_path}`;
    },
};

export default qcService;
