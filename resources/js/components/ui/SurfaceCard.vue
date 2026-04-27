<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    bodyClass: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="dashboard-surface flex h-full flex-col p-4 sm:p-5">
        <div v-if="title || description || $slots.actions" class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h3 v-if="title" class="text-base font-semibold tracking-tight text-slate-950">
                    {{ title }}
                </h3>
                <p v-if="description" class="mt-1 text-sm text-slate-500">
                    {{ description }}
                </p>
            </div>
            <div v-if="$slots.actions" class="shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div class="flex-1" :class="[title || description || $slots.actions ? 'mt-4' : '', bodyClass]">
            <slot />
        </div>
    </section>
</template>
