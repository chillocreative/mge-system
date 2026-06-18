import apiClient from './apiClient';

const attendanceService = {
    async list(params = {}) {
        const res = await apiClient.get('/attendance', { params });
        return res.data;
    },
    async summary(params = {}) {
        const res = await apiClient.get('/attendance/summary', { params });
        return res.data;
    },
    async uploadHistory() {
        const res = await apiClient.get('/attendance/uploads');
        return res.data;
    },
    async upload(formData) {
        const res = await apiClient.post('/attendance/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return res.data;
    },
    async deleteBatch(batch) {
        const res = await apiClient.delete(`/attendance/batch/${batch}`);
        return res.data;
    },
};

export default attendanceService;
