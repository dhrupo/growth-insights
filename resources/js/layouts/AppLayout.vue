<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { primaryNavigation } from '@/navigation';

const route = useRoute();
const router = useRouter();

const mobileMenuOpen = ref(false);

const pageTitle = computed(() => route.meta.title ?? 'Dashboard');
const pageDescription = computed(
    () => route.meta.description ?? 'Operational dashboard with fast access to charted context.',
);
const pageMode = computed(() => route.meta.mode ?? 'simple');

const isActive = (path) => {
    if (path === '/dashboard') {
        return route.path.startsWith('/dashboard');
    }

    return route.path === path;
};

const goTo = async (path) => {
    mobileMenuOpen.value = false;
    await router.push(path);
};

watch(
    () => route.fullPath,
    () => {
        mobileMenuOpen.value = false;
    },
);
</script>

<template>
    <div class="min-h-screen px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
        <div class="mx-auto flex min-h-[calc(100vh-2rem)] max-w-[1600px] gap-6 lg:min-h-[calc(100vh-3rem)]">
            <aside class="hidden w-72 shrink-0 xl:block">
                <div class="dashboard-frame sticky top-6 flex h-[calc(100vh-3rem)] flex-col p-5">
                    <div class="rounded-[28px] bg-slate-950 px-5 py-5 text-white shadow-[0_24px_48px_-24px_rgba(15,23,42,0.9)]">
                        <p class="text-xs font-medium uppercase tracking-[0.3em] text-slate-400">Growth Insights</p>
                        <h1 class="mt-3 text-2xl font-semibold tracking-tight">Operational dashboard</h1>
                        <p class="mt-2 text-sm text-slate-300">
                            Vue 3, Element Plus, Tailwind, and ECharts in a clean route-driven shell.
                        </p>
                    </div>

                    <div class="mt-5 rounded-[24px] border border-slate-200 bg-slate-50/80 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Mode</p>
                        <p class="mt-2 text-sm font-medium text-slate-900">{{ pageMode === 'advanced' ? 'Advanced' : 'Simple' }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            Simple mode stays focused. Advanced mode adds filters and diagnostic surfaces.
                        </p>
                    </div>

                    <nav class="mt-5 space-y-2">
                        <button
                            v-for="item in primaryNavigation"
                            :key="item.path"
                            type="button"
                            class="flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left transition focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                            :class="isActive(item.path) ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-transparent bg-white text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900'"
                            @click="goTo(item.path)"
                        >
                            <component :is="item.icon" class="mt-0.5 size-5 shrink-0" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ item.label }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ item.hint }}</span>
                            </span>
                        </button>
                    </nav>

                    <div class="mt-auto rounded-[24px] border border-slate-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Foundation</p>
                        <div class="mt-3 space-y-2 text-sm text-slate-600">
                            <p>Route-first navigation</p>
                            <p>Reusable card surfaces</p>
                            <p>ECharts-ready chart wrapper</p>
                        </div>
                    </div>
                </div>
            </aside>

            <el-drawer v-model="mobileMenuOpen" :with-header="false" size="84%">
                <div class="flex h-full flex-col gap-4 p-2">
                    <div class="rounded-[28px] bg-slate-950 px-5 py-5 text-white">
                        <p class="text-xs font-medium uppercase tracking-[0.3em] text-slate-400">Growth Insights</p>
                        <h1 class="mt-3 text-2xl font-semibold tracking-tight">Operational dashboard</h1>
                    </div>

                    <nav class="space-y-2">
                        <button
                            v-for="item in primaryNavigation"
                            :key="item.path"
                            type="button"
                            class="flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left transition"
                            :class="isActive(item.path) ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-slate-200 bg-white text-slate-700'"
                            @click="goTo(item.path)"
                        >
                            <component :is="item.icon" class="mt-0.5 size-5 shrink-0" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium">{{ item.label }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ item.hint }}</span>
                            </span>
                        </button>
                    </nav>
                </div>
            </el-drawer>

            <main class="min-w-0 flex-1">
                <header class="dashboard-frame flex items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <el-button
                            class="xl:hidden"
                            circle
                            plain
                            @click="mobileMenuOpen = true"
                        >
                            <el-icon><Menu /></el-icon>
                        </el-button>

                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Growth Insights</p>
                            <h2 class="truncate text-xl font-semibold text-slate-950 sm:text-2xl">{{ pageTitle }}</h2>
                            <p class="hidden text-sm text-slate-500 sm:block">{{ pageDescription }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <el-tag effect="light" type="primary" class="hidden sm:inline-flex">
                            {{ pageMode === 'advanced' ? 'Advanced mode' : 'Simple mode' }}
                        </el-tag>
                        <el-button plain>Refresh</el-button>
                        <el-button type="primary">Export</el-button>
                    </div>
                </header>

                <section class="mt-6">
                    <router-view />
                </section>
            </main>
        </div>
    </div>
</template>
