import apiClient from './apiClient';

const base = '/environment/documents';

export default {
    list(params) { return apiClient.get(base, { params }); },
    create(data) { return apiClient.post(base, data, { headers: { 'Content-Type': 'multipart/form-data' } }); },
    download(id) { return apiClient.get(`${base}/${id}/download`, { responseType: 'blob' }); },
    remove(id) { return apiClient.delete(`${base}/${id}`); },
};
