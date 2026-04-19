import { defineStore } from 'pinia';

import { dashboardApi } from '@/services/dashboardApi';

const clone = (value) => JSON.parse(JSON.stringify(value));

const humanizeUsername = (value) =>
    value
        .split(/[._-]+/g)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');

const mergeAnalysisResponse = (baseAnalysis, payload, username = '') => {
    const analysis = clone(baseAnalysis);
    const resolvedUsername = username
        || payload?.profile?.username
        || payload?.username
        || analysis.username
        || '';

    analysis.analysisRunId = payload?.analysisRunId ?? analysis.analysisRunId ?? null;
    analysis.username = resolvedUsername;
    analysis.profile.username = resolvedUsername;
    analysis.profile.displayName = payload?.profile?.displayName
        ?? payload?.profile?.name
        ?? (resolvedUsername ? humanizeUsername(resolvedUsername) : '');
    analysis.profile.role = payload?.profile?.role
        ?? analysis.profile.role;
    analysis.profile.bio = payload?.profile?.bio
        ?? analysis.profile.bio;
    analysis.profile.followers = payload?.profile?.followers ?? analysis.profile.followers;
    analysis.profile.publicRepos = payload?.profile?.publicRepos ?? analysis.profile.publicRepos;
    analysis.profile.contributionStreak = payload?.profile?.contributionStreak ?? analysis.profile.contributionStreak;
    analysis.profile.publicPullRequests = payload?.profile?.publicPullRequests ?? analysis.profile.publicPullRequests;
    analysis.summary = Array.isArray(payload?.summary) && payload.summary.length
        ? payload.summary
        : analysis.summary;
    analysis.evidenceSummary = payload?.evidenceSummary ?? analysis.evidenceSummary ?? null;
    analysis.weeklyPlan = Array.isArray(payload?.weeklyPlan) ? payload.weeklyPlan : (analysis.weeklyPlan ?? []);
    analysis.thirtyDayPlan = Array.isArray(payload?.thirtyDayPlan) ? payload.thirtyDayPlan : (analysis.thirtyDayPlan ?? []);
    analysis.connection = payload?.connection
        ? {
            ...analysis.connection,
            ...payload.connection,
        }
        : analysis.connection;
    analysis.scoreBreakdown = payload?.scoreBreakdown ?? analysis.scoreBreakdown;
    analysis.skillDistribution = payload?.skillDistribution ?? analysis.skillDistribution;
    analysis.skillSignals = Array.isArray(payload?.skillSignals) ? payload.skillSignals : (analysis.skillSignals ?? []);
    analysis.strengths = Array.isArray(payload?.strengths) && payload.strengths.length
        ? payload.strengths
        : analysis.strengths;
    analysis.weaknesses = Array.isArray(payload?.weaknesses) && payload.weaknesses.length
        ? payload.weaknesses
        : analysis.weaknesses;
    analysis.recommendations = Array.isArray(payload?.recommendations) && payload.recommendations.length
        ? payload.recommendations
        : analysis.recommendations;
    analysis.improvementActions = Array.isArray(payload?.improvementActions) ? payload.improvementActions : (analysis.improvementActions ?? []);
    analysis.howToGetNoticed = payload?.howToGetNoticed ?? analysis.howToGetNoticed;
    analysis.trajectory = payload?.trajectory ?? analysis.trajectory;
    analysis.contributionStyle = payload?.contributionStyle ?? analysis.contributionStyle;
    analysis.credibilityNotice = payload?.credibilityNotice ?? analysis.credibilityNotice ?? null;
    analysis.analyzedRepositories = Array.isArray(payload?.analyzedRepositories) ? payload.analyzedRepositories : (analysis.analyzedRepositories ?? []);
    analysis.suggestedRepositories = Array.isArray(payload?.suggestedRepositories) ? payload.suggestedRepositories : (analysis.suggestedRepositories ?? []);
    analysis.source = payload?.source ?? analysis.source;
    analysis.status = 'ready';
    analysis.lastAnalyzedAt = payload?.lastAnalyzedAt ?? new Date().toISOString();
    analysis.error = null;

    return analysis;
};

const defaultFilters = () => ({
    range: '14d',
    segment: 'all',
    query: '',
    mode: 'simple',
});

const emptySummary = () => [];

const emptyTimeline = () => ({
    categories: [],
    series: [],
    updatedAt: null,
});

const emptyAcquisitionMix = () => ({
    categories: [],
    values: [],
});

const emptySimulator = () => ({
    current: null,
    projected: null,
    uplift: null,
    confidence: null,
    notes: [],
    series: [],
});

