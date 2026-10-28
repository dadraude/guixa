<script lang="ts" setup>
import { trans } from 'laravel-vue-i18n';
import type { HTMLAttributes } from "vue"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <nav
    :aria-label="trans('breadcrumb')"
    data-slot="breadcrumb"
    :class="props.class"
  >
    <slot />
  </nav>
</template>
