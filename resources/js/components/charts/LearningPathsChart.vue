<template>
  <div class="learning-paths-chart">
    <div class="chart-legend">
      <div class="legend-item">
        <div class="legend-color track-color"></div>
        <span>Tracks</span>
      </div>
      <div class="legend-item">
        <div class="legend-color course-color"></div>
        <span>Courses</span>
      </div>
    </div>

    <div class="chart-container">
      <canvas
        ref="chartCanvas"
        class="chart-canvas"
        @click="handleChartClick"
      ></canvas>

      <!-- Fallback visualization -->
      <div v-if="!hasData" class="no-data-state">
        <Icon name="book-open" class="w-12 h-12 text-gray-400" />
        <p class="text-gray-600 dark:text-gray-400">No learning paths data available</p>
      </div>
    </div>

    <!-- Progress List View -->
    <div class="progress-list">
      <div class="list-section" v-if="trackEntries.length > 0">
        <h4 class="section-title">Tracks</h4>
        <div class="progress-items">
          <div
            v-for="[trackId, progress] in trackEntries"
            :key="`track-${trackId}`"
            class="progress-item track-item"
            @click="handlePathClick('track', trackId)"
          >
            <div class="item-info">
              <span class="item-name">{{ getPathName(trackId, 'track') }}</span>
              <span class="item-progress">{{ Math.round(progress) }}%</span>
            </div>
            <div class="progress-bar">
              <div
                class="progress-fill track-fill"
                :style="{ width: `${progress}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <div class="list-section" v-if="courseEntries.length > 0">
        <h4 class="section-title">Courses</h4>
        <div class="progress-items">
          <div
            v-for="[courseId, progress] in courseEntries"
            :key="`course-${courseId}`"
            class="progress-item course-item"
            @click="handlePathClick('course', courseId)"
          >
            <div class="item-info">
              <span class="item-name">{{ getPathName(courseId, 'course') }}</span>
              <span class="item-progress">{{ Math.round(progress) }}%</span>
            </div>
            <div class="progress-bar">
              <div
                class="progress-fill course-fill"
                :style="{ width: `${progress}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import Icon from '@/components/Icon.vue';

// Props
interface Props {
  trackProgress: Record<string, number>;
  courseProgress: Record<string, number>;
  maxItems?: number;
  showChart?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  maxItems: 10,
  showChart: true
});

// Emits
interface Emits {
  pathSelected: [type: 'track' | 'course', id: string];
}

const emit = defineEmits<Emits>();

// Refs
const chartCanvas = ref<HTMLCanvasElement | null>(null);
const chartInstance = ref<any>(null);

// Computed properties
const trackEntries = computed(() => {
  return Object.entries(props.trackProgress)
    .sort(([, a], [, b]) => b - a)
    .slice(0, Math.ceil(props.maxItems / 2));
});

const courseEntries = computed(() => {
  return Object.entries(props.courseProgress)
    .sort(([, a], [, b]) => b - a)
    .slice(0, Math.ceil(props.maxItems / 2));
});

const hasData = computed(() => {
  return trackEntries.value.length > 0 || courseEntries.value.length > 0;
});

const chartData = computed(() => {
  const allEntries = [
    ...trackEntries.value.map(([id, progress]) => ({
      id,
      progress,
      type: 'track' as const,
      name: getPathName(id, 'track')
    })),
    ...courseEntries.value.map(([id, progress]) => ({
      id,
      progress,
      type: 'course' as const,
      name: getPathName(id, 'course')
    }))
  ].sort((a, b) => b.progress - a.progress);

  return {
    labels: allEntries.map(item => item.name),
    datasets: [
      {
        label: 'Tracks',
        data: allEntries.map(item => item.type === 'track' ? item.progress : 0),
        backgroundColor: '#3B82F6',
        borderColor: '#2563EB',
        borderWidth: 1
      },
      {
        label: 'Courses',
        data: allEntries.map(item => item.type === 'course' ? item.progress : 0),
        backgroundColor: '#10B981',
        borderColor: '#059669',
        borderWidth: 1
      }
    ],
    metadata: allEntries
  };
});

