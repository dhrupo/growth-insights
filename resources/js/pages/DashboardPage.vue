<script setup>
import { defineAsyncComponent } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { useDashboardInsights } from '@/composables/useDashboardInsights';
import { useGrowthAnalysisWorkbench } from '@/composables/useGrowthAnalysisWorkbench';
import MetricCard from '@/components/dashboard/MetricCard.vue';
import SurfaceCard from '@/components/ui/SurfaceCard.vue';
import { dashboardModes } from '@/navigation';

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
    segmentOption,
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

const {
    analysis,
    analysisProfile,
    analysisConnection,
    analysisSummary,
    analysisSourceLabel,
    analysisNotice,
    analysisUpdatedLabel,
    publicForm,
    privateForm,
    publicLoading,
    privateLoading,
    publicError,
    privateError,
    skillRadarOption,
    runPublicAnalysis,
    connectPrivateWorkspace,
} = useGrowthAnalysisWorkbench();

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
                    <p class="dashboard-chip">Developer growth insights</p>
                    <h3 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                        Enter a GitHub username, run a public scan, then unlock private signals with a token.
                    </h3>
                    <p class="mt-3 text-sm leading-6 text-slate-500 sm:text-base">
                        The shell now behaves like a real product flow: public analysis loads first, private
                        connection stays optional, and the score model updates without changing the dashboard layout.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <el-tag effect="light" :type="analysis.source === 'live' ? 'success' : analysis.source === 'mixed' ? 'warning' : 'info'">
                        {{ analysisSourceLabel }}
                    </el-tag>
                    <el-tag effect="light" type="primary">
                        {{ analysisUpdatedLabel }}
                    </el-tag>
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
                class="mt-5"
                type="info"
                :title="analysisNotice"
                :closable="false"
                show-icon
            />

            <el-alert
                v-if="error"
                class="mt-4"
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

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.12fr)_minmax(360px,0.88fr)]">
            <SurfaceCard
                title="Public analysis"
                description="Run a public GitHub scan from a username and inspect the visible profile first."
            >
                <template #actions>
                    <el-tag effect="light" type="info">{{ analysisProfile.username }}</el-tag>
                </template>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <el-form class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                        <el-form-item label="GitHub username" class="mb-0">
                            <el-input
                                v-model="publicForm.username"
                                size="large"
                                placeholder="octocat"
                                autocomplete="off"
                                @keyup.enter="runPublicAnalysis"
                            />
                        </el-form-item>

                        <el-form-item label=" " class="mb-0">
                            <el-button
                                type="primary"
                                size="large"
                                :loading="publicLoading"
                                @click="runPublicAnalysis"
                            >
                                Run public analysis
                            </el-button>
                        </el-form-item>
                    </el-form>
                </div>

                <el-alert
                    v-if="publicError"
                    class="mt-5"
                    type="warning"
                    :title="publicError"
                    :closable="false"
                    show-icon
                />

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Display name</p>
                        <p class="mt-2 text-sm font-semibold text-slate-950">{{ analysisProfile.displayName }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ analysisProfile.role }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Followers</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ analysisProfile.followers }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Public repos</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ analysisProfile.publicRepos }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">PR cadence</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ analysisProfile.publicPullRequests }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ analysisProfile.contributionStreak }} day streak</p>
                    </div>
                </div>

                <div class="mt-6">
                    <p class="dashboard-chip">Analysis summary</p>
                    <ul class="mt-4 space-y-3">
                        <li
                            v-for="item in analysisSummary"
                            :key="item"
                            class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600"
                        >
                            <span class="mt-1 size-2 rounded-full bg-brand-500"></span>
                            <span class="min-w-0">{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </SurfaceCard>

            <SurfaceCard
                title="Private connection"
                description="Optionally attach a token to add private repository context to the same analysis model."
            >
                <template #actions>
                    <el-switch v-model="privateForm.enabled" inline-prompt active-text="On" inactive-text="Off" />
                </template>

                <div v-if="privateForm.enabled" class="space-y-5">
                    <el-form class="space-y-4">
                        <el-form-item label="GitHub username" class="mb-0">
                            <el-input
                                v-model="privateForm.username"
                                size="large"
                                placeholder="octocat"
                                autocomplete="off"
                            />
                        </el-form-item>

                        <el-form-item label="Personal access token" class="mb-0">
                            <el-input
                                v-model="privateForm.token"
                                size="large"
                                type="password"
                                show-password
                                placeholder="ghp_..."
                                autocomplete="off"
                            />
                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                Kept in browser memory only. This frontend does not persist the token.
                            </p>
                        </el-form-item>

                        <el-button
                            type="primary"
                            size="large"
                            class="w-full"
                            :loading="privateLoading"
                            @click="connectPrivateWorkspace"
                        >
                            Connect private analysis
                        </el-button>
                    </el-form>
                </div>

                <el-empty
                    v-else
                    description="Enable token-backed analysis to reveal the private connection form."
                />

                <el-alert
                    v-if="privateError"
                    class="mt-5"
                    type="warning"
                    :title="privateError"
                    :closable="false"
                    show-icon
                />

                <div class="mt-5 rounded-[24px] border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Workspace</p>
                        <el-tag effect="light" :type="analysisConnection.connected ? 'success' : 'info'">
                            {{ analysisConnection.connected ? 'Connected' : 'Disconnected' }}
                        </el-tag>
                    </div>
                    <p class="mt-2 text-sm font-medium text-slate-950">{{ analysisConnection.workspace }}</p>
                    <p class="mt-1 text-sm text-slate-500">Token preview: {{ analysisConnection.tokenPreview }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ analysisConnection.note }}</p>
                </div>
            </SurfaceCard>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,0.7fr)]">
            <SurfaceCard
                title="Skill distribution"
                description="A radar chart that summarizes the current profile or seed fallback signal."
            >
                <template #actions>
                    <el-tag effect="light" type="success">{{ analysis.source === 'live' ? 'Live' : 'Fallback' }}</el-tag>
                </template>

                <AsyncEChart :option="skillRadarOption" height="340px" />

                <div class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="(label, index) in analysis.skillDistribution.categories"
                        :key="label"
                        class="dashboard-chip"
                    >
                        {{ label }} {{ analysis.skillDistribution.values[index] }}%
                    </span>
                </div>
            </SurfaceCard>

            <div class="space-y-6">
                <SurfaceCard
                    title="Activity trend"
                    description="Repository activity pacing and contribution movement over time."
                >
                    <template #actions>
                        <el-tag effect="light" type="primary">
                            {{ isLoading ? 'Loading' : store.timeline.updatedAt ? `Updated ${store.timeline.updatedAt}` : 'Seed data' }}
                        </el-tag>
                    </template>

                    <AsyncEChart :option="trendOption" height="260px" />
                </SurfaceCard>

                <SurfaceCard
                    title="Analysis scope"
                    description="The same filters shape the public scan and later private enrichment."
                >
                    <template #actions>
                        <el-button
                            size="small"
                            :loading="isRefreshing"
                            @click="refresh"
                        >
                            Sync data
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
                            <el-option label="Frontend" value="frontend" />
                            <el-option label="Backend" value="backend" />
                            <el-option label="Open source" value="oss" />
                        </el-select>
                        <el-input
                            v-model="filters.query"
                            size="large"
                            placeholder="Search repositories"
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
                description="Strengths, weaknesses, and recommendations derived from the analysis model."
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
