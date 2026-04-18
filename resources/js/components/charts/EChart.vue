<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

import * as echarts from 'echarts/core';
import {
    DatasetComponent,
    GridComponent,
    LegendComponent,
    RadarComponent,
    TooltipComponent,
} from 'echarts/components';
import { BarChart, LineChart, PieChart, RadarChart } from 'echarts/charts';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
    DatasetComponent,
    GridComponent,
    LegendComponent,
    RadarComponent,
    TooltipComponent,
    BarChart,
    LineChart,
    PieChart,
    RadarChart,
    CanvasRenderer,
]);

const props = defineProps({
    option: {
        type: Object,
        required: true,
    },
    height: {
        type: String,
        default: '320px',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const root = ref(null);
let chart;
let resizeObserver;

const renderChart = () => {
    if (!chart) {
        return;
    }

    chart.clear();
    chart.setOption(props.option, {
        notMerge: true,
        lazyUpdate: true,
    });
};

const handleResize = () => {
    chart?.resize();
};

onMounted(() => {
    chart = echarts.init(root.value, undefined, {
        renderer: 'canvas',
        useDirtyRect: true,
    });

    if (typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(handleResize);
        resizeObserver.observe(root.value);
    }

    renderChart();
});

watch(
    () => props.option,
    () => {
        renderChart();
    },
    {
        deep: true,
    },
);

watch(
    () => props.loading,
    (loading) => {
        if (!chart) {
            return;
        }

        if (loading) {
            chart.showLoading('default', {
                text: 'Loading chart',
            });
            return;
        }

        chart.hideLoading();
    },
    {
        immediate: true,
    },
);

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    chart?.dispose();
    chart = undefined;
});
</script>

<template>
    <div class="relative" :style="{ height }">
        <div ref="root" class="h-full w-full"></div>

        <div
            v-if="loading"
            class="absolute inset-0 rounded-[24px] bg-white/60"
        >
            <el-skeleton animated :rows="4" class="p-4" />
        </div>
    </div>
</template>
