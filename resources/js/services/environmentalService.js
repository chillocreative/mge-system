import apiClient from './apiClient';

const environmentalService = {
    // Overview
    getOverview: (projectId) =>
        apiClient.get('/environmental/overview', { params: { project_id: projectId } }),

    // Waste Records
    getWasteRecords: (params) => apiClient.get('/environmental/waste', { params }),
    createWaste: (formData) =>
        apiClient.post('/environmental/waste', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
    updateWaste: (id, data) => apiClient.put(`/environmental/waste/${id}`, data),

    // Site Inspections
    getInspections: (params) => apiClient.get('/environmental/inspections', { params }),
    getInspection: (id) => apiClient.get(`/environmental/inspections/${id}`),
    createInspection: (formData) =>
        apiClient.post('/environmental/inspections', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
    updateInspection: (id, data) => apiClient.put(`/environmental/inspections/${id}`, data),
    getInspectionPdfUrl: (id) => `/api/environmental/inspections/${id}/pdf`,

    // Environmental Audits
    getAudits: (params) => apiClient.get('/environmental/audits', { params }),
    getAudit: (id) => apiClient.get(`/environmental/audits/${id}`),
    createAudit: (formData) =>
        apiClient.post('/environmental/audits', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
    updateAudit: (id, data) => apiClient.put(`/environmental/audits/${id}`, data),
    getAuditPdfUrl: (id) => `/api/environmental/audits/${id}/pdf`,

    // Photos
    uploadPhotos: (type, id, formData) =>
        apiClient.post(`/environmental/${type}/${id}/photos`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
};

export default environmentalService;
