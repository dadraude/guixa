<script setup lang="ts">
import { formatEventDateTime, formatMoney } from '@/lib/locale';
import { Form, Head, Link } from '@inertiajs/vue3';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import HouseholdWalletController from '@/actions/App/Http/Controllers/Manage/HouseholdWalletController';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from 'laravel-vue-i18n';

defineProps<{
    wallet: App.Http.WalletStatementRow;
    household: App.Http.WalletHouseholdRow | null;
}>();

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

        <div v-if="household" class="pilot-wallet-forms">
            <div
                class="pilot-wallet-form-grid grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4"
            >
                <section class="pilot-layout-panel">
                    <h2 class="pilot-heading text-lg font-medium">
                        {{ trans('Record a top-up') }}
                    </h2>
                    <Form
                        v-bind="
                            HouseholdWalletController.topUp.form(household.id)
                        "
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
                        v-bind="
                            HouseholdWalletController.correct.form(household.id)
                        "
                        :reset-on-success="['amount', 'note']"
                        class="pilot-layout-form flex flex-col"
                        v-slot="{ errors, processing }"
                    >
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
                            :disabled="processing"
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
                                    v-if="household && movement.basketId"
                                    :href="
                                        BasketController.show.url({
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
                                    v-else-if="movement.basketId"
                                    data-test="basket-source-text"
                                    class="break-all"
                                    >{{
                                        trans('Basket: :name', {
                                            name: movement.basketId,
                                        })
                                    }}</span
                                >
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
            v-if="household"
            :href="HouseholdController.show(household.id)"
            class="pilot-back-link text-sm underline-offset-4 hover:underline"
        >
            {{ trans('Back to :household', { household: household.name }) }}
        </Link>
    </div>
</template>
