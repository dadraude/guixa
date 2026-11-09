<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import ProducerController from '@/actions/App/Http/Controllers/Catalogue/ProducerController';
import ProducerProductController from '@/actions/App/Http/Controllers/Catalogue/ProducerProductController';
import WeeklyProductController from '@/actions/App/Http/Controllers/Catalogue/WeeklyProductController';
import Heading from '@/components/Heading.vue';
import WeeklyProductFields from '@/components/WeeklyProductFields.vue';

const props = defineProps<{
    producer: { id: string; name: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Producers', href: ProducerController.index() }],
    },
});
</script>

<template>
    <Head :title="trans('New product')" />

    <div class="pilot-layout-page pilot-t44-page max-w-3xl">
        <Heading
            :title="trans('New product')"
            :description="
                trans('Weekly product of :name', { name: props.producer.name })
            "
        />

        <Form
            v-bind="WeeklyProductController.store.form(props.producer.id)"
            class="pilot-layout-panel pilot-layout-form max-w-xl"
            v-slot="{ errors, processing }"
        >
            <WeeklyProductFields
                :errors="errors"
                :processing="processing"
                :submit-label="trans('Create')"
            />
        </Form>
        <Link
            :href="ProducerProductController.index(props.producer.id)"
            class="inline-flex min-h-11 items-center text-sm underline"
            >{{ trans('Back to products') }}</Link
        >
    </div>
</template>
