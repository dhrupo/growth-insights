import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_DASHBOARD_API_BASE_URL || '/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    timeout: 60000,
});

const endpointMap = {
    summary: '/dashboard/summary',
    timeline: '/dashboard/timeline',
    insights: '/dashboard/insights',
    simulator: '/dashboard/simulator',
    latestAnalysis: (username) => `/dashboard/github/latest-analysis/${encodeURIComponent(username)}`,
    publicAnalysis: '/dashboard/github/public-analysis',
};

const unwrap = (response) => response?.data?.data ?? response?.data ?? null;
const failure = (error) => ({
    data: null,
    error: {
        status: error?.response?.status ?? null,
        message: error?.response?.data?.message ?? error?.message ?? 'Request failed.',
    },
});

const requestSlice = async (slice, params) => {
    try {
        const response = await api.get(endpointMap[slice], { params });
        return {
            data: unwrap(response),
            error: null,
        };
    } catch (error) {
        if (import.meta.env.DEV) {
            console.debug(`[dashboard-api] ${slice} endpoint unavailable`, error?.message ?? error);
        }

        return failure(error);
    }
};

const postSlice = async (slice, data) => {
    try {
        const response = await api.post(endpointMap[slice], data);
        return {
            data: unwrap(response),
            error: null,
        };
    } catch (error) {
        if (import.meta.env.DEV) {
            console.debug(`[dashboard-api] ${slice} endpoint unavailable`, error?.message ?? error);
        }

        return failure(error);
    }
};

export const dashboardApi = {
    fetchLatestAnalysis: async (username) => {
        try {
            const response = await api.get(endpointMap.latestAnalysis(username));
            return {
                data: unwrap(response),
                error: null,
            };
        } catch (error) {
            if (import.meta.env.DEV) {
                console.debug('[dashboard-api] latest analysis endpoint unavailable', error?.message ?? error);
            }

            return failure(error);
        }
    },
    fetchSummary: (params) => requestSlice('summary', params),
    fetchTimeline: (params) => requestSlice('timeline', params),
    fetchInsights: (params) => requestSlice('insights', params),
    fetchSimulator: (params) => requestSlice('simulator', params),
    fetchPublicAnalysis: (params) => postSlice('publicAnalysis', params),
};
