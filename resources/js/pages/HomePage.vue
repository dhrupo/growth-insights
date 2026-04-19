<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const username = ref('');

const openProfile = async () => {
    const next = username.value.trim().replace(/^@+/, '');

    if (!next) {
        return;
    }

    await router.push(`/${next}`);
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 px-4 py-6 sm:px-6 lg:px-8">
        <main class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-5xl items-center">
            <section class="w-full rounded-[36px] border border-slate-200 bg-white px-6 py-10 shadow-[0_30px_80px_-48px_rgba(15,23,42,0.5)] sm:px-10 sm:py-12 lg:px-14">
                <p class="text-xs font-semibold uppercase tracking-[0.32em] text-brand-600">Growth Insights</p>

                <div class="mt-5 grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(300px,0.85fr)] lg:items-start">
                    <div>
                        <h1 class="text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl">
                            Turn a GitHub profile into a readable growth story.
                        </h1>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">
                            Enter a GitHub username to see contribution trends, strengths, weak spots,
                            and a practical next-step plan. Private repositories stay optional and can
                            be added later by connecting GitHub from inside the profile page.
                        </p>

                        <el-form class="mt-8" @submit.prevent="openProfile">
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <el-input
                                    v-model="username"
                                    size="large"
                                    placeholder="Enter a GitHub username"
                                    autocomplete="off"
                                    @keyup.enter="openProfile"
                                >
                                    <template #prepend>@</template>
                                </el-input>
                                <el-button
                                    type="primary"
                                    size="large"
                                    class="sm:min-w-40"
                                    @click="openProfile"
                                >
                                    Analyze profile
                                </el-button>
                            </div>
                        </el-form>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <button
                                type="button"
                                class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700"
                                @click="username = 'dhrupo'"
                            >
                                Try `dhrupo`
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700"
                                @click="username = 'octocat'"
                            >
                                Try `octocat`
                            </button>
                        </div>
                    </div>

                    <div class="rounded-[28px] border border-slate-200 bg-slate-50 p-5">
                        <p class="text-sm font-semibold text-slate-950">What you get</p>
                        <div class="mt-4 space-y-3">
                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-sm font-semibold text-slate-950">1. Public growth profile</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">
                                    A clean read of visible GitHub activity, momentum, and score drivers.
                                </p>
                            </article>
                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-sm font-semibold text-slate-950">2. Clear strengths and risks</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">
                                    Evidence-backed strengths, weaknesses, and recommended next steps.
                                </p>
                            </article>
                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-sm font-semibold text-slate-950">3. Optional private repo layer</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">
                                    Connect GitHub from the profile page if you want authorized private data included.
                                </p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
