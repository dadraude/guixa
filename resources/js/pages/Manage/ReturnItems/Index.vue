<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import ReturnItemController from '@/actions/App/Http/Controllers/Manage/ReturnItemController';
import Heading from '@/components/Heading.vue';
import ActiveSwitch from '@/components/ActiveSwitch.vue';
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
                <Link :href="ReturnItemController.create()">
                    <Plus aria-hidden="true" />
                    New return item
                </Link>
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
                        <th scope="col" class="py-2">{{ trans('Active') }}</th>
                        <th scope="col" class="py-2">{{ trans('Name') }}</th>
                        <th scope="col" class="py-2">{{ trans('Amount') }}</th>
                        <th scope="col" class="py-2">{{ trans('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b"
                        :class="{ 'text-muted-foreground': !item.active }"
                    >
                        <td class="py-2">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Active')
                            }}</span>
                            <Form
                                v-bind="
                                    item.active
                                        ? ReturnItemController.destroy.form(
                                              item.id,
                                          )
                                        : ReturnItemController.reactivate.form(
                                              item.id,
                                          )
                                "
                                v-slot="{ processing }"
                                class="pilot-status-switch-form"
                            >
                                <ActiveSwitch
                                    :id="item.id"
                                    :active="item.active"
                                    :name="item.name"
                                    :processing="processing"
                                />
                            </Form>
                        </td>
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
                                trans('Actions')
                            }}</span>
                            <div class="pilot-table-actions">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-11"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            ReturnItemController.edit(item.id)
                                        "
                                        :aria-label="`${trans('Edit')}: ${item.name}`"
                                        :title="trans('Edit')"
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
