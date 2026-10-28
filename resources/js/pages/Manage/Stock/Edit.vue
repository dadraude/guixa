<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import Heading from '@/components/Heading.vue';
import StockProductFields from '@/components/StockProductFields.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock', href: StockProductController.index() },
            { title: 'Edit product', href: '#' },
        ],
    },
});

defineProps<{
    product: App.Http.StockProductRow;
}>();
</script>

<template>
    <Head :title="trans('Edit stock product')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page max-w-xl">
        <Heading :title="trans('Edit :name', { name: product.name })" />

        <Form
            v-bind="StockProductController.update.form(product.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <StockProductFields :product="product" :errors="errors" />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-stock-product"
            >
                {{ trans('Save') }}
            </Button>
        </Form>
    </div>
</template>
