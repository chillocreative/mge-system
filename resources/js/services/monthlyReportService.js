import apiClient from './apiClient';

const monthlyReportService = {
    async list(params = {}) {
        const response = await apiClient.get('/monthly-reports', { params });
        return response.data;
    },
    async listForProject(projectId, params = {}) {
        const response = await apiClient.get(`/projects/${projectId}/monthly-reports`, { params });
        return response.data;
    },
    async create(projectId, data) {
        const response = await apiClient.post(`/projects/${projectId}/monthly-reports`, data);
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/monthly-reports/${id}`);
        return response.data;
    },
    async update(id, data) {
        const response = await apiClient.put(`/monthly-reports/${id}`, data);
        return response.data;
    },
    async saveSection(id, key, data) {
        const response = await apiClient.put(`/monthly-reports/${id}/sections/${key}`, data);
        return response.data;
    },
    async regenerate(id, key) {
        const response = await apiClient.post(`/monthly-reports/${id}/regenerate`, {}, key ? { params: { key } } : undefined);
        return response.data;
    },
    async finalise(id) {
        const response = await apiClient.post(`/monthly-reports/${id}/finalise`);
        return response.data;
    },
    async reopen(id) {
        const response = await apiClient.post(`/monthly-reports/${id}/reopen`);
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/monthly-reports/${id}`);
        return response.data;
    },
    getPdfUrl(id) {
        return `/api/monthly-reports/${id}/export/pdf`;
    },
};

export default monthlyReportService;
