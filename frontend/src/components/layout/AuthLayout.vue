<template>
  <v-container fluid class="pa-0 login-container">
    <v-row no-gutters class="fill-height">
      <!-- ── Left panel ──────────────────────────────────────────────────── -->
      <v-col
        cols="12"
        md="6"
        class="left-panel d-none d-md-flex flex-column pa-12 text-white"
      >
        <!-- Decorative orbs -->
        <div class="orb orb-1" />
        <div class="orb orb-2" />
        <div class="orb orb-3" />

        <!-- Brand -->
        <div class="brand-mark d-flex align-center mb-auto">
          <div class="brand-icon-wrapper mr-3">
            <v-icon icon="mdi-store-outline" size="22" color="white" />
          </div>
          <span
            class="text-h6 font-weight-black"
            style="letter-spacing: -0.3px"
          >
            {{ t('app.name') }}
          </span>
        </div>

        <!-- Content -->
        <div class="left-content fade-in">
          <slot name="left-content" />
        </div>

        <!-- Footer -->
        <div class="text-caption mt-auto left-footer-text">
          © {{ new Date().getFullYear() }} {{ t('app.footer') }}
        </div>
      </v-col>

      <!-- ── Right panel ─────────────────────────────────────────────────── -->
      <v-col cols="12" md="6" class="d-flex flex-column right-panel">
        <!-- Header — logo (visible when the left panel is hidden, i.e. on
             mobile) + language/theme switchers. Kept out of the form card
             itself so it's reachable from every auth page uniformly. -->
        <div class="d-flex align-center justify-space-between pa-4">
          <div class="d-flex align-center d-md-none">
            <div class="brand-icon-wrapper-sm mr-2">
              <v-icon icon="mdi-store-outline" size="16" color="white" />
            </div>
            <span class="text-body-1 font-weight-black">
              {{ t('app.name') }}
            </span>
          </div>
          <v-spacer class="d-none d-md-flex" />

          <div class="d-flex align-center ga-1">
            <v-btn
              icon
              variant="text"
              size="small"
              :title="t('common.language')"
              @click="toggleLocale"
            >
              <v-icon icon="mdi-translate" size="20" />
            </v-btn>
            <v-btn
              icon
              variant="text"
              size="small"
              :title="t('preferences.theme')"
              @click="toggleTheme"
            >
              <v-icon
                :icon="
                  isDark
                    ? 'mdi-white-balance-sunny'
                    : 'mdi-moon-waning-crescent'
                "
                size="20"
              />
            </v-btn>
          </div>
        </div>

        <div class="auth-form-panel__content">
          <slot />
        </div>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTheme } from 'vuetify'

  const { t, locale } = useI18n()
  const theme = useTheme()

  const isDark = computed(() => theme.global.current.value.dark)

  function toggleLocale() {
    const next = locale.value === 'en' ? 'km' : 'en'
    locale.value = next
    localStorage.setItem('lang', next)
  }

  function applyTheme(val) {
    if (val === 'auto') {
      const hour = new Date().getHours()
      theme.change(hour >= 18 || hour < 6 ? 'dark' : 'light')
    } else {
      theme.change(val)
    }
  }

  function toggleTheme() {
    const next = isDark.value ? 'light' : 'dark'
    localStorage.setItem('theme', next)
    applyTheme(next)
  }

  onMounted(() => {
    applyTheme(localStorage.getItem('theme') || 'light')
  })
</script>

<style scoped>
  /* ── Container ────────────────────────────────────────────────────────── */
  .login-container {
    min-height: 100vh;
    overflow: hidden;
  }

  .auth-form-panel__content {
    flex: 1 1 auto;
    width: 100%;
    max-width: 540px;
    margin: 0 auto;
    padding: 8px 24px 64px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  /* Dark theme's default surface (~#212121)
     reads from this same --v-theme-surface variable. */
  :global(.v-theme--dark) .login-container {
    --v-theme-surface: 40, 42, 50;
  }

  /* ── Left panel ───────────────────────────────────────────────────────── */
  .left-panel {
    position: relative;
    overflow: hidden;
    background: linear-gradient(
      150deg,
      #0f172a 0%,
      #1e3a5f 35%,
      #1d4ed8 70%,
      #6366f1 100%
    );
  }

  /* Mesh gradient overlay */
  .left-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
      radial-gradient(
        ellipse at 20% 50%,
        rgba(99, 102, 241, 0.35) 0%,
        transparent 60%
      ),
      radial-gradient(
        ellipse at 80% 10%,
        rgba(59, 130, 246, 0.25) 0%,
        transparent 55%
      ),
      radial-gradient(
        ellipse at 60% 90%,
        rgba(139, 92, 246, 0.2) 0%,
        transparent 50%
      );
    pointer-events: none;
  }

  /* Floating orbs */
  .orb {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    filter: blur(60px);
  }
  .orb-1 {
    width: 340px;
    height: 340px;
    top: -100px;
    right: -80px;
    background: rgba(99, 102, 241, 0.3);
  }
  .orb-2 {
    width: 240px;
    height: 240px;
    bottom: 40px;
    left: -60px;
    background: rgba(59, 130, 246, 0.25);
  }
  .orb-3 {
    width: 180px;
    height: 180px;
    top: 45%;
    left: 55%;
    background: rgba(139, 92, 246, 0.2);
  }

  .brand-icon-wrapper {
    background: rgba(255, 255, 255, 0.15);
    padding: 9px;
    border-radius: 11px;
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    display: grid;
    place-items: center;
  }

  .brand-icon-wrapper-sm {
    background: rgb(var(--v-theme-primary));
    padding: 6px;
    border-radius: 8px;
    display: grid;
    place-items: center;
  }

  .left-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    z-index: 1;
  }

  .left-footer-text {
    color: rgba(255, 255, 255, 0.25);
    position: relative;
    z-index: 1;
  }

  /* ── Right panel ──────────────────────────────────────────────────────── */
  .right-panel {
    background: rgb(var(--v-theme-surface));
    transition: background-color 0.25s ease;
  }

  /* ── Animation ────────────────────────────────────────────────────────── */
  .fade-in {
    animation: fadeIn 0.7s cubic-bezier(0.16, 1, 0.3, 1);
  }
  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>
