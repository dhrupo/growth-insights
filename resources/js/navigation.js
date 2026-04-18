import {
    Document,
    Histogram,
    Monitor,
    Operation,
    Setting,
} from '@element-plus/icons-vue';

export const primaryNavigation = [
    {
        label: 'Overview',
        path: '/dashboard',
        icon: Monitor,
        hint: 'Public and private analysis entry',
    },
    {
        label: 'Advanced',
        path: '/dashboard/advanced',
        icon: Operation,
        hint: 'Filters and diagnostic surfaces',
    },
    {
        label: 'Workbench',
        path: '/analytics',
        icon: Histogram,
        hint: 'Run analysis and inspect score drivers',
    },
    {
        label: 'Reports',
        path: '/reports',
        icon: Document,
        hint: 'Shareable summaries and exports',
    },
    {
        label: 'Settings',
        path: '/settings',
        icon: Setting,
        hint: 'Scoring and defaults',
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
        eyebrow: 'Public analysis',
        title: 'Run a public GitHub scan',
        description: 'Enter a username, inspect the public profile, and read the score model without any token.',
        highlights: [
            'Start with a username and see the public profile immediately.',
            'Use the score breakdown to spot consistency, collaboration, and testing gaps.',
            'Use this path before deciding whether private workspace access is worth connecting.',
        ],
    },
    reports: {
        eyebrow: 'Private workspace',
        title: 'Connect a token for private signals',
        description: 'Attach a personal access token and layer private repositories onto the same analysis flow.',
        highlights: [
            'Token-backed connection unlocks private repository context.',
            'The connection form stays isolated from the public scan so each step can fail cleanly.',
            'Private data is designed to merge into the same score and recommendation surface.',
        ],
    },
    settings: {
        eyebrow: 'Scoring model',
        title: 'Tune the scoring defaults',
        description: 'Keep the current model transparent, with room for thresholds, weightings, and refresh cadence.',
        highlights: [
            'Weight consistency, contribution, and testing according to the same visible score model.',
            'Keep freshness and refresh cadence obvious while the backend catches up.',
            'Surface model changes without changing the dashboard shell.',
        ],
    },
};
