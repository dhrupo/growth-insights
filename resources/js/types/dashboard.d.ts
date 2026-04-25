export type DashboardMode = 'simple' | 'advanced';

export type DashboardDataSource = 'empty' | 'mixed' | 'live';

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
    why?: string | null;
    evidence?: unknown;
    successMetric?: string | null;
    focusArea?: string | null;
    aiNote?: string | null;
}

export interface DashboardWeeklyPlanItem {
    day: string;
    title: string;
    action: string;
    aiNote?: string | null;
}

export interface DashboardThirtyDayPlanItem {
    week: string;
    title: string;
    action: string;
    focus?: string | null;
    aiNote?: string | null;
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

export interface GithubSkillSignal {
    key: string;
    label: string;
    score: number;
    confidence: string;
    rawConfidence: number;
    notes: string;
    evidence: string[];
}

export interface GithubTrajectoryWindow {
    label: string;
    days: number;
    score: number;
    confidence: number;
    active_days: number;
    commits: number;
    pull_requests: number;
    issues: number;
    repos_touched: number;
    momentum: string;
}

export interface GithubAnalyzedRepository {
    name: string;
    fullName?: string | null;
    url?: string | null;
    visibility: string;
    language?: string | null;
    lastActivity?: string | null;
    commitCount: number;
    pullRequestCount: number;
    issueCount: number;
    description?: string | null;
    signals?: Record<string, unknown>;
}

export interface GithubSuggestedRepository {
    repo: string;
    url?: string | null;
    language?: string | null;
    description?: string | null;
    whyFit?: string | null;
    realisticContribution?: string | null;
    stars?: number | null;
}

export interface GithubContributionStyle {
    label: string;
    summary: string;
    confidence: string;
    evidence: string[];
}

export interface GithubVisibilityAdviceAction {
    action: string;
    why?: string;
    evidence?: string;
}

export interface GithubVisibilityAdvice {
    summary: string;
    actions: GithubVisibilityAdviceAction[];
}

export interface GithubTrajectorySummary {
    windows: Record<string, GithubTrajectoryWindow> | GithubTrajectoryWindow[];
    summary: string;
    outlook: string;
    confidence: string;
}

export interface GithubAnalysisSnapshot {
    score: number | null;
    confidence: number | null;
    momentum: string;
    languages: string[];
    activeWeeks: number;
    activeDays: number;
    commits: number;
    pullRequests: number;
    issues: number;
}

export interface GithubImprovementAction {
    title: string;
    detail: string;
    why?: string;
    metric?: string;
}

export interface GithubAnalysisWorkbench {
    analysisRunId?: number | null;
    username: string;
    source: DashboardDataSource;
    status: DashboardAnalysisStatus;
    lastAnalyzedAt?: string | null;
    evidenceSummary?: string | null;
    snapshot?: GithubAnalysisSnapshot;
    weeklyPlan?: DashboardWeeklyPlanItem[];
    thirtyDayPlan?: DashboardThirtyDayPlanItem[];
    profile: GithubAnalysisProfile;
    summary: string[];
    connection: GithubAnalysisConnection;
    scoreBreakdown: GithubScoreBreakdown;
    skillDistribution: GithubSkillDistribution;
    skillSignals: GithubSkillSignal[];
    strengths: DashboardInsightItem[];
    weaknesses: DashboardInsightItem[];
    recommendations: DashboardInsightItem[];
    improvementActions: GithubImprovementAction[];
    howToGetNoticed: GithubVisibilityAdvice;
    trajectory: GithubTrajectorySummary;
    contributionStyle: GithubContributionStyle;
    credibilityNotice?: string | null;
    analyzedRepositories: GithubAnalyzedRepository[];
    suggestedRepositories: GithubSuggestedRepository[];
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
