<template>
  <div class="unified-progress-dashboard">
    <!-- Header Section -->
    <div class="dashboard-header">
      <div class="header-content">
        <h1 class="dashboard-title">Learning Progress</h1>
        <div class="sync-status">
          <div
            class="sync-indicator"
            :class="syncStatusClass"
            :title="syncStatusText"
          >
            <Icon :name="syncStatusIcon" class="w-4 h-4" />
            <span class="sync-text">{{ syncStatusText }}</span>
          </div>
        </div>
      </div>

      <!-- Time Range Selector -->
      <div class="time-range-selector">
        <select
          v-model="selectedTimeRange"
          class="time-range-select"
          @change="handleTimeRangeChange"
        >
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="year">This Year</option>
          <option value="all">All Time</option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="loading-spinner"></div>
      <p>Loading your progress...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <Icon name="alert-circle" class="w-8 h-8 text-red-500" />
      <p class="error-message">{{ error }}</p>
      <button @click="refreshData" class="retry-button">
        Try Again
      </button>
    </div>

    <!-- Main Dashboard Content -->
    <div v-else class="dashboard-content">
      <!-- Overview Cards -->
      <div class="overview-cards">
        <div class="progress-card overall-progress">
          <div class="card-header">
            <h3>Overall Progress</h3>
            <Icon name="trending-up" class="w-5 h-5" />
          </div>
          <div class="progress-value">
            <span class="progress-percentage">{{ overallProgress }}%</span>
            <div class="progress-bar">
              <div
                class="progress-fill"
                :style="{ width: `${overallProgress}%` }"
              ></div>
            </div>
          </div>
        </div>

        <div class="progress-card learning-streak">
          <div class="card-header">
            <h3>Learning Streak</h3>
            <Icon name="flame" class="w-5 h-5" />
          </div>
          <div class="streak-value">
            <span class="streak-number">{{ learningStreak }}</span>
            <span class="streak-label">days</span>
          </div>
        </div>

        <div class="progress-card time-spent">
          <div class="card-header">
            <h3>Time This Week</h3>
            <Icon name="clock" class="w-5 h-5" />
          </div>
          <div class="time-value">
            <span class="time-number">{{ formatTime(weeklyTimeSpent) }}</span>
          </div>
        </div>

        <div class="progress-card achievements">
          <div class="card-header">
            <h3>Achievements</h3>
            <Icon name="award" class="w-5 h-5" />
          </div>
          <div class="achievement-value">
            <span class="achievement-number">{{ achievements.length }}</span>
            <span class="achievement-label">earned</span>
          </div>
        </div>
      </div>

      <!-- Interactive Charts Section -->
      <div class="charts-section">
        <div class="chart-container progress-chart">
          <div class="chart-header">
            <h3>Progress Over Time</h3>
            <div class="chart-controls">
              <button
                v-for="chartType in chartTypes"
                :key="chartType.value"
                @click="selectedChartType = chartType.value"
                :class="['chart-type-btn', { active: selectedChartType === chartType.value }]"
              >
                {{ chartType.label }}
              </button>
            </div>
          </div>
          <div class="chart-content">
            <ProgressChart
              :data="chartData"
              :type="selectedChartType"
              :time-range="selectedTimeRange"
              @drill-down="handleChartDrillDown"
            />
          </div>
        </div>

        <div class="chart-container learning-paths-chart">
          <div class="chart-header">
            <h3>Learning Paths Progress</h3>
          </div>
          <div class="chart-content">
            <LearningPathsChart
              :track-progress="trackProgress"
              :course-progress="courseProgress"
              @path-selected="handlePathSelected"
            />
          </div>
        </div>
      </div>

      <!-- Recent Activity and Milestones -->
      <div class="activity-section">
        <div class="recent-activity">
          <div class="section-header">
            <h3>Recent Activity</h3>
            <button @click="showAllActivity" class="view-all-btn">
              View All
            </button>
          </div>
          <div class="activity-list">
            <div
              v-for="activity in recentActivity.slice(0, 5)"
              :key="activity.id"
              class="activity-item"
            >
              <div class="activity-icon">
                <Icon :name="getActivityIcon(activity.progressType)" class="w-4 h-4" />
              </div>
              <div class="activity-content">
                <p class="activity-title">{{ getActivityTitle(activity) }}</p>
                <p class="activity-time">{{ formatRelativeTime(activity.timestamp) }}</p>
              </div>
              <div class="activity-progress">
                <span class="progress-value">{{ Math.round((activity.value / activity.maxValue) * 100) }}%</span>
              </div>
            </div>
          </div>
        </div>

        <div class="upcoming-milestones">
          <div class="section-header">
            <h3>Upcoming Milestones</h3>
          </div>
          <div class="milestones-list">
            <div
              v-for="milestone in upcomingMilestones.slice(0, 3)"
              :key="milestone.id"
              class="milestone-item"
            >
              <div class="milestone-icon">
                <Icon :name="milestone.icon || 'target'" class="w-5 h-5" />
              </div>
              <div class="milestone-content">
                <h4 class="milestone-title">{{ milestone.title }}</h4>
                <p class="milestone-description">{{ milestone.description }}</p>
                <div class="milestone-progress">
                  <div class="progress-bar small">
                    <div
                      class="progress-fill"
                      :style="{ width: `${(milestone.currentValue / milestone.targetValue) * 100}%` }"
                    ></div>
                  </div>
                  <span class="progress-text">
                    {{ milestone.currentValue }} / {{ milestone.targetValue }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Export and Analytics Section -->
      <div class="analytics-section">
        <div class="export-controls">
          <h3>Export Progress Report</h3>
          <div class="export-buttons">
            <button
              v-for="format in exportFormats"
              :key="format.value"
              @click="handleExport(format.value)"
              :disabled="exportLoading"
              class="export-btn"
            >
              <Icon :name="format.icon" class="w-4 h-4" />
              {{ format.label }}
            </button>
          </div>
        </div>

        <div class="learning-insights" v-if="analytics">
          <h3>Learning Insights</h3>
          <div class="insights-grid">
            <div class="insight-card">
              <h4>Strong Categories</h4>
              <div class="category-tags">
                <span
                  v-for="category in analytics.strongCategories.slice(0, 3)"
                  :key="category"
                  class="category-tag strong"
                >
                  {{ category }}
                </span>
              </div>
            </div>

            <div class="insight-card">
              <h4>Improvement Areas</h4>
              <div class="category-tags">
                <span
                  v-for="area in analytics.improvementAreas.slice(0, 3)"
                  :key="area"
                  class="category-tag improvement"
                >
                  {{ area }}
                </span>
              </div>
            </div>

            <div class="insight-card">
              <h4>Learning Velocity</h4>
              <div class="velocity-indicator">
                <div class="velocity-bar">
                  <div
                    class="velocity-fill"
                    :style="{ width: `${Math.min(analytics.learningVelocity * 10, 100)}%` }"
                  ></div>
                </div>
                <span class="velocity-text">{{ analytics.learningVelocity.toFixed(1) }}x</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Achievement Notification -->
    <Transition name="achievement-popup">
      <div v-if="showAchievementPopup" class="achievement-popup">
        <div class="achievement-content">
          <Icon name="award" class="achievement-icon" />
          <h3>Achievement Unlocked!</h3>
          <p>{{ latestAchievement?.title }}</p>
          <button @click="dismissAchievement" class="dismiss-btn">
            Awesome!
          </button>
        </div>
      </div>
    </Transition>

    <!-- Milestone Notification -->
    <Transition name="milestone-popup">
      <div v-if="showMilestonePopup" class="milestone-popup">
        <div class="milestone-content">
          <Icon :name="latestMilestone?.icon || 'target'" class="milestone-icon" />
          <h3>Milestone Reached!</h3>
          <p>{{ latestMilestone?.title }}</p>
          <button @click="dismissMilestone" class="dismiss-btn">
            Continue Learning
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useGlobalUnifiedProgress } from '@/composables/useUnifiedProgress';
import { useNotifications } from '@/composables/useNotifications';
import Icon from '@/components/Icon.vue';
import ProgressChart from '@/components/charts/ProgressChart.vue';
import LearningPathsChart from '@/components/charts/LearningPathsChart.vue';
import type {
  Achievement,
  Milestone,
  LearningProgress,
  ProgressMetrics
} from '@/types/enhanced-classroom';