const emptyAnalysis = () => ({
    analysisRunId: null,
    username: '',
    source: 'empty',
    status: 'idle',
    lastAnalyzedAt: null,
    evidenceSummary: null,
    weeklyPlan: [],
    thirtyDayPlan: [],
    profile: {
        username: '',
        displayName: '',
        role: '',
        bio: '',
        followers: 0,
        publicRepos: 0,
        contributionStreak: 0,
        publicPullRequests: 0,
    },
    summary: [],
    connection: {
        enabled: false,
        connected: false,
        workspace: 'GitHub is not connected',
        note: 'Connect GitHub to analyze your own contribution history and optionally include private repositories you authorize.',
    },
    scoreBreakdown: {
        categories: [],
        values: [],
        benchmark: [],
    },
    skillDistribution: {
        categories: [],
        values: [],
    },
    skillSignals: [],
    strengths: [],
    weaknesses: [],
    recommendations: [],
    improvementActions: [],
    howToGetNoticed: {
        summary: '',
        actions: [],
    },
    trajectory: {
        windows: [],
        summary: '',
        outlook: '',
        confidence: 'Low',
    },
    contributionStyle: {
        label: '',
        summary: '',
        confidence: '',
        evidence: [],
    },
    credibilityNotice: null,
    analyzedRepositories: [],
    suggestedRepositories: [],
    error: null,
});

const defaultSliceState = (value) => clone(value);

const sliceDefinitions = [
    {
        key: 'summary',
        fetch: dashboardApi.fetchSummary,
        fallback: emptySummary(),
        apply(state, value) {
            state.summary = clone(value);
        },
        normalize: (payload) => {
            if (!payload) {
                return null;
            }

            if (Array.isArray(payload.metrics)) {
                return payload.metrics;
            }

            if (Array.isArray(payload.summary)) {
                return payload.summary;
            }

            if (Array.isArray(payload.items)) {
                return payload.items;
            }

            return null;
        },
    },
    {
        key: 'timeline',
        fetch: dashboardApi.fetchTimeline,
        fallback: emptyTimeline(),
        apply(state, value) {
            state.timeline = clone(value);
        },
        normalize: (payload) => {
            if (!payload) {
                return null;
            }

            if (Array.isArray(payload.categories) && Array.isArray(payload.series)) {
                return {
                    categories: payload.categories,
                    series: payload.series,
                    updatedAt: payload.updatedAt ?? payload.updated_at ?? null,
                };
            }

            if (Array.isArray(payload.labels) && Array.isArray(payload.series)) {
                return {
                    categories: payload.labels,
                    series: payload.series,
                    updatedAt: payload.updatedAt ?? payload.updated_at ?? null,
                };
            }

            return null;
        },
    },
    {
        key: 'insights',
        fetch: dashboardApi.fetchInsights,
        fallback: {
            acquisitionMix: emptyAcquisitionMix(),
            strengths: [],
            weaknesses: [],
            recommendations: [],
        },
        apply(state, value) {
            if (value.acquisitionMix) {
                state.acquisitionMix = clone(value.acquisitionMix);
            }

            if (Array.isArray(value.strengths)) {
                state.strengths = clone(value.strengths);
            }

            if (Array.isArray(value.weaknesses)) {
                state.weaknesses = clone(value.weaknesses);
            }

            if (Array.isArray(value.recommendations)) {
                state.recommendations = clone(value.recommendations);
            }
        },
        normalize: (payload) => {
            if (!payload) {
                return null;
            }

            return {
                acquisitionMix: payload.acquisitionMix ?? payload.mix ?? null,
                strengths: payload.strengths ?? null,
                weaknesses: payload.weaknesses ?? null,
                recommendations: payload.recommendations ?? null,
            };
        },
    },
    {
        key: 'simulator',
        fetch: dashboardApi.fetchSimulator,
        fallback: emptySimulator(),
        apply(state, value) {
            state.simulator = clone(value);
        },
        normalize: (payload) => {
            if (!payload) {
                return null;
            }

            return {
                current: payload.current ?? null,
                projected: payload.projected ?? null,
                uplift: payload.uplift ?? null,
                confidence: payload.confidence ?? null,
                notes: payload.notes ?? null,
                series: payload.series ?? null,
            };
        },
    },
];

let requestCounter = 0;

