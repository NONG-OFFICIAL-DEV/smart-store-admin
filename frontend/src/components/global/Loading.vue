<template>
  <v-overlay
    :model-value="loadingStore.isLoading && loadingStore.mode === 'overlay'"
    class="align-center justify-center"
    persistent
  >
    <div class="d-flex flex-column align-center justify-center text-center pa-6">
      
      <!-- Modern Geometric 3-Dot Loader -->
      <div class="dot-loader mb-6">
        <div class="dot bg-primary"></div>
        <div class="dot bg-primary"></div>
        <div class="dot bg-primary"></div>
      </div>

      <!-- Next-Stack Text -->
      <div class="loading-stack">
        <!-- <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis mb-1">
          {{ loadingStore.message || 'Processing Request' }}
        </h3> -->
        <!-- <p class="text-caption text-disabled">
          Finishing up execution
        </p> -->
      </div>

    </div>
  </v-overlay>
</template>

<script setup>
import { useLoadingStore } from '@/stores/loading'
const loadingStore = useLoadingStore()
</script>

<style scoped>
.modern-overlay :deep(.v-overlay__scrim) {
  backdrop-filter: blur(10px);
}

/* Custom Modern Dot Animation */
.dot-loader {
  display: flex;
  gap: 8px;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  /* Fallback color if Vuetify class isn't parsed in style */
  background-color: rgb(var(--v-theme-primary)); 
  animation: dotElastic 1s infinite ease-in-out;
}

.dot:nth-child(2) { animation-delay: 0.15s; }
.dot:nth-child(3) { animation-delay: 0.3s; }

@keyframes dotElastic {
  0%, 100% { transform: scale(1); opacity: 0.4; }
  50% { transform: scale(1.4); opacity: 1; }
}
</style>