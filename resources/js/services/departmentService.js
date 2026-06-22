import apiClient from './apiClient';

const departmentService = {
    async list(params = { per_page: 200 }) {
        const res = await apiClient.get('/departments', { params });
        return res.data;
    },
    async create(data) {
        const res = await apiClient.post('/departments', data);
        return res.data;
    },
    async update(id, data) {
        const res = await apiClient.put(`/departments/${id}`, data);
        return res.data;
    },
    async remove(id) {
        const res = await apiClient.delete(`/departments/${id}`);
        return res.data;
    },
};

export default departmentService;