// Props
interface Props {
  userId?: string;
  timeRange?: 'week' | 'month' | 'year' | 'all';
  showAnalytics?: boolean;
  enableExport?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  timeRange: 'month',
  showAnalytics: true,
  enableExport: true
});

// Emits
interface Emits {
  progressExported: [format: 'pdf' | 'csv' | 'json'];
  analyticsRequested: [userId: string, timeRange: string];
  pathSelected: [type: 'track' | 'course', id: string];
}

const emit = defineEmits<Emits>();

// Composables
const {
  userProgress,
  trackProgress,
  courseProgress,
  achievements,
  analytics,
  loading,
  error,
  overallProgress,
  recentActivity,
  upcomingMilestones,
  learningStreak,
  syncStatus,
  pendingSyncOperations,
  fetchUserProgress,
  subscribeToProgressUpdates,
  unsubscribeFromProgressUpdates,
  getProgressMetrics,
  exportProgress
} = useGlobalUnifiedProgress();

const { showNotification } = useNotifications();

// Reactive state
const selectedTimeRange = ref(props.timeRange);
const selectedChartType = ref<'line' | 'bar' | 'area'>('line');
const exportLoading = ref(false);
const showAchievementPopup = ref(false);
const showMilestonePopup = ref(false);
const latestAchievement = ref<Achievement | null>(null);
const latestMilestone = ref<Milestone | null>(null);
const progressMetrics = ref<ProgressMetrics | null>(null);
const weeklyTimeSpent = ref(0);

