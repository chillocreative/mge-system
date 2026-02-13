import apiClient from './apiClient';

const dashboardService = {
    async getData() {
        const response = await apiClient.get('/dashboard');
        return response.data;
    },
};

export default dashboardService;
