import { defineStore } from 'pinia';

import { dashboardFallback } from '@/data/dashboardFallback';
import { dashboardApi } from '@/services/dashboardApi';

const clone = (value) => JSON.parse(JSON.stringify(value));

const fallbackAnalysis = () => clone(dashboardFallback.analysis);

const normalizeUsername = (value) => value.trim().replace(/^@+/, '');

const maskToken = (value) => {
    if (!value) {
        return 'Not connected';
    }

    if (value.length <= 8) {
        return 'Connected';
    }

    return `${value.slice(0, 4)}…${value.slice(-4)}`;
};

const humanizeUsername = (value) =>
    value
        .split(/[._-]+/g)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');

const mergeAnalysisResponse = (baseAnalysis, payload, username) => {
    const analysis = clone(baseAnalysis);

    analysis.username = username;
    analysis.profile.username = username;
    analysis.profile.displayName = payload?.profile?.displayName
        ?? payload?.profile?.name
        ?? humanizeUsername(username);
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
    analysis.connection = payload?.connection
        ? {
            ...analysis.connection,
            ...payload.connection,
            tokenPreview: payload.connection.tokenPreview ?? analysis.connection.tokenPreview,
        }
        : analysis.connection;
    analysis.scoreBreakdown = payload?.scoreBreakdown ?? analysis.scoreBreakdown;
    analysis.skillDistribution = payload?.skillDistribution ?? analysis.skillDistribution;
    analysis.strengths = Array.isArray(payload?.strengths) && payload.strengths.length
        ? payload.strengths
        : analysis.strengths;
    analysis.weaknesses = Array.isArray(payload?.weaknesses) && payload.weaknesses.length
        ? payload.weaknesses
        : analysis.weaknesses;
    analysis.recommendations = Array.isArray(payload?.recommendations) && payload.recommendations.length
        ? payload.recommendations
        : analysis.recommendations;
    analysis.source = payload?.source ?? analysis.source;
    analysis.status = 'ready';
    analysis.privateStatus = payload?.privateStatus ?? analysis.privateStatus;
    analysis.lastAnalyzedAt = new Date().toISOString();
    analysis.error = null;

    return analysis;
};

const defaultFilters = () => ({
    range: '14d',
    segment: 'all',
    query: '',
    mode: 'simple',
});

const defaultSliceState = (value) => clone(value);

