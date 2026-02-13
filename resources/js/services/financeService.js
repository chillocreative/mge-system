import apiClient from './apiClient';

const financeService = {
    // ── Finance Overview & Reports ──
    async getOverview() {
        const response = await apiClient.get('/finance/overview');
        return response.data;
    },
    async getMonthlySummary(params = {}) {
        const response = await apiClient.get('/finance/monthly-summary', { params });
        return response.data;
    },
    async getBudgetVsActual() {
        const response = await apiClient.get('/finance/budget-vs-actual');
        return response.data;
    },

    // ── Invoices ──
    async listInvoices(params = {}) {
        const response = await apiClient.get('/invoices', { params });
        return response.data;
    },
    async getInvoice(id) {
        const response = await apiClient.get(`/invoices/${id}`);
        return response.data;
    },
    async createInvoice(data) {
        const response = await apiClient.post('/invoices', data);
        return response.data;
    },
    async updateInvoice(id, data) {
        const response = await apiClient.put(`/invoices/${id}`, data);
        return response.data;
    },
    async deleteInvoice(id) {
        const response = await apiClient.delete(`/invoices/${id}`);
        return response.data;
    },
    async recordPayment(invoiceId, data) {
        const response = await apiClient.post(`/invoices/${invoiceId}/payments`, data);
        return response.data;
    },
    async markAsSent(invoiceId) {
        const response = await apiClient.patch(`/invoices/${invoiceId}/mark-sent`);
        return response.data;
    },
    getPdfDownloadUrl(invoiceId) {
        return `/api/invoices/${invoiceId}/pdf`;
    },
    getPdfPreviewUrl(invoiceId) {
        return `/api/invoices/${invoiceId}/pdf/preview`;
    },

    // ── Expenses ──
    async listExpenses(params = {}) {
        const response = await apiClient.get('/expenses', { params });
        return response.data;
    },
    async createExpense(formData) {
        const response = await apiClient.post('/expenses', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async deleteExpense(id) {
        const response = await apiClient.delete(`/expenses/${id}`);
        return response.data;
    },
    async approveExpense(id) {
        const response = await apiClient.patch(`/expenses/${id}/approve`);
        return response.data;
    },
    async rejectExpense(id) {
        const response = await apiClient.patch(`/expenses/${id}/reject`);
        return response.data;
    },
};

export default financeService;
