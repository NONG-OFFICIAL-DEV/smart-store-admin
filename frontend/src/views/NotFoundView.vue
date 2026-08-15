<template>
  <v-container fluid class="not-found-container fill-height pa-0">
    <!-- Animated background grid -->
    <div class="grid-bg" aria-hidden="true">
      <div v-for="i in 64" :key="i" class="grid-cell" :style="{ animationDelay: `${(i * 0.08) % 3}s` }" />
    </div>

    <!-- Glitch overlay numbers -->
    <div class="glitch-overlay" aria-hidden="true">
      <span class="big-404">404</span>
    </div>

    <v-row align="center" justify="center" class="content-row fill-height">
      <v-col cols="12" sm="10" md="7" lg="5" class="text-center">

        <v-card class="glass-card pa-8 pa-md-12" rounded="xl" elevation="0">

          <!-- Icon -->
          <div class="icon-wrap mb-6">
            <v-icon size="56" color="primary" class="pulse-icon">mdi-compass-off-outline</v-icon>
          </div>

          <!-- Heading -->
          <div class="label-chip mb-3">
            <v-chip color="primary" variant="tonal" size="small" class="font-weight-bold tracking-widest">
              {{ $t('not_found.error_code') }}
            </v-chip>
          </div>

          <h1 class="page-title mb-3">{{ $t('not_found.title') }}</h1>

          <p class="page-subtitle mb-8">
            {{ $t('not_found.description_line1') }}<br>
            {{ $t('not_found.description_line2') }}
          </p>

          <!-- Path display -->
          <v-sheet
            v-if="currentPath"
            color="surface-variant"
            rounded="lg"
            class="path-display mb-8 pa-3"
          >
            <span class="path-label">{{ $t('not_found.attempted_path') }}</span>
            <code class="path-code">{{ currentPath }}</code>
          </v-sheet>

          <!-- Actions -->
          <div class="d-flex flex-column flex-sm-row gap-3 justify-center">
            <v-btn
              color="primary"
              variant="flat"
              size="large"
              rounded="lg"
              class="action-btn"
              prepend-icon="mdi-home-outline"
              @click="goHome"
            >
              {{ $t('not_found.go_to_dashboard') }}
            </v-btn>

            <v-btn
              variant="tonal"
              size="large"
              rounded="lg"
              class="action-btn"
              prepend-icon="mdi-arrow-left"
              @click="goBack"
            >
              {{ $t('not_found.go_back') }}
            </v-btn>
          </div>

        </v-card>

      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const currentPath = computed(() => route.fullPath)

function goHome() {
  router.push({ name: 'Dashboard' })
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push({ name: 'Dashboard' })
  }
}
</script>

<style scoped>

/* ── Animated grid background ── */
.grid-bg {
  position: absolute;
  inset: 0;
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  grid-template-rows: repeat(8, 1fr);
  z-index: 0;
  opacity: 0.04;
}

.grid-cell {
  border: 1px solid rgb(var(--v-theme-primary));
  animation: cellPulse 3s ease-in-out infinite;
}

@keyframes cellPulse {
  0%, 100% { opacity: 0.3; }
  50% { opacity: 1; }
}

/* ── Giant background 404 ── */
.glitch-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1;
  pointer-events: none;
  user-select: none;
}

.big-404 {
  font-size: clamp(120px, 28vw, 320px);
  font-weight: 900;
  letter-spacing: -0.04em;
  color: transparent;
  -webkit-text-stroke: 1px rgba(var(--v-theme-primary), 0.08);
  line-height: 1;
  animation: glitch 6s infinite;
}

@keyframes glitch {
  0%, 90%, 100% { transform: translate(0, 0) skewX(0deg); opacity: 1; }
  92% { transform: translate(-4px, 2px) skewX(-1deg); opacity: 0.8; }
  94% { transform: translate(4px, -2px) skewX(1deg); opacity: 0.9; }
  96% { transform: translate(-2px, 0px) skewX(0deg); opacity: 0.85; }
}

/* ── Glass card ── */
.glass-card {
  background: rgba(var(--v-theme-surface), 0.72) !important;
  backdrop-filter: blur(20px) saturate(1.4);
  -webkit-backdrop-filter: blur(20px) saturate(1.4);
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

/* ── Icon ── */
.icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: rgba(var(--v-theme-primary), 0.1);
  margin: 0 auto;
}

.pulse-icon {
  animation: iconPulse 2.4s ease-in-out infinite;
}

@keyframes iconPulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.12); opacity: 0.75; }
}

/* ── Typography ── */
.page-title {
  font-size: clamp(1.6rem, 4vw, 2.4rem);
  font-weight: 700;
  letter-spacing: -0.02em;
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.2;
}

.page-subtitle {
  font-size: 1rem;
  color: rgb(var(--v-theme-on-surface-variant));
  line-height: 1.7;
}

.tracking-widest {
  letter-spacing: 0.12em;
  text-transform: uppercase;
  font-size: 0.7rem !important;
}

/* ── Path display ── */
.path-display {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
}

.path-label {
  font-size: 0.8rem;
  color: rgb(var(--v-theme-on-surface-variant));
  white-space: nowrap;
}

.path-code {
  font-family: 'Fira Code', 'Cascadia Code', monospace;
  font-size: 0.85rem;
  color: rgb(var(--v-theme-primary));
  word-break: break-all;
}

/* ── Buttons ── */
.action-btn {
  min-width: 180px;
  font-weight: 600;
  letter-spacing: 0.01em;
}

.gap-3 {
  gap: 12px;
}
</style>