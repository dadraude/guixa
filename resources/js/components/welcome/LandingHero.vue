<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Button } from '@/components/ui/button';
</script>

<template>
    <section class="welcome-hero" aria-labelledby="welcome-title">
        <div class="welcome-hero-copy">
            <p class="welcome-eyebrow">
                {{ trans('Agroecological consumer cooperative · Manresa') }}
            </p>
            <h1 id="welcome-title">
                {{ trans('What we eat can transform our world.') }}
            </h1>
            <p class="welcome-intro">
                {{
                    trans(
                        'We are La Guixa. We organise to consume consciously and build community.',
                    )
                }}
            </p>
            <div class="welcome-hero-actions">
                <Button as-child size="lg" class="welcome-primary-action">
                    <a href="#contact">{{ trans('Come and meet us') }}</a>
                </Button>
                <a class="welcome-text-action" href="#how-it-works">
                    {{ trans('How it works') }}
                </a>
            </div>
        </div>
        <div class="welcome-hero-art" aria-hidden="true">
            <svg
                viewBox="0 0 220 180"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <g
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M49 99c-3-35 15-75 53-79 35-4 59 25 66 77M59 97c-2-33 14-64 45-68 29-3 49 20 54 68"
                    />
                    <path
                        d="M45 99c-12-8-22-5-24 5-2 7 5 12 19 15l9 29c3 13 15 19 35 22 22 3 50 3 73-1 18-3 29-10 33-25l8-25c14-3 21-8 19-15-2-9-12-12-24-5-43 16-105 17-148 0Z"
                    />
                    <path
                        d="M36 112c47 17 119 18 168 0M52 128c42 13 104 13 142 0M58 143c38 11 92 11 130 0M75 156c34 7 70 7 100 0"
                    />
                    <path
                        d="M57 121l4 3m16 1 6 2m17 1 6 1m18 0 6-1m17-1 6-2m18-1 5-3M65 137l6 2m20 2 7 2m20 0 7-1m19-1 7-2m19-3 6-2M78 151l7 2m19 1 7 1m18 0 7-1m19-1 7-2"
                    />
                    <path
                        d="M35 101c-9-13-14-25-7-28 5-2 10 4 13 11-5-14-3-22 4-22 6 1 8 9 8 17 0-12 6-17 11-12 5 5 3 14-2 22M40 95c-17-12-24-10-24-4 1 6 12 8 24 10M76 99c-11-20-11-32-5-35 6-3 11 6 13 16-1-18 2-27 9-27 7 0 9 10 7 24 5-14 11-19 17-14 5 5 0 14-7 23M82 96c-14-12-21-10-21-4 0 5 9 8 21 9"
                    />
                    <path
                        d="M111 97c-1-18 3-28 11-28 6 0 8 7 7 16 5-12 11-16 16-11 5 5 0 13-7 21M143 99l27-45c4-6 12-7 17-3 5 3 6 10 2 16l-19 34M165 61l18 11m-23-2 18 10m-23-1 18 10M177 50c0-10 4-15 11-15m-4 18c5-8 12-10 17-7M187 83c7-11 13-12 17-10"
                    />
                </g>
            </svg>
        </div>
    </section>
</template>

<style scoped>
.welcome-hero {
    display: grid;
    align-items: center;
    gap: 1.5rem;
    padding-block: clamp(3.5rem, 8vw, 6rem);
}

.welcome-hero-copy {
    min-width: 0;
}

.welcome-eyebrow {
    margin: 0 0 0.75rem;
    color: var(--welcome-hero-cream);
    font-size: 0.8125rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    line-height: 1.5;
    text-transform: uppercase;
    overflow-wrap: anywhere;
}

.welcome-hero h1 {
    max-width: 16ch;
    margin: 0;
    font-size: clamp(2.5rem, 6.5vw, 4.25rem);
    font-weight: 900;
    letter-spacing: -0.045em;
    line-height: 1.08;
    text-wrap: balance;
    overflow-wrap: anywhere;
}

.welcome-intro {
    max-width: 38rem;
    margin: 1rem 0 0;
    font-size: clamp(1rem, 2vw, 1.1875rem);
    line-height: 1.6;
}

.welcome-hero-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 1.5rem;
    margin-top: 1.5rem;
}

.welcome-primary-action {
    min-height: 2.75rem;
    height: auto;
    border: 0;
    border-radius: 0.4rem;
    background: var(--welcome-hero-cream);
    padding-inline: 1.25rem;
    color: #173e53;
    font-weight: 750;
    text-decoration: none;
    white-space: normal;
}

.welcome-primary-action:hover {
    background: #fff9eb;
}

.welcome-text-action {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    color: var(--welcome-hero-cream);
    font-weight: 700;
    text-underline-offset: 0.25em;
}

.welcome-text-action:hover {
    color: #fff9eb;
}

.welcome-hero-art {
    display: none;
    justify-self: end;
    color: var(--welcome-hero-cream);
}

.welcome-hero-art svg {
    display: block;
    width: min(100%, 20rem);
    height: auto;
}

.welcome-hero :is(a:focus-visible) {
    outline: 3px solid var(--welcome-hero-cream);
    outline-offset: 3px;
}

@container (min-width: 48rem) {
    .welcome-hero {
        grid-template-columns: minmax(0, 1.35fr) minmax(12rem, 0.65fr);
        gap: 2rem;
    }

    .welcome-hero-art {
        display: block;
        padding: 1rem;
    }
}

@media (max-width: 47.999rem) {
    .welcome-hero-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .welcome-text-action {
        align-self: start;
    }
}
</style>
