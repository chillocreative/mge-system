import apiClient from './apiClient';

const emailService = {
    // ── List ──
    async getEmails(params = {}) {
        const response = await apiClient.get('/emails', { params });
        return response.data;
    },
    async getEmail(id) {
        const response = await apiClient.get(`/emails/${id}`);
        return response.data;
    },
    async getUnreadCount() {
        const response = await apiClient.get('/emails/unread-count');
        return response.data;
    },

    // ── Send / Reply ──
    async sendEmail(formData) {
        const response = await apiClient.post('/emails/send', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async replyToEmail(emailId, formData) {
        const response = await apiClient.post(`/emails/${emailId}/reply`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },

    // ── Drafts ──
    async saveDraft(data) {
        const response = await apiClient.post('/emails/drafts', data);
        return response.data;
    },
    async deleteDraft(id) {
        const response = await apiClient.delete(`/emails/drafts/${id}`);
        return response.data;
    },

    // ── Actions ──
    async toggleStar(id) {
        const response = await apiClient.patch(`/emails/${id}/star`);
        return response.data;
    },
    async moveToTrash(id) {
        const response = await apiClient.patch(`/emails/${id}/trash`);
        return response.data;
    },
    async restoreFromTrash(id) {
        const response = await apiClient.patch(`/emails/${id}/restore`);
        return response.data;
    },

    // ── Attachments ──
    getAttachmentDownloadUrl(attachmentId) {
        return `/api/emails/attachments/${attachmentId}/download`;
    },
};

export default emailService;
