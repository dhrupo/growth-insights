import { computed, reactive, watch } from 'vue';

import { useDashboardStore } from '@/stores/dashboard';

const buildSkillRadarOption = (analysis) => {
    const values = analysis.skillDistribution?.values ?? [];
    const benchmark = analysis.scoreBreakdown?.benchmark ?? [];
    const indicatorValues = values.length ? values : [0];
    const maxValue = Math.max(100, ...indicatorValues, ...(benchmark.length ? benchmark : [0]));

    return {
        color: ['#2563eb', '#0f766e'],
        tooltip: {
            trigger: 'item',
        },
        legend: {
            bottom: 0,
        },
        radar: {
            indicator: (analysis.skillDistribution?.categories ?? ['Analysis']).map((name, index) => ({
                name,
                max: Math.max(20, Math.ceil((indicatorValues[index] ?? 0) * 1.2), Math.ceil(maxValue)),
            })),
            splitNumber: 4,
            axisName: {
                color: '#334155',
            },
            splitLine: {
                lineStyle: {
                    color: '#e2e8f0',
                },
            },
            splitArea: {
                areaStyle: {
                    color: ['rgba(248,250,252,0.9)', 'rgba(241,245,249,0.9)'],
                },
            },
        },
        series: [
            {
                name: 'Skill distribution',
                type: 'radar',
                symbol: 'circle',
                data: [
                    {
                        value: values,
                        name: 'Current profile',
                        areaStyle: {
                            opacity: 0.18,
                        },
                    },
                    {
                        value: benchmark.length ? benchmark : values,
                        name: 'Benchmark',
                        areaStyle: {
                            opacity: 0.08,
                        },
                    },
                ],
            },
        ],
    };
};

export function useGrowthAnalysisWorkbench() {
    const store = useDashboardStore();

    const publicForm = reactive({
        username: store.analysis.username,
    });

    const privateForm = reactive({
        username: store.analysis.username,
        token: '',
        enabled: false,
    });

    watch(
        () => store.analysis.username,
        (username) => {
            publicForm.username = username;
            privateForm.username = username;
        },
        { immediate: true },
    );

    const analysis = computed(() => store.analysis);
    const analysisProfile = computed(() => store.analysis.profile);
    const analysisConnection = computed(() => store.analysis.connection);
    const analysisSummary = computed(() => store.analysis.summary);
    const analysisSourceLabel = computed(() => {
        if (analysis.value.source === 'live') {
            return 'Live API';
        }

        if (analysis.value.source === 'mixed') {
            return 'Mixed source';
        }

        return 'Seed preview';
    });
    const analysisNotice = computed(() =>
        analysis.value.source === 'live'
            ? 'Connected to live API payloads.'
            : 'Showing a local preview until the backend analysis endpoints are ready.',
    );
    const analysisUpdatedLabel = computed(() => {
        const value = analysis.value.lastAnalyzedAt;

        if (!value) {
            return 'Not run yet';
        }

        const parsed = Date.parse(value);

        if (Number.isNaN(parsed)) {
            return value;
        }

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(parsed));
    });
    const publicLoading = computed(() => store.analysisStatus === 'loading');
    const privateLoading = computed(() => store.analysisConnectionStatus === 'loading');
    const publicError = computed(() => store.analysisError);
    const privateError = computed(() => store.analysisConnectionError);
    const skillRadarOption = computed(() => buildSkillRadarOption(store.analysis));

    const runPublicAnalysis = async () => {
        await store.runPublicAnalysis({
            username: publicForm.username,
            range: store.filters.range,
            segment: store.filters.segment,
        });
    };

    const connectPrivateWorkspace = async () => {
        await store.connectPrivateWorkspace({
            username: privateForm.username || publicForm.username,
            token: privateForm.token,
        });
    };

    return {
        store,
        analysis,
        analysisProfile,
        analysisConnection,
        analysisSummary,
        analysisSourceLabel,
        analysisNotice,
        analysisUpdatedLabel,
        publicForm,
        privateForm,
        publicLoading,
        privateLoading,
        publicError,
        privateError,
        skillRadarOption,
        runPublicAnalysis,
        connectPrivateWorkspace,
    };
}
