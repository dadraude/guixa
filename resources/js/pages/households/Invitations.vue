<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PendingInvitationController from '@/actions/App/Http/Controllers/Households/PendingInvitationController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { index as householdsIndex } from '@/routes/households';
import { index } from '@/routes/households/invitations';

type InvitationRow = {
    id: string;
    email: string;
    name: string | null;
    household: string;
    sent_at: string | null;
    expires_at: string;
    is_expired: boolean;
};

defineProps<{
    invitations: InvitationRow[];
    status?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('Households'), href: householdsIndex() },
            { title: t('Invitations'), href: index() },
        ],
    },
});

const formatDate = (value: string | null): string =>
    value ? new Date(value).toLocaleDateString('ca-ES') : '';
</script>

<template>
    <Head :title="t('Pending invitations')" />

    <div class="flex h-full flex-1 flex-col gap-8 p-4">
        <Heading
            :title="t('Pending invitations')"
            :description="
                t('Invitations that were sent and have not been accepted yet.')
            "
        />

        <p
            v-if="status"
            class="text-sm font-medium text-green-600"
            data-test="status"
        >
            {{ status }}
        </p>

        <p v-if="invitations.length === 0" class="text-muted-foreground">
            {{ t('No pending invitations.') }}
        </p>

        <ul v-else class="divide-y rounded-lg border" data-test="invitations">
            <li
                v-for="invitation in invitations"
                :key="invitation.id"
                class="flex flex-wrap items-center justify-between gap-3 p-4"
            >
                <div class="flex flex-col">
                    <span class="font-medium">{{ invitation.email }}</span>
                    <span class="text-sm text-muted-foreground">
                        {{ t('Household') }}: {{ invitation.household }} ·
                        {{ t('Sent') }}: {{ formatDate(invitation.sent_at) }} ·
                        {{ t('Expires') }}:
                        {{ formatDate(invitation.expires_at) }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <Badge v-if="invitation.is_expired" variant="outline">{{
                        t('Expired')
                    }}</Badge>

                    <Form
                        v-bind="
                            PendingInvitationController.resend.form(
                                invitation.id,
                            )
                        "
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                            data-test="resend-button"
                            >{{ t('Resend') }}</Button
                        >
                    </Form>

                    <Form
                        v-bind="
                            PendingInvitationController.cancel.form(
                                invitation.id,
                            )
                        "
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                            data-test="cancel-button"
                            >{{ t('Cancel invitation') }}</Button
                        >
                    </Form>
                </div>
            </li>
        </ul>
    </div>
</template>
