<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import LogoFill from '../../assets/logo-fill.png';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img :src="LogoFill" :class="className" alt="Coderium" />
</template>
