<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: String,
        required: true,
    },
    delta: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    icon: {
        type: [Object, Function],
        default: null,
    },
    tone: {
        type: String,
        default: 'blue',
    },
});

const toneMap = {
    blue: 'bg-blue-50 text-blue-700 ring-blue-100',
    emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    amber: 'bg-amber-50 text-amber-700 ring-amber-100',
    rose: 'bg-rose-50 text-rose-700 ring-rose-100',
};

const toneClass = computed(() => toneMap[props.tone] ?? toneMap.blue);
</script>

<template>
    <article class="dashboard-surface p-5">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-medium text-slate-500">{{ title }}</p>
                <div class="mt-2 flex items-end gap-3">
                    <span class="text-2xl font-semibold tracking-tight text-slate-950">{{ value }}</span>
                    <span
                        v-if="delta"
                        class="rounded-full px-2 py-1 text-xs font-semibold ring-1"
                        :class="toneClass"
                    >
                        {{ delta }}
                    </span>
                </div>
            </div>

            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-[0_16px_32px_-16px_rgba(15,23,42,0.45)]">
                <component v-if="icon" :is="icon" class="size-5" />
                <span v-else class="h-2.5 w-2.5 rounded-full bg-white/90"></span>
            </div>
        </div>

        <p v-if="description" class="mt-4 text-sm leading-6 text-slate-500">
            {{ description }}
        </p>
    </article>
</template>
