<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import type { HTMLAttributes } from "vue"
import { Loader2Icon } from "@lucide/vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <Loader2Icon
    role="status"
    :aria-label="trans('Loading')"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
