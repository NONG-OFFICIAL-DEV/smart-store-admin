<template>
  <div class="d-flex flex-column gap-6">
    <!-- Language -->
    <v-card variant="outlined" rounded="lg">
      <v-card-text class="pa-5">
        <div class="d-flex align-center gap-2 mb-4">
          <v-icon size="18" color="primary">mdi-translate</v-icon>
          <span class="text-subtitle-2 font-weight-bold">{{ t('preferences.language') }}</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <v-btn
            v-for="lang in languages"
            :key="lang.code"
            :variant="locale === lang.code ? 'tonal' : 'outlined'"
            :color="locale === lang.code ? 'primary' : 'default'"
            rounded="lg"
            size="small"
            class="text-none lang-btn"
            @click="changeLang(lang.code)"
          >
            <v-img :src="lang.flag" width="18" height="18" class="flag-img me-2" cover />
            {{ lang.label }}
            <v-icon v-if="locale === lang.code" end size="12">mdi-check</v-icon>
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <!-- Appearance -->
    <v-card variant="outlined" rounded="lg" class="mt-4">
      <v-card-text class="pa-5">
        <div class="d-flex align-center gap-2 mb-4">
          <v-icon size="18" color="primary">mdi-tune-variant</v-icon>
          <span class="text-subtitle-2 font-weight-bold">{{ t('app_bar.appearance') }}</span>
        </div>
        <AppearanceSettings />
      </v-card-text>
    </v-card>

    <!-- Default branch -->
    <v-card v-if="showDefaultBranch" variant="outlined" rounded="lg" class="mt-4">
      <v-card-text class="pa-5">
        <div class="d-flex align-center gap-2 mb-4">
          <v-icon size="18" color="primary">mdi-store-outline</v-icon>
          <span class="text-subtitle-2 font-weight-bold">{{ t('account.preferences.default_branch') }}</span>
        </div>
        <v-select
          :model-value="prefs.defaultBranchId"
          :items="branchStore.branches"
          item-title="name"
          item-value="id"
          variant="outlined"
          rounded="lg"
          clearable
          :label="t('account.preferences.default_branch')"
          :hint="t('account.preferences.default_branch_hint')"
          persistent-hint
          @update:model-value="prefs.setDefaultBranch"
        />
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
  import { computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useUiPreferencesStore } from '@/stores/uiPreferencesStore'
  import AppearanceSettings from './AppearanceSettings.vue'
  import authService from '@/api/auth'

  const { t, locale } = useI18n()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const prefs = useUiPreferencesStore()

  // Only meaningful for an owner/multi-branch user — staff are always
  // forced onto their one assigned branch (see authStore.fetchMe()), and a
  // super admin has no branch concept at all.
  const showDefaultBranch = computed(
    () => !authStore.isSuperAdmin && !authStore.branch_id && branchStore.branches.length > 1
  )

  const languages = computed(() => [
    { code: 'km', label: t('lang.km') || 'ខ្មែរ', flag: '/images/kh.png' },
    { code: 'en', label: t('lang.en') || 'English', flag: '/images/gb.png' }
  ])

  function changeLang(code) {
    locale.value = code
    localStorage.setItem('lang', code)
    authService.updatePreferences({ preferred_language: code }).catch(() => {})
  }

  onMounted(() => {
    if (!branchStore.branches.length) branchStore.fetchBranches()
  })
</script>

<style scoped>
  .lang-btn {
    min-width: 110px;
  }
  .flag-img {
    border-radius: 3px;
    flex-shrink: 0;
  }
</style>
