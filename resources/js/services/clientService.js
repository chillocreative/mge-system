import apiClient from './apiClient';

const clientService = {
    async list(params = {}) {
        const response = await apiClient.get('/clients', { params });
        return response.data;
    },

    async get(id) {
        const response = await apiClient.get(`/clients/${id}`);
        return response.data;
    },

    async create(data) {
        const response = await apiClient.post('/clients', data);
        return response.data;
    },

    async update(id, data) {
        const response = await apiClient.put(`/clients/${id}`, data);
        return response.data;
    },

    async delete(id) {
        const response = await apiClient.delete(`/clients/${id}`);
        return response.data;
    },
};

export default clientService;
