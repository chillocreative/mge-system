import axios from 'axios';
import apiClient from './apiClient';

const getCsrfCookie = () => {
    return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
};

const authService = {
    async login(credentials) {
        await getCsrfCookie();
        const response = await apiClient.post('/login', credentials);
        return response.data;
    },

    async register(data) {
        await getCsrfCookie();
        const response = await apiClient.post('/register', data);
        return response.data;
    },

    async logout() {
        const response = await apiClient.post('/logout');
        return response.data;
    },

    async getUser() {
        const response = await apiClient.get('/user');
        return response.data;
    },
};

export default authService;
