<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { euros } from '@/components/pickup/euros';
import { formatEventDateTime } from '@/lib/locale';

defineProps<{ baskets: App.Http.BasketSummaryRow[]; pending: boolean }>();
const statusLabels: Record<string, string> = {
    draft: trans('Draft'),
    reversed: trans('Goods fully reversed'),
    validated: trans('Pending collection'),
    collected: trans('Collected'),
    cancelled: trans('Cancelled'),
};
</script>

<template>
    <Head :title="pending ? trans('Pending collections') : trans('Baskets')" />
    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="pending ? trans('Pending collections') : trans('Baskets')"
            :description="
                trans('Record independent stock purchases and collection.')
            "
        />
        <Button
            v-if="pending"
            as-child
            variant="outline"
            class="min-h-11 self-start"
            ><Link
                :href="BasketController.index.url()"
                data-test="all-baskets"
                >{{ trans('All baskets') }}</Link
            ></Button
        >
        <Link
            :href="BasketController.create.url()"
            data-visual-page-focus-first
        >
            <Button type="button" class="min-h-11">{{
                trans('Create basket')
            }}</Button>
        </Link>
        <p
            v-if="baskets.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
            data-test="empty-baskets"
        >
            {{
                pending
                    ? trans('No baskets are pending collection.')
                    : trans('No baskets yet.')
            }}
        </p>
        <ul v-else class="pilot-list" data-test="basket-list">
            <li
                v-for="basket in baskets"
                :key="basket.id"
                class="pilot-list-row flex-wrap gap-2"
            >
                <div class="pilot-list-row-body min-w-0">
                    <Link
                        :href="BasketController.show.url({ basket: basket.id })"
                        class="break-all underline"
                        data-visual-page-focus-last
                    >
                        {{ basket.householdName }}
                    </Link>
                    <span
                        class="text-xs text-muted-foreground"
                        :data-test="pending ? 'validated-at' : undefined"
                        >{{
                            formatEventDateTime(
                                pending && basket.validatedAt
                                    ? basket.validatedAt
                                    : basket.createdAt,
                            )
                        }}</span
                    >
                </div>
                <Badge
                    variant="secondary"
                    :data-test="`status-${basket.status}`"
                    >{{ statusLabels[basket.status] ?? basket.status }}</Badge
                >
                <span class="pilot-amount">{{ euros(basket.totalCents) }}</span>
            </li>
        </ul>
    </div>
</template>
