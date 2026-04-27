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
    <div class="min-h-screen bg-slate-100 px-2.5 py-3 sm:px-4 sm:py-4 lg:px-5">
        <main class="mx-auto max-w-[1180px]">
            <header class="mb-4 rounded-[26px] border border-slate-200/80 bg-white px-5 py-5 shadow-[0_24px_60px_-40px_rgba(15,23,42,0.45)] sm:mb-5 sm:rounded-[30px] sm:px-6">
                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-brand-600 sm:text-xs sm:tracking-[0.28em]">Growth Insights</p>
                <div class="mt-2.5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <h1 class="text-[2.15rem] font-semibold leading-[1.05] tracking-tight text-slate-950 sm:text-4xl">
                            {{ pageTitle }}
                        </h1>
                        <p class="mt-3 max-w-2xl text-[15px] leading-7 text-slate-600 sm:text-base sm:leading-7">
                            {{ pageDescription }}
                        </p>
                    </div>
                </div>
            </header>

            <router-view />
        </main>
    </div>
</template>
