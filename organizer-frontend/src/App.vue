<script setup>
import { RouterView } from 'vue-router'
import AppToast from '@/components/ui/ToastStack.vue'
</script>

<template>
  <RouterView />
  <AppToast />
</template>