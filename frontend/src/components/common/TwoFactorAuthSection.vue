<template>
  <v-card class="pa-6" rounded="lg" elevation="0" border>
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis">
          {{ $t('two_factor.title') }}
        </h3>
        <p class="text-caption text-medium-emphasis mb-0">
          {{ $t('two_factor.subtitle') }}
        </p>
      </div>
      <v-chip
        :color="enabled ? 'success' : 'default'"
        variant="tonal"
        size="small"
      >
        {{ enabled ? $t('two_factor.enabled') : $t('two_factor.disabled') }}
      </v-chip>
    </div>

    <v-btn
      v-if="!enabled"
      color="primary"
      variant="flat"
      rounded="lg"
      prepend-icon="mdi-shield-lock-outline"
      @click="startSetup"
    >
      {{ $t('two_factor.enable') }}
    </v-btn>
    <v-btn
      v-else
      color="error"
      variant="tonal"
      rounded="lg"
      prepend-icon="mdi-shield-off-outline"
      :loading="disabling"
      @click="handleDisable"
    >
      {{ $t('two_factor.disable') }}
    </v-btn>

    <!-- Setup dialog — QR + confirm code, then recovery codes shown once -->
    <AppDialog
      v-model="setupOpen"
      :title="$t('two_factor.setup_title')"
      icon="mdi-shield-lock-outline"
      :hide-submit="step !== 'confirm'"
      :submit-text="$t('two_factor.confirm_button')"
      :loading="confirming"
      :disable-submit="code.length !== 6"
      @submit="handleConfirm"
      @close="resetSetup"
    >
      <div v-if="step === 'qr'" class="text-center">
        <p class="text-body-2 text-medium-emphasis mb-4">
          {{ $t('two_factor.scan_instructions') }}
        </p>
        <div class="qr-wrap mb-4" v-html="qrCodeSvg"></div>
        <p class="text-caption text-medium-emphasis mb-1">{{ $t('two_factor.manual_entry') }}</p>
        <code class="secret-code">{{ secret }}</code>
      </div>

      <div v-else-if="step === 'confirm'">
        <p class="text-body-2 text-medium-emphasis mb-4">
          {{ $t('two_factor.enter_code_instructions') }}
        </p>
        <v-otp-input
          v-model="code"
          length="6"
          variant="outlined"
          :error="!!confirmError"
          @finish="handleConfirm"
        />
        <div v-if="confirmError" class="text-caption text-error mt-1">
          {{ confirmError }}
        </div>
        <v-btn variant="text" size="small" class="mt-2 text-none" @click="step = 'qr'">
          {{ $t('two_factor.back_to_qr') }}
        </v-btn>
      </div>

      <div v-else-if="step === 'recovery_codes'">
        <v-alert type="warning" variant="tonal" density="comfortable" class="mb-4 rounded-lg">
          {{ $t('two_factor.recovery_codes_warning') }}
        </v-alert>
        <div class="recovery-codes-grid mb-4">
          <code v-for="rc in recoveryCodes" :key="rc" class="recovery-code">{{ rc }}</code>
        </div>
        <v-btn color="primary" variant="flat" rounded="lg" block @click="finishSetup">
          {{ $t('two_factor.recovery_codes_saved') }}
        </v-btn>
      </div>

      <template v-if="step === 'qr'" #actions>
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" @click="setupOpen = false">
          {{ $t('btn.cancel') }}
        </v-btn>
        <v-btn color="primary" variant="flat" rounded="lg" @click="step = 'confirm'">
          {{ $t('btn.next') }}
        </v-btn>
      </template>
    </AppDialog>
  </v-card>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import AppDialog from '@/components/common/AppDialog.vue'
  import { setupTwoFactorApi, confirmTwoFactorApi, disableTwoFactorApi } from '@/api/twoFactorService'

  const { t } = useI18n()
  const authStore = useAuthStore()
  const { notif } = useAppUtils()

  const enabled = computed(() => !!authStore.me?.two_factor_confirmed_at)

  const setupOpen = ref(false)
  const step = ref('qr') // 'qr' | 'confirm' | 'recovery_codes'
  const secret = ref('')
  const qrCodeSvg = ref('')
  const code = ref('')
  const confirmError = ref('')
  const confirming = ref(false)
  const disabling = ref(false)
  const recoveryCodes = ref([])

  async function startSetup() {
    setupOpen.value = true
    step.value = 'qr'
    code.value = ''
    confirmError.value = ''
    const { data } = await setupTwoFactorApi()
    secret.value = data.data.secret
    qrCodeSvg.value = data.data.qr_code_svg
  }

  async function handleConfirm() {
    if (code.value.length !== 6) return
    confirmError.value = ''
    confirming.value = true
    try {
      const { data } = await confirmTwoFactorApi(code.value)
      recoveryCodes.value = data.data.recovery_codes
      step.value = 'recovery_codes'
      await authStore.fetchMe()
    } catch (err) {
      confirmError.value = err.response?.data?.message ?? t('two_factor.errors.invalid_code')
    } finally {
      confirming.value = false
    }
  }

  function finishSetup() {
    setupOpen.value = false
    resetSetup()
    notif(t('two_factor.enabled_success'), { type: 'success' })
  }

  function resetSetup() {
    step.value = 'qr'
    secret.value = ''
    qrCodeSvg.value = ''
    code.value = ''
    confirmError.value = ''
    recoveryCodes.value = []
  }

  async function handleDisable() {
    disabling.value = true
    try {
      await disableTwoFactorApi()
      await authStore.fetchMe()
      notif(t('two_factor.disabled_success'), { type: 'success' })
    } catch (err) {
      notif(err.response?.data?.message ?? t('messages.error_occurred'), { type: 'error' })
    } finally {
      disabling.value = false
    }
  }
</script>

<style scoped>
  .qr-wrap :deep(svg) {
    width: 180px;
    height: 180px;
  }
  .secret-code {
    font-size: 0.85rem;
    letter-spacing: 1px;
    padding: 4px 10px;
    border-radius: 6px;
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .recovery-codes-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
  }
  .recovery-code {
    text-align: center;
    padding: 8px;
    border-radius: 6px;
    background: rgba(var(--v-theme-on-surface), 0.06);
    font-size: 0.85rem;
    letter-spacing: 0.5px;
  }
</style>
