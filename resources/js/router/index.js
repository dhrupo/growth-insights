import { createRouter, createWebHistory } from 'vue-router';

import AppLayout from '@/layouts/AppLayout.vue';

const routes = [
    {
        path: '/',
        component: AppLayout,
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('@/pages/DashboardPage.vue'),
                meta: {
                    title: 'Growth Insights',
                    description: 'Connect GitHub, analyze your own profile, and keep the dashboard focused on your account.',
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
    const pageTitle = to.meta.title ? `${to.meta.title} | Growth Insights` : 'Growth Insights';
    document.title = pageTitle;
});

export default router;
