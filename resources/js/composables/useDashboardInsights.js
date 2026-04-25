import { computed } from 'vue';

import { dashboardIcons } from '@/icons';
import { useDashboardStore } from '@/stores/dashboard';

const resolveIcon = (iconName) => dashboardIcons[iconName] ?? dashboardIcons.Operation;

const buildTrendOption = (timeline) => ({
    color: ['#2563eb', '#0f766e'],
    grid: {
        left: 12,
        right: 12,
        top: 28,
        bottom: 12,
    },
    tooltip: {
        trigger: 'axis',
    },
    legend: {
        top: 0,
        icon: 'roundRect',
    },
    xAxis: {
        type: 'category',
        boundaryGap: false,
        data: timeline.categories,
        axisLine: {
            lineStyle: {
                color: '#cbd5e1',
            },
        },
        axisTick: {
            show: false,
        },
    },
    yAxis: {
        type: 'value',
        splitLine: {
            lineStyle: {
                color: '#e2e8f0',
            },
        },
    },
    series: timeline.series.map((series, index) => ({
        name: series.name,
        type: 'line',
        smooth: true,
        symbol: 'circle',
        symbolSize: 8,
        lineStyle: {
            width: 3,
        },
        areaStyle: {
            opacity: index === 0 ? 0.12 : 0.08,
        },
        data: series.data,
    })),
});

const buildSimulatorOption = (simulator) => ({
    color: ['#2563eb'],
    grid: {
        left: 12,
        right: 12,
        top: 24,
        bottom: 12,
    },
    tooltip: {
        trigger: 'axis',
    },
    xAxis: {
        type: 'category',
        data: simulator.series.map((point) => point.label),
        axisLine: {
            lineStyle: {
                color: '#cbd5e1',
            },
        },
        axisTick: {
            show: false,
        },
    },
    yAxis: {
        type: 'value',
        splitLine: {
            lineStyle: {
                color: '#e2e8f0',
            },
        },
    },
    series: [
        {
            name: 'Projected value',
            type: 'line',
            smooth: true,
            symbol: 'circle',
            symbolSize: 8,
            lineStyle: {
                width: 3,
            },
            areaStyle: {
                opacity: 0.12,
            },
            data: simulator.series.map((point) => point.value),
        },
    ],
});

const buildSegmentOption = (strengths, weaknesses) => ({
    color: ['#16a34a', '#ef4444'],
    tooltip: {
        trigger: 'item',
    },
    legend: {
        bottom: 0,
        textStyle: {
            color: '#475569',
        },
    },
    series: [
        {
            name: 'Signal balance',
            type: 'pie',
            radius: ['56%', '78%'],
            avoidLabelOverlap: false,
            itemStyle: {
                borderRadius: 14,
                borderColor: '#fff',
                borderWidth: 4,
            },
            label: {
                show: true,
                formatter: '{b}\n{d}%',
                color: '#0f172a',
                fontWeight: 600,
            },
            data: [
                { value: strengths.length || 1, name: 'Strengths' },
                { value: weaknesses.length || 1, name: 'Weaknesses' },
            ],
        },
    ],
});

export function useDashboardInsights() {
    const store = useDashboardStore();

    const summaryCards = computed(() =>
        store.hasAnalysisRun
            ? store.summary.map((item) => ({
                ...item,
                icon: resolveIcon(item.icon),
            }))
            : [],
    );

    const liveBadge = computed(() => {
        if (store.hasLiveData) {
            return 'Live API ready';
        }

        return store.analysisStatus === 'loading' ? 'Analyzing' : 'Waiting for analysis';
    });

    const trendOption = computed(() => buildTrendOption(store.timeline));
    const segmentOption = computed(() => buildSegmentOption(store.analysis.strengths ?? [], store.analysis.weaknesses ?? []));
    const simulatorOption = computed(() => buildSimulatorOption(store.simulator));

    const refresh = async () => {
        if (!store.hasAnalysisRun) {
            return;
        }

        await store.fetchDashboardData();
    };

    const filters = store.filters;

    return {
        store,
        filters,
        liveBadge,
        summaryCards,
        trendOption,
        segmentOption,
        simulatorOption,
        strengths: computed(() => store.analysis.strengths ?? []),
        weaknesses: computed(() => store.analysis.weaknesses ?? []),
        recommendations: computed(() => store.analysis.recommendations ?? []),
        simulator: computed(() => store.simulator),
        refresh,
        isLoading: computed(() => store.isLoading),
        isRefreshing: computed(() => store.isRefreshing),
        syncLabel: computed(() => store.syncLabel),
        error: computed(() => store.error),
    };
}