export const useDashboardStore = defineStore('dashboard', {
    state: () => ({
        filters: defaultFilters(),
        status: 'idle',
        dataSource: 'empty',
        error: null,
        lastSyncedAt: null,
        analysis: emptyAnalysis(),
        analysisStatus: 'idle',
        analysisError: null,
        hasAutoStartedAnalysis: false,
        summary: defaultSliceState(emptySummary()),
        timeline: defaultSliceState(emptyTimeline()),
        acquisitionMix: defaultSliceState(emptyAcquisitionMix()),
        strengths: [],
        weaknesses: [],
        recommendations: [],
        simulator: defaultSliceState(emptySimulator()),
    }),
    getters: {
        isLoading: (state) => state.status === 'loading',
        isRefreshing: (state) => state.status === 'refreshing',
        hasLiveData: (state) => state.dataSource === 'live' || state.dataSource === 'mixed',
        hasAnalysisRun: (state) => Boolean(state.analysis?.analysisRunId),
        syncLabel: (state) => {
            if (!state.lastSyncedAt) {
                return 'Not analyzed yet';
            }

            return new Intl.DateTimeFormat(undefined, {
                hour: 'numeric',
                minute: '2-digit',
            }).format(new Date(state.lastSyncedAt));
        },
    },
    actions: {
        resetAnalysisState() {
            this.analysis = {
                ...emptyAnalysis(),
            };
            this.summary = [];
            this.timeline = emptyTimeline();
            this.acquisitionMix = emptyAcquisitionMix();
            this.strengths = [];
            this.weaknesses = [];
            this.recommendations = [];
            this.simulator = emptySimulator();
            this.dataSource = 'empty';
            this.status = 'idle';
            this.lastSyncedAt = null;
            this.error = null;
            this.analysisStatus = 'idle';
            this.analysisError = null;
            this.hasAutoStartedAnalysis = false;
        },
        setFilters(patch = {}) {
            Object.assign(this.filters, patch);
        },
        async fetchDashboardData(overrides = {}) {
            const requestId = ++requestCounter;
            const nextFilters = {
                ...this.filters,
                ...overrides,
            };

            Object.assign(this.filters, nextFilters);
            this.status = this.lastSyncedAt ? 'refreshing' : 'loading';
            this.error = null;

            if (!this.analysis?.analysisRunId) {
                this.summary = [];
                this.timeline = emptyTimeline();
                this.acquisitionMix = emptyAcquisitionMix();
                this.strengths = [];
                this.weaknesses = [];
                this.recommendations = [];
                this.simulator = emptySimulator();
                this.dataSource = 'empty';
                this.status = 'ready';
                this.lastSyncedAt = null;
                this.error = null;

                return this;
            }

            const query = {
                analysis_run_id: this.analysis.analysisRunId,
                range: nextFilters.range,
                segment: nextFilters.segment,
                query: nextFilters.query,
                mode: nextFilters.mode,
            };

            const settled = await Promise.allSettled(
                sliceDefinitions.map(async (slice) => {
                    const response = await slice.fetch(query);
                    const normalized = slice.normalize(response?.data ?? null);

                    return {
                        key: slice.key,
                        value: normalized ?? clone(slice.fallback),
                        live: Boolean(normalized),
                        apply: slice.apply,
                    };
                }),
            );

            if (requestId !== requestCounter) {
                return;
            }

            let liveSlices = 0;

            settled.forEach((result) => {
                if (result.status !== 'fulfilled') {
                    return;
                }

                const { value, live, apply } = result.value;
                apply(this, value);

                if (live) {
                    liveSlices += 1;
                }
            });

            this.dataSource = liveSlices === sliceDefinitions.length
                ? 'live'
                : liveSlices > 0
                    ? 'mixed'
                    : 'empty';

            this.status = 'ready';
            this.lastSyncedAt = new Date().toISOString();
            this.error = liveSlices === 0
                ? 'No dashboard slices were returned for the selected analysis yet.'
                : null;

            return this;
        },
        async refresh() {
            return this.fetchDashboardData();
        },
        async syncCurrentAnalysis() {
            this.analysisStatus = 'loading';
            this.analysisError = null;

            const payload = await dashboardApi.syncCurrentAnalysis();
            const responsePayload = payload?.data ?? null;
            const responseError = payload?.error ?? null;

            if (!responsePayload) {
                this.analysisStatus = 'error';
                this.analysisError = responseError?.message ?? 'Analysis failed.';
                return null;
            }

            this.analysis = mergeAnalysisResponse(emptyAnalysis(), responsePayload);
            this.analysisStatus = 'ready';
            this.dataSource = responsePayload.source === 'mixed' ? 'mixed' : 'live';
            this.analysisError = null;
            await this.fetchDashboardData();

            return this.analysis;
        },
        async ensureCurrentAnalysis() {
            if (!this.analysis?.connection?.connected || this.analysis?.analysisRunId || this.hasAutoStartedAnalysis) {
                return this.analysis;
            }

            this.hasAutoStartedAnalysis = true;

            return this.syncCurrentAnalysis();
        },
        async loadCurrentAnalysis() {
            this.analysisStatus = 'loading';
            this.analysisError = null;

            const payload = await dashboardApi.fetchCurrentAnalysis();
            const responsePayload = payload?.data ?? null;
            const responseError = payload?.error ?? null;

            if (!responsePayload) {
                this.analysisStatus = 'idle';
                this.analysisError = responseError?.message ?? null;
                this.resetAnalysisState();
                return null;
            }

            this.analysis = mergeAnalysisResponse(emptyAnalysis(), responsePayload);
            this.analysisStatus = 'ready';
            this.dataSource = responsePayload.analysisRunId
                ? (responsePayload.source === 'mixed' ? 'mixed' : 'live')
                : 'empty';
            this.analysisError = null;
            if (responsePayload.analysisRunId) {
                await this.fetchDashboardData();
            }

            return this.analysis;
        },
    },
});
