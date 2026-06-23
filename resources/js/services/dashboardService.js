import apiClient from './apiClient';

const dashboardService = {
    async getData(departmentId) {
        const response = await apiClient.get('/dashboard', {
            params: departmentId ? { department_id: departmentId } : {},
        });
        return response.data;
    },
};

export default dashboardService;
