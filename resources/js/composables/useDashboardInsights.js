import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';

import { dashboardIcons } from '@/icons';
import { useDashboardStore } from '@/stores/dashboard';

const resolveIcon = (iconName) => dashboardIcons[iconName] ?? dashboardIcons.Operation;

const chartColors = ['#2563eb', '#0f766e', '#f59e0b', '#ef4444'];

const buildTrendOption = (timeline) => ({
    color: ['#2563eb', '#0f766e'],
    grid: {
        left: 12,
        right: 12,
        top: 28,
        bottom: 12,
        containLabel: true,
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

const buildSourceOption = (acquisitionMix) => ({
    color: ['#2563eb'],
    grid: {
        left: 84,
        right: 16,
        top: 12,
        bottom: 12,
        containLabel: true,
    },
    tooltip: {
        trigger: 'axis',
        axisPointer: {
            type: 'shadow',
        },
    },
    xAxis: {
        type: 'value',
        splitLine: {
            lineStyle: {
                color: '#e2e8f0',
            },
        },
    },
    yAxis: {
        type: 'category',
        data: acquisitionMix.categories,
        axisLine: {
            lineStyle: {
                color: '#cbd5e1',
            },
        },
        axisTick: {
            show: false,
        },
    },
    series: [
        {
            type: 'bar',
            barWidth: 16,
            borderRadius: [0, 8, 8, 0],
            data: acquisitionMix.values,
        },
    ],
});

const buildSimulatorOption = (simulator) => ({
    color: ['#2563eb'],
    grid: {
        left: 12,
        right: 12,
        top: 24,
        bottom: 12,
        containLabel: true,
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
    color: chartColors,
    tooltip: {
        trigger: 'item',
    },
    legend: {
        bottom: 0,
    },
    series: [
        {
            name: 'Signal balance',
            type: 'pie',
            radius: ['52%', '76%'],
            avoidLabelOverlap: false,
            itemStyle: {
                borderRadius: 10,
                borderColor: '#fff',
                borderWidth: 2,
            },
            label: {
                show: false,
            },
            data: [
                { value: strengths.length || 1, name: 'Strengths' },
                { value: weaknesses.length || 1, name: 'Weaknesses' },
            ],
        },
    ],
});

export function useDashboardInsights() {
    const route = useRoute();
    const store = useDashboardStore();

    const mode = computed(() => route.meta.mode ?? 'simple');
    const isAdvanced = computed(() => mode.value === 'advanced');

    const summaryCards = computed(() =>
        store.summary.map((item) => ({
            ...item,
            icon: resolveIcon(item.icon),
        })),
    );

    const liveBadge = computed(() => {
        if (store.hasLiveData) {
            return 'Live API ready';
        }

        return store.lastSyncedAt ? 'Seed fallback' : 'Loading';
    });

    const trendOption = computed(() => buildTrendOption(store.timeline));
    const sourceOption = computed(() => buildSourceOption(store.acquisitionMix));
    const segmentOption = computed(() => buildSegmentOption(store.strengths, store.weaknesses));
    const simulatorOption = computed(() => buildSimulatorOption(store.simulator));

    const refresh = async () => {
        await store.fetchDashboardData({
            mode: mode.value,
        });
    };

    watch(
        mode,
        async () => {
            await refresh();
        },
        { immediate: true },
    );

    const filters = store.filters;

    return {
        store,
        filters,
        isAdvanced,
        liveBadge,
        summaryCards,
        trendOption,
        sourceOption,
        segmentOption,
        simulatorOption,
        strengths: computed(() => store.strengths),
        weaknesses: computed(() => store.weaknesses),
        recommendations: computed(() => store.recommendations),
        simulator: computed(() => store.simulator),
        refresh,
        isLoading: computed(() => store.isLoading),
        isRefreshing: computed(() => store.isRefreshing),
        syncLabel: computed(() => store.syncLabel),
        error: computed(() => store.error),
    };
}
