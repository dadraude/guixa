<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import DeliveredForm from '@/components/pickup/DeliveredForm.vue';
import { euros } from '@/components/pickup/euros';
import { Badge } from '@/components/ui/badge';
import { formatQuantity } from '@/lib/locale';

defineProps<{
    lines: App.Http.BasketWeeklyLineRow[];
    /** Staff can change the actual quantity of a draft; members only read it. */
    editable: boolean;
    processing: boolean;
}>();

const emit = defineEmits<{
    save: [productId: string, quantity: string];
}>();
</script>

<template>
    <section class="flex flex-col gap-4" data-test="weekly-lines">
        <h3 class="text-base font-medium">{{ trans('Weekly order') }}</h3>
        <p
            v-if="lines.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-weekly-lines"
        >
            {{ trans('There is nothing to collect from the weekly order.') }}
        </p>
        <ul v-else class="pilot-list">
            <li
                v-for="line in lines"
                :key="line.productId"
                class="pilot-list-row"
                :data-test="`weekly-line-${line.productId}`"
            >
                <div class="pilot-list-row-body">
                    <span class="font-medium wrap-anywhere">{{
                        line.name
                    }}</span>
                    <span class="text-sm text-muted-foreground">
                        {{
                            trans('Ordered :ordered, allocated :allocated', {
                                ordered: formatQuantity(line.ordered),
                                allocated: formatQuantity(line.allocated),
                            })
                        }}
                        {{ line.unit }} · {{ euros(line.unitPriceCents) }}
                    </span>
                    <Badge
                        v-if="line.differsFromAllocation"
                        variant="outline"
                        :data-test="`weekly-difference-${line.productId}`"
                    >
                        {{ trans('Differs from the allocation') }}
                    </Badge>
                    <span
                        v-if="line.effectiveQuantity !== line.quantity"
                        class="text-sm"
                        :data-test="`weekly-effective-${line.productId}`"
                    >
                        {{
                            trans('Effective after corrections: :quantity', {
                                quantity: formatQuantity(
                                    line.effectiveQuantity,
                                ),
                            })
                        }}
                        {{ line.unit }}
                    </span>
                </div>
                <DeliveredForm
                    v-if="editable"
                    :line="{
                        productId: line.productId,
                        name: line.name,
                        quantity: line.quantity,
                    }"
                    :processing="processing"
                    @save="
                        (productId, quantity) =>
                            emit('save', productId, quantity)
                    "
                />
                <span v-else class="pilot-list-figures">
                    <span data-test="weekly-actual"
                        >{{ formatQuantity(line.quantity) }}
                        {{ line.unit }}</span
                    >
                    <span class="pilot-amount" data-test="weekly-amount">{{
                        euros(line.amountCents)
                    }}</span>
                </span>
            </li>
        </ul>
    </section>
</template>
