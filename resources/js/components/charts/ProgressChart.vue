<template>
  <div class="progress-chart">
    <canvas
      ref="chartCanvas"
      class="chart-canvas"
      @click="handleChartClick"
    ></canvas>

    <!-- Fallback for when chart library is not available -->
    <div v-if="!chartInstance" class="chart-fallback">
      <div class="fallback-content">
        <Icon name="bar-chart" class="w-12 h-12 text-gray-400" />
        <p class="text-gray-600 dark:text-gray-400">Chart visualization loading...</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import Icon from '@/components/Icon.vue';

// Props
interface Props {
  data: any;
  type: 'line' | 'bar' | 'area';
  timeRange: string;
  height?: number;
}

const props = withDefaults(defineProps<Props>(), {
  height: 300
});

// Emits
interface Emits {
  drillDown: [dataPoint: any];
}

const emit = defineEmits<Emits>();

// Refs
const chartCanvas = ref<HTMLCanvasElement | null>(null);
const chartInstance = ref<any>(null);

// Chart configuration
const getChartConfig = () => {
  const baseConfig = {
    type: props.type,
    data: props.data,
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
            color: getComputedStyle(document.documentElement).getPropertyValue('--text-color') || '#374151'
          }
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleColor: '#fff',
          bodyColor: '#fff',
          borderColor: '#374151',
          borderWidth: 1,
          cornerRadius: 8,
          displayColors: false,
          callbacks: {
            title: (context: any) => {
              return `${context[0].label}`;
            },
            label: (context: any) => {
              return `Progress: ${context.parsed.y}%`;
            }
          }
        }
      },
      scales: {
        x: {
          grid: {
            display: false
          },
          ticks: {
            color: getComputedStyle(document.documentElement).getPropertyValue('--text-color') || '#6B7280'
          }
        },
        y: {
          beginAtZero: true,
          max: 100,
          grid: {
            color: 'rgba(107, 114, 128, 0.1)'
          },
          ticks: {
            color: getComputedStyle(document.documentElement).getPropertyValue('--text-color') || '#6B7280',
            callback: (value: any) => `${value}%`
          }
        }
      },
      interaction: {
        intersect: false,
        mode: 'index' as const
      },
      onClick: (event: any, elements: any[]) => {
        if (elements.length > 0) {
          const element = elements[0];
          const dataPoint = {
            index: element.index,
            datasetIndex: element.datasetIndex,
            value: props.data.datasets[element.datasetIndex].data[element.index],
            label: props.data.labels[element.index]
          };
          emit('drillDown', dataPoint);
        }
      }
    }
  };

  // Customize based on chart type
  if (props.type === 'area') {
    baseConfig.type = 'line';
    if (baseConfig.data.datasets && baseConfig.data.datasets[0]) {
      baseConfig.data.datasets[0].fill = true;
    }
  }

  return baseConfig;
};

// Chart methods
const createChart = async () => {
  if (!chartCanvas.value) return;

  try {
    // Try to use Chart.js if available
    const Chart = (window as any).Chart;
    if (Chart) {
      chartInstance.value = new Chart(chartCanvas.value, getChartConfig());
    } else {
      // Fallback to simple canvas drawing
      drawSimpleChart();
    }
  } catch (error) {
    console.error('Failed to create chart:', error);
    drawSimpleChart();
  }
};

const updateChart = () => {
  if (chartInstance.value) {
    chartInstance.value.data = props.data;
    chartInstance.value.update();
  } else {
    drawSimpleChart();
  }
};

const destroyChart = () => {
  if (chartInstance.value) {
    chartInstance.value.destroy();
    chartInstance.value = null;
  }
};

// Fallback simple chart drawing
const drawSimpleChart = () => {
  if (!chartCanvas.value || !props.data.datasets?.[0]?.data) return;

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

  const data = props.data.datasets[0].data;
  const labels = props.data.labels;
  const maxValue = Math.max(...data, 100);

  // Draw grid lines
  ctx.strokeStyle = 'rgba(107, 114, 128, 0.2)';
  ctx.lineWidth = 1;

  // Horizontal grid lines
  for (let i = 0; i <= 5; i++) {
    const y = padding + (chartHeight / 5) * i;
    ctx.beginPath();
    ctx.moveTo(padding, y);
    ctx.lineTo(width - padding, y);
    ctx.stroke();
  }

  // Draw chart based on type
  if (props.type === 'line' || props.type === 'area') {
    drawLineChart(ctx, data, labels, padding, chartWidth, chartHeight, maxValue);
  } else if (props.type === 'bar') {
    drawBarChart(ctx, data, labels, padding, chartWidth, chartHeight, maxValue);
  }

  // Mark that we have a chart instance (even if simple)
  chartInstance.value = { simple: true };
};

