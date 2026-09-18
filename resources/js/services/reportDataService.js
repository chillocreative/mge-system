import apiClient from './apiClient';

const p = (projectId, path = '') => `/projects/${projectId}${path}`;

const reportDataService = {
    async getContractParticulars(projectId) { return (await apiClient.get(p(projectId, '/contract-particulars'))).data; },
    async updateContractParticulars(projectId, data) { return (await apiClient.put(p(projectId, '/contract-particulars'), data)).data; },

    async listParties(projectId) { return (await apiClient.get(p(projectId, '/parties'))).data; },
    async createParty(projectId, data) { return (await apiClient.post(p(projectId, '/parties'), data)).data; },
    async updateParty(projectId, partyId, data) { return (await apiClient.put(p(projectId, `/parties/${partyId}`), data)).data; },
    async deleteParty(projectId, partyId) { return (await apiClient.delete(p(projectId, `/parties/${partyId}`))).data; },
    async uploadPartyLogo(projectId, partyId, formData) {
        return (await apiClient.post(p(projectId, `/parties/${partyId}/logo`), formData, { headers: { 'Content-Type': 'multipart/form-data' } })).data;
    },
    getPartyLogoUrl(projectId, partyId) { return `/api${p(projectId, `/parties/${partyId}/logo`)}`; },

    async getOrgChart(projectId) { return (await apiClient.get(p(projectId, '/org-chart'))).data; },
    async updateOrgChart(projectId, members) { return (await apiClient.put(p(projectId, '/org-chart'), { members })).data; },

    async getBaseline(projectId) { return (await apiClient.get(p(projectId, '/schedule-baseline'))).data; },
    async replaceBaseline(projectId, rows) { return (await apiClient.put(p(projectId, '/schedule-baseline'), { rows })).data; },
    async listPeriods(projectId) { return (await apiClient.get(p(projectId, '/progress-periods'))).data; },
    async suggestPeriod(projectId, periodEnd) { return (await apiClient.get(p(projectId, '/progress-periods/suggest'), { params: { period_end: periodEnd } })).data; },
    async createPeriod(projectId, data) { return (await apiClient.post(p(projectId, '/progress-periods'), data)).data; },
    async updatePeriod(projectId, id, data) { return (await apiClient.put(p(projectId, `/progress-periods/${id}`), data)).data; },
    async deletePeriod(projectId, id) { return (await apiClient.delete(p(projectId, `/progress-periods/${id}`))).data; },

    async listDelayNotices(projectId) { return (await apiClient.get(p(projectId, '/delay-notices'))).data; },
    async createDelayNotice(projectId, data) { return (await apiClient.post(p(projectId, '/delay-notices'), data)).data; },
    async updateDelayNotice(projectId, id, data) { return (await apiClient.put(p(projectId, `/delay-notices/${id}`), data)).data; },
    async deleteDelayNotice(projectId, id) { return (await apiClient.delete(p(projectId, `/delay-notices/${id}`))).data; },

    async listTests(projectId) { return (await apiClient.get(p(projectId, '/tests'))).data; },
    async createTest(projectId, data) { return (await apiClient.post(p(projectId, '/tests'), data)).data; },
    async updateTest(projectId, id, data) { return (await apiClient.put(p(projectId, `/tests/${id}`), data)).data; },
    async deleteTest(projectId, id) { return (await apiClient.delete(p(projectId, `/tests/${id}`))).data; },

    async getCategories(projectId, kind) { return (await apiClient.get(p(projectId, '/resource-categories'), { params: { kind } })).data; },
    async replaceCategories(projectId, kind, rows) { return (await apiClient.put(p(projectId, `/resource-categories?kind=${kind}`), { kind, rows })).data; },
    async seedCategories(projectId, kind) { return (await apiClient.post(p(projectId, `/resource-categories/seed-defaults?kind=${kind}`))).data; },

    async listProgrammeVersions(projectId) { return (await apiClient.get(p(projectId, '/programme-versions'))).data; },
    async previewProgrammeXlsx(projectId, file) {
        const formData = new FormData();
        formData.append('file', file);
        return (await apiClient.post(p(projectId, '/programme-versions/preview'), formData)).data;
    },
    async importProgrammeXlsx(projectId, payload) { return (await apiClient.post(p(projectId, '/programme-versions/import'), payload)).data; },
    async importProgrammeMspdi(projectId, file, meta = {}) {
        const formData = new FormData();
        formData.append('file', file);
        Object.entries(meta).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') formData.append(k, v);
        });
        return (await apiClient.post(p(projectId, '/programme-versions/import-mspdi'), formData)).data;
    },
    async updateProgrammeVersion(projectId, id, data) { return (await apiClient.put(p(projectId, `/programme-versions/${id}`), data)).data; },
    async deleteProgrammeVersion(projectId, id) { return (await apiClient.delete(p(projectId, `/programme-versions/${id}`))).data; },
    async listProgrammeActivities(projectId, id, params = {}) { return (await apiClient.get(p(projectId, `/programme-versions/${id}/activities`), { params })).data; },

    async listImages(projectId, params = {}) { return (await apiClient.get(p(projectId, '/report-images'), { params })).data; },
    async uploadImage(projectId, formData) {
        return (await apiClient.post(p(projectId, '/report-images'), formData, { headers: { 'Content-Type': 'multipart/form-data' } })).data;
    },
    async updateImage(projectId, id, data) { return (await apiClient.put(p(projectId, `/report-images/${id}`), data)).data; },
    async deleteImage(projectId, id) { return (await apiClient.delete(p(projectId, `/report-images/${id}`))).data; },
    getImageViewUrl(projectId, id) { return `/api${p(projectId, `/report-images/${id}/view`)}`; },
};

export default reportDataService;
