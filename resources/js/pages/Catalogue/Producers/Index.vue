<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import ProducerController from '@/actions/App/Http/Controllers/Catalogue/ProducerController';
import ProducerProductController from '@/actions/App/Http/Controllers/Catalogue/ProducerProductController';
import Heading from '@/components/Heading.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Producers', href: ProducerController.index() }],
    },
});

defineProps<{
    producers: App.Http.ProducerRow[];
}>();
</script>

<template>
    <Head :title="trans('Producers')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="trans('Producers')"
            :description="trans('Choose a producer to administer its products')"
        />

        <p v-if="producers.length === 0" class="text-sm text-muted-foreground">
            {{ trans('No producers yet.') }}
        </p>

        <ul v-else class="pilot-list">
            <li
                v-for="producer in producers"
                :key="producer.id"
                class="flex flex-wrap items-center justify-between gap-4"
                :class="{ 'text-muted-foreground': !producer.active }"
            >
                <span class="flex min-w-0 flex-wrap items-center gap-4">
                    <span class="font-medium break-words">{{
                        producer.name
                    }}</span>
                    <StatusIndicator
                        :label="
                            producer.active
                                ? trans('Active')
                                : trans('Deactivated')
                        "
                        :tone="producer.active ? 'success' : 'danger'"
                    />
                </span>
                <Button
                    as-child
                    variant="outline"
                    class="h-auto min-h-11 max-w-full min-w-0 py-2 text-center break-words whitespace-normal"
                >
                    <Link
                        :href="ProducerProductController.index(producer.id)"
                        :aria-label="
                            trans('View products of :name', {
                                name: producer.name,
                            })
                        "
                    >
                        {{ trans('View products') }}
                    </Link>
                </Button>
            </li>
        </ul>
    </div>
</template>
