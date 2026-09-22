import apiClient from './apiClient';

const mailSettingService = {
    async get() {
        const res = await apiClient.get('/settings/mail');
        return res.data;
    },
    async status() {
        const res = await apiClient.get('/settings/mail/status');
        return res.data;
    },
    async update(data) {
        const res = await apiClient.put('/settings/mail', data);
        return res.data;
    },
    async test(data) {
        const res = await apiClient.post('/settings/mail/test', data);
        return res.data;
    },
};

export default mailSettingService;
