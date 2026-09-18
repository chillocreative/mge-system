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
    getDocxUrl(id) {
        return `/api/monthly-reports/${id}/export/docx`;
    },
    getChartUrl(id, key, version) {
        const v = version ?? Date.now();
        return `/api/monthly-reports/${id}/charts/${key}?v=${v}`;
    },
    async listAssets(id) {
        const response = await apiClient.get(`/monthly-reports/${id}/assets`);
        return response.data;
    },
    async uploadAsset(id, file, kind = 'gantt_page') {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('kind', kind);
        // Do not set Content-Type manually — apiClient/axios sets the correct
        // multipart boundary automatically from the FormData instance.
        const response = await apiClient.post(`/monthly-reports/${id}/assets`, formData);
        return response.data;
    },
    async updateAsset(id, assetId, data) {
        const response = await apiClient.put(`/monthly-reports/${id}/assets/${assetId}`, data);
        return response.data;
    },
    async deleteAsset(id, assetId) {
        const response = await apiClient.delete(`/monthly-reports/${id}/assets/${assetId}`);
        return response.data;
    },
};

export default monthlyReportService;
