import apiClient from './apiClient';

const inventoryService = {
    // ── Categories ──
    async listCategories() {
        const response = await apiClient.get('/inventory/categories');
        return response.data;
    },
    async createCategory(data) {
        const response = await apiClient.post('/inventory/categories', data);
        return response.data;
    },
    async updateCategory(id, data) {
        const response = await apiClient.put(`/inventory/categories/${id}`, data);
        return response.data;
    },
    async deleteCategory(id) {
        const response = await apiClient.delete(`/inventory/categories/${id}`);
        return response.data;
    },

    // ── Items ──
    async listItems(params = {}) {
        const response = await apiClient.get('/inventory/items', { params });
        return response.data;
    },
    async getItem(id) {
        const response = await apiClient.get(`/inventory/items/${id}`);
        return response.data;
    },
    async createItem(data) {
        const response = await apiClient.post('/inventory/items', data);
        return response.data;
    },
    async updateItem(id, data) {
        const response = await apiClient.put(`/inventory/items/${id}`, data);
        return response.data;
    },
    async deleteItem(id) {
        const response = await apiClient.delete(`/inventory/items/${id}`);
        return response.data;
    },

    // ── Transactions ──
    async listTransactions(itemId, params = {}) {
        const response = await apiClient.get(`/inventory/items/${itemId}/transactions`, { params });
        return response.data;
    },
    async recordTransaction(itemId, data) {
        const response = await apiClient.post(`/inventory/items/${itemId}/transactions`, data);
        return response.data;
    },

    // ── Low Stock ──
    async getLowStock() {
        const response = await apiClient.get('/inventory/items/low-stock');
        return response.data;
    },
};

export default inventoryService;
