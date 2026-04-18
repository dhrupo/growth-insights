import { createRouter, createWebHistory } from 'vue-router';

import AppLayout from '@/layouts/AppLayout.vue';
import { sectionRoutes } from '@/navigation';

const routes = [
    {
        path: '/',
        component: AppLayout,
        children: [
            {
                path: '',
                redirect: { name: 'dashboard-simple' },
            },
            {
                path: 'dashboard',
                name: 'dashboard-simple',
                component: () => import('@/pages/DashboardPage.vue'),
                meta: {
                    title: 'Dashboard',
                    description: 'Core KPIs, trend lines, and a fast operational read.',
                    mode: 'simple',
                },
            },
            {
                path: 'dashboard/advanced',
                name: 'dashboard-advanced',
                component: () => import('@/pages/DashboardPage.vue'),
                meta: {
                    title: 'Dashboard',
                    description: 'Expanded filters, secondary charts, and diagnostics.',
                    mode: 'advanced',
                },
            },
            {
                path: 'analytics',
                name: 'analytics',
                component: () => import('@/pages/AnalysisWorkbenchPage.vue'),
                meta: {
                    title: 'Analysis Workbench',
                    description: 'Run public and private GitHub analysis and inspect the deeper signal breakdown.',
                },
            },
            {
                path: 'reports',
                name: 'reports',
                component: () => import('@/pages/SectionPage.vue'),
                props: sectionRoutes.reports,
                meta: {
                    title: 'Reports',
                    description: sectionRoutes.reports.description,
                },
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('@/pages/SectionPage.vue'),
                props: sectionRoutes.settings,
                meta: {
                    title: 'Settings',
                    description: sectionRoutes.settings.description,
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
