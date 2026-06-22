import apiClient from './apiClient';

const designationService = {
    async list(params = { per_page: 200 }) {
        const res = await apiClient.get('/designations', { params });
        return res.data;
    },
    async create(data) {
        const res = await apiClient.post('/designations', data);
        return res.data;
    },
    async update(id, data) {
        const res = await apiClient.put(`/designations/${id}`, data);
        return res.data;
    },
    async remove(id) {
        const res = await apiClient.delete(`/designations/${id}`);
        return res.data;
    },
};

export default designationService;
