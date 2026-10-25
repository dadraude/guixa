<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import Heading from '@/components/Heading.vue';
import StockProductFields from '@/components/StockProductFields.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock', href: StockProductController.index() },
            { title: 'New product', href: StockProductController.create() },
        ],
    },
});
</script>

<template>
    <Head title="New stock product" />

    <div class="pilot-page pilot-manager-page max-w-xl">
        <Heading title="New stock product" />

        <Form
            v-bind="StockProductController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <StockProductFields :errors="errors" />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-stock-product"
            >
                Create
            </Button>
        </Form>
    </div>
</template>
