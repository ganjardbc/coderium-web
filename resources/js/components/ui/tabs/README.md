# Tabs Component

A reusable tabs component for Vue 3 with TypeScript support.

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'

const activeTab = ref('tab1')
</script>

<template>
  <Tabs v-model="activeTab" default-value="tab1">
    <TabsList>
      <TabsTrigger value="tab1">Tab 1</TabsTrigger>
      <TabsTrigger value="tab2">Tab 2</TabsTrigger>
      <TabsTrigger value="tab3" disabled>Tab 3 (Disabled)</TabsTrigger>
    </TabsList>

    <TabsContent value="tab1">
      <div class="space-y-4">
        <h3 class="text-lg font-semibold">Tab 1 Content</h3>
        <p>This is the content for tab 1.</p>
      </div>
    </TabsContent>

    <TabsContent value="tab2">
      <div class="space-y-4">
        <h3 class="text-lg font-semibold">Tab 2 Content</h3>
        <p>This is the content for tab 2.</p>
      </div>
    </TabsContent>

    <TabsContent value="tab3">
      <div class="space-y-4">
        <h3 class="text-lg font-semibold">Tab 3 Content</h3>
        <p>This is the content for tab 3.</p>
      </div>
    </TabsContent>
  </Tabs>
</template>
```

## Components

### Tabs
The root container component that manages tab state.

**Props:**
- `defaultValue?: string` - The default active tab
- `modelValue?: string` - For v-model binding

**Events:**
- `update:modelValue` - Emitted when active tab changes

### TabsList
Container for tab triggers with consistent styling.

**Props:**
- `class?: string` - Additional CSS classes

### TabsTrigger
Individual tab button.

**Props:**
- `value: string` - Unique identifier for the tab
- `class?: string` - Additional CSS classes
- `disabled?: boolean` - Whether the tab is disabled

### TabsContent
Container for tab content that shows/hides based on active tab.

**Props:**
- `value: string` - Unique identifier matching the trigger
- `class?: string` - Additional CSS classes

## Features

- ✅ Reactive state management with Vue 3 Composition API
- ✅ TypeScript support
- ✅ v-model binding
- ✅ Disabled state support
- ✅ Consistent styling with design system
- ✅ Accessible keyboard navigation
- ✅ Customizable with CSS classes
