import apiClient from './apiClient';

// The value the API actually stores. It is NOT 'final' — every UI check used that spelling and so
// no report ever read as locked: the editor stayed writable, Reopen was unreachable and the status
// filter matched nothing. Import this instead of writing the literal.
export const REPORT_STATUS_DRAFT = 'draft';
export const REPORT_STATUS_FINAL = 'finalised';

const environmentReportService = {
    async list(params = {}) {
        const response = await apiClient.get('/environment/reports', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/environment/reports/${id}`);
        return response.data;
    },
    async create(data) {
        const response = await apiClient.post('/environment/reports', data);
        return response.data;
    },
    async update(id, data) {
        const response = await apiClient.put(`/environment/reports/${id}`, data);
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/environment/reports/${id}`);
        return response.data;
    },
    async regenerate(id, key) {
        const response = await apiClient.post(`/environment/reports/${id}/regenerate/${key}`);
        return response.data;
    },
    async finalise(id) {
        const response = await apiClient.post(`/environment/reports/${id}/finalise`);
        return response.data;
    },
    async reopen(id) {
        const response = await apiClient.post(`/environment/reports/${id}/reopen`);
        return response.data;
    },
    async listAssets(id) {
        const response = await apiClient.get(`/environment/reports/${id}/assets`);
        return response.data;
    },
    async uploadAsset(id, kind, file, caption = '') {
        const fd = new FormData();
        fd.append('kind', kind);
        if (caption) fd.append('caption', caption);
        fd.append('file', file);
        const response = await apiClient.post(`/environment/reports/${id}/assets`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async updateAsset(assetId, data) {
        const response = await apiClient.put(`/environment/reports/assets/${assetId}`, data);
        return response.data;
    },
    async removeAsset(assetId) {
        const response = await apiClient.delete(`/environment/reports/assets/${assetId}`);
        return response.data;
    },
    assetUrl(assetId) {
        return `/api/environment/reports/assets/${assetId}/download`;
    },
    pdfUrl(id, inline = false) {
        return `/api/environment/reports/${id}/export/pdf${inline ? '?inline=1' : ''}`;
    },
    docxUrl(id) {
        return `/api/environment/reports/${id}/export/docx`;
    },
    async downloadPdf(id) {
        const response = await apiClient.get(`/environment/reports/${id}/export/pdf`, { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `environment-report-${id}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    },
    async downloadDocx(id) {
        const response = await apiClient.get(`/environment/reports/${id}/export/docx`, { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data], {
            type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `environment-report-${id}.docx`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    },
};

export default environmentReportService;