const sliceDefinitions = [
    {
        key: 'summary',
        fetch: dashboardApi.fetchSummary,
        fallback: dashboardFallback.summary,
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
        fallback: dashboardFallback.timeline,
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
            acquisitionMix: dashboardFallback.acquisitionMix,
            strengths: dashboardFallback.strengths,
            weaknesses: dashboardFallback.weaknesses,
            recommendations: dashboardFallback.recommendations,
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
        fallback: dashboardFallback.simulator,
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
        dataSource: 'seed',
        error: null,
        lastSyncedAt: null,
        analysis: fallbackAnalysis(),
        analysisStatus: 'idle',
        analysisConnectionStatus: 'idle',
        analysisError: null,
        analysisConnectionError: null,
        summary: defaultSliceState(dashboardFallback.summary),
        timeline: defaultSliceState(dashboardFallback.timeline),
        acquisitionMix: defaultSliceState(dashboardFallback.acquisitionMix),
        strengths: defaultSliceState(dashboardFallback.strengths),
        weaknesses: defaultSliceState(dashboardFallback.weaknesses),
        recommendations: defaultSliceState(dashboardFallback.recommendations),
        simulator: defaultSliceState(dashboardFallback.simulator),
    }),
    getters: {
        isLoading: (state) => state.status === 'loading',
        isRefreshing: (state) => state.status === 'refreshing',
        hasLiveData: (state) => state.dataSource === 'live' || state.dataSource === 'mixed',
        syncLabel: (state) => {
            if (!state.lastSyncedAt) {
                return 'Seed data';
            }

            return new Intl.DateTimeFormat(undefined, {
                hour: 'numeric',
                minute: '2-digit',
            }).format(new Date(state.lastSyncedAt));
        },
    },
    actions: {
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

            const query = {
                range: nextFilters.range,
                segment: nextFilters.segment,
                query: nextFilters.query,
                mode: nextFilters.mode,
                github_username: this.analysis?.username || undefined,
            };

            const settled = await Promise.allSettled(
                sliceDefinitions.map(async (slice) => {
                    const response = await slice.fetch(query);
                    const normalized = slice.normalize(response);

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
                    : 'seed';

            this.status = 'ready';
            this.lastSyncedAt = new Date().toISOString();
            this.error = liveSlices === 0
                ? 'Dashboard data is using the local fallback seed until the API is ready.'
                : null;

            return this;
        },
        async refresh() {
            return this.fetchDashboardData();
        },
        async runPublicAnalysis({ username, range, segment } = {}) {
            const normalizedUsername = normalizeUsername(username || this.analysis.username || '');

            if (!normalizedUsername) {
                this.analysisStatus = 'error';
                this.analysisError = 'Enter a GitHub username to run a public analysis.';
                return null;
            }

            this.analysisStatus = 'loading';
            this.analysisError = null;

            const payload = await dashboardApi.fetchPublicAnalysis({
                username: normalizedUsername,
                range: range ?? this.filters.range,
                segment: segment ?? this.filters.segment,
            });

            const nextAnalysis = mergeAnalysisResponse(
                dashboardFallback.analysis,
                payload,
                normalizedUsername,
            );

            if (!payload) {
                nextAnalysis.source = 'seed';
                nextAnalysis.summary = dashboardFallback.analysis.summary;
                nextAnalysis.connection = {
                    ...dashboardFallback.analysis.connection,
                    note: 'Seed data is showing until the public analysis API is available.',
                };
            } else {
                nextAnalysis.source = 'live';
            }

            this.analysis = nextAnalysis;
            this.analysisStatus = 'ready';
            this.dataSource = payload ? 'live' : 'seed';
            this.analysisError = null;

            if (payload) {
                await this.fetchDashboardData();
            }

            return this.analysis;
        },
        async connectPrivateWorkspace({ username, token } = {}) {
            const normalizedUsername = normalizeUsername(username || this.analysis.username || '');
            const connectionToken = token?.trim() || '';

            if (!normalizedUsername) {
                this.analysisConnectionStatus = 'error';
                this.analysisConnectionError = 'Enter a GitHub username before connecting a private workspace.';
                return null;
            }

            if (connectionToken.length < 8) {
                this.analysisConnectionStatus = 'error';
                this.analysisConnectionError = 'Token-backed private analysis needs a token of at least 8 characters.';
                return null;
            }

            this.analysisConnectionStatus = 'loading';
            this.analysisConnectionError = null;

            const payload = await dashboardApi.connectPrivateAnalysis({
                username: normalizedUsername,
                token: connectionToken,
            });

            const nextAnalysis = mergeAnalysisResponse(
                this.analysis,
                payload,
                normalizedUsername,
            );

            nextAnalysis.privateStatus = 'ready';
            nextAnalysis.connection = {
                enabled: true,
                connected: true,
                workspace: payload?.connection?.workspace
                    ?? `Private workspace connected for ${normalizedUsername}`,
                tokenPreview: maskToken(connectionToken),
                note: payload?.connection?.note
                    ?? 'Private repository context will be folded into the next analysis run.',
            };

            if (!payload) {
                nextAnalysis.source = this.analysis.source === 'live' ? 'mixed' : 'seed';
                nextAnalysis.connection.note = 'Private workspace connection is prepared locally until the API is ready.';
            } else if (this.analysis.source === 'live') {
                nextAnalysis.source = 'live';
            } else {
                nextAnalysis.source = 'mixed';
            }

            this.analysis = nextAnalysis;
            this.analysisConnectionStatus = 'ready';
            this.analysisConnectionError = null;

            if (payload) {
                await this.fetchDashboardData();
            }

            return this.analysis;
        },
    },
});
