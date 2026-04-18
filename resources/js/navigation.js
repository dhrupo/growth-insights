import {
    Document,
    Histogram,
    Monitor,
    Operation,
    Setting,
} from '@element-plus/icons-vue';

export const primaryNavigation = [
    {
        label: 'Dashboard',
        path: '/dashboard',
        icon: Monitor,
        hint: 'Core metrics and pacing',
    },
    {
        label: 'Advanced',
        path: '/dashboard/advanced',
        icon: Operation,
        hint: 'Filters and drill-downs',
    },
    {
        label: 'Analytics',
        path: '/analytics',
        icon: Histogram,
        hint: 'Trend exploration',
    },
    {
        label: 'Reports',
        path: '/reports',
        icon: Document,
        hint: 'Exports and summaries',
    },
    {
        label: 'Settings',
        path: '/settings',
        icon: Setting,
        hint: 'Workspace defaults',
    },
];

export const dashboardModes = [
    {
        label: 'Simple mode',
        name: 'dashboard-simple',
        description: 'Fast read, primary KPIs only.',
    },
    {
        label: 'Advanced mode',
        name: 'dashboard-advanced',
        description: 'Expanded filters and diagnostics.',
    },
];

export const sectionRoutes = {
    analytics: {
        eyebrow: 'Analysis',
        title: 'Analytics',
        description: 'Explore cohorts, usage patterns, and performance movement across the workspace.',
        highlights: [
            'Time-series inspection with consistent filters.',
            'Chart-first exploration with table drill-downs.',
            'Reusable layouts for segment and channel analysis.',
        ],
    },
    reports: {
        eyebrow: 'Reporting',
        title: 'Reports',
        description: 'Ship clean exports and audit-ready snapshots without duplicating the dashboard shell.',
        highlights: [
            'Saved views and scheduled exports.',
            'Operational summaries for stakeholders.',
            'A shared route and card pattern with the dashboard.',
        ],
    },
    settings: {
        eyebrow: 'Workspace',
        title: 'Settings',
        description: 'Keep global defaults, display preferences, and access controls in one predictable place.',
        highlights: [
            'Layout and density preferences.',
            'Refresh cadence and data retention defaults.',
            'Mode defaults for simple and advanced users.',
        ],
    },
};
