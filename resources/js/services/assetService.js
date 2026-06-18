import apiClient from './apiClient';

const assetService = {
    // ── Vehicles ──
    async listVehicles(params = {}) {
        const response = await apiClient.get('/vehicles', { params });
        return response.data;
    },
    async getVehicle(id) {
        const response = await apiClient.get(`/vehicles/${id}`);
        return response.data;
    },
    async createVehicle(data) {
        const response = await apiClient.post('/vehicles', data);
        return response.data;
    },
    async updateVehicle(id, data) {
        const response = await apiClient.put(`/vehicles/${id}`, data);
        return response.data;
    },
    async deleteVehicle(id) {
        const response = await apiClient.delete(`/vehicles/${id}`);
        return response.data;
    },

    // ── Vehicle Documents ──
    async addDocument(vehicleId, formData) {
        const response = await apiClient.post(`/vehicles/${vehicleId}/documents`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async deleteDocument(vehicleId, documentId) {
        const response = await apiClient.delete(`/vehicles/${vehicleId}/documents/${documentId}`);
        return response.data;
    },
    getDocumentDownloadUrl(vehicleId, documentId) {
        return `/api/vehicles/${vehicleId}/documents/${documentId}/download`;
    },

    // ── Dashboard / Expiring ──
    async getExpiring(days = 30) {
        const response = await apiClient.get('/assets/expiring', { params: { days } });
        return response.data;
    },
};

export default assetService;
