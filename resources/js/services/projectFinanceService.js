import apiClient from './apiClient';

const projectFinanceService = {
    list(resource, params = {}) { return apiClient.get(`/project-finance/${resource}`, { params }); },
    create(resource, data) { return apiClient.post(`/project-finance/${resource}`, data); },
    update(resource, id, data) { return apiClient.put(`/project-finance/${resource}/${id}`, data); },
    remove(resource, id) { return apiClient.delete(`/project-finance/${resource}/${id}`); },
    chart(params = {}) { return apiClient.get('/project-finance/reports/chart', { params }); },
    import(file, projectId, resource = 'auto') { const fd = new FormData(); fd.append('file', file); fd.append('project_id', projectId); fd.append('resource', resource); return apiClient.post('/project-finance/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } }); },
    exportUrl(resource, params = {}, format = 'xlsx') { const query = new URLSearchParams({ ...params, format }).toString(); return `/api/project-finance/${resource}/export?${query}`; },
};

export default projectFinanceService;
