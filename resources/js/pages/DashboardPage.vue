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

const snapshotStats = computed(() => {
    const summary = analysis.value.evidenceSummary ?? '';
    const matches = [
        { key: 'weeks', label: 'Active weeks', match: summary.match(/(\d+)\s+active weeks/i) },
        { key: 'days', label: 'Active days', match: summary.match(/(\d+)\s+active days/i) },
        { key: 'commits', label: 'Commits', match: summary.match(/(\d+)\s+commits/i) },
        { key: 'prs', label: 'PRs', match: summary.match(/(\d+)\s+PRs/i) },
    ];

    return matches.map((item) => ({
        key: item.key,
        label: item.label,
        value: item.match?.[1] ?? '0',
    }));
});

const snapshotMeta = computed(() => {
    const summary = analysis.value.evidenceSummary ?? '';
    const rawScore = summary.match(/overall score\s+([\d.]+)/i)?.[1];

    return {
        score: rawScore ? (Number.parseFloat(rawScore) / 10).toFixed(1) : null,
        confidence: summary.match(/confidence\s+([\d.]+)/i)?.[1] ?? null,
        momentum: summary.match(/Momentum label:\s*([a-z-]+)/i)?.[1] ?? null,
        languages: summary.match(/Top languages:\s*([^.]+)/i)?.[1]?.split(',').map((item) => item.trim()).filter(Boolean) ?? [],
    };
});

