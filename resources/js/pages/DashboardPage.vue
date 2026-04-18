<script setup>
import { computed, defineAsyncComponent, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
    Collection,
    Coin,
    Operation,
    TrendCharts,
} from '@element-plus/icons-vue';

import MetricCard from '@/components/dashboard/MetricCard.vue';
import SurfaceCard from '@/components/ui/SurfaceCard.vue';
import { dashboardModes } from '@/navigation';

const AsyncEChart = defineAsyncComponent(() => import('@/components/charts/EChart.vue'));

const route = useRoute();
const router = useRouter();

const mode = computed(() => route.meta.mode ?? 'simple');
const isAdvanced = computed(() => mode.value === 'advanced');

const controls = reactive({
    range: '14d',
    segment: 'all',
    query: '',
});

const summaryCards = [
    {
        title: 'Revenue',
        value: '$128.4k',
        delta: '+12.4%',
        tone: 'emerald',
        description: 'Rolling 30-day revenue with clean trend context.',
        icon: Coin,
    },
    {
        title: 'Conversion rate',
        value: '4.8%',
        delta: '+0.6%',
        tone: 'blue',
        description: 'Primary conversion across the selected cohort.',
        icon: TrendCharts,
    },
    {
        title: 'Active segments',
        value: '18',
        delta: '+3',
        tone: 'amber',
        description: 'Segments tracked across the current workspace.',
        icon: Collection,
    },
    {
        title: 'Exceptions',
        value: '7',
        delta: '-2',
        tone: 'rose',
        description: 'Anomaly queue and operational follow-up items.',
        icon: Operation,
    },
];

const trendOption = computed(() => ({
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
        data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
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
            name: 'Sessions',
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
            data: [320, 412, 350, 494, 520, 608, 690],
        },
        {
            name: 'Conversions',
            type: 'line',
            smooth: true,
            symbol: 'circle',
            symbolSize: 8,
            lineStyle: {
                width: 3,
            },
            areaStyle: {
                opacity: 0.08,
            },
            data: [90, 110, 98, 128, 132, 155, 176],
        },
    ],
}));

const sourceOption = computed(() => ({
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
        data: ['Organic', 'Paid', 'Direct', 'Referral'],
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
            data: [52, 34, 26, 18],
        },
    ],
}));

const segmentOption = computed(() => ({
    color: ['#2563eb', '#14b8a6', '#f59e0b', '#ef4444'],
    tooltip: {
        trigger: 'item',
    },
    legend: {
        bottom: 0,
    },
    series: [
        {
            name: 'Segments',
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
                { value: 38, name: 'SMB' },
                { value: 29, name: 'Mid-market' },
                { value: 21, name: 'Enterprise' },
                { value: 12, name: 'Other' },
            ],
        },
    ],
}));

const activityRows = [
    {
        name: 'Q2 paid campaign',
        status: 'Healthy',
        value: '$21.8k',
        trend: '+8.2%',
    },
    {
        name: 'Lifecycle email',
        status: 'Watch',
        value: '$9.4k',
        trend: '+1.3%',
    },
    {
        name: 'Inbound demo flow',
        status: 'Healthy',
        value: '$14.1k',
        trend: '+4.9%',
    },
    {
        name: 'Paid search',
        status: 'Attention',
        value: '$6.3k',
        trend: '-2.1%',
    },
];

const switchMode = async (targetName) => {
    if (targetName === route.name) {
        return;
    }

    await router.push({ name: targetName });
};
</script>

<template>
    <div class="space-y-6">
        <section class="dashboard-surface p-6 sm:p-7">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="dashboard-chip">Dashboard shell</p>
                    <h3 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                        Route-aware dashboard with a clean simple and advanced split.
                    </h3>
                    <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                        Simple mode keeps the high-signal overview visible. Advanced mode layers on more controls,
                        a segment chart, and a table for deeper analysis without changing the route structure.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <el-radio-group :model-value="route.name" size="large" @change="switchMode">
                        <el-radio-button
                            v-for="item in dashboardModes"
                            :key="item.name"
                            :label="item.name"
                        >
                            {{ item.label }}
                        </el-radio-button>
                    </el-radio-group>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    v-for="card in summaryCards"
                    :key="card.title"
                    v-bind="card"
                />
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(320px,1fr)]">
            <SurfaceCard
                title="Traffic trend"
                description="Line chart for week-over-week pacing and conversion movement."
            >
                <template #actions>
                    <el-tag effect="light" type="primary">Updated 2m ago</el-tag>
                </template>

                <AsyncEChart :option="trendOption" height="340px" />
            </SurfaceCard>

            <div class="space-y-6">
                <SurfaceCard
                    title="Source mix"
                    description="Simple breakdown of the current acquisition mix."
                >
                    <AsyncEChart :option="sourceOption" height="260px" />
                </SurfaceCard>

                <SurfaceCard
                    title="Controls"
                    description="Shared filters stay compact in simple mode and expand in advanced mode."
                >
                    <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
                        <el-select v-model="controls.range" size="large">
                            <el-option label="7 days" value="7d" />
                            <el-option label="14 days" value="14d" />
                            <el-option label="30 days" value="30d" />
                        </el-select>
                        <el-select v-model="controls.segment" size="large">
                            <el-option label="All segments" value="all" />
                            <el-option label="SMB" value="smb" />
                            <el-option label="Mid-market" value="mid" />
                            <el-option label="Enterprise" value="enterprise" />
                        </el-select>
                        <el-input v-model="controls.query" size="large" placeholder="Search campaigns" />
                    </div>
                </SurfaceCard>
            </div>
        </section>

        <section
            v-if="isAdvanced"
            class="grid gap-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(360px,0.95fr)]"
        >
            <SurfaceCard
                title="Segment breakdown"
                description="Advanced mode adds a second analytical surface without changing the route."
            >
                <AsyncEChart :option="segmentOption" height="320px" />
            </SurfaceCard>

            <SurfaceCard
                title="Recent activity"
                description="A compact Element Plus table for diagnostics and review."
            >
                <el-table :data="activityRows" size="small" stripe>
                    <el-table-column prop="name" label="Campaign" min-width="160" />
                    <el-table-column prop="status" label="Status" width="110">
                        <template #default="{ row }">
                            <el-tag
                                :type="row.status === 'Healthy' ? 'success' : row.status === 'Watch' ? 'warning' : 'danger'"
                                effect="light"
                            >
                                {{ row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="value" label="Value" width="100" />
                    <el-table-column prop="trend" label="Trend" width="90" />
                </el-table>
            </SurfaceCard>
        </section>
    </div>
</template>
