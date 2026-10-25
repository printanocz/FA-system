import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/AppLayout';
import '../css/app.css';

type PageModule = { default: ResolvedComponent };

const pages = import.meta.glob<PageModule>('./pages/**/*.tsx', { eager: true });

void createInertiaApp({
    title: (title) => (title ? `${title} · Splatno` : 'Splatno'),
    resolve: (name) => {
        const page = pages[`./pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        page.default.layout ??= (node: ReactNode) => <AppLayout>{node}</AppLayout>;
        return page;
    },
    strictMode: true,
});
