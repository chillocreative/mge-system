import apiClient from './apiClient';

const correspondenceService = {
    async list(params = {}) {
        const response = await apiClient.get('/correspondence', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/correspondence/${id}`);
        return response.data;
    },
    async create(formData) {
        const response = await apiClient.post('/correspondence', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async update(id, formData) {
        // Laravel handles PUT with multipart via _method spoofing on a POST request
        formData.append('_method', 'PUT');
        const response = await apiClient.post(`/correspondence/${id}`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/correspondence/${id}`);
        return response.data;
    },
    async uploadFiles(id, formData) {
        const response = await apiClient.post(`/correspondence/${id}/files`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    getFileDownloadUrl(fileId) {
        return `/api/correspondence/files/${fileId}/download`;
    },
    async deleteFile(fileId) {
        const response = await apiClient.delete(`/correspondence/files/${fileId}`);
        return response.data;
    },
    // ── Correspondence types (dynamic tabs) ──
    async types() {
        const response = await apiClient.get('/correspondence-types');
        return response.data;
    },
    async createType(data) {
        const response = await apiClient.post('/correspondence-types', data);
        return response.data;
    },
    async updateType(id, data) {
        const response = await apiClient.put(`/correspondence-types/${id}`, data);
        return response.data;
    },
    async deleteType(id) {
        const response = await apiClient.delete(`/correspondence-types/${id}`);
        return response.data;
    },
    // ── Workflow (Batch 7) ──
    async events(id) {
        const response = await apiClient.get(`/correspondence/${id}/events`);
        return response.data;
    },
    async handOver(id, data) {
        const response = await apiClient.post(`/correspondence/${id}/handover`, data);
        return response.data;
    },
    async note(id, note) {
        const response = await apiClient.post(`/correspondence/${id}/note`, { note });
        return response.data;
    },
    async changeStatus(id, status, note = null) {
        const response = await apiClient.post(`/correspondence/${id}/status`, { status, note });
        return response.data;
    },
    async close(id, closing_reference, note = null) {
        const response = await apiClient.post(`/correspondence/${id}/close`, { closing_reference, note });
        return response.data;
    },
    async reopen(id, note = null) {
        const response = await apiClient.post(`/correspondence/${id}/reopen`, { note });
        return response.data;
    },
    async downloadPdf(id, refNo) {
        const response = await apiClient.get(`/correspondence/${id}/pdf`, { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `correspondence-${refNo || id}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    },
    // ── Project parties ──
    async listParties(projectId) {
        const response = await apiClient.get('/project-parties', { params: { project_id: projectId } });
        return response.data;
    },
    async createParty(data) {
        const response = await apiClient.post('/project-parties', data);
        return response.data;
    },
    async updateParty(id, data) {
        const response = await apiClient.put(`/project-parties/${id}`, data);
        return response.data;
    },
    async deleteParty(id) {
        const response = await apiClient.delete(`/project-parties/${id}`);
        return response.data;
    },
    async downloadFile(fileId, fileName) {
        const response = await apiClient.get(`/correspondence/files/${fileId}/download`, {
            responseType: 'blob',
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', fileName || `file-${fileId}`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    },
};

export default correspondenceService;
