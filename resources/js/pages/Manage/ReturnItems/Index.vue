<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ReturnItemController from '@/actions/App/Http/Controllers/Manage/ReturnItemController';
import Heading from '@/components/Heading.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import { Button } from '@/components/ui/button';
import { trans } from 'laravel-vue-i18n';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Return items', href: ReturnItemController.index() },
        ],
    },
});

defineProps<{
    items: App.Http.ReturnItemRow[];
}>();
</script>

<template>
    <Head title="Return items" />

    <div class="pilot-page pilot-manager-page">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Return items"
                description="Containers households bring back and get credited for"
            />
            <Button as-child>
                <Link :href="ReturnItemController.create()"
                    >New return item</Link
                >
            </Button>
        </div>

        <p
            v-if="items.length === 0"
            class="pilot-empty-state text-sm text-muted-foreground"
        >
            No return items yet.
        </p>

        <div v-else class="pilot-manager-list">
            <table class="pilot-data-table pilot-manager-table w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th scope="col" class="py-2">{{ trans('Name') }}</th>
                        <th scope="col" class="py-2">{{ trans('Amount') }}</th>
                        <th scope="col" class="py-2">{{ trans('Status') }}</th>
                        <th scope="col" class="py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b"
                        :class="{ 'text-muted-foreground': !item.active }"
                    >
                        <td class="py-2 font-medium">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Name')
                            }}</span
                            >{{ item.name }}
                        </td>
                        <td class="py-2 tabular-nums">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Amount')
                            }}</span
                            >{{ item.amount }} EUR
                        </td>
                        <td class="py-2">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Status')
                            }}</span>
                            <StatusIndicator
                                :label="item.active ? 'Active' : 'Deactivated'"
                                :tone="item.active ? 'success' : 'danger'"
                            />
                        </td>
                        <td class="py-2">
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="
                                            ReturnItemController.edit(item.id)
                                        "
                                        >Edit</Link
                                    >
                                </Button>
                                <Form
                                    v-if="item.active"
                                    v-bind="
                                        ReturnItemController.destroy.form(
                                            item.id,
                                        )
                                    "
                                >
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        :data-test="`deactivate-${item.id}`"
                                    >
                                        Deactivate
                                    </Button>
                                </Form>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
