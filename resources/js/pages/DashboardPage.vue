<script setup>
import { defineAsyncComponent } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { useDashboardInsights } from '@/composables/useDashboardInsights';
import { dashboardModes } from '@/navigation';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import SurfaceCard from '@/components/ui/SurfaceCard.vue';

const AsyncEChart = defineAsyncComponent(() => import('@/components/charts/EChart.vue'));

const route = useRoute();
const router = useRouter();

const {
    filters,
    isAdvanced,
    liveBadge,
    summaryCards,
    trendOption,
    sourceOption,
    simulatorOption,
    strengths,
    weaknesses,
    recommendations,
    simulator,
    refresh,
    isLoading,
    isRefreshing,
    syncLabel,
    error,
    store,
} = useDashboardInsights();

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
                        Route-aware dashboard with live data adapters.
                    </h3>
                    <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                        Simple mode keeps the high-signal overview visible. Advanced mode layers on more context,
                        with the shell already wired to consume live API payloads for analysis summaries, timelines,
                        strengths, weaknesses, recommendations, and simulator output.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <el-tag effect="light" :type="store.hasLiveData ? 'success' : 'warning'">
                        {{ liveBadge }}
                    </el-tag>
                    <el-tag effect="light" type="info">
                        {{ syncLabel }}
                    </el-tag>

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

            <el-alert
                v-if="error"
                class="mt-5"
                :title="error"
                type="warning"
                :closable="false"
                show-icon
            />

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    v-for="card in summaryCards"
                    :key="card.key"
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
                    <el-tag effect="light" type="primary">
                        {{ isLoading ? 'Loading' : store.timeline.updatedAt ? `Updated ${store.timeline.updatedAt}` : 'Seed data' }}
                    </el-tag>
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
                    <template #actions>
                        <el-button
                            size="small"
                            :loading="isRefreshing"
                            @click="refresh"
                        >
                            Refresh data
                        </el-button>
                    </template>

                    <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
                        <el-select
                            v-model="filters.range"
                            size="large"
                            @change="refresh"
                        >
                            <el-option label="7 days" value="7d" />
                            <el-option label="14 days" value="14d" />
                            <el-option label="30 days" value="30d" />
                        </el-select>
                        <el-select
                            v-model="filters.segment"
                            size="large"
                            @change="refresh"
                        >
                            <el-option label="All segments" value="all" />
                            <el-option label="SMB" value="smb" />
                            <el-option label="Mid-market" value="mid" />
                            <el-option label="Enterprise" value="enterprise" />
                        </el-select>
                        <el-input
                            v-model="filters.query"
                            size="large"
                            placeholder="Search campaigns"
                            @keyup.enter="refresh"
                        />
                    </div>
                </SurfaceCard>
            </div>
        </section>

        <section
            v-if="isAdvanced"
            class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(360px,0.75fr)]"
        >
            <SurfaceCard
                title="Signal balance"
                description="Strengths, weaknesses, and recommendations sourced from the analysis API."
            >
                <div class="mb-6 rounded-[24px] border border-slate-200 bg-slate-50 p-3">
                    <AsyncEChart :option="segmentOption" height="220px" />
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <p class="dashboard-chip">Strengths</p>
                        <div class="mt-4 space-y-3">
                            <article
                                v-for="item in strengths"
                                :key="item.id"
                                class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-emerald-950">{{ item.title }}</h4>
                                    <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-700">
                                        {{ item.impact }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-emerald-900/80">{{ item.detail }}</p>
                            </article>
                        </div>
                    </div>

                    <div>
                        <p class="dashboard-chip">Weaknesses</p>
                        <div class="mt-4 space-y-3">
                            <article
                                v-for="item in weaknesses"
                                :key="item.id"
                                class="rounded-2xl border border-rose-100 bg-rose-50/70 p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-rose-950">{{ item.title }}</h4>
                                    <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-rose-700">
                                        {{ item.impact }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-rose-900/80">{{ item.detail }}</p>
                            </article>
                        </div>
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-200/80 pt-5">
                    <p class="dashboard-chip">Recommendations</p>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="item in recommendations"
                            :key="item.id"
                            class="rounded-2xl border border-slate-200 bg-white p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-semibold text-slate-950">{{ item.title }}</h4>
                                <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-brand-700">
                                    {{ item.impact }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ item.detail }}</p>
                        </article>
                    </div>
                </div>
            </SurfaceCard>

            <SurfaceCard
                title="Simulator output"
                description="Projection curve backed by the simulator endpoint."
            >
                <template #actions>
                    <el-tag effect="light" type="primary">{{ simulator.confidence }}</el-tag>
                </template>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Current</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ simulator.current }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Projected</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ simulator.projected }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2 xl:col-span-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Uplift</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-emerald-700">{{ simulator.uplift }}</p>
                    </div>
                </div>

                <div class="mt-5">
                    <AsyncEChart :option="simulatorOption" height="260px" />
                </div>

                <ul class="mt-5 space-y-3">
                    <li
                        v-for="note in simulator.notes"
                        :key="note"
                        class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600"
                    >
                        <span class="mt-1 size-2 rounded-full bg-brand-500"></span>
                        <span class="min-w-0">{{ note }}</span>
                    </li>
                </ul>
            </SurfaceCard>
        </section>
    </div>
</template>