// Chart configuration
const chartTypes = [
  { value: 'line', label: 'Line' },
  { value: 'bar', label: 'Bar' },
  { value: 'area', label: 'Area' }
];

const exportFormats = [
  { value: 'pdf', label: 'PDF', icon: 'file-text' },
  { value: 'csv', label: 'CSV', icon: 'download' },
  { value: 'json', label: 'JSON', icon: 'code' }
];

// Computed properties
const syncStatusClass = computed(() => {
  switch (syncStatus.value) {
    case 'synced': return 'sync-success';
    case 'syncing': return 'sync-pending';
    case 'offline': return 'sync-offline';
    case 'error': return 'sync-error';
    default: return 'sync-unknown';
  }
});

const syncStatusText = computed(() => {
  switch (syncStatus.value) {
    case 'synced': return 'Synced';
    case 'syncing': return `Syncing (${pendingSyncOperations.value})`;
    case 'offline': return 'Offline';
    case 'error': return 'Sync Error';
    default: return 'Unknown';
  }
});

const syncStatusIcon = computed(() => {
  switch (syncStatus.value) {
    case 'synced': return 'check-circle';
    case 'syncing': return 'refresh-cw';
    case 'offline': return 'wifi-off';
    case 'error': return 'alert-circle';
    default: return 'help-circle';
  }
});

const chartData = computed(() => {
  if (!progressMetrics.value) return [];

  // Transform progress metrics into chart data
  // This would be customized based on the actual chart library used
  return {
    labels: getTimeLabels(selectedTimeRange.value),
    datasets: [{
      label: 'Progress',
      data: getProgressDataPoints(),
      borderColor: '#3B82F6',
      backgroundColor: 'rgba(59, 130, 246, 0.1)',
      tension: 0.4
    }]
  };
});

// Methods
const handleTimeRangeChange = async () => {
  await fetchProgressMetrics();
  emit('analyticsRequested', getCurrentUserId(), selectedTimeRange.value);
};

const handleChartDrillDown = (dataPoint: any) => {
  console.log('Chart drill-down:', dataPoint);
  // Implement drill-down functionality
};

const handlePathSelected = (type: 'track' | 'course', id: string) => {
  emit('pathSelected', type, id);
};