// Methods
const getPathName = (id: string, type: 'track' | 'course'): string => {
  // In a real app, this would fetch the actual name from a store or API
  // For now, return a formatted version of the ID
  const formattedId = id.replace(/[-_]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  return `${formattedId}`;
};

const handlePathClick = (type: 'track' | 'course', id: string) => {
  emit('pathSelected', type, id);
};

const handleChartClick = (event: MouseEvent) => {
  if (!chartInstance.value) return;

  try {
    const points = chartInstance.value.getElementsAtEventForMode(
      event,
      'nearest',
      { intersect: true },
      true
    );

    if (points.length > 0) {
      const point = points[0];
      const dataIndex = point.index;
      const metadata = chartData.value.metadata[dataIndex];

      if (metadata) {
        emit('pathSelected', metadata.type, metadata.id);
      }
    }
  } catch (error) {
    console.error('Chart click error:', error);
  }
};

const createChart = async () => {
  if (!chartCanvas.value || !props.showChart) return;

  try {
    const Chart = (window as any).Chart;
    if (Chart && hasData.value) {
      const config = {
        type: 'bar',
        data: chartData.value,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              position: 'top' as const,
              labels: {
                usePointStyle: true,
                padding: 20,
                filter: (legendItem: any, data: any) => {
                  // Only show legend items that have data
                  const datasetIndex = legendItem.datasetIndex;
                  const dataset = data.datasets[datasetIndex];
                  return dataset.data.some((value: number) => value > 0);
                }
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleColor: '#fff',
              bodyColor: '#fff',
              borderColor: '#374151',
              borderWidth: 1,
              cornerRadius: 8,
              filter: (tooltipItem: any) => {
                return tooltipItem.parsed.y > 0;
              },
              callbacks: {
                title: (context: any) => {
                  return context[0].label;
                },
                label: (context: any) => {
                  if (context.parsed.y === 0) return null;
                  const pathType = context.datasetIndex === 0 ? 'Track' : 'Course';
                  return `${pathType}: ${context.parsed.y}%`;
                }
              }
            }
          },
          scales: {
            x: {
              stacked: true,
              grid: {
                display: false
              },
              ticks: {
                maxRotation: 45,
                minRotation: 0,
                color: '#6B7280'
              }
            },
            y: {
              stacked: true,
              beginAtZero: true,
              max: 100,
              grid: {
                color: 'rgba(107, 114, 128, 0.1)'
              },
              ticks: {
                color: '#6B7280',
                callback: (value: any) => `${value}%`
              }
            }
          },
          interaction: {
            intersect: false,
            mode: 'index' as const
          }
        }
      };

      chartInstance.value = new Chart(chartCanvas.value, config);
    } else {
      // Fallback to simple visualization
      drawSimpleChart();
    }
  } catch (error) {
    console.error('Failed to create learning paths chart:', error);
    drawSimpleChart();
  }
};

const drawSimpleChart = () => {
  if (!chartCanvas.value || !hasData.value) return;

  const canvas = chartCanvas.value;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  // Set canvas size
  const rect = canvas.getBoundingClientRect();
  canvas.width = rect.width * window.devicePixelRatio;
  canvas.height = rect.height * window.devicePixelRatio;
  ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

  const width = rect.width;
  const height = rect.height;
  const padding = 40;
  const chartWidth = width - padding * 2;
  const chartHeight = height - padding * 2;

  // Clear canvas
  ctx.clearRect(0, 0, width, height);

  const allData = chartData.value.metadata;
  const barWidth = chartWidth / allData.length * 0.8;
  const barSpacing = chartWidth / allData.length * 0.2;

  // Draw bars
  allData.forEach((item, index) => {
    const x = padding + (chartWidth / allData.length) * index + barSpacing / 2;
    const barHeight = (item.progress / 100) * chartHeight;
    const y = padding + chartHeight - barHeight;

    // Set color based on type
    ctx.fillStyle = item.type === 'track' ? '#3B82F6' : '#10B981';
    ctx.fillRect(x, y, barWidth, barHeight);

    // Draw progress text
    ctx.fillStyle = '#374151';
    ctx.font = '12px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(
      `${Math.round(item.progress)}%`,
      x + barWidth / 2,
      y - 5
    );
  });

  chartInstance.value = { simple: true };
};

const updateChart = () => {
  if (chartInstance.value && !chartInstance.value.simple) {
    chartInstance.value.data = chartData.value;
    chartInstance.value.update();
  } else {
    drawSimpleChart();
  }
};

const destroyChart = () => {
  if (chartInstance.value && !chartInstance.value.simple) {
    chartInstance.value.destroy();
  }
  chartInstance.value = null;
};

// Lifecycle hooks
onMounted(async () => {
  await nextTick();
  if (hasData.value) {
    createChart();
  }
});

onUnmounted(() => {
  destroyChart();
});

// Watch for data changes
watch([() => props.trackProgress, () => props.courseProgress], () => {
  if (hasData.value) {
    updateChart();
  } else {
    destroyChart();
  }
}, { deep: true });

watch(() => props.showChart, (newValue) => {
  if (newValue && hasData.value) {
    nextTick(() => createChart());
  } else {
    destroyChart();
  }
});
</script>

<style scoped>
.learning-paths-chart {
  @apply space-y-4;
}

.chart-legend {
  @apply flex items-center gap-6 justify-center;
}

.legend-item {
  @apply flex items-center gap-2;
}

.legend-color {
  @apply w-3 h-3 rounded-full;
}

.track-color {
  @apply bg-blue-600;
}

.course-color {
  @apply bg-green-600;
}

.chart-container {
  @apply relative h-64;
}

.chart-canvas {
  @apply w-full h-full;
}

.no-data-state {
  @apply absolute inset-0 flex flex-col items-center justify-center text-center space-y-2;
}

.progress-list {
  @apply space-y-6;
}

.list-section {
  @apply space-y-3;
}

.section-title {
  @apply text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide;
}

.progress-items {
  @apply space-y-2;
}

.progress-item {
  @apply p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors;
}

.item-info {
  @apply flex items-center justify-between mb-2;
}

.item-name {
  @apply font-medium text-gray-900 dark:text-white truncate;
}

.item-progress {
  @apply text-sm font-semibold text-gray-600 dark:text-gray-400;
}

.progress-bar {
  @apply w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2;
}

.progress-fill {
  @apply h-2 rounded-full transition-all duration-300;
}

.track-fill {
  @apply bg-blue-600;
}

.course-fill {
  @apply bg-green-600;
}

.track-item:hover .track-fill {
  @apply bg-blue-700;
}

.course-item:hover .course-fill {
  @apply bg-green-700;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .chart-container {
    @apply h-48;
  }

  .item-name {
    @apply text-sm;
  }

  .item-progress {
    @apply text-xs;
  }
}
</style>
