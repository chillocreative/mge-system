import apiClient from './apiClient';

const materialService = {
    list(params = {}) { return apiClient.get('/materials', { params }); },
    create(data) { return apiClient.post('/materials', data); },
    update(id, data) { return apiClient.put(`/materials/${id}`, data); },
};

export default materialService;
