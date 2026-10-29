<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import Heading from '@/components/Heading.vue';
import StockProductFields from '@/components/StockProductFields.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Stock', href: StockProductController.index() }],
    },
});

defineProps<{ producer: App.Http.ProducerRow }>();
</script>

<template>
    <Head :title="trans('New stock product')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page max-w-xl">
        <Heading
            :title="trans('New stock product')"
            :description="
                trans('Supplying producer: :name', { name: producer.name })
            "
        />

        <Form
            v-bind="StockProductController.store.form(producer.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <StockProductFields :errors="errors" />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-stock-product"
            >
                {{ trans('Create') }}
            </Button>
        </Form>
    </div>
</template>
