<script setup lang="ts">
import { provide, ref, watch } from 'vue'

interface Props {
  defaultValue?: string
  modelValue?: string
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  defaultValue: ''
})

const emit = defineEmits<Emits>()

const activeTab = ref(props.modelValue || props.defaultValue)

watch(() => props.modelValue, (newValue) => {
  if (newValue !== undefined) {
    activeTab.value = newValue
  }
})

watch(activeTab, (newValue) => {
  emit('update:modelValue', newValue)
})

const setActiveTab = (value: string) => {
  activeTab.value = value
}

provide('activeTab', activeTab)
provide('setActiveTab', setActiveTab)
</script>

<template>
  <div class="tabs">
    <slot />
  </div>
</template>