const handleExport = async (format: 'pdf' | 'csv' | 'json') => {
  try {
    exportLoading.value = true;
    const blob = await exportProgress(format);

    // Create download link
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `progress-report.${format}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    emit('progressExported', format);
    showNotification({
      type: 'success',
      title: 'Export Complete',
      message: `Progress report exported as ${format.toUpperCase()}`
    });

  } catch (error) {
    console.error('Export failed:', error);
    showNotification({
      type: 'error',
      title: 'Export Failed',
      message: 'Failed to export progress report'
    });
  } finally {
    exportLoading.value = false;
  }
};

const refreshData = async () => {
  const userId = getCurrentUserId();
  await fetchUserProgress(userId);
  await fetchProgressMetrics();
};

const showAllActivity = () => {
  // Navigate to detailed activity page
  console.log('Show all activity');
};

const dismissAchievement = () => {
  showAchievementPopup.value = false;
  latestAchievement.value = null;
};

const dismissMilestone = () => {
  showMilestonePopup.value = false;
  latestMilestone.value = null;
};

// Utility functions
const getCurrentUserId = (): string => {
  return props.userId || localStorage.getItem('user_id') || 'current-user';
};

const formatTime = (minutes: number): string => {
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;

  if (hours > 0) {
    return `${hours}h ${mins}m`;
  }
  return `${mins}m`;
};

const formatRelativeTime = (timestamp: Date): string => {
  const now = new Date();
  const diff = now.getTime() - new Date(timestamp).getTime();
  const minutes = Math.floor(diff / (1000 * 60));
  const hours = Math.floor(minutes / 60);
  const days = Math.floor(hours / 24);

  if (days > 0) return `${days}d ago`;
  if (hours > 0) return `${hours}h ago`;
  if (minutes > 0) return `${minutes}m ago`;
  return 'Just now';
};

const getActivityIcon = (progressType: string): string => {
  switch (progressType) {
    case 'completion': return 'check-circle';
    case 'time': return 'clock';
    case 'score': return 'star';
    case 'engagement': return 'heart';
    default: return 'activity';
  }
};

const getActivityTitle = (activity: LearningProgress): string => {
  const typeLabel = activity.targetType.charAt(0).toUpperCase() + activity.targetType.slice(1);
  return `${typeLabel} Progress Updated`;
};

const getTimeLabels = (timeRange: string): string[] => {
  // Generate time labels based on selected range
  const now = new Date();
  const labels: string[] = [];

  switch (timeRange) {
    case 'week':
      for (let i = 6; i >= 0; i--) {
        const date = new Date(now);
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('en-US', { weekday: 'short' }));
      }
      break;
    case 'month':
      for (let i = 29; i >= 0; i -= 7) {
        const date = new Date(now);
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
      }
      break;
    case 'year':
      for (let i = 11; i >= 0; i--) {
        const date = new Date(now);
        date.setMonth(date.getMonth() - i);
        labels.push(date.toLocaleDateString('en-US', { month: 'short' }));
      }
      break;
    default:
      labels.push('All Time');
  }

  return labels;
};

const getProgressDataPoints = (): number[] => {
  // Generate progress data points based on user progress
  // This would be calculated from actual progress data
  return Array.from({ length: getTimeLabels(selectedTimeRange.value).length },
    (_, i) => Math.random() * 100
  );
};

const fetchProgressMetrics = async () => {
  try {
    progressMetrics.value = await getProgressMetrics(selectedTimeRange.value);
    weeklyTimeSpent.value = progressMetrics.value.timeSpentThisWeek || 0;
  } catch (error) {
    console.error('Failed to fetch progress metrics:', error);
  }
};

// Event handlers for real-time updates
const handleAchievementUnlocked = (event: CustomEvent) => {
  const achievement = event.detail as Achievement;
  latestAchievement.value = achievement;
  showAchievementPopup.value = true;

  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    if (showAchievementPopup.value) {
      dismissAchievement();
    }
  }, 5000);
};

const handleMilestoneReached = (event: CustomEvent) => {
  const milestone = event.detail as Milestone;
  latestMilestone.value = milestone;
  showMilestonePopup.value = true;

  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    if (showMilestonePopup.value) {
      dismissMilestone();
    }
  }, 5000);
};

// Lifecycle hooks
onMounted(async () => {
  const userId = getCurrentUserId();

  // Initial data fetch
  await fetchUserProgress(userId);
  await fetchProgressMetrics();

  // Subscribe to real-time updates
  subscribeToProgressUpdates(userId);

  // Listen for achievement and milestone events
  window.addEventListener('achievementUnlocked', handleAchievementUnlocked);
  window.addEventListener('milestoneReached', handleMilestoneReached);
});

onUnmounted(() => {
  unsubscribeFromProgressUpdates();
  window.removeEventListener('achievementUnlocked', handleAchievementUnlocked);
  window.removeEventListener('milestoneReached', handleMilestoneReached);
});

// Watch for prop changes
watch(() => props.userId, async (newUserId) => {
  if (newUserId) {
    await fetchUserProgress(newUserId);
    subscribeToProgressUpdates(newUserId);
  }
});

watch(() => props.timeRange, (newTimeRange) => {
  selectedTimeRange.value = newTimeRange;
  fetchProgressMetrics();
});
</script>

<style scoped>
.unified-progress-dashboard {
  @apply max-w-7xl mx-auto p-6 space-y-8;
}

/* Header Styles */
.dashboard-header {
  @apply flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4;
}

.header-content {
  @apply flex items-center gap-4;
}

.dashboard-title {
  @apply text-3xl font-bold text-gray-900 dark:text-white;
}

.sync-status {
  @apply flex items-center;
}

.sync-indicator {
  @apply flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium;
}

.sync-success {
  @apply bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200;
}

.sync-pending {
  @apply bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200;
}

.sync-offline {
  @apply bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200;
}

.sync-error {
  @apply bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200;
}

.time-range-select {
  @apply px-4 py-2 border border-gray-300 rounded-lg bg-white dark:bg-gray-800 dark:border-gray-600 dark:text-white;
}

/* Loading and Error States */
.loading-state, .error-state {
  @apply flex flex-col items-center justify-center py-12 text-center;
}

.loading-spinner {
  @apply w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mb-4;
}

.error-message {
  @apply text-gray-600 dark:text-gray-400 mb-4;
}

.retry-button {
  @apply px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors;
}

/* Overview Cards */
.overview-cards {
  @apply grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6;
}

.progress-card {
  @apply bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700;
}

.card-header {
  @apply flex items-center justify-between mb-4;
}

.card-header h3 {
  @apply text-sm font-medium text-gray-600 dark:text-gray-400;
}

.progress-value {
  @apply space-y-2;
}

.progress-percentage {
  @apply text-3xl font-bold text-gray-900 dark:text-white;
}

.progress-bar {
  @apply w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2;
}

.progress-fill {
  @apply bg-blue-600 h-2 rounded-full transition-all duration-300;
}

.streak-value, .time-value, .achievement-value {
  @apply flex items-baseline gap-2;
}

.streak-number, .time-number, .achievement-number {
  @apply text-3xl font-bold text-gray-900 dark:text-white;
}

.streak-label, .achievement-label {
  @apply text-sm text-gray-600 dark:text-gray-400;
}

/* Charts Section */
.charts-section {
  @apply grid grid-cols-1 lg:grid-cols-2 gap-8;
}

.chart-container {
  @apply bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700;
}

.chart-header {
  @apply flex items-center justify-between mb-6;
}

.chart-header h3 {
  @apply text-lg font-semibold text-gray-900 dark:text-white;
}

.chart-controls {
  @apply flex gap-2;
}

.chart-type-btn {
  @apply px-3 py-1 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors;
}

.chart-type-btn.active {
  @apply bg-blue-600 text-white border-blue-600;
}

.chart-content {
  @apply h-64;
}

/* Activity Section */
.activity-section {
  @apply grid grid-cols-1 lg:grid-cols-2 gap-8;
}

.recent-activity, .upcoming-milestones {
  @apply bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700;
}

.section-header {
  @apply flex items-center justify-between mb-6;
}

.section-header h3 {
  @apply text-lg font-semibold text-gray-900 dark:text-white;
}

.view-all-btn {
  @apply text-sm text-blue-600 hover:text-blue-700 font-medium;
}

.activity-list, .milestones-list {
  @apply space-y-4;
}

.activity-item {
  @apply flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors;
}

.activity-icon {
  @apply flex-shrink-0 w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center;
}

.activity-content {
  @apply flex-1 min-w-0;
}

.activity-title {
  @apply font-medium text-gray-900 dark:text-white;
}

.activity-time {
  @apply text-sm text-gray-600 dark:text-gray-400;
}

.activity-progress {
  @apply flex-shrink-0;
}

.milestone-item {
  @apply flex gap-4 p-4 rounded-lg border border-gray-200 dark:border-gray-700;
}

.milestone-icon {
  @apply flex-shrink-0 w-10 h-10 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center;
}

.milestone-content {
  @apply flex-1 space-y-2;
}

.milestone-title {
  @apply font-semibold text-gray-900 dark:text-white;
}

.milestone-description {
  @apply text-sm text-gray-600 dark:text-gray-400;
}

.milestone-progress {
  @apply flex items-center gap-3;
}

.progress-bar.small {
  @apply h-1.5 flex-1;
}

.progress-text {
  @apply text-xs text-gray-600 dark:text-gray-400;
}

/* Analytics Section */
.analytics-section {
  @apply grid grid-cols-1 lg:grid-cols-2 gap-8;
}

.export-controls, .learning-insights {
  @apply bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700;
}

.export-controls h3, .learning-insights h3 {
  @apply text-lg font-semibold text-gray-900 dark:text-white mb-4;
}

.export-buttons {
  @apply flex flex-wrap gap-3;
}

.export-btn {
  @apply flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors;
}

.insights-grid {
  @apply space-y-4;
}

.insight-card {
  @apply p-4 rounded-lg border border-gray-200 dark:border-gray-700;
}

.insight-card h4 {
  @apply font-medium text-gray-900 dark:text-white mb-3;
}

.category-tags {
  @apply flex flex-wrap gap-2;
}

.category-tag {
  @apply px-3 py-1 rounded-full text-sm font-medium;
}

.category-tag.strong {
  @apply bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200;
}

.category-tag.improvement {
  @apply bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200;
}

.velocity-indicator {
  @apply flex items-center gap-3;
}

.velocity-bar {
  @apply flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2;
}

.velocity-fill {
  @apply bg-purple-600 h-2 rounded-full transition-all duration-300;
}

.velocity-text {
  @apply font-semibold text-gray-900 dark:text-white;
}

/* Popup Notifications */
.achievement-popup, .milestone-popup {
  @apply fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50;
}

.achievement-content, .milestone-content {
  @apply bg-white dark:bg-gray-800 rounded-xl p-8 max-w-md mx-4 text-center shadow-xl;
}

.achievement-icon, .milestone-icon {
  @apply w-16 h-16 mx-auto mb-4 text-yellow-500;
}

.achievement-content h3, .milestone-content h3 {
  @apply text-xl font-bold text-gray-900 dark:text-white mb-2;
}

.achievement-content p, .milestone-content p {
  @apply text-gray-600 dark:text-gray-400 mb-6;
}

.dismiss-btn {
  @apply px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors;
}

/* Transitions */
.achievement-popup-enter-active, .achievement-popup-leave-active,
.milestone-popup-enter-active, .milestone-popup-leave-active {
  @apply transition-all duration-300;
}

.achievement-popup-enter-from, .achievement-popup-leave-to,
.milestone-popup-enter-from, .milestone-popup-leave-to {
  @apply opacity-0;
}

.achievement-popup-enter-from .achievement-content,
.achievement-popup-leave-to .achievement-content,
.milestone-popup-enter-from .milestone-content,
.milestone-popup-leave-to .milestone-content {
  @apply scale-75;
}

/* Responsive Design */
@media (max-width: 640px) {
  .unified-progress-dashboard {
    @apply p-4 space-y-6;
  }

  .dashboard-header {
    @apply flex-col items-start gap-3;
  }

  .overview-cards {
    @apply grid-cols-1 gap-4;
  }

  .charts-section, .activity-section, .analytics-section {
    @apply grid-cols-1 gap-6;
  }

  .chart-content {
    @apply h-48;
  }
}
</style>
