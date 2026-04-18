<script setup>
import { computed, defineAsyncComponent, ref } from 'vue';

import SurfaceCard from '@/components/ui/SurfaceCard.vue';
import { useDashboardStore } from '@/stores/dashboard';

const AsyncEChart = defineAsyncComponent(() => import('@/components/charts/EChart.vue'));
const store = useDashboardStore();

const username = ref(store.analysis.username);
const accessToken = ref('');

const analysis = computed(() => store.analysis);
const summaryList = computed(() => analysis.value.summary ?? []);
const scoreBreakdownOption = computed(() => ({
    color: ['#2563eb', '#93c5fd'],
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: { bottom: 0 },
    grid: { left: 16, right: 16, top: 12, bottom: 36, containLabel: true },
    xAxis: {
        type: 'value',
        splitLine: { lineStyle: { color: '#e2e8f0' } },
    },
    yAxis: {
        type: 'category',
        data: analysis.value.scoreBreakdown.categories,
        axisTick: { show: false },
    },
    series: [
        {
            name: 'Current',
            type: 'bar',
            data: analysis.value.scoreBreakdown.values,
            barWidth: 14,
            itemStyle: { borderRadius: [0, 8, 8, 0] },
        },
        {
            name: 'Benchmark',
            type: 'bar',
            data: analysis.value.scoreBreakdown.benchmark,
            barWidth: 14,
            itemStyle: { borderRadius: [0, 8, 8, 0], color: '#bfdbfe' },
        },
    ],
}));

const skillRadarOption = computed(() => ({
    tooltip: {},
    radar: {
        radius: '62%',
        indicator: analysis.value.skillDistribution.categories.map((label) => ({ name: label, max: 100 })),
        splitLine: { lineStyle: { color: '#dbeafe' } },
        splitArea: { areaStyle: { color: ['rgba(219,234,254,0.12)', 'rgba(239,246,255,0.25)'] } },
    },
    series: [
        {
            type: 'radar',
            data: [
                {
                    value: analysis.value.skillDistribution.values,
                    areaStyle: { color: 'rgba(37, 99, 235, 0.18)' },
                    lineStyle: { color: '#2563eb', width: 2 },
                    itemStyle: { color: '#2563eb' },
                },
            ],
        },
    ],
}));

const runPublicAnalysis = async () => {
    await store.runPublicAnalysis({ username: username.value });
};

const connectPrivateWorkspace = async () => {
    await store.connectPrivateWorkspace({
        username: username.value,
        token: accessToken.value,
    });
    accessToken.value = '';
};
</script>

<template>
    <div class="space-y-6">
        <section class="grid gap-6 xl:grid-cols-[minmax(360px,0.95fr)_minmax(0,1.05fr)]">
            <SurfaceCard
                title="Analyze a GitHub profile"
                description="Run public analysis immediately, then optionally layer in private repositories with a token-backed connection."
            >
                <div class="space-y-5">
                    <el-input
                        v-model="username"
                        size="large"
                        placeholder="GitHub username"
                    >
                        <template #prepend>@</template>
                    </el-input>

                    <div class="flex flex-wrap gap-3">
                        <el-button
                            type="primary"
                            size="large"
                            :loading="store.analysisStatus === 'loading'"
                            @click="runPublicAnalysis"
                        >
                            Run public analysis
                        </el-button>
                        <el-tag effect="light" type="info">
                            {{ analysis.source === 'live' ? 'Live analysis' : 'Seed fallback' }}
                        </el-tag>
                    </div>

                    <el-alert
                        v-if="store.analysisError"
                        :title="store.analysisError"
                        type="warning"
                        :closable="false"
                        show-icon
                    />

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Private workspace</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Token-backed sync is opt-in and only includes repositories the token can access.
                        </p>
                        <el-input
                            v-model="accessToken"
                            class="mt-4"
                            size="large"
                            type="password"
                            show-password
                            placeholder="GitHub token for private repo analysis"
                        />
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <el-button
                                size="large"
                                :loading="store.analysisConnectionStatus === 'loading'"
                                @click="connectPrivateWorkspace"
                            >
                                Connect private workspace
                            </el-button>
                            <span class="text-sm text-slate-500">{{ analysis.connection.tokenPreview }}</span>
                        </div>

                        <el-alert
                            v-if="store.analysisConnectionError"
                            class="mt-4"
                            :title="store.analysisConnectionError"
                            type="warning"
                            :closable="false"
                            show-icon
                        />
                    </div>
                </div>
            </SurfaceCard>

            <SurfaceCard
                :title="analysis.profile.displayName"
                :description="analysis.profile.bio"
            >
                <template #actions>
                    <el-tag effect="light" :type="analysis.privateStatus === 'ready' ? 'success' : 'info'">
                        {{ analysis.connection.workspace }}
                    </el-tag>
                </template>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Followers</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ analysis.profile.followers }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Public repos</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ analysis.profile.publicRepos }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Active weeks</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ analysis.profile.contributionStreak }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Visible PRs</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ analysis.profile.publicPullRequests }}</p>
                    </div>
                </div>

                <ul class="mt-5 space-y-3">
                    <li
                        v-for="line in summaryList"
                        :key="line"
                        class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600"
                    >
                        <span class="mt-1 size-2 rounded-full bg-brand-500"></span>
                        <span class="min-w-0">{{ line }}</span>
                    </li>
                </ul>
            </SurfaceCard>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <SurfaceCard
                title="Score breakdown"
                description="Compare the current score components with a baseline reference."
            >
                <AsyncEChart :option="scoreBreakdownOption" height="320px" />
            </SurfaceCard>

            <SurfaceCard
                title="Skill distribution"
                description="Shows where the strongest technical signals are currently concentrated."
            >
                <AsyncEChart :option="skillRadarOption" height="320px" />
            </SurfaceCard>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
            <SurfaceCard title="Strengths and weaknesses" description="Evidence-backed signals from the latest analysis run.">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="dashboard-chip">Strengths</p>
                        <div class="mt-4 space-y-3">
                            <article
                                v-for="item in analysis.strengths"
                                :key="item.id"
                                class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-emerald-950">{{ item.title }}</h4>
                                    <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-700">{{ item.impact }}</span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-emerald-900/80">{{ item.detail }}</p>
                            </article>
                        </div>
                    </div>

                    <div>
                        <p class="dashboard-chip">Weaknesses</p>
                        <div class="mt-4 space-y-3">
                            <article
                                v-for="item in analysis.weaknesses"
                                :key="item.id"
                                class="rounded-2xl border border-rose-100 bg-rose-50/70 p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-rose-950">{{ item.title }}</h4>
                                    <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-rose-700">{{ item.impact }}</span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-rose-900/80">{{ item.detail }}</p>
                            </article>
                        </div>
                    </div>
                </div>
            </SurfaceCard>

            <SurfaceCard title="Recommended next steps" description="Rule-based and AI-enhanced coaching from the latest run.">
                <div class="space-y-3">
                    <article
                        v-for="item in analysis.recommendations"
                        :key="item.id"
                        class="rounded-2xl border border-slate-200 bg-white p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-slate-950">{{ item.title }}</h4>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-brand-700">{{ item.impact }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ item.detail }}</p>
                    </article>
                </div>
            </SurfaceCard>
        </section>
    </div>
</template>
