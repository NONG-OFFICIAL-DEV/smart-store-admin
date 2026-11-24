<template>
  <v-card variant="outlined" rounded="lg">
    <v-card-text class="pa-5">
      <div class="d-flex align-center justify-space-between mb-4">
        <div class="d-flex align-center gap-2">
          <v-icon size="18" color="primary">mdi-devices</v-icon>
          <span class="text-title-small font-weight-bold">{{ t('account.security.title') }}</span>
        </div>
        <v-btn
          size="small"
          variant="tonal"
          color="error"
          rounded="lg"
          :disabled="otherSessionsCount === 0"
          :loading="revoking"
          @click="confirmRevokeOthers"
        >
          {{ t('account.security.sign_out_others') }}
        </v-btn>
      </div>

      <v-skeleton-loader v-if="loading" type="list-item-two-line@3" />

      <v-list v-else density="comfortable" class="pa-0">
        <v-list-item v-for="s in sessions" :key="s.id" rounded="lg" class="mb-1 session-item">
          <template #prepend>
            <v-icon icon="mdi-monitor-cellphone" size="20" class="me-3" />
          </template>
          <v-list-item-title class="text-body-medium font-weight-medium d-flex align-center gap-2">
            {{ s.device_name || t('account.security.unknown_device') }}
            <v-chip v-if="s.is_current" size="x-small" color="success" variant="tonal" rounded="lg">
              {{ t('account.security.this_device') }}
            </v-chip>
          </v-list-item-title>
          <v-list-item-subtitle class="text-body-small">
            {{ s.ip_address }} · {{ t('account.security.last_active') }} {{ formatDateTime(s.last_used_at || s.created_at) }}
          </v-list-item-subtitle>
        </v-list-item>

        <v-list-item v-if="!sessions.length">
          <v-list-item-title class="text-body-medium text-medium-emphasis">
            {{ t('account.security.no_sessions') }}
          </v-list-item-title>
        </v-list-item>
      </v-list>
    </v-card-text>
  </v-card>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useDate } from '@/composables/useDate'
  import authService from '@/api/auth'

  const emit = defineEmits(['unsupported'])

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const { formatDateTime } = useDate()

  const loading = ref(true)
  const revoking = ref(false)
  const sessions = ref([])

  const otherSessionsCount = computed(() => sessions.value.filter(s => !s.is_current).length)

  async function load() {
    loading.value = true
    try {
      const { data } = await authService.sessions()
      sessions.value = data.data.sessions
    } catch (err) {
      if (err?.response?.status === 404) {
        emit('unsupported')
      } else {
        notif(t('account.security.load_failed'), { type: 'error' })
      }
    } finally {
      loading.value = false
    }
  }

  function confirmRevokeOthers() {
    confirm({
      title: t('account.security.sign_out_others'),
      message: t('account.security.sign_out_others_confirm'),
      options: { type: 'warning' },
      agree: revokeOthers
    })
  }

  async function revokeOthers() {
    revoking.value = true
    try {
      await authService.revokeOtherSessions()
      notif(t('account.security.sign_out_others_success'), { type: 'success' })
      await load()
    } catch {
      notif(t('account.security.sign_out_others_failed'), { type: 'error' })
    } finally {
      revoking.value = false
    }
  }

  onMounted(load)
</script>

<style scoped>
  .session-item {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
</style>
