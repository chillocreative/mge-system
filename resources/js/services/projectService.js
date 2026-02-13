import apiClient from './apiClient';

const projectService = {
    // ── Projects ──
    async list(params = {}) {
        const response = await apiClient.get('/projects', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/projects/${id}`);
        return response.data;
    },
    async create(data) {
        const response = await apiClient.post('/projects', data);
        return response.data;
    },
    async update(id, data) {
        const response = await apiClient.put(`/projects/${id}`, data);
        return response.data;
    },
    async delete(id) {
        const response = await apiClient.delete(`/projects/${id}`);
        return response.data;
    },

    // ── Milestones ──
    async getMilestones(projectId, params = {}) {
        const response = await apiClient.get(`/projects/${projectId}/milestones`, { params });
        return response.data;
    },
    async createMilestone(projectId, data) {
        const response = await apiClient.post(`/projects/${projectId}/milestones`, data);
        return response.data;
    },
    async updateMilestone(projectId, milestoneId, data) {
        const response = await apiClient.put(`/projects/${projectId}/milestones/${milestoneId}`, data);
        return response.data;
    },
    async deleteMilestone(projectId, milestoneId) {
        const response = await apiClient.delete(`/projects/${projectId}/milestones/${milestoneId}`);
        return response.data;
    },

    // ── Site Logs ──
    async getSiteLogs(projectId, params = {}) {
        const response = await apiClient.get(`/projects/${projectId}/site-logs`, { params });
        return response.data;
    },
    async createSiteLog(projectId, data) {
        const response = await apiClient.post(`/projects/${projectId}/site-logs`, data);
        return response.data;
    },
    async updateSiteLog(projectId, logId, data) {
        const response = await apiClient.put(`/projects/${projectId}/site-logs/${logId}`, data);
        return response.data;
    },
    async deleteSiteLog(projectId, logId) {
        const response = await apiClient.delete(`/projects/${projectId}/site-logs/${logId}`);
        return response.data;
    },

    // ── Documents ──
    async getDocuments(projectId, params = {}) {
        const response = await apiClient.get(`/projects/${projectId}/documents`, { params });
        return response.data;
    },
    async uploadDocument(projectId, formData) {
        const response = await apiClient.post(`/projects/${projectId}/documents`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async deleteDocument(projectId, documentId) {
        const response = await apiClient.delete(`/projects/${projectId}/documents/${documentId}`);
        return response.data;
    },
    getDocumentDownloadUrl(projectId, documentId) {
        return `/api/projects/${projectId}/documents/${documentId}/download`;
    },

    // ── Calendar Events ──
    async getEvents(projectId, params = {}) {
        const response = await apiClient.get(`/projects/${projectId}/events`, { params });
        return response.data;
    },
    async createEvent(projectId, data) {
        const response = await apiClient.post(`/projects/${projectId}/events`, data);
        return response.data;
    },
    async updateEvent(projectId, eventId, data) {
        const response = await apiClient.put(`/projects/${projectId}/events/${eventId}`, data);
        return response.data;
    },
    async deleteEvent(projectId, eventId) {
        const response = await apiClient.delete(`/projects/${projectId}/events/${eventId}`);
        return response.data;
    },
};

export default projectService;
