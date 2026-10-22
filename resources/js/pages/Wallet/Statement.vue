<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import HouseholdWalletController from '@/actions/App/Http/Controllers/Manage/HouseholdWalletController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    wallet: App.Http.WalletStatementRow;
    household: App.Http.WalletHouseholdRow | null;
}>();

const typeLabels: Record<string, string> = {
    top_up: 'Top-up',
    charge: 'Charge',
    credit: 'Credit',
    correction: 'Correction',
};
</script>

<template>
    <Head title="Wallet" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            :title="household ? `${household.name} wallet` : 'Wallet'"
            :description="`Balance: ${wallet.balance} EUR`"
        />

        <div v-if="household" class="grid gap-8 md:grid-cols-2">
            <section class="flex flex-col gap-3">
                <h2 class="text-lg font-medium">Record a top-up</h2>
                <Form
                    v-bind="HouseholdWalletController.topUp.form(household.id)"
                    :reset-on-success="['amount', 'note']"
                    class="flex flex-col gap-3"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="top-up-amount">Amount (EUR)</Label>
                        <Input
                            id="top-up-amount"
                            name="amount"
                            inputmode="decimal"
                            required
                        />
                        <InputError :message="errors.amount || errors.domain" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="top-up-note">Note (optional)</Label>
                        <Input id="top-up-note" name="note" />
                        <InputError :message="errors.note" />
                    </div>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="record-top-up"
                    >
                        Record top-up
                    </Button>
                </Form>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-lg font-medium">Record a correction</h2>
                <Form
                    v-bind="
                        HouseholdWalletController.correct.form(household.id)
                    "
                    :reset-on-success="['amount', 'note']"
                    class="flex flex-col gap-3"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="correction-amount">
                            Amount (EUR, negative to subtract)
                        </Label>
                        <Input
                            id="correction-amount"
                            name="amount"
                            inputmode="decimal"
                            required
                        />
                        <InputError :message="errors.amount || errors.domain" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="correction-note">Note</Label>
                        <Input id="correction-note" name="note" required />
                        <InputError :message="errors.note" />
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        :disabled="processing"
                        data-test="record-correction"
                    >
                        Record correction
                    </Button>
                </Form>
            </section>
        </div>

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-medium">Movements</h2>

            <p
                v-if="wallet.movements.length === 0"
                class="text-sm text-muted-foreground"
            >
                No movements yet.
            </p>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th class="py-2">Date</th>
                        <th class="py-2">Type</th>
                        <th class="py-2">Note</th>
                        <th class="py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="movement in wallet.movements"
                        :key="movement.id"
                        class="border-b"
                    >
                        <td class="py-2">
                            {{ movement.occurredAt }}
                        </td>
                        <td class="py-2">
                            {{ typeLabels[movement.type] ?? movement.type }}
                        </td>
                        <td class="py-2">{{ movement.note }}</td>
                        <td class="py-2 text-right">
                            {{ movement.amount }} EUR
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <Link
            v-if="household"
            :href="HouseholdController.show(household.id)"
            class="text-sm text-muted-foreground underline-offset-4 hover:underline"
        >
            Back to {{ household.name }}
        </Link>
    </div>
</template>
