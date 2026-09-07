import apiClient from './apiClient';

/**
 * Project sites (Ciri 25) — the physical sites/zones within a project that
 * operational records can be filed under.
 */
const projectSiteService = {
    async list(projectId, activeOnly = false) {
        const response = await apiClient.get('/project-sites', {
            params: { project_id: projectId, active_only: activeOnly ? 1 : undefined },
        });
        return response.data;
    },
    async create(data) {
        const response = await apiClient.post('/project-sites', data);
        return response.data;
    },
    async update(id, data) {
        const response = await apiClient.put(`/project-sites/${id}`, data);
        return response.data;
    },
    async remove(id) {
        const response = await apiClient.delete(`/project-sites/${id}`);
        return response.data;
    },
};

export default projectSiteService;
