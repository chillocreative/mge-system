import apiClient from './apiClient';

const roleService = {
    async list() {
        const res = await apiClient.get('/roles');
        return res.data;
    },
    async get(id) {
        const res = await apiClient.get(`/roles/${id}`);
        return res.data;
    },
    async create(data) {
        const res = await apiClient.post('/roles', data);
        return res.data;
    },
    async update(id, data) {
        const res = await apiClient.put(`/roles/${id}`, data);
        return res.data;
    },
    async remove(id) {
        const res = await apiClient.delete(`/roles/${id}`);
        return res.data;
    },
    async permissions() {
        const res = await apiClient.get('/permissions');
        return res.data;
    },

    // ── Per-user access ──
    async getUserAccess(userId) {
        const res = await apiClient.get(`/users/${userId}/access`);
        return res.data;
    },
    async updateUserAccess(userId, data) {
        const res = await apiClient.put(`/users/${userId}/access`, data);
        return res.data;
    },
};

export default roleService;
