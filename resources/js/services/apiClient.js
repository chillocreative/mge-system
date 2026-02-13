import axios from 'axios';

const apiClient = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    withXSRFToken: true,
});

// Response interceptor for error handling
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            // CSRF token mismatch - refresh page
            window.location.reload();
        }

        // 401 is handled by AuthContext + ProtectedRoute (React Router)
        // No hard redirect here to avoid infinite reload loops

        return Promise.reject(error);
    }
);

export default apiClient;
