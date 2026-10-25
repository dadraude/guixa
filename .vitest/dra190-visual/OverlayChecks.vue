<script setup lang="ts">
import { toast } from 'vue-sonner';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const dialogInitiallyOpen =
    new URLSearchParams(location.search).get('open') === 'dialog';
</script>

<template>
    <div class="fixed top-2 right-2 z-[90] flex gap-2">
        <Dialog :default-open="dialogInitiallyOpen">
            <DialogTrigger class="min-h-11 rounded-md border bg-card px-3"
                >Dialog</DialogTrigger
            >
            <DialogContent>
                <DialogTitle>Pilot dialog</DialogTitle>
                <p>Portal palette check</p>
            </DialogContent>
        </Dialog>
        <Tooltip>
            <TooltipTrigger class="min-h-11 rounded-md border bg-card px-3"
                >Tooltip</TooltipTrigger
            >
            <TooltipContent>Pilot tooltip</TooltipContent>
        </Tooltip>
        <button
            class="min-h-11 rounded-md border bg-card px-3"
            @click="toast.success('Pilot toast')"
        >
            Toast
        </button>
    </div>
</template>
