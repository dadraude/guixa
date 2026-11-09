<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Form, Head } from '@inertiajs/vue3';
import ReturnItemController from '@/actions/App/Http/Controllers/Catalogue/ReturnItemController';
import Heading from '@/components/Heading.vue';
import ReturnItemFields from '@/components/ReturnItemFields.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Return items', href: ReturnItemController.index() },
            { title: 'Edit return item', href: '#' },
        ],
    },
});

defineProps<{
    item: App.Http.ReturnItemRow;
}>();
</script>

<template>
    <Head :title="trans('Edit return item')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page max-w-xl">
        <Heading :title="trans('Edit :name', { name: item.name })" />

        <Form
            v-bind="ReturnItemController.update.form(item.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <ReturnItemFields :item="item" :errors="errors" />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-return-item"
            >
                {{ trans('Save') }}
            </Button>
        </Form>
    </div>
</template>
