<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import HouseholdMemberController from '@/actions/App/Http/Controllers/Manage/HouseholdMemberController';
import HouseholdWalletController from '@/actions/App/Http/Controllers/Manage/HouseholdWalletController';
import InvitationController from '@/actions/App/Http/Controllers/Manage/InvitationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    household: App.Http.HouseholdRow;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Households', href: HouseholdController.index() },
        ],
    },
});

const expiry = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString() : '';
</script>

<template>
    <Head :title="household.name" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            :title="household.name"
            :description="
                household.active
                    ? 'Active household'
                    : 'Inactive household: adding a member reactivates it'
            "
        />

        <Form
            v-bind="HouseholdController.update.form(household.id)"
            class="flex items-end gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="rename">Name</Label>
                <Input
                    id="rename"
                    name="name"
                    :default-value="household.name"
                    required
                />
                <InputError :message="errors.name || errors.domain" />
            </div>
            <Button type="submit" variant="secondary" :disabled="processing">
                Rename
            </Button>
        </Form>

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-medium">Members</h2>

            <p
                v-if="household.members.length === 0"
                class="text-sm text-muted-foreground"
            >
                This household has no members.
            </p>

            <ul v-else class="divide-y rounded-lg border">
                <li
                    v-for="member in props.household.members"
                    :key="member.accountId"
                    class="flex flex-col gap-3 p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span>
                            <span class="font-medium">{{ member.name }}</span>
                            <span class="ml-2 text-sm text-muted-foreground">
                                {{ member.email }}
                            </span>
                        </span>
                        <Badge
                            :variant="
                                member.invitationPending
                                    ? 'secondary'
                                    : 'default'
                            "
                        >
                            {{
                                member.invitationPending
                                    ? 'Invitation pending'
                                    : 'Active'
                            }}
                        </Badge>
                    </div>

                    <div
                        v-if="member.invitationPending"
                        class="flex flex-wrap items-end gap-3"
                    >
                        <p class="text-sm text-muted-foreground">
                            <template v-if="member.invitationExpired">
                                Link expired on
                                {{ expiry(member.invitationExpiresAt) }}; resend
                                it
                            </template>
                            <template v-else>
                                Link valid until
                                {{ expiry(member.invitationExpiresAt) }}
                            </template>
                        </p>

                        <Form
                            v-bind="
                                InvitationController.resend.form(
                                    member.accountId,
                                )
                            "
                            v-slot="{ errors, processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :disabled="processing"
                            >
                                Resend invitation
                            </Button>
                            <InputError :message="errors.domain" />
                        </Form>

                        <Form
                            v-bind="
                                InvitationController.changeEmail.form(
                                    member.accountId,
                                )
                            "
                            class="flex items-end gap-2"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-1">
                                <Input
                                    name="email"
                                    type="email"
                                    required
                                    placeholder="New email"
                                    class="h-8"
                                />
                                <InputError
                                    :message="errors.email || errors.domain"
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :disabled="processing"
                            >
                                Change email
                            </Button>
                        </Form>
                    </div>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-medium">Add a member</h2>

            <Form
                v-bind="HouseholdMemberController.store.form(household.id)"
                :reset-on-success="['name', 'email']"
                class="flex flex-wrap items-end gap-2"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="member-name">Name</Label>
                    <Input id="member-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="member-email">Email</Label>
                    <Input
                        id="member-email"
                        name="email"
                        type="email"
                        required
                    />
                    <InputError :message="errors.email || errors.domain" />
                </div>
                <Button type="submit" :disabled="processing">
                    Send invitation
                </Button>
            </Form>
        </section>

        <Link
            :href="HouseholdWalletController.show(household.id)"
            class="text-sm font-medium underline-offset-4 hover:underline"
        >
            Wallet: balance, top-ups and corrections
        </Link>

        <Link
            :href="HouseholdController.index()"
            class="text-sm text-muted-foreground underline-offset-4 hover:underline"
        >
            Back to households
        </Link>
    </div>
</template>
