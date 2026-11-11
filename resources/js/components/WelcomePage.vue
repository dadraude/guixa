<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import LandingContact from '@/components/welcome/LandingContact.vue';
import LandingHeader from '@/components/welcome/LandingHeader.vue';
import LandingHero from '@/components/welcome/LandingHero.vue';
import LandingStory from '@/components/welcome/LandingStory.vue';

defineProps<{ actionHref: string }>();
</script>

<template>
    <div class="welcome-page pilot-theme min-h-svh" data-visual-root>
        <div class="welcome-shell">
            <LandingHeader :action-href="actionHref" />
        </div>
        <main>
            <div class="welcome-hero-band">
                <div class="welcome-shell">
                    <LandingHero />
                </div>
            </div>
            <div class="welcome-shell welcome-story-shell">
                <LandingStory />
            </div>
            <LandingContact />
        </main>
        <div class="welcome-shell">
            <footer class="welcome-footer">
                <a class="welcome-brand welcome-footer-brand" href="#top">
                    {{ trans('la guixa') }}
                </a>
                <span class="welcome-footer-divider" aria-hidden="true"></span>
                <p>{{ trans('Agroecological consumer cooperative') }}</p>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.welcome-page {
    --welcome-paper: #faf1dc;
    --welcome-ink: #2c2c2b;
    --welcome-blue: #286585;
    --welcome-blue-hover: #1d526f;
    --welcome-blue-soft: #e7f0f3;
    --welcome-ochre: #a05a10;
    --welcome-border: #d8d7d1;
    --welcome-focus: #a85f12;
    color: var(--welcome-ink);
    background: var(--welcome-paper);
    font-family: var(--font-body);
}

:global(.dark) .welcome-page {
    --welcome-paper: #1c201f;
    --welcome-ink: #f2f0e9;
    --welcome-blue: #8fc5df;
    --welcome-blue-hover: #b2d9e8;
    --welcome-blue-soft: #27363d;
    --welcome-ochre: #e5a650;
    --welcome-border: #4f5a5d;
    --welcome-focus: #f1bd6e;
}

.welcome-shell {
    width: min(100% - 2.5rem, 72rem);
    margin-inline: auto;
    container-type: inline-size;
}

.welcome-hero-band {
    --welcome-hero-surface: #286585;
    --welcome-hero-cream: #faf1dc;
    --welcome-focus: var(--welcome-hero-cream);
    position: relative;
    color: var(--welcome-hero-cream);
    background: var(--welcome-hero-surface);
}

.welcome-hero-band::before {
    position: absolute;
    inset: 0;
    background: var(--welcome-paper);
    opacity: 1;
    mask-image: url('../../images/welcome/hero-wear-horizontal-mask.png');
    mask-mode: luminance;
    mask-position: center;
    mask-repeat: no-repeat;
    mask-size: 100% 100%;
    pointer-events: none;
    content: '';
}

.welcome-hero-band > .welcome-shell {
    position: relative;
    z-index: 1;
}

:global(.dark) .welcome-hero-band {
    --welcome-hero-surface: #23485c;
}

.welcome-story-shell {
    padding-top: clamp(3rem, 6vw, 4.5rem);
}

.welcome-brand {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    color: var(--welcome-ink);
    font-size: 2.25rem;
    font-weight: 900;
    letter-spacing: -0.07em;
    line-height: 1;
    text-decoration: none;
}

.welcome-footer {
    display: flex;
    flex-wrap: wrap;
    min-height: 5rem;
    align-items: center;
    gap: 1.25rem;
    padding-block: 1.25rem;
}

.welcome-footer-brand {
    flex-shrink: 0;
    font-size: 1.75rem;
    white-space: nowrap;
}

.welcome-footer-divider {
    width: 1px;
    align-self: stretch;
    background: var(--welcome-border);
}

.welcome-footer p {
    margin: 0;
    color: var(--welcome-ink);
    font-size: 0.9375rem;
}

:deep(:focus-visible) {
    outline: 3px solid var(--welcome-focus);
    outline-offset: 3px;
}

@media (min-width: 48rem) {
    .welcome-shell {
        width: min(100% - 4rem, 72rem);
    }
}

@media (max-width: 25rem) {
    .welcome-shell {
        width: min(100% - 2rem, 72rem);
    }
}
</style>