const snapshotNarrative = computed(() => {
    const stats = Object.fromEntries(snapshotStats.value.map((item) => [item.key, item.value]));
    const languageText = snapshotMeta.value.languages.length
        ? snapshotMeta.value.languages.join(', ')
        : 'the visible repository mix';

    if (analysis.value.evidenceSummary) {
        return `${stats.days} active days across ${stats.weeks} active weeks produced ${stats.commits} commits and ${stats.prs} pull requests. The current profile leans toward ${languageText}, and momentum is reading as ${snapshotMeta.value.momentum ?? 'stable'}.`;
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

const monthPlan = computed(() => {
    const recommendationTitles = analysis.value.recommendations?.map((item) => item.title) ?? [];
    const weaknessTitles = analysis.value.weaknesses?.map((item) => item.title) ?? [];

    return [
        {
            week: 'Week 1',
            title: 'Stabilize your baseline',
            action: 'Keep a visible shipping cadence and complete one contribution that is easy to review end to end.',
            focus: recommendationTitles[0] ?? 'Consistency and visible shipping',
        },
        {
            week: 'Week 2',
            title: 'Improve your weakest signal',
            action: 'Choose the weakest area in the current report and improve it with one measurable output, not a vague intention.',
            focus: weaknessTitles[0] ?? recommendationTitles[1] ?? 'Quality or breadth',
        },
        {
            week: 'Week 3',
            title: 'Broaden the profile',
            action: 'Add one adjacent area such as tests, docs, tooling, or a second repository so the profile is not overly concentrated.',
            focus: recommendationTitles[1] ?? 'Signal breadth',
        },
        {
            week: 'Week 4',
            title: 'Create a stronger public proof point',
            action: 'Ship one contribution that is publicly reviewable and summarize what changed so the month ends with a strong artifact.',
            focus: recommendationTitles[2] ?? 'Public proof of work',
        },
    ];
});

const scoreRangeSummary = computed(() => {
    const current = simulator.value.current ?? 'n/a';
    const projected = simulator.value.projected ?? 'n/a';
    const uplift = simulator.value.uplift ?? '0.0';

    return [
        `Current score: ${current}`,
        `Scenario score: ${projected}`,
        `Estimated movement: ${uplift}`,
    ];
});

const analyticsHighlights = computed(() => [
    {
        label: 'Momentum',
        value: snapshotMeta.value.momentum ?? 'Stable',
        tone: 'text-blue-700',
    },
    {
        label: 'Confidence',
        value: snapshotMeta.value.confidence ? `${snapshotMeta.value.confidence}%` : 'Not scored',
        tone: 'text-emerald-700',
    },
    { label: 'Last analyzed', value: analysisUpdatedLabel.value, tone: 'text-slate-700' },
]);

onMounted(async () => {
    store.resetAnalysisState();
    await store.loadCurrentAnalysis();
});
</script>

<template>
    <div class="space-y-6">
        <section class="grid gap-6">
            <SurfaceCard
                title="Profile analysis"
                description="Connect GitHub first, then analyze the account you authorized. The dashboard stays focused on your own profile instead of arbitrary public usernames."
            >
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-500">Current profile</p>
                        <h2 class="mt-2 truncate text-3xl font-semibold tracking-tight text-slate-950">
                            {{ profileTitle }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ analysisNotice }}
                        </p>
                    </div>

                    <div class="flex w-full max-w-xl flex-col gap-3 sm:flex-row sm:justify-end">
                        <a
                            :href="githubConnectUrl"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-brand-200 bg-white px-5 py-3 text-sm font-semibold text-brand-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50 sm:flex-1"
                        >
                            {{ hasConnection ? 'Reconnect GitHub' : 'Connect GitHub' }}
                        </a>
                        <el-button
                            type="primary"
                            size="large"
                            class="sm:flex-1"
                            :disabled="!canAnalyze"
                            :loading="publicLoading"
                            @click="runCurrentAnalysis"
                        >
                            {{ store.hasAnalysisRun ? 'Recalculate analysis' : 'Analyze my profile' }}
                        </el-button>
                    </div>
                </div>

                <div v-if="analysis.evidenceSummary" class="mt-6 overflow-hidden rounded-[28px] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.14),_transparent_42%),linear-gradient(135deg,#0f172a_0%,#172554_100%)] px-5 py-5 text-white shadow-[0_24px_48px_-28px_rgba(15,23,42,0.9)]">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-300">Analysis snapshot</p>
                            <h3 class="mt-3 text-2xl font-semibold tracking-tight text-white">
                                {{ snapshotMeta.score ? `${snapshotMeta.score}/10` : 'Live profile read' }}
                            </h3>
                            <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-200">
                                {{ snapshotNarrative }}
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    v-for="language in snapshotMeta.languages"
                                    :key="language"
                                    class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-medium text-slate-100"
                                >
                                    {{ language }}
                                </span>
                            </div>
                        </div>

                        <div class="grid min-w-0 gap-3 sm:grid-cols-3 xl:w-[340px] xl:grid-cols-1">
                            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Momentum</p>
                                <p class="mt-2 text-lg font-semibold text-white">{{ snapshotMeta.momentum ?? 'Stable' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Confidence</p>
                                <p class="mt-2 text-lg font-semibold text-white">{{ snapshotMeta.confidence ? `${snapshotMeta.confidence}%` : 'Not scored' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">Focus</p>
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

            <section class="grid gap-6">
                <SurfaceCard
                    title="Signal visuals"
                    description="A softer visual summary of where the strongest signals are concentrated and where the profile currently leans."
                >
                    <div class="grid items-stretch gap-6 xl:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-semibold text-slate-950">Skill distribution</p>
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
                            <p class="text-sm font-semibold text-slate-950">Signal balance</p>
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
                    description="Signals the system believes are already working in your favor."
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
                    description="Signals that are currently suppressing growth, momentum, or confidence."
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
                    description="What to do next, why it matters, and which signal it is trying to move."
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
                                <span class="font-medium text-slate-900">Why this matters:</span>
                                {{ item.why }}
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
                    title="Next 7-day plan"
                    description="A short weekly plan built from the current report so the next step is obvious."
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
                                <span class="font-medium text-slate-900">Coach note:</span>
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
                    description="A month-long structure for turning the current report into a more durable public profile."
                >
                    <div class="max-h-[520px] space-y-3 overflow-y-auto pr-1">
                        <article
                            v-for="item in monthPlan"
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
                        </article>
                    </div>
                </SurfaceCard>
            </section>

            <section class="grid items-stretch gap-6 xl:grid-cols-2">
                <SurfaceCard
                    class="h-full"
                    title="Activity trend"
                    description="Week-by-week contribution movement for the current analysis window."
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
                    title="Simulator output"
                    description="A directional view of how the score could move with a modest increase in contribution and validation."
                >
                    <template #actions>
                        <el-tag effect="light" type="primary">{{ simulator.confidence }}</el-tag>
                    </template>

                    <p class="text-sm leading-6 text-slate-600">
                        This is a scenario model, not a prediction. It shows how the score could move if you improve the specific drivers used in this analysis.
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
                    title="Momentum and confidence"
                    description="A separate read on how trustworthy the current profile is and what the current signal mix is emphasizing."
                >
                    <div class="flex h-full flex-col gap-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                v-for="item in analyticsHighlights"
                                :key="item.label"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">{{ item.label }}</p>
                                <p class="mt-2 text-xl font-semibold" :class="item.tone">{{ item.value }}</p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                v-for="item in scoreRangeSummary"
                                :key="item"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-medium text-slate-700"
                            >
                                {{ item }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Current focus areas</p>
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

                        <ul class="space-y-3">
                            <li
                                v-for="note in simulator.notes"
                                :key="note"
                                class="rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-700"
                            >
                                {{ note }}
                            </li>
                        </ul>
                    </div>
                </SurfaceCard>
            </section>
        </template>
    </div>
</template>
