import apiClient from './apiClient';

const siteFormService = {
    async list(params = {}) {
        const response = await apiClient.get('/site-forms', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/site-forms/${id}`);
        return response.data;
    },
    async create(payload) {
        const response = await apiClient.post('/site-forms', payload);
        return response.data;
    },
    async update(id, payload) {
        const response = await apiClient.put(`/site-forms/${id}`, payload);
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/site-forms/${id}`);
        return response.data;
    },
    async nextRef(params = {}) {
        const response = await apiClient.get('/site-forms/next-ref', { params });
        return response.data;
    },
    async uploadAttachment(id, slot, file) {
        const fd = new FormData();
        if (slot) fd.append('slot', slot);
        fd.append('file', file);
        const response = await apiClient.post(`/site-forms/${id}/attachments`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async removeAttachment(attachmentId) {
        const response = await apiClient.delete(`/site-forms/attachments/${attachmentId}`);
        return response.data;
    },
    attachmentUrl(attachmentId) {
        return `/api/site-forms/attachments/${attachmentId}/download`;
    },
};

export default siteFormService;
