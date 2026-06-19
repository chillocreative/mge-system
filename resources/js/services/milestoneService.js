import apiClient from './apiClient';

const milestoneService = {
    async list(params = {}) {
        const res = await apiClient.get('/milestones', { params });
        return res.data;
    },
    async create(data) {
        const res = await apiClient.post('/milestones', data);
        return res.data;
    },
    async update(id, data) {
        const res = await apiClient.put(`/milestones/${id}`, data);
        return res.data;
    },
    async remove(id) {
        const res = await apiClient.delete(`/milestones/${id}`);
        return res.data;
    },
};

export default milestoneService;
