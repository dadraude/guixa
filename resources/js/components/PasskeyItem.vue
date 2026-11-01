<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { KeyRound, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Passkey } from '@/types/auth';

const props = defineProps<{
    passkey: Passkey;
}>();

const emit = defineEmits<{
    remove: [id: number, onError: () => void];
}>();

const isDeleting = ref(false);

// Radix focuses the first control without scrolling, so keep it visible in a tall dialog.
const scrollFocusIntoView = (event: FocusEvent): void =>
    (event.target as HTMLElement).scrollIntoView({ block: 'nearest' });

const handleDelete = () => {
    isDeleting.value = true;
    emit('remove', props.passkey.id, () => {
        isDeleting.value = false;
    });
};
</script>

<template>
    <div
        class="@container flex items-center justify-between gap-2 border-b p-3 last:border-b-0"
    >
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <div
                class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted @xs:flex"
            >
                <KeyRound class="h-5 w-5 text-muted-foreground" />
            </div>
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <p
                        class="font-medium tracking-tight [overflow-wrap:anywhere]"
                    >
                        {{ passkey.name }}
                    </p>
                    <span
                        v-if="passkey.authenticator"
                        class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border ring-inset"
                    >
                        {{ passkey.authenticator }}
                    </span>
                </div>
                <p
                    class="text-sm [overflow-wrap:anywhere] text-muted-foreground"
                >
                    {{
                        trans('Added :time', { time: passkey.created_at_diff })
                    }}
                    <template v-if="passkey.last_used_at_diff">
                        <span
                            class="mx-1 text-muted-foreground"
                            aria-hidden="true"
                            >/</span
                        >
                        {{
                            trans('Last used :time', {
                                time: passkey.last_used_at_diff,
                            })
                        }}
                    </template>
                </p>
            </div>
        </div>

        <Dialog>
            <DialogTrigger as-child>
                <Button
                    variant="ghost"
                    size="sm"
                    :aria-label="trans('Remove')"
                    class="min-h-11 min-w-11 shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
            </DialogTrigger>

            <DialogContent
                class="pilot-touch-dialog"
                @focusin="scrollFocusIntoView"
            >
                <DialogTitle>{{ trans('Remove passkey') }}</DialogTitle>
                <DialogDescription>
                    {{
                        trans(
                            'Are you sure you want to remove the :name passkey? You will no longer be able to use it to sign in.',
                            { name: passkey.name },
                        )
                    }}
                </DialogDescription>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary">{{
                            trans('Cancel')
                        }}</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="isDeleting"
                        @click="handleDelete"
                    >
                        {{
                            isDeleting
                                ? trans('Removing...')
                                : trans('Remove passkey')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
