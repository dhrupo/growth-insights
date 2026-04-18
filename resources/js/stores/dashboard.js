import { defineStore } from 'pinia';

import { dashboardFallback } from '@/data/dashboardFallback';
import { dashboardApi } from '@/services/dashboardApi';

const clone = (value) => JSON.parse(JSON.stringify(value));

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
    },
});
