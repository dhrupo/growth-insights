<script setup>
import SurfaceCard from '@/components/ui/SurfaceCard.vue';

defineProps({
    eyebrow: {
        type: String,
        default: 'Section',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    highlights: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(340px,0.9fr)]">
        <SurfaceCard :title="title" :description="description">
            <template #actions>
                <el-tag effect="light" type="info">{{ eyebrow }}</el-tag>
            </template>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Route</p>
                    <p class="mt-2 text-sm font-medium text-slate-900">Single route, multiple surfaces</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Layout</p>
                    <p class="mt-2 text-sm font-medium text-slate-900">Element Plus controls with Tailwind shell</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Charts</p>
                    <p class="mt-2 text-sm font-medium text-slate-900">Ready for modular ECharts composition</p>
                </div>
            </div>
        </SurfaceCard>

        <SurfaceCard title="Highlights" description="Foundation notes to keep the section architecture consistent.">
            <ul class="space-y-3">
                <li
                    v-for="item in highlights"
                    :key="item"
                    class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600"
                >
                    <span class="mt-1 size-2 rounded-full bg-brand-500"></span>
                    <span class="min-w-0">{{ item }}</span>
                </li>
            </ul>
        </SurfaceCard>
    </div>
</template>
