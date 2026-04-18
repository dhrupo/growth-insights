export function registerDashboardTheme() {
    const root = document.documentElement;

    root.style.setProperty('--el-color-primary', '#2563eb');
    root.style.setProperty('--el-color-success', '#059669');
    root.style.setProperty('--el-color-warning', '#d97706');
    root.style.setProperty('--el-color-danger', '#dc2626');
    root.style.setProperty('--el-border-radius-base', '12px');
}
