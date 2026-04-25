<script setup>
import { computed, defineAsyncComponent, onMounted } from 'vue';

import { useDashboardInsights } from '@/composables/useDashboardInsights';
import { useGrowthAnalysisWorkbench } from '@/composables/useGrowthAnalysisWorkbench';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import SurfaceCard from '@/components/ui/SurfaceCard.vue';

const AsyncEChart = defineAsyncComponent(() => import('@/components/charts/EChart.vue'));

const {
    summaryCards,
    trendOption,
    segmentOption,
    simulatorOption,
    simulator,
    error,
    store,
} = useDashboardInsights();

const {
    analysis,
    analysisProfile,
    analysisConnection,
    analysisSummary,
    analysisNotice,
    publicLoading,
    backgroundLoading,
    publicError,
    skillRadarOption,
    analysisUpdatedLabel,
    runCurrentAnalysis,
} = useGrowthAnalysisWorkbench();

const githubConnectUrl = '/auth/github/redirect';
const hasConnection = computed(() => analysisConnection.value.connected);
const canAnalyze = computed(() => hasConnection.value);
const loadingInitialProfile = computed(() => hasConnection.value && (backgroundLoading.value || publicLoading.value));
const profileTitle = computed(() => {
    if (analysisProfile.value.displayName) {
        return analysisProfile.value.displayName;
    }

    if (analysisProfile.value.username) {
        return `@${analysisProfile.value.username}`;
    }

    return 'Your GitHub profile';
});

const snapshot = computed(() => analysis.value.snapshot ?? {
    score: null,
    confidence: null,
    momentum: 'Stable',
    languages: [],
    activeWeeks: 0,
    activeDays: 0,
    commits: 0,
    pullRequests: 0,
    issues: 0,
});

const snapshotStats = computed(() => {
    return [
        { key: 'weeks', label: 'Active weeks', value: String(snapshot.value.activeWeeks ?? 0) },
        { key: 'days', label: 'Active days', value: String(snapshot.value.activeDays ?? 0) },
        { key: 'commits', label: 'Commits', value: String(snapshot.value.commits ?? 0) },
        { key: 'prs', label: 'PRs', value: String(snapshot.value.pullRequests ?? 0) },
    ];
});

const snapshotNarrative = computed(() => {
    const languageText = snapshot.value.languages.length
        ? snapshot.value.languages.join(', ')
        : 'the visible repository mix';
    const momentumText = (snapshot.value.momentum ?? 'Stable').toLowerCase();

    if (analysis.value.analysisRunId) {
        return `Over the last ${snapshot.value.activeWeeks} active weeks, you showed up on ${snapshot.value.activeDays} days, made ${snapshot.value.commits} commits, and opened ${snapshot.value.pullRequests} pull requests. Your public work is showing the strongest signal in ${languageText}, and your recent pace looks ${momentumText}.`;
    }

    return '';
});

const focusAreas = computed(() =>
    (analysis.value.skillDistribution?.categories ?? [])
        .map((label, index) => ({
            label,
            value: analysis.value.skillDistribution?.values?.[index] ?? 0,
        }))
        .sort((left, right) => right.value - left.value)
        .slice(0, 3),
);

const trimmedSummary = computed(() => analysisSummary.value.slice(0, 3));
const analyzedRepositories = computed(() => analysis.value.analyzedRepositories ?? []);
const skillSignals = computed(() => analysis.value.skillSignals ?? []);
const thirtyDayPlan = computed(() => analysis.value.thirtyDayPlan ?? []);
const improvementActions = computed(() => analysis.value.improvementActions ?? []);
const visibilityAdvice = computed(() => analysis.value.howToGetNoticed ?? { summary: '', actions: [] });
const suggestedRepositories = computed(() => analysis.value.suggestedRepositories ?? []);
const contributionStyle = computed(() => analysis.value.contributionStyle ?? { label: '', summary: '', confidence: '', evidence: [] });
const trajectoryWindows = computed(() => {
    const windows = analysis.value.trajectory?.windows ?? [];
    const values = Array.isArray(windows) ? windows : Object.values(windows);

    return [...values].sort((left, right) => Number(right.days || 0) - Number(left.days || 0));
});

