<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const pageTitle = computed(() => route.meta.title ?? 'Growth Insights');
const pageDescription = computed(
    () => route.meta.description ?? 'Developer growth signals from the GitHub account you explicitly connect.',
);
</script>

<template>
    <div class="min-h-screen bg-slate-100 px-4 py-5 sm:px-6 lg:px-8">
        <main class="mx-auto max-w-7xl">
            <header class="mb-6 rounded-[32px] border border-slate-200 bg-white px-6 py-6 shadow-[0_24px_60px_-40px_rgba(15,23,42,0.45)] sm:px-8">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-brand-600">Growth Insights</p>
                <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                            {{ pageTitle }}
                        </h1>
                        <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base">
                            {{ pageDescription }}
                        </p>
                    </div>
                </div>
            </header>

            <router-view />
        </main>
    </div>
</template>
