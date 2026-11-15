<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { ref, useId } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    holder: App.Http.Controllers.Manage.RoleHolderRow;
    role: Guixa.Shared.Domain.Role;
    roleLabel: string;
    processing: boolean;
}>();

const emit = defineEmits<{ remove: [accountId: string] }>();

const open = ref(false);
const reasonId = useId();

// Removing your own Manager role ends your access to this screen, so it asks for a stronger warning (S3).
const removesOwnAccess = () => props.holder.isSelf && props.role === 'manager';

function confirm() {
    open.value = false;
    emit('remove', props.holder.accountId);
}
</script>

<template>
    <Button
        type="button"
        variant="outline"
        size="sm"
        class="min-h-11"
        :disabled="processing || !holder.canRemove"
        :aria-describedby="holder.removeBlockedReason ? reasonId : undefined"
        :aria-label="
            trans('Remove :role from :name', {
                role: roleLabel,
                name: holder.name,
            })
        "
        @click="open = true"
    >
        {{ trans('Remove role') }}
    </Button>
    <p
        v-if="holder.removeBlockedReason"
        :id="reasonId"
        class="basis-full text-sm text-muted-foreground"
    >
        {{ holder.removeBlockedReason }}
    </p>

    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="false"
            class="grid-cols-[minmax(0,1fr)]"
        >
            <DialogHeader class="space-y-3">
                <DialogTitle>
                    {{ trans('Remove the :role role?', { role: roleLabel }) }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        removesOwnAccess()
                            ? trans(
                                  'You are removing your own Manager role. You will lose access to the management screens right away.',
                              )
                            : trans(':name will lose the :role role.', {
                                  name: holder.name,
                                  role: roleLabel,
                              })
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-auto min-h-11 whitespace-normal"
                    >
                        {{ trans('Cancel') }}
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="destructive"
                    class="h-auto min-h-11 whitespace-normal"
                    @click="confirm"
                >
                    {{ trans('Remove role') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
