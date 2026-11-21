<template>
  <v-container fluid class="pa-0 account-page">
    <!-- Header -->
    <AppToolbar
      :title="t('account.title')"
      :subtitle="t('account.subtitle')"
    />

    <v-tabs v-model="tab" color="primary" class="mb-4">
      <v-tab value="profile">{{ t('account.tabs.profile') }}</v-tab>
      <v-tab value="preferences">{{ t('account.tabs.preferences') }}</v-tab>
      <v-tab value="keyboard">{{ t('account.tabs.keyboard') }}</v-tab>
      <v-tab v-if="sessionsSupported !== false" value="security">{{ t('account.tabs.security') }}</v-tab>
    </v-tabs>

    <div class="account-content">
      <ProfileTab v-if="tab === 'profile'" />
      <PreferencesTab v-else-if="tab === 'preferences'" />
      <KeyboardTab v-else-if="tab === 'keyboard'" />
      <SecurityTab v-else-if="tab === 'security'" @unsupported="sessionsSupported = false" />
    </div>
  </v-container>
</template>

<script setup>
  import { ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import ProfileTab from '@/components/account/ProfileTab.vue'
  import PreferencesTab from '@/components/account/PreferencesTab.vue'
  import KeyboardTab from '@/components/account/KeyboardTab.vue'
  import SecurityTab from '@/components/account/SecurityTab.vue'
  import { AppToolbar } from '@nong-official-dev/core'

  const VALID_TABS = ['profile', 'preferences', 'keyboard', 'security']

  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()

  // Refresh keeps the tab — driven entirely by the URL, not local-only state.
  const tab = ref(VALID_TABS.includes(route.query.tab) ? route.query.tab : 'profile')
  // Set by SecurityTab if the backend ever lacks sessions support (not
  // expected today — /v1/auth/sessions is a real endpoint — but keeps this
  // page from showing a permanently-broken tab if that ever changes).
  const sessionsSupported = ref(true)

  watch(tab, value => {
    router.replace({ query: { ...route.query, tab: value } })
  })
  watch(
    () => route.query.tab,
    value => {
      if (VALID_TABS.includes(value) && value !== tab.value) tab.value = value
    }
  )
</script>

<style scoped>
  .account-content {
    max-width: 880px;
  }
</style>
