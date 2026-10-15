import apiClient from './apiClient';

const leaveService = {
    // ── Leave Requests ──
    async list(params = {}) {
        const response = await apiClient.get('/leaves', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/leaves/${id}`);
        return response.data;
    },
    async apply(formData) {
        const response = await apiClient.post('/leaves', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    // Ask the server what a request would cost before submitting it, so the
    // form can show the day-by-day working (rest days, public holidays).
    async preview(data) {
        const response = await apiClient.post('/leaves/preview', data);
        return response.data;
    },
    async approve(id) {
        const response = await apiClient.post(`/leaves/${id}/approve`);
        return response.data;
    },
    async reject(id, data = {}) {
        const response = await apiClient.post(`/leaves/${id}/reject`, data);
        return response.data;
    },
    async cancel(id) {
        const response = await apiClient.post(`/leaves/${id}/cancel`);
        return response.data;
    },
    async balance(params = {}) {
        const response = await apiClient.get('/leaves/balance', { params });
        return response.data;
    },
    async pendingApprovals(params = {}) {
        const response = await apiClient.get('/leaves/pending-approvals', { params });
        return response.data;
    },
    async myEmployee() {
        const response = await apiClient.get('/leaves/my-employee');
        return response.data;
    },
    // Staff the current user may file leave for, plus what else the form allows
    // (notice exception, recording an approval received outside the system).
    async fileableEmployees() {
        const response = await apiClient.get('/leaves/fileable-employees');
        return response.data;
    },
    // HR: approved requests waiting to be recorded.
    async toRecord(params = {}) {
        const response = await apiClient.get('/leaves/to-record', { params });
        return response.data;
    },
    // HR: record an approved request. data.unpaid = 'none' | 'all' | 'excess'.
    async record(id, data = {}) {
        const response = await apiClient.post(`/leaves/${id}/record`, data);
        return response.data;
    },
    // Same-origin download link for the proof of an approval given outside the system.
    approvalProofUrl(id) {
        return `/api/leaves/${id}/approval-proof`;
    },

    // ── Leave Types ──
    async listTypes() {
        const response = await apiClient.get('/leave-types');
        return response.data;
    },
    async createType(data) {
        const response = await apiClient.post('/leave-types', data);
        return response.data;
    },
    async updateType(id, data) {
        const response = await apiClient.put(`/leave-types/${id}`, data);
        return response.data;
    },
    async deleteType(id) {
        const response = await apiClient.delete(`/leave-types/${id}`);
        return response.data;
    },

    // ── Leave Policy — Public Holidays ──
    async holidays(params = {}) {
        const response = await apiClient.get('/leave-policy/holidays', { params });
        return response.data;
    },
    async createHoliday(data) {
        const response = await apiClient.post('/leave-policy/holidays', data);
        return response.data;
    },
    async updateHoliday(id, data) {
        const response = await apiClient.put(`/leave-policy/holidays/${id}`, data);
        return response.data;
    },
    async deactivateHoliday(id) {
        const response = await apiClient.delete(`/leave-policy/holidays/${id}`);
        return response.data;
    },
};

export default leaveService;
