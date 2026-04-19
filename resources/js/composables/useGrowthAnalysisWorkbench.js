import { computed, ref } from 'vue';

import { useDashboardStore } from '@/stores/dashboard';

const buildSkillRadarOption = (analysis) => {
    const values = analysis.skillDistribution?.values ?? [];
    const benchmark = analysis.scoreBreakdown?.benchmark ?? [];
    const indicatorValues = values.length ? values : [0];
    const maxValue = Math.max(120, ...indicatorValues, ...(benchmark.length ? benchmark : [0]));

    return {
        color: ['#2563eb', '#0f766e'],
        tooltip: {
            trigger: 'item',
        },
        legend: {
            bottom: 0,
            textStyle: {
                color: '#475569',
            },
        },
        radar: {
            radius: '72%',
            indicator: (analysis.skillDistribution?.categories ?? ['Analysis']).map((name, index) => ({
                name,
                max: Math.max(24, Math.ceil((indicatorValues[index] ?? 0) * 1.2), Math.ceil(maxValue)),
            })),
            splitNumber: 5,
            axisName: {
                color: '#334155',
                fontSize: 12,
                fontWeight: 500,
            },
            axisLine: {
                lineStyle: {
                    color: 'rgba(148, 163, 184, 0.35)',
                    width: 1,
                },
            },
            splitLine: {
                lineStyle: {
                    color: 'rgba(203, 213, 225, 0.7)',
                    width: 1,
                },
            },
            splitArea: {
                areaStyle: {
                    color: ['rgba(248,250,252,0.96)', 'rgba(241,245,249,0.88)'],
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
                        symbolSize: 6,
                        lineStyle: {
                            color: '#2563eb',
                            width: 2,
                        },
                        areaStyle: {
                            color: 'rgba(37, 99, 235, 0.16)',
                        },
                    },
                    {
                        value: benchmark.length ? benchmark : values,
                        name: 'Benchmark',
                        symbolSize: 4,
                        lineStyle: {
                            color: 'rgba(15, 118, 110, 0.6)',
                            width: 1.5,
                            type: 'dashed',
                        },
                        areaStyle: {
                            color: 'rgba(15, 118, 110, 0.05)',
                        },
                    },
                ],
            },
        ],
    };
};

export function useGrowthAnalysisWorkbench() {
    const store = useDashboardStore();
    const analyzeActionLoading = ref(false);

    const analysis = computed(() => store.analysis);
    const analysisProfile = computed(() => store.analysis.profile);
    const analysisConnection = computed(() => store.analysis.connection);
    const analysisSummary = computed(() => store.analysis.summary);
    const analysisNotice = computed(() =>
        analysisConnection.value.connected
            ? (
                analysis.value.analysisRunId
                    ? 'This is the latest report for the GitHub account you connected.'
                    : 'GitHub is connected. Run the analysis to see your profile.'
            )
            : 'Connect GitHub to see a report based on your own activity.',
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
    const publicLoading = computed(() => analyzeActionLoading.value);
    const backgroundLoading = computed(() => store.analysisStatus === 'loading');
    const publicError = computed(() => store.analysisError);
    const skillRadarOption = computed(() => buildSkillRadarOption(store.analysis));

    const runCurrentAnalysis = async () => {
        analyzeActionLoading.value = true;

        try {
            await store.syncCurrentAnalysis();
        } finally {
            analyzeActionLoading.value = false;
        }
    };

    return {
        store,
        analysis,
        analysisProfile,
        analysisConnection,
        analysisSummary,
        analysisNotice,
        analysisUpdatedLabel,
        publicLoading,
        backgroundLoading,
        publicError,
        skillRadarOption,
        runCurrentAnalysis,
    };
}
