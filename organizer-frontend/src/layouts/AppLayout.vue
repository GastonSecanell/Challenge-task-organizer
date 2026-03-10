<script setup>
import { RouterView } from 'vue-router'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import AppSidebar from '@/components/AppSidebar.vue'
import ToastStack from '@/components/ToastStack.vue'

const sidebarOpen = ref(true)
const sidebarCollapsed = ref(false)
const isMobile = ref(false)

function recalcLayout() {
  const mobile = window.innerWidth < 980
  isMobile.value = mobile
  if (mobile) {
    sidebarOpen.value = false
    sidebarCollapsed.value = false
  } else {
    sidebarOpen.value = true
  }
}

/* function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
  if (!sidebarOpen.value) sidebarCollapsed.value = false
} */

function closeSidebar() {
  sidebarOpen.value = false
  sidebarCollapsed.value = false
}

function toggleCollapse() {
  if (isMobile.value) return
  sidebarCollapsed.value = !sidebarCollapsed.value
}

onMounted(() => {
  recalcLayout()
  window.addEventListener('resize', recalcLayout)
})

onBeforeUnmount(() => window.removeEventListener('resize', recalcLayout))

const shellClass = computed(() => {
  return [
    'app-shell',
    sidebarOpen.value ? 'app-shell--open' : 'app-shell--closed',
    sidebarCollapsed.value ? 'app-shell--collapsed' : '',
    isMobile.value ? 'app-shell--mobile' : '',
  ]
})
</script>

<template>
  <div :class="shellClass">
    <!-- <md-icon-button class="sidebar-toggle" type="button" title="Menú" @click="toggleSidebar">
      <i class="mdi mdi-menu" aria-hidden="true"></i>
    </md-icon-button> -->

    <div v-if="isMobile && sidebarOpen" class="sidebar-overlay" @click="closeSidebar" />

    <AppSidebar
      :isOpen="sidebarOpen"
      :isCollapsed="sidebarCollapsed"
      :isMobile="isMobile"
      @close="closeSidebar"
      @toggle-collapse="toggleCollapse"
    />

    <main class="main">
      <RouterView />
    </main>

    <ToastStack />
  </div>
</template>

