<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import ReturnItemController from '@/actions/App/Http/Controllers/Manage/ReturnItemController';
import Heading from '@/components/Heading.vue';
import ReturnItemFields from '@/components/ReturnItemFields.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Return items', href: ReturnItemController.index() },
            { title: 'New return item', href: ReturnItemController.create() },
        ],
    },
});
</script>

<template>
    <Head title="New return item" />

    <div class="max-w-xl p-4">
        <Heading title="New return item" />

        <Form
            v-bind="ReturnItemController.store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <ReturnItemFields :errors="errors" />

            <Button
                type="submit"
                :disabled="processing"
                data-test="save-return-item"
            >
                Create
            </Button>
        </Form>
    </div>
</template>
