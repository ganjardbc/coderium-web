<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import LogoWhite from '../../assets/logo-white.png';
import LogoFill from '../../assets/logo-fill.png';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const { appearance } = useAppearance();
const isLightMode = computed(() => appearance.value === 'light');
</script>

<template>
    <img
        :src="isLightMode ? LogoFill : LogoWhite"
        :class="className"
        alt="Coderium"
    />
</template>
