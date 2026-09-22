import apiClient from './apiClient';

const waterQualityService = {
    async list(params = {}) {
        const response = await apiClient.get('/environment/water-quality', { params });
        return response.data;
    },
    async get(id) {
        const response = await apiClient.get(`/environment/water-quality/${id}`);
        return response.data;
    },
    async create(data) {
        const response = await apiClient.post('/environment/water-quality', data);
        return response.data;
    },
    async update(id, data) {
        const response = await apiClient.put(`/environment/water-quality/${id}`, data);
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/environment/water-quality/${id}`);
        return response.data;
    },
    pdfUrl(id) {
        return `/api/environment/water-quality/${id}/pdf`;
    },
    async downloadPdf(id) {
        const response = await apiClient.get(`/environment/water-quality/${id}/pdf`, { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `water-quality-worksheet-${id}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    },
};

export default waterQualityService;
