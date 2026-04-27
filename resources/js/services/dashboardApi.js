import axios from 'axios';

const DEFAULT_TIMEOUT = 60000;
const LONG_RUNNING_TIMEOUT = 180000;

const api = axios.create({
    baseURL: import.meta.env.VITE_DASHBOARD_API_BASE_URL || '/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    timeout: DEFAULT_TIMEOUT,
});

const endpointMap = {
    summary: '/dashboard/summary',
    timeline: '/dashboard/timeline',
    insights: '/dashboard/insights',
    simulator: '/dashboard/simulator',
    currentAnalysis: '/dashboard/github/current-analysis',
    syncCurrentAnalysis: '/dashboard/github/current-analysis/sync',
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

const postSlice = async (slice, data, config = {}) => {
    try {
        const response = await api.post(endpointMap[slice], data, config);
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
    fetchCurrentAnalysis: async () => {
        try {
            const response = await api.get(endpointMap.currentAnalysis);
            return {
                data: unwrap(response),
                error: null,
            };
        } catch (error) {
            if (import.meta.env.DEV) {
                console.debug('[dashboard-api] current analysis endpoint unavailable', error?.message ?? error);
            }

            return failure(error);
        }
    },
    syncCurrentAnalysis: async () => postSlice('syncCurrentAnalysis', undefined, {
        timeout: LONG_RUNNING_TIMEOUT,
    }),
    fetchSummary: (params) => requestSlice('summary', params),
    fetchTimeline: (params) => requestSlice('timeline', params),
    fetchInsights: (params) => requestSlice('insights', params),
    fetchSimulator: (params) => requestSlice('simulator', params),
};
