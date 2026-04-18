import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_DASHBOARD_API_BASE_URL || '/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    timeout: 15000,
});

const endpointMap = {
    summary: '/dashboard/summary',
    timeline: '/dashboard/timeline',
    insights: '/dashboard/insights',
    simulator: '/dashboard/simulator',
    publicAnalysis: '/dashboard/github/public-analysis',
    privateConnection: '/dashboard/github/private-connection',
};

const unwrap = (response) => response?.data?.data ?? response?.data ?? null;

const requestSlice = async (slice, params) => {
    try {
        const response = await api.get(endpointMap[slice], { params });
        return unwrap(response);
    } catch (error) {
        if (import.meta.env.DEV) {
            console.debug(`[dashboard-api] ${slice} endpoint unavailable`, error?.message ?? error);
        }

        return null;
    }
};

const postSlice = async (slice, data) => {
    try {
        const response = await api.post(endpointMap[slice], data);
        return unwrap(response);
    } catch (error) {
        if (import.meta.env.DEV) {
            console.debug(`[dashboard-api] ${slice} endpoint unavailable`, error?.message ?? error);
        }

        return null;
    }
};

export const dashboardApi = {
    fetchSummary: (params) => requestSlice('summary', params),
    fetchTimeline: (params) => requestSlice('timeline', params),
    fetchInsights: (params) => requestSlice('insights', params),
    fetchSimulator: (params) => requestSlice('simulator', params),
    fetchPublicAnalysis: (params) => postSlice('publicAnalysis', params),
    connectPrivateAnalysis: (params) => postSlice('privateConnection', params),
};
