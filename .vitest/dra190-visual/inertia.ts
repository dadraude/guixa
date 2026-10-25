import { defineComponent, h, reactive } from 'vue';

export const page = reactive({
    component: 'Orders/Index',
    url: '/cycles',
    props: {
        sidebarOpen: true,
        errors: {} as Record<string, string>,
        roleAreas: [{ role: 'member', label: 'Member', href: '/cycles' }],
        auth: {
            user: {
                id: 1,
                name: 'Maria Riera',
                email: 'maria@example.test',
                email_verified_at: null,
            },
        },
    },
});

export const usePage = () => page;
export const Head = defineComponent({ setup: () => () => null });
export const Link = defineComponent({
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
        return () =>
            h(
                'a',
                {
                    ...attrs,
                    href: typeof attrs.href === 'string' ? attrs.href : '#',
                },
                slots.default?.(),
            );
    },
});
export const router = {
    put(url: string, data: unknown, options: Record<string, () => void>) {
        window.dispatchEvent(
            new CustomEvent('fixture-request', {
                detail: { method: 'PUT', url, data },
            }),
        );
        options.onStart?.();
        if (new URLSearchParams(location.search).get('hold') !== '1')
            options.onFinish?.();
    },
    delete(url: string, options: Record<string, () => void>) {
        window.dispatchEvent(
            new CustomEvent('fixture-request', {
                detail: { method: 'DELETE', url },
            }),
        );
        options.onStart?.();
        options.onFinish?.();
    },
    flushAll() {},
};
