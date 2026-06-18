import apiClient from './apiClient';

const payrollService = {
    async list(params = {}) {
        const res = await apiClient.get('/payroll', { params });
        return res.data;
    },
    async get(id) {
        const res = await apiClient.get(`/payroll/${id}`);
        return res.data;
    },
    async summary(params = {}) {
        const res = await apiClient.get('/payroll/summary', { params });
        return res.data;
    },
    async config() {
        const res = await apiClient.get('/payroll/config');
        return res.data;
    },
    async generate(data) {
        const res = await apiClient.post('/payroll/generate', data);
        return res.data;
    },
    async recalculate(id, data) {
        const res = await apiClient.patch(`/payroll/${id}/recalculate`, data);
        return res.data;
    },
    async approve(id) {
        const res = await apiClient.patch(`/payroll/${id}/approve`);
        return res.data;
    },
    async markPaid(id) {
        const res = await apiClient.patch(`/payroll/${id}/mark-paid`);
        return res.data;
    },
    async emailPayslip(id) {
        const res = await apiClient.post(`/payroll/${id}/email-payslip`);
        return res.data;
    },
    async batchEmail(data) {
        const res = await apiClient.post('/payroll/batch-email', data);
        return res.data;
    },
    payslipUrl(id) {
        return `/api/payroll/${id}/payslip`;
    },
    eaFormUrl(employeeId, year) {
        return `/api/payroll/ea-form/${employeeId}/${year}`;
    },
};

export default payrollService;
