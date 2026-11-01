<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import Heading from '@/components/Heading.vue';
import StockProductFields from '@/components/StockProductFields.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: ProductController.index() },
            { title: 'New product', href: ProductController.create() },
        ],
    },
});
</script>

<template>
    <Head :title="trans('New product')" />

    <div class="pilot-layout-page pilot-t44-page max-w-3xl">
        <Heading :title="trans('New product')" />

        <Form
            v-bind="ProductController.store.form()"
            class="pilot-layout-panel pilot-layout-form max-w-xl"
            v-slot="{ errors, processing }"
        >
            <StockProductFields :errors="errors" />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-product"
            >
                {{ trans('Create') }}
            </Button>
        </Form>
    </div>
</template>
