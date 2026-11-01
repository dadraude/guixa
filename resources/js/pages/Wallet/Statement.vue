<script setup lang="ts">
import { formatEventDateTime, formatMoney } from '@/lib/locale';
import { Form, Head, Link } from '@inertiajs/vue3';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import MemberBasketController from '@/actions/App/Http/Controllers/Member/BasketController';
import Heading from '@/components/Heading.vue';
import FormSelect from '@/components/FormSelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

const props = defineProps<{
    wallet: App.Http.WalletStatementRow;
    household: App.Http.WalletHouseholdRow | null;
    canManageReceipts?: boolean;
    householdReturnHref?: string;
    receiptAction?: string;
    correctionAction?: string;
    correctionId?: string;
    status?: string | null;
}>();

const receipts = computed(() =>
    props.wallet.movements.filter((movement) => movement.type === 'top_up'),
);
const receiptOptions = computed(() =>
    receipts.value.map((movement) => ({
        value: movement.id,
        label: trans(':amount · :date · :note', {
            amount: formatMoney(movement.amount),
            date: formatEventDateTime(movement.occurredAt),
            note: movement.note || trans('No note'),
        }),
    })),
);
const originalReceipt = (id: string) =>
    receipts.value.find((movement) => movement.id === id);

const typeLabels: Record<string, string> = {
    top_up: trans('Top-up'),
    charge: trans('Charge'),
    credit: trans('Credit'),
    correction: trans('Correction'),
};
</script>