const scoreBreakdownOption = computed(() => ({
    color: ['#2563eb', '#93c5fd'],
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: { bottom: 8, textStyle: { color: '#475569' } },
    grid: { left: 16, right: 16, top: 12, bottom: 56 },
    xAxis: {
        type: 'value',
        splitLine: { lineStyle: { color: '#e2e8f0' } },
    },
    yAxis: {
        type: 'category',
        data: analysis.value.scoreBreakdown?.categories ?? [],
        axisTick: { show: false },
    },
    series: [
        {
            name: 'Current',
            type: 'bar',
            data: analysis.value.scoreBreakdown?.values ?? [],
            barWidth: 14,
            itemStyle: { borderRadius: [0, 8, 8, 0] },
        },
        {
            name: 'Benchmark',
            type: 'bar',
            data: analysis.value.scoreBreakdown?.benchmark ?? [],
            barWidth: 14,
            itemStyle: { borderRadius: [0, 8, 8, 0], color: '#bfdbfe' },
        },
    ],
}));

const coverageHighlights = computed(() => [
    {
        label: 'Recent trend',
        value: snapshot.value.momentum ?? 'Stable',
        tone: 'text-blue-700',
    },
    {
        label: 'Repos analyzed',
        value: String(analyzedRepositories.value.length || 0),
        tone: 'text-emerald-700',
    },
    { label: 'Last updated', value: analysisUpdatedLabel.value, tone: 'text-slate-700' },
]);

const trajectoryOption = computed(() => ({
    color: ['#2563eb', '#0f766e'],
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: { bottom: 0, textStyle: { color: '#475569' } },
    grid: { left: 20, right: 16, top: 16, bottom: 56 },
    xAxis: {
        type: 'category',
        data: trajectoryWindows.value.map((item) => item.label),
        axisTick: { show: false },
    },
    yAxis: {
        type: 'value',
        min: 0,
        max: 10,
        splitLine: { lineStyle: { color: '#e2e8f0' } },
    },
    series: [
        {
            name: 'Score',
            type: 'bar',
            barWidth: 18,
            data: trajectoryWindows.value.map((item) => Number((Number(item.score || 0) / 10).toFixed(1))),
            itemStyle: { color: 'rgba(37, 99, 235, 0.85)', borderRadius: [8, 8, 0, 0] },
        },
        {
            name: 'Confidence',
            type: 'bar',
            barWidth: 18,
            data: trajectoryWindows.value.map((item) => Number((Number(item.confidence || 0) / 10).toFixed(1))),
            itemStyle: { color: 'rgba(15, 118, 110, 0.45)', borderRadius: [8, 8, 0, 0] },
        },
    ],
}));

onMounted(async () => {
    store.resetAnalysisState();
    await store.loadCurrentAnalysis();
    await store.ensureCurrentAnalysis();
});
</script>