const drawLineChart = (
  ctx: CanvasRenderingContext2D,
  data: number[],
  labels: string[],
  padding: number,
  chartWidth: number,
  chartHeight: number,
  maxValue: number
) => {
  const stepX = chartWidth / (data.length - 1);

  // Draw area fill if area chart
  if (props.type === 'area') {
    ctx.fillStyle = 'rgba(59, 130, 246, 0.1)';
    ctx.beginPath();
    ctx.moveTo(padding, padding + chartHeight);

    data.forEach((value, index) => {
      const x = padding + stepX * index;
      const y = padding + chartHeight - (value / maxValue) * chartHeight;
      ctx.lineTo(x, y);
    });

    ctx.lineTo(padding + chartWidth, padding + chartHeight);
    ctx.closePath();
    ctx.fill();
  }

  // Draw line
  ctx.strokeStyle = '#3B82F6';
  ctx.lineWidth = 2;
  ctx.beginPath();

  data.forEach((value, index) => {
    const x = padding + stepX * index;
    const y = padding + chartHeight - (value / maxValue) * chartHeight;

    if (index === 0) {
      ctx.moveTo(x, y);
    } else {
      ctx.lineTo(x, y);
    }
  });

  ctx.stroke();

  // Draw points
  ctx.fillStyle = '#3B82F6';
  data.forEach((value, index) => {
    const x = padding + stepX * index;
    const y = padding + chartHeight - (value / maxValue) * chartHeight;

    ctx.beginPath();
    ctx.arc(x, y, 4, 0, 2 * Math.PI);
    ctx.fill();
  });
};

const drawBarChart = (
  ctx: CanvasRenderingContext2D,
  data: number[],
  labels: string[],
  padding: number,
  chartWidth: number,
  chartHeight: number,
  maxValue: number
) => {
  const barWidth = chartWidth / data.length * 0.8;
  const barSpacing = chartWidth / data.length * 0.2;

  ctx.fillStyle = '#3B82F6';

  data.forEach((value, index) => {
    const x = padding + (chartWidth / data.length) * index + barSpacing / 2;
    const barHeight = (value / maxValue) * chartHeight;
    const barY = padding + chartHeight - barHeight;

    ctx.fillRect(x, barY, barWidth, barHeight);
  });
};

const handleChartClick = (event: MouseEvent) => {
  if (!chartInstance.value?.simple) return;

  // Simple click handling for fallback chart
  const rect = chartCanvas.value?.getBoundingClientRect();
  if (!rect) return;

  const x = event.clientX - rect.left;
  const y = event.clientY - rect.top;

  // Calculate which data point was clicked
  const padding = 40;
  const chartWidth = rect.width - padding * 2;
  const dataIndex = Math.floor(((x - padding) / chartWidth) * props.data.labels.length);

  if (dataIndex >= 0 && dataIndex < props.data.labels.length) {
    const dataPoint = {
      index: dataIndex,
      datasetIndex: 0,
      value: props.data.datasets[0].data[dataIndex],
      label: props.data.labels[dataIndex]
    };
    emit('drillDown', dataPoint);
  }
};

// Lifecycle hooks
onMounted(async () => {
  await nextTick();
  createChart();
});

onUnmounted(() => {
  destroyChart();
});

// Watch for data changes
watch(() => props.data, () => {
  updateChart();
}, { deep: true });

watch(() => props.type, () => {
  destroyChart();
  nextTick(() => {
    createChart();
  });
});
</script>

<style scoped>
.progress-chart {
  @apply relative w-full h-full;
}

.chart-canvas {
  @apply w-full h-full;
}

.chart-fallback {
  @apply absolute inset-0 flex items-center justify-center;
}

.fallback-content {
  @apply text-center space-y-2;
}
</style>
