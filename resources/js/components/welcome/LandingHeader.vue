<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';

defineProps<{ actionHref: string }>();
</script>

<template>
    <header id="top" class="welcome-header">
        <a class="welcome-brand" href="#top" :aria-label="trans('La Guixa')">
            {{ trans('la guixa') }}
        </a>
        <nav class="welcome-nav" :aria-label="trans('Main navigation')">
            <a href="#cooperative">{{ trans('The cooperative') }}</a>
            <a href="#how-it-works">{{ trans('How it works') }}</a>
        </nav>
        <a id="welcome-action" class="welcome-access" :href="actionHref">
            {{ trans('Member access') }}
        </a>
    </header>
</template>

<style scoped>
.welcome-header {
    display: grid;
    grid-template-areas:
        'brand'
        'nav'
        'access';
    grid-template-columns: minmax(0, 1fr);
    align-items: start;
    gap: 0.5rem;
    padding-block: 1.25rem;
}

.welcome-brand {
    grid-area: brand;
    color: var(--welcome-ink);
    font-size: 1.75rem;
    font-weight: 900;
    letter-spacing: -0.07em;
    line-height: 1;
    text-decoration: none;
}

.welcome-nav {
    grid-area: nav;
    display: flex;
    align-items: center;
    gap: clamp(0.75rem, 2.5vw, 2rem);
}

.welcome-nav a {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    color: var(--welcome-ink);
    font-size: 0.9375rem;
    font-weight: 600;
    text-decoration: none;
    text-underline-offset: 0.25em;
}

.welcome-nav a:hover {
    color: var(--welcome-blue);
    text-decoration: underline;
}

.welcome-access {
    grid-area: access;
    justify-self: start;
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--welcome-blue);
    border-radius: 0.35rem;
    padding: 0.5rem 0.8rem;
    color: var(--welcome-blue);
    font-size: 0.8125rem;
    font-weight: 700;
    line-height: 1.2;
    text-align: center;
    text-decoration: none;
}

.welcome-access:hover {
    background: var(--welcome-blue-soft);
}

.welcome-nav {
    grid-column: 1 / -1;
    justify-content: space-between;
    border-top: 1px solid var(--welcome-border);
    margin-top: 0.375rem;
}

.welcome-nav a {
    font-size: 0.875rem;
}

.welcome-header :is(a:focus-visible) {
    outline: 3px solid var(--welcome-focus);
    outline-offset: 3px;
}

@media (min-width: 48rem) {
    .welcome-header {
        display: flex;
        min-height: 5.5rem;
        gap: clamp(1.5rem, 4vw, 3.5rem);
    }

    .welcome-header > .welcome-brand {
        margin-right: auto;
    }

    .welcome-nav {
        justify-content: initial;
        border: 0;
        margin: 0;
    }

    .welcome-access {
        min-width: 10rem;
        font-size: 0.9375rem;
    }
}
</style>
