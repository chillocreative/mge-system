import apiClient from './apiClient';

const discussionService = {
    async list(projectId, params = {}) {
        const response = await apiClient.get('/project-discussions', {
            params: { project_id: projectId, ...params },
        });
        return response.data;
    },
    async post(data) {
        const response = await apiClient.post('/project-discussions', data);
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/project-discussions/${id}`);
        return response.data;
    },
};

export default discussionService;
