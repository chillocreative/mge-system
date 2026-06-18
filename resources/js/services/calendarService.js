import apiClient from './apiClient';

const calendarService = {
    // ── Events ──
    async listEvents(params = {}) {
        const response = await apiClient.get('/calendar/events', { params });
        return response.data;
    },
    async createEvent(data) {
        const response = await apiClient.post('/calendar/events', data);
        return response.data;
    },
    async updateEvent(id, data) {
        const response = await apiClient.put(`/calendar/events/${id}`, data);
        return response.data;
    },
    async deleteEvent(id) {
        const response = await apiClient.delete(`/calendar/events/${id}`);
        return response.data;
    },

    // ── Google Calendar ──
    async googleStatus() {
        const response = await apiClient.get('/calendar/google/status');
        return response.data;
    },
    async googleSync() {
        const response = await apiClient.post('/calendar/google/sync');
        return response.data;
    },
    getConnectUrl() {
        return '/api/calendar/google/connect';
    },
};

export default calendarService;
