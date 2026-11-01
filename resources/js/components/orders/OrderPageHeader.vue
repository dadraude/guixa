<script setup lang="ts">
import { ShoppingBasket } from '@lucide/vue';

defineProps<{ title: string; description: string }>();
</script>

<template>
    <header
        class="order-page-header flex flex-wrap items-start gap-4 border-b pb-6"
    >
        <div
            class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground"
        >
            <ShoppingBasket class="size-6" aria-hidden="true" />
        </div>
        <div class="min-w-0 flex-1 space-y-2">
            <h1
                class="pilot-heading text-2xl font-semibold tracking-tight wrap-anywhere"
            >
                {{ title }}
            </h1>
            <p class="text-base text-muted-foreground">
                {{ description }}
            </p>
        </div>
    </header>
</template>
