import apiClient from './apiClient';

const safetyService = {
    // Overview
    getOverview: (projectId) =>
        apiClient.get('/safety/overview', { params: { project_id: projectId } }),

    // Incidents
    getIncidents: (params) => apiClient.get('/safety/incidents', { params }),
    getIncident: (id) => apiClient.get(`/safety/incidents/${id}`),
    createIncident: (formData) =>
        apiClient.post('/safety/incidents', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
    updateIncident: (id, data) => apiClient.put(`/safety/incidents/${id}`, data),
    getIncidentPdfUrl: (id) => `/api/safety/incidents/${id}/pdf`,

    // Hazards
    getHazards: (params) => apiClient.get('/safety/hazards', { params }),
    createHazard: (formData) =>
        apiClient.post('/safety/hazards', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
    updateHazard: (id, data) => apiClient.put(`/safety/hazards/${id}`, data),
    getHazardPdfUrl: (id) => `/api/safety/hazards/${id}/pdf`,

    // Toolbox Meetings
    getMeetings: (params) => apiClient.get('/safety/meetings', { params }),
    getMeeting: (id) => apiClient.get(`/safety/meetings/${id}`),
    createMeeting: (formData) =>
        apiClient.post('/safety/meetings', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
    getMeetingPdfUrl: (id) => `/api/safety/meetings/${id}/pdf`,

    // Checklists
    getChecklists: (params) => apiClient.get('/safety/checklists', { params }),
    getChecklist: (id) => apiClient.get(`/safety/checklists/${id}`),
    createChecklist: (data) => apiClient.post('/safety/checklists', data),
    getChecklistPdfUrl: (id) => `/api/safety/checklists/${id}/pdf`,

    // Photos
    uploadPhotos: (type, id, formData) =>
        apiClient.post(`/safety/${type}/${id}/photos`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
    async listHirarc(params = {}) {
        const response = await apiClient.get('/safety/hirarc', { params });
        return response.data;
    },
    async getHirarc(id) {
        const response = await apiClient.get(`/safety/hirarc/${id}`);
        return response.data;
    },
    async createHirarc(data) {
        const response = await apiClient.post('/safety/hirarc', data);
        return response.data;
    },
    async updateHirarc(id, data) {
        const response = await apiClient.put(`/safety/hirarc/${id}`, data);
        return response.data;
    },
    async archiveHirarc(id) {
        const response = await apiClient.delete(`/safety/hirarc/${id}`);
        return response.data;
    },

    // ── Permit To Work (PTW) ──
    async listPermits(params = {}) {
        const response = await apiClient.get('/safety/permits', { params });
        return response.data;
    },
    async getPermit(id) {
        const response = await apiClient.get(`/safety/permits/${id}`);
        return response.data;
    },
    async createPermit(data) {
        const response = await apiClient.post('/safety/permits', data);
        return response.data;
    },
    async updatePermit(id, data) {
        const response = await apiClient.put(`/safety/permits/${id}`, data);
        return response.data;
    },
    async submitPermit(id) {
        const response = await apiClient.post(`/safety/permits/${id}/submit`);
        return response.data;
    },
    async approvePermit(id, decision_notes = null) {
        const response = await apiClient.post(`/safety/permits/${id}/approve`, { decision_notes });
        return response.data;
    },
    async rejectPermit(id, decision_notes = null) {
        const response = await apiClient.post(`/safety/permits/${id}/reject`, { decision_notes });
        return response.data;
    },
    async closePermit(id) {
        const response = await apiClient.post(`/safety/permits/${id}/close`);
        return response.data;
    },
};

export default safetyService;
