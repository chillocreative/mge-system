import apiClient from './apiClient';

const masterDataService = {
    async listParties(params = {}) {
        const response = await apiClient.get('/master-data/parties', { params });
        return response.data;
    },
    async createParty(data) {
        const response = await apiClient.post('/master-data/parties', data);
        return response.data;
    },
    async updateParty(id, data) {
        const response = await apiClient.put(`/master-data/parties/${id}`, data);
        return response.data;
    },
    async deleteParty(id) {
        const response = await apiClient.delete(`/master-data/parties/${id}`);
        return response.data;
    },
    async listCategories(params = {}) {
        const response = await apiClient.get('/master-data/categories', { params });
        return response.data;
    },
    async createCategory(data) {
        const response = await apiClient.post('/master-data/categories', data);
        return response.data;
    },
    async updateCategory(id, data) {
        const response = await apiClient.put(`/master-data/categories/${id}`, data);
        return response.data;
    },
    async deleteCategory(id) {
        const response = await apiClient.delete(`/master-data/categories/${id}`);
        return response.data;
    },
    async getReferenceSettings(projectId) {
        const response = await apiClient.get(`/projects/${projectId}/reference-settings`);
        return response.data;
    },
    async updateReferenceSettings(projectId, data) {
        const response = await apiClient.put(`/projects/${projectId}/reference-settings`, data);
        return response.data;
    },
    async previewReference(projectId, type, date = null) {
        const response = await apiClient.get(`/projects/${projectId}/document-references/preview`, {
            params: { type, ...(date ? { date } : {}) },
        });
        return response.data;
    },
};

export default masterDataService;
