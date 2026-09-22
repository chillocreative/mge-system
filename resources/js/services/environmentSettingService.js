import apiClient from './apiClient';

const environmentSettingService = {
    async get(projectId) {
        const response = await apiClient.get(`/environment/settings/${projectId}`);
        return response.data;
    },
    async update(projectId, data) {
        const response = await apiClient.put(`/environment/settings/${projectId}`, data);
        return response.data;
    },
    async uploadImage(projectId, kind, file) {
        const fd = new FormData();
        fd.append('kind', kind);
        fd.append('file', file);
        const response = await apiClient.post(`/environment/settings/${projectId}/image`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    imageUrl(projectId, kind) {
        return `/api/environment/settings/${projectId}/image/${kind}`;
    },
    async removeImage(projectId, kind) {
        const response = await apiClient.delete(`/environment/settings/${projectId}/image/${kind}`);
        return response.data;
    },
};

export default environmentSettingService;