<template>
    <Head :title="trans('Wallet')" />

    <div class="pilot-layout-page pilot-t44-page">
        <Heading
            :title="
                household
                    ? trans(':name wallet', { name: household.name })
                    : trans('Wallet')
            "
            :description="
                trans('Balance: :amount', {
                    amount: formatMoney(wallet.balance),
                })
            "
        />

        <p v-if="status" role="status" class="pilot-layout-notice">
            {{ status }}
        </p>

        <div v-if="household && canManageReceipts" class="pilot-wallet-forms">
            <div
                class="pilot-wallet-form-grid grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4"
            >
                <section class="pilot-layout-panel">
                    <h2 class="pilot-heading text-lg font-medium">
                        {{ trans('Record a top-up') }}
                    </h2>
                    <Form
                        :action="receiptAction"
                        method="post"
                        :reset-on-success="['amount', 'note']"
                        class="pilot-layout-form flex flex-col"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="top-up-amount">{{
                                trans('Amount (EUR)')
                            }}</Label>
                            <Input
                                id="top-up-amount"
                                name="amount"
                                inputmode="decimal"
                                required
                            />
                            <InputError
                                :message="errors.amount || errors.domain"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="top-up-note">{{
                                trans('Note (optional)')
                            }}</Label>
                            <Input id="top-up-note" name="note" />
                            <InputError :message="errors.note" />
                        </div>
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="record-top-up"
                        >
                            {{ trans('Record top-up') }}
                        </Button>
                    </Form>
                </section>

                <section class="pilot-layout-panel">
                    <h2 class="pilot-heading text-lg font-medium">
                        {{ trans('Record a correction') }}
                    </h2>
                    <Form
                        :action="correctionAction"
                        method="post"
                        :reset-on-success="[
                            'amount',
                            'note',
                            'source_movement_id',
                        ]"
                        class="pilot-layout-form flex flex-col"
                        v-slot="{ errors, processing }"
                    >
                        <input
                            type="hidden"
                            name="correction_id"
                            :value="correctionId"
                        />
                        <div class="grid gap-2">
                            <Label for="correction-source">{{
                                trans('Original receipt')
                            }}</Label>
                            <FormSelect
                                id="correction-source"
                                name="source_movement_id"
                                :options="receiptOptions"
                                content-class="wallet-receipt-menu"
                                :placeholder="trans('Choose a receipt')"
                                :disabled="receipts.length === 0"
                                required
                            />
                            <InputError :message="errors.source_movement_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="correction-amount">
                                {{
                                    trans('Amount (EUR, negative to subtract)')
                                }}
                            </Label>
                            <Input
                                id="correction-amount"
                                name="amount"
                                inputmode="decimal"
                                required
                            />
                            <InputError
                                :message="errors.amount || errors.domain"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="correction-note">{{
                                trans('Note')
                            }}</Label>
                            <Input id="correction-note" name="note" required />
                            <InputError :message="errors.note" />
                        </div>
                        <Button
                            type="submit"
                            variant="secondary"
                            :disabled="processing || receipts.length === 0"
                            data-test="record-correction"
                        >
                            {{ trans('Record correction') }}
                        </Button>
                    </Form>
                </section>
            </div>
        </div>

        <section class="pilot-layout-panel">
            <h2 class="pilot-heading text-lg font-medium">
                {{ trans('Movements') }}
            </h2>

            <p
                v-if="wallet.movements.length === 0"
                class="pilot-layout-notice text-sm text-muted-foreground"
            >
                {{ trans('No movements yet.') }}
            </p>

            <div v-else class="pilot-wallet-movements">
                <table
                    class="pilot-data-table pilot-wallet-table w-full text-sm"
                >
                    <thead>
                        <tr class="border-b text-left">
                            <th scope="col" class="py-2">
                                {{ trans('Date') }}
                            </th>
                            <th scope="col" class="py-2">
                                {{ trans('Type') }}
                            </th>
                            <th scope="col" class="py-2">
                                {{ trans('Note') }}
                            </th>
                            <th scope="col" class="py-2">
                                {{ trans('Source') }}
                            </th>
                            <th scope="col" class="py-2 text-right">
                                {{ trans('Amount') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="movement in wallet.movements"
                            :key="movement.id"
                            class="border-b"
                        >
                            <td class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Date') }}</span
                                >
                                {{ formatEventDateTime(movement.occurredAt) }}
                                <small
                                    v-if="movement.recordedBy"
                                    class="block break-all text-muted-foreground"
                                >
                                    {{
                                        trans('Recorded by :id', {
                                            id: movement.recordedBy,
                                        })
                                    }}
                                </small>
                            </td>
                            <td class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Type') }}</span
                                >
                                {{ typeLabels[movement.type] ?? movement.type }}
                            </td>
                            <td class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Note') }}</span
                                >{{ movement.note }}
                            </td>
                            <td class="py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Source') }}</span
                                >
                                <Link
                                    v-if="movement.basketId"
                                    :href="
                                        household
                                            ? BasketController.show.url({
                                                  basket: movement.basketId,
                                              })
                                            : MemberBasketController.show.url({
                                                  basket: movement.basketId,
                                              })
                                    "
                                    class="inline-flex min-h-11 max-w-full items-center break-all underline"
                                    data-test="basket-source-link"
                                >
                                    {{
                                        trans('Basket: :name', {
                                            name: movement.basketId,
                                        })
                                    }}
                                </Link>
                                <span
                                    v-else-if="movement.sourceMovementId"
                                    data-test="receipt-correction-source"
                                    class="break-words"
                                >
                                    {{
                                        originalReceipt(
                                            movement.sourceMovementId,
                                        )
                                            ? trans(
                                                  'Correction of receipt :amount',
                                                  {
                                                      amount: formatMoney(
                                                          originalReceipt(
                                                              movement.sourceMovementId,
                                                          )!.amount,
                                                      ),
                                                  },
                                              )
                                            : trans(
                                                  'Correction of receipt :id',
                                                  {
                                                      id: movement.sourceMovementId,
                                                  },
                                              )
                                    }}
                                </span>
                                <span v-else-if="movement.cycleId">{{
                                    trans('Cycle')
                                }}</span>
                            </td>
                            <td class="pilot-amount py-2">
                                <span
                                    class="pilot-cell-label"
                                    aria-hidden="true"
                                    >{{ trans('Amount') }}</span
                                >
                                {{ formatMoney(movement.amount) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Link
            v-if="household && householdReturnHref"
            :href="householdReturnHref"
            class="pilot-back-link text-sm underline-offset-4 hover:underline"
        >
            {{ trans('Back to :household', { household: household.name }) }}
        </Link>
    </div>
</template>

<style scoped>
.pilot-t44-page :deep(.pilot-heading) {
    overflow-wrap: anywhere;
}

:global(.wallet-receipt-menu) {
    max-width: calc(100vw - 1.25rem);
}

:global(.wallet-receipt-menu [data-slot='select-item']),
:global(.wallet-receipt-menu [data-slot='select-item'] > span:last-child) {
    min-width: 0;
    overflow-wrap: anywhere;
}
</style>
