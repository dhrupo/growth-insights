import { createRouter, createWebHistory } from 'vue-router';

import AppLayout from '@/layouts/AppLayout.vue';

const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('@/pages/HomePage.vue'),
        meta: {
            title: 'Growth Insights',
            description: 'Start with a GitHub username and open a full growth profile.',
        },
    },
    {
        path: '/dashboard',
        redirect: '/',
    },
    {
        path: '/dashboard/advanced',
        redirect: '/',
    },
    {
        path: '/:username',
        component: AppLayout,
        children: [
            {
                path: '',
                name: 'profile',
                component: () => import('@/pages/DashboardPage.vue'),
                meta: {
                    title: 'Growth Insights',
                    description: 'Public GitHub analysis with optional private enrichment.',
                },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFoundPage.vue'),
        meta: {
            title: 'Page not found',
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

router.afterEach((to) => {
    const username = typeof to.params.username === 'string' ? to.params.username : null;
    const pageTitle = username
        ? `@${username} | Growth Insights`
        : (to.meta.title ? `${to.meta.title} | Growth Insights` : 'Growth Insights');
    document.title = pageTitle;
});

export default router;
