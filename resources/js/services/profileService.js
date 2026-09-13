import apiClient from './apiClient';

const profileService = {
    async updateProfile(formData) {
        // POST + method spoofing so multipart/form-data (avatar) is parsed by
        // PHP, exactly matching the existing staffService.update() pattern.
        formData.append('_method', 'PUT');
        const response = await apiClient.post('/profile', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data;
    },
    async changePassword(data) {
        const response = await apiClient.put('/profile/password', data);
        return response.data;
    },
    getAvatarUrl() {
        return '/api/profile/avatar';
    },
};

export default profileService;
