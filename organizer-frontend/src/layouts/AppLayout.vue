<script setup>
import { RouterView } from 'vue-router'
import AppHeader from '@/components/layout/AppHeader.vue'
</script>

<template>
  <div class="min-h-screen bg-[var(--bg-page)] text-[var(--text-primary)]">
    <AppHeader />

    <main class="mx-auto w-full max-w-[1700px] px-6 py-6">
      <RouterView />
    </main>
  </div>
</template>