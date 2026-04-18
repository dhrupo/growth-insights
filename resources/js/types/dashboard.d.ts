export type DashboardMode = 'simple' | 'advanced';

export type DashboardDataSource = 'seed' | 'mixed' | 'live';

export type DashboardAnalysisStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface DashboardFilters {
    range: string;
    segment: string;
    query: string;
    mode: DashboardMode;
}

export interface DashboardMetric {
    key: string;
    title: string;
    value: string;
    delta?: string;
    description?: string;
    tone?: 'blue' | 'emerald' | 'amber' | 'rose';
    icon?: string;
}

export interface DashboardSeries {
    name: string;
    type: 'line' | 'bar' | 'pie' | 'radar';
    data: number[];
}

export interface DashboardTimeline {
    categories: string[];
    series: DashboardSeries[];
    updatedAt?: string;
}

export interface DashboardInsightItem {
    id: string;
    title: string;
    detail: string;
    impact?: string;
    priority?: 'low' | 'medium' | 'high';
}

export interface DashboardSimulatorSeriesPoint {
    label: string;
    value: number;
}

export interface DashboardSimulatorOutput {
    current: string;
    projected: string;
    uplift: string;
    confidence: string;
    notes: string[];
    series: DashboardSimulatorSeriesPoint[];
}

export interface GithubAnalysisProfile {
    username: string;
    displayName: string;
    role: string;
    bio: string;
    followers: number;
    publicRepos: number;
    contributionStreak: number;
    publicPullRequests: number;
}

export interface GithubAnalysisConnection {
    enabled: boolean;
    connected: boolean;
    workspace: string;
    tokenPreview: string;
    note: string;
}

export interface GithubScoreBreakdown {
    categories: string[];
    values: number[];
    benchmark?: number[];
}

export interface GithubSkillDistribution {
    categories: string[];
    values: number[];
}

export interface GithubAnalysisWorkbench {
    username: string;
    source: DashboardDataSource;
    status: DashboardAnalysisStatus;
    privateStatus: DashboardAnalysisStatus;
    lastAnalyzedAt?: string | null;
    profile: GithubAnalysisProfile;
    summary: string[];
    connection: GithubAnalysisConnection;
    scoreBreakdown: GithubScoreBreakdown;
    skillDistribution: GithubSkillDistribution;
    strengths: DashboardInsightItem[];
    weaknesses: DashboardInsightItem[];
    recommendations: DashboardInsightItem[];
    error?: string | null;
}

export interface DashboardPayload {
    summary: DashboardMetric[];
    timeline: DashboardTimeline;
    acquisitionMix: {
        categories: string[];
        values: number[];
    };
    strengths: DashboardInsightItem[];
    weaknesses: DashboardInsightItem[];
    recommendations: DashboardInsightItem[];
    simulator: DashboardSimulatorOutput;
    analysis: GithubAnalysisWorkbench;
}
