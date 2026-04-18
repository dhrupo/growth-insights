export type DashboardMode = 'simple' | 'advanced';

export type DashboardDataSource = 'seed' | 'mixed' | 'live';

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
    type: 'line' | 'bar' | 'pie';
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
}