<template>
    <div class="space-y-6">
        <section class="grid gap-6">
            <SurfaceCard
                title="Profile analysis"
                description="Connect GitHub first, then analyze the account you authorized."
            >
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-500">Your profile</p>
                        <h2 class="mt-2 truncate text-3xl font-semibold tracking-tight text-slate-950">
                            {{ profileTitle }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ analysisNotice }}
                        </p>
                    </div>

                    <div class="flex w-full max-w-xl flex-col gap-3 sm:flex-row sm:items-stretch sm:justify-end">
                        <a
                            :href="githubConnectUrl"
                            class="inline-flex h-12 items-center justify-center rounded-xl border border-brand-200 bg-white px-5 text-sm font-semibold text-brand-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 sm:flex-1"
                        >
                            {{ hasConnection ? 'Reconnect GitHub' : 'Connect GitHub' }}
                        </a>
                        <el-button
                            type="primary"
                            size="large"
                            class="!h-12 sm:flex-1"
                            :disabled="!canAnalyze"
                            :loading="publicLoading"
                            @click="runCurrentAnalysis"
                        >
                            {{ store.hasAnalysisRun ? 'Recalculate analysis' : 'Analyze my profile' }}
                        </el-button>
                    </div>
                </div>

                <div v-if="analysis.analysisRunId" class="mt-5 overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.14),_transparent_42%),linear-gradient(135deg,#0f172a_0%,#172554_100%)] px-4 py-4 text-white shadow-[0_24px_48px_-28px_rgba(15,23,42,0.9)]">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-300">Quick read</p>
                            <h3 class="mt-3 text-2xl font-semibold tracking-tight text-white">
                                {{ snapshot.score !== null ? `${snapshot.score.toFixed(1)}/10` : 'Live profile read' }}
                            </h3>
                            <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-200">
                                {{ snapshotNarrative }}
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    v-for="language in snapshot.languages"
                                    :key="language"
                                    class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-medium text-slate-100"
                                >
                                    {{ language }}
                                </span>
                            </div>
                        </div>

                        <div class="grid min-w-0 gap-3 sm:grid-cols-3 xl:w-[340px] xl:grid-cols-1">
                            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Recent trend</p>
                                <p class="mt-2 text-lg font-semibold text-white">{{ snapshot.momentum ?? 'Stable' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">How complete this view is</p>
                                <p class="mt-2 text-lg font-semibold text-white">{{ snapshot.confidence !== null ? `${snapshot.confidence}%` : 'Not scored' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Main strength</p>
                                <p class="mt-2 text-lg font-semibold text-white">{{ focusAreas[0]?.label ?? 'Generalist' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-4">
                        <div
                            v-for="item in snapshotStats"
                            :key="item.key"
                            class="rounded-2xl border border-white/10 bg-white/8 px-4 py-3 backdrop-blur-sm"
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">{{ item.label }}</p>
                            <p class="mt-2 text-2xl font-semibold text-white">{{ item.value }}</p>
                        </div>
                    </div>
                </div>
            </SurfaceCard>
        </section>

        <el-alert
            v-if="publicError"
            type="warning"
            :title="publicError"
            :closable="false"
            show-icon
        />

        <el-alert
            v-if="error"
            type="warning"
            :title="error"
            :closable="false"
            show-icon
        />

        <section v-if="loadingInitialProfile" class="grid gap-6">
            <SurfaceCard title="Analyzing GitHub activity" description="Pulling repositories, timeline signals, and recommendation inputs for this profile.">
                <el-skeleton animated>
                    <template #template>
                        <div class="space-y-4">
                            <el-skeleton-item variant="h1" style="width: 40%" />
                            <el-skeleton-item variant="text" style="width: 100%" />
                            <el-skeleton-item variant="text" style="width: 86%" />
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <el-skeleton-item
                                    v-for="index in 4"
                                    :key="index"
                                    variant="rect"
                                    style="height: 132px; border-radius: 24px"
                                />
                            </div>
                        </div>
                    </template>
                </el-skeleton>
            </SurfaceCard>
        </section>

        <template v-else-if="store.hasAnalysisRun">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    v-for="card in summaryCards"
                    :key="card.key"
                    v-bind="card"
                />
            </section>

            <section class="grid gap-6">
                <SurfaceCard
                    :title="profileTitle"
                    :description="analysisProfile.bio || 'Public GitHub activity translated into growth signals.'"
                >
                    <template #actions>
                        <el-tag effect="light" type="primary">
                            {{ analysisProfile.role || 'Developer profile' }}
                        </el-tag>
                    </template>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Followers</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ analysisProfile.followers }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Public repos</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ analysisProfile.publicRepos }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Active weeks</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ analysisProfile.contributionStreak }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Visible PRs</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950">{{ analysisProfile.publicPullRequests }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <span
                            v-for="item in focusAreas"
                            :key="item.label"
                            class="rounded-full border border-brand-100 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-brand-700"
                        >
                            {{ item.label }} {{ item.value }}%
                        </span>
                    </div>

                    <div class="mt-6 space-y-3">
                        <article
                            v-for="line in trimmedSummary"
                            :key="line"
                            class="rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600"
                        >
                            {{ line }}
                        </article>
                    </div>
                </SurfaceCard>
            </section>

            <section class="grid items-start gap-6 xl:grid-cols-[1.25fr_1fr]">
                <SurfaceCard
                    class="h-[680px]"
                    title="Repositories used for this report"
                    description="These are the repositories that contributed most to the current result."
                >
                    <div class="h-full space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="repo in analyzedRepositories"
                            :key="repo.fullName || repo.name"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a
                                        v-if="repo.url"
                                        :href="repo.url"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="truncate text-sm font-semibold text-slate-950 hover:text-brand-700"
                                    >
                                        {{ repo.fullName || repo.name }}
                                    </a>
                                    <h3 v-else class="truncate text-sm font-semibold text-slate-950">{{ repo.fullName || repo.name }}</h3>
                                    <p v-if="repo.description" class="mt-2 text-sm leading-6 text-slate-600">{{ repo.description }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.24em]" :class="repo.visibility === 'private' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border border-slate-200'">
                                    {{ repo.visibility }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="dashboard-chip">{{ repo.language || 'Unknown stack' }}</span>
                                <span class="dashboard-chip">{{ repo.commitCount }} commits</span>
                                <span class="dashboard-chip">{{ repo.pullRequestCount }} PRs</span>
                                <span class="dashboard-chip">{{ repo.issueCount }} issues</span>
                            </div>
                        </article>
                        <div v-if="analyzedRepositories.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                            No repository source details are available for this run.
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    class="h-[680px]"
                    title="What stands out in your GitHub profile"
                    description="A simple read on your style of work and the strongest visible skill signals."
                >
                    <div class="flex h-full flex-col gap-4">
                        <div class="rounded-2xl border border-brand-100 bg-brand-50/70 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-950">{{ contributionStyle.label || 'Visible work style' }}</h3>
                                <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-brand-700">{{ contributionStyle.confidence }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ contributionStyle.summary }}</p>
                            <ul v-if="contributionStyle.evidence?.length" class="mt-3 space-y-2 text-sm leading-6 text-slate-600">
                                <li v-for="item in contributionStyle.evidence" :key="item">• {{ item }}</li>
                            </ul>
                        </div>

                        <div class="flex-1 space-y-3 overflow-y-auto pr-1">
                            <article
                                v-for="signal in skillSignals"
                                :key="signal.key"
                                class="rounded-2xl border border-slate-200 bg-white p-4"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-slate-950">{{ signal.label }}</h3>
                                    <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">
                                        {{ signal.score.toFixed(1) }}/10 · {{ signal.confidence }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-slate-700">{{ signal.notes }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span v-for="item in signal.evidence" :key="`${signal.key}-${item}`" class="dashboard-chip">
                                        {{ item }}
                                    </span>
                                </div>
                            </article>
                        </div>
                    </div>
                </SurfaceCard>
            </section>

            <section class="grid gap-6">
                <SurfaceCard
                    title="Where your profile is strongest"
                    description="A quick visual summary of the areas that show up most clearly."
                >
                    <div class="grid items-stretch gap-6 xl:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-semibold text-slate-950">Skill mix</p>
                            <div class="mt-3">
                                <AsyncEChart :option="skillRadarOption" height="380px" />
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="(label, index) in analysis.skillDistribution.categories"
                                    :key="label"
                                    class="dashboard-chip"
                                >
                                    {{ label }} {{ analysis.skillDistribution.values[index] }}%
                                </span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-semibold text-slate-950">Strengths vs weaknesses</p>
                            <div class="mt-3">
                                <AsyncEChart :option="segmentOption" height="380px" />
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Strengths</p>
                                    <p class="mt-2 text-2xl font-semibold text-emerald-950">{{ analysis.strengths.length }}</p>
                                </div>
                                <div class="rounded-2xl border border-rose-100 bg-rose-50/70 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-rose-700">Weaknesses</p>
                                    <p class="mt-2 text-2xl font-semibold text-rose-950">{{ analysis.weaknesses.length }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </SurfaceCard>
            </section>

            <section class="grid items-stretch gap-6 xl:grid-cols-3">
                <SurfaceCard
                    class="h-full"
                    title="Strengths"
                    description="These are the habits and signals already helping your profile."
                >
                    <div class="max-h-[440px] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="item in analysis.strengths"
                            :key="item.id"
                            class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-emerald-950">{{ item.title }}</h3>
                                <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-700">{{ item.impact }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-emerald-950/85">{{ item.detail }}</p>
                        </article>
                        <div v-if="analysis.strengths.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                            No strong signals were confident enough to highlight in this run.
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    class="h-full"
                    title="Weaknesses"
                    description="These are the areas holding the profile back right now."
                >
                    <div class="max-h-[440px] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="item in analysis.weaknesses"
                            :key="item.id"
                            class="rounded-2xl border border-rose-100 bg-rose-50/70 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-rose-950">{{ item.title }}</h3>
                                <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-rose-700">{{ item.impact }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-rose-950/85">{{ item.detail }}</p>
                        </article>
                        <div v-if="analysis.weaknesses.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                            No major weak signals were flagged in this run.
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    class="h-full"
                    title="Recommended next steps"
                    description="Start here if you want the fastest improvement."
                >
                    <div class="max-h-[440px] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="item in analysis.recommendations"
                            :key="item.id"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-950">{{ item.title }}</h3>
                                <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-brand-700">{{ item.impact }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ item.detail }}</p>
                            <p v-if="item.why" class="mt-3 rounded-xl bg-white px-3 py-2 text-sm leading-6 text-slate-600">
                                <span class="font-medium text-slate-900">Why this helps:</span>
                                {{ item.why }}
                            </p>
                            <p v-if="item.successMetric" class="mt-3 text-sm leading-6 text-slate-600">
                                <span class="font-medium text-slate-900">What good looks like:</span>
                                {{ item.successMetric }}
                            </p>
                            <p v-if="item.aiNote" class="mt-3 rounded-xl bg-brand-50 px-3 py-2 text-sm leading-6 text-brand-900">
                                <span class="font-medium">Extra context:</span>
                                {{ item.aiNote }}
                            </p>
                            <p v-if="item.evidence?.title" class="mt-3 text-xs uppercase tracking-[0.24em] text-slate-500">
                                Triggered by {{ item.evidence.title }}
                            </p>
                        </article>
                        <div v-if="analysis.recommendations.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                            No concrete next-step recommendations were generated for this run.
                        </div>
                    </div>
                </SurfaceCard>
            </section>

            <section class="grid items-stretch gap-6 xl:grid-cols-2">
                <SurfaceCard
                    class="h-full"
                    title="How to get noticed more"
                    description="Practical ways to make your GitHub activity easier for other people to notice and trust."
                >
                    <div class="flex h-full flex-col gap-4">
                        <p class="text-sm leading-6 text-slate-700">{{ visibilityAdvice.summary }}</p>
                        <div class="max-h-[420px] space-y-3 overflow-y-auto pr-1">
                            <article
                                v-for="(item, index) in visibilityAdvice.actions"
                                :key="`${item.action}-${index}`"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <h3 class="text-sm font-semibold text-slate-950">{{ item.action }}</h3>
                                <p v-if="item.why" class="mt-2 text-sm leading-6 text-slate-700">{{ item.why }}</p>
                                <p v-if="item.evidence" class="mt-3 text-xs uppercase tracking-[0.24em] text-slate-500">{{ item.evidence }}</p>
                            </article>
                            <div v-if="visibilityAdvice.actions.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                                No visibility guidance was generated for this run.
                            </div>
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    class="h-full"
                    title="Best actions to take next"
                    description="A short list of moves that should improve the profile fastest."
                >
                    <div class="max-h-[420px] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="(item, index) in improvementActions"
                            :key="`${item.title}-${index}`"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <h3 class="text-sm font-semibold text-slate-950">{{ item.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ item.detail }}</p>
                            <p v-if="item.why" class="mt-3 text-sm leading-6 text-slate-600">
                                <span class="font-medium text-slate-900">Why this matters:</span> {{ item.why }}
                            </p>
                            <p v-if="item.metric" class="mt-3 text-xs uppercase tracking-[0.24em] text-slate-500">{{ item.metric }}</p>
                        </article>
                        <div v-if="improvementActions.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                            No additional AI improvement actions were generated for this run.
                        </div>
                    </div>
                </SurfaceCard>
            </section>

            <section class="grid items-stretch gap-6 xl:grid-cols-2">
                <SurfaceCard
                    class="h-full"
                    title="Next 7-day plan"
                    description="A simple plan for the next 7 days."
                >
                    <div class="max-h-[520px] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="item in analysis.weeklyPlan"
                            :key="`${item.day}-${item.title}`"
                            class="rounded-2xl border border-slate-200 bg-white p-4"
                        >
                            <div class="flex items-center gap-3">
                                <span class="inline-flex rounded-full bg-brand-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-brand-700">
                                    {{ item.day }}
                                </span>
                                <h4 class="text-sm font-semibold text-slate-950">{{ item.title }}</h4>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-slate-700">{{ item.action }}</p>
                            <p v-if="item.aiNote" class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm leading-6 text-slate-600">
                                <span class="font-medium text-slate-900">Helpful note:</span>
                                {{ item.aiNote }}
                            </p>
                        </article>
                        <div v-if="analysis.weeklyPlan.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                            No weekly plan is available for this run yet.
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    class="h-full"
                    title="Next 30-day plan"
                    description="A simple plan for the next 30 days."
                >
                    <div class="max-h-[520px] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="item in thirtyDayPlan"
                            :key="item.week"
                            class="rounded-2xl border border-slate-200 bg-white p-4"
                        >
                            <div class="flex items-center gap-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-700">
                                    {{ item.week }}
                                </span>
                                <h4 class="text-sm font-semibold text-slate-950">{{ item.title }}</h4>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-slate-700">{{ item.action }}</p>
                            <p class="mt-3 text-xs uppercase tracking-[0.24em] text-slate-500">
                                Focus: {{ item.focus }}
                            </p>
                            <p v-if="item.aiNote" class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm leading-6 text-slate-600">
                                <span class="font-medium text-slate-900">Helpful note:</span>
                                {{ item.aiNote }}
                            </p>
                        </article>
                        <div v-if="thirtyDayPlan.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                            No 30-day plan is available for this run yet.
                        </div>
                    </div>
                </SurfaceCard>
            </section>

            <section class="grid items-stretch gap-6 xl:grid-cols-2">
                <SurfaceCard
                    class="h-full"
                    title="Activity trend"
                    description="Your weekly activity over the current analysis window."
                >
                    <AsyncEChart :option="trendOption" height="320px" />
                </SurfaceCard>

                <SurfaceCard
                    class="h-full"
                    title="Score breakdown"
                    description="The current score components compared with a lighter benchmark line."
                >
                    <AsyncEChart :option="scoreBreakdownOption" height="320px" />
                </SurfaceCard>
            </section>

            <section class="grid items-stretch gap-6 xl:grid-cols-2">
                <SurfaceCard
                    class="h-full"
                    title="Window comparison"
                    description="How this profile reads across nested analysis windows, rather than a week-by-week forecast."
                >
                    <AsyncEChart :option="trajectoryOption" height="280px" />
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-950">{{ analysis.trajectory.summary }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-700">{{ analysis.trajectory.outlook }}</p>
                        <p class="mt-3 text-xs uppercase tracking-[0.24em] text-slate-500">
                            How sure this is: {{ analysis.trajectory.confidence }}
                        </p>
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    class="h-full"
                    title="Suggested repositories to contribute to"
                    description="Repositories that look like a good fit for your current skills and contribution style."
                >
                    <div class="max-h-[420px] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="item in suggestedRepositories"
                            :key="item.repo"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <a
                                    v-if="item.url"
                                    :href="item.url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="text-sm font-semibold text-slate-950 hover:text-brand-700"
                                >
                                    {{ item.repo }}
                                </a>
                                <h3 v-else class="text-sm font-semibold text-slate-950">{{ item.repo }}</h3>
                                <span v-if="item.language" class="dashboard-chip">{{ item.language }}</span>
                            </div>
                            <p v-if="item.description" class="mt-2 text-sm leading-6 text-slate-700">{{ item.description }}</p>
                            <p v-if="item.whyFit" class="mt-3 text-sm leading-6 text-slate-600">
                                <span class="font-medium text-slate-900">Why this fits you:</span>
                                {{ item.whyFit }}
                            </p>
                            <p v-if="item.realisticContribution" class="mt-3 text-sm leading-6 text-slate-600">
                                <span class="font-medium text-slate-900">A realistic first contribution:</span>
                                {{ item.realisticContribution }}
                            </p>
                        </article>
                        <div v-if="suggestedRepositories.length === 0" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                            No repository suggestions were generated for this run.
                        </div>
                    </div>
                </SurfaceCard>
            </section>

            <section class="grid items-stretch gap-6 xl:grid-cols-2">
                <SurfaceCard
                    class="h-full"
                    title="Simulator output"
                    description="A rough estimate of how your score could move if you improve the main drivers."
                >
                    <template #actions>
                        <el-tag effect="light" type="primary">{{ simulator.confidence }}</el-tag>
                    </template>

                    <p class="text-sm leading-6 text-slate-600">
                        This is a what-if view, not a promise. It shows how the score may move if you improve the main areas highlighted in this report.
                    </p>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Current</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ simulator.current }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Projected</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ simulator.projected }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Uplift</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight text-emerald-700">{{ simulator.uplift }}</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <AsyncEChart :option="simulatorOption" height="280px" />
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    class="h-full"
                    title="How complete this report is"
                    description="A quick check of what this report covered and how much signal it had to work with."
                >
                    <div class="flex h-full flex-col gap-4">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="item in coverageHighlights"
                                :key="item.label"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">{{ item.label }}</p>
                                <p class="mt-2 text-xl font-semibold" :class="item.tone">{{ item.value }}</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Top areas this report picked up</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="item in focusAreas"
                                    :key="item.label"
                                    class="rounded-full border border-brand-100 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-brand-700"
                                >
                                    {{ item.label }} {{ item.value }}%
                                </span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Big picture</p>
                            <p class="mt-3 text-sm leading-6 text-slate-700">{{ analysis.trajectory.outlook || 'Window comparisons become clearer as more reviewable activity accumulates.' }}</p>
                        </div>
                    </div>
                </SurfaceCard>
            </section>
        </template>
    </div>
</template>
