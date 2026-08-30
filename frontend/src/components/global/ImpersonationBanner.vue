<template>
  <div v-if="authStore.impersonating" class="impersonation-banner">
    <v-icon icon="mdi-account-eye-outline" size="18" class="me-2" />
    <span>{{ t('impersonation.banner_text', { name: authStore.impersonating.tenantName }) }}</span>
    <v-btn size="small" variant="flat" color="white" class="ms-3" @click="handleReturn">
      {{ t('impersonation.return_to_admin') }}
    </v-btn>
  </div>
</template>

<script setup>
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'

  const { t } = useI18n()
  const router = useRouter()
  const authStore = useAuthStore()

  async function handleReturn() {
    await authStore.returnToAdmin()
    router.push('/tenants')
  }
</script>

<style scoped>
  .impersonation-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 3000;
    background: #b45309;
    color: #fff;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 600;
  }
  .impersonation-banner .v-btn {
    color: #b45309 !important;
  }
</style>
