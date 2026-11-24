<template>
  <v-dialog v-model="model" :max-width="400" :fullscreen="smAndDown" scrollable>
    <v-card rounded="lg" class="d-flex flex-column">
      <v-card-title class="d-flex align-center justify-space-between pa-4 flex-grow-0">
        <span class="text-title-large">{{ t('pos.loyalty.redeem_title') }}</span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="close" />
      </v-card-title>
      <v-divider />

      <v-card-text class="py-4 flex-grow-1">
        <v-alert v-if="error" type="error" variant="tonal" density="compact" rounded="lg" class="mb-4" :text="error" />

        <!-- Reward found and still pending — preview + Apply -->
        <template v-if="preview">
          <v-card variant="tonal" color="primary" rounded="lg" class="pa-3 mb-4">
            <div class="text-body-small text-medium-emphasis">{{ t('pos.loyalty.reward_label') }}</div>
            <div class="text-body-large font-weight-bold">{{ preview.reward.name }}</div>
            <div class="text-body-small mt-1">
              {{ t('loyalty_rewards.points_cost_chip', { points: preview.reward.points_cost }) }}
            </div>
          </v-card>
          <v-btn color="primary" variant="flat" rounded="lg" block height="44" @click="apply">
            {{ t('pos.loyalty.apply') }}
          </v-btn>
        </template>

        <!-- Lookup form -->
        <template v-else>
          <div v-if="scanning" class="mb-4">
            <div id="loyalty-qr-reader" class="loyalty-qr-reader" />
            <v-btn variant="text" rounded="lg" block class="mt-2" @click="stopScan">
              {{ t('pos.loyalty.stop_scan') }}
            </v-btn>
          </div>
          <template v-else>
            <v-text-field
              v-model="code"
              :label="t('pos.loyalty.code_label')"
              :placeholder="t('pos.loyalty.code_placeholder')"
              variant="outlined"
              rounded="lg"
              density="comfortable"
              hide-details
              class="mb-3"
              autofocus
              maxlength="8"
              @keydown.enter="lookup"
            />
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              block
              height="44"
              class="mb-2"
              :loading="loading"
              :disabled="!code.trim()"
              @click="lookup"
            >
              {{ t('pos.loyalty.check_code') }}
            </v-btn>
            <v-btn variant="tonal" rounded="lg" block height="44" prepend-icon="mdi-camera-outline" @click="startScan">
              {{ t('pos.loyalty.scan_qr') }}
            </v-btn>
          </template>
        </template>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { computed, nextTick, ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useDisplay } from 'vuetify'
  import { showLoyaltyRedemptionApi } from '@/api/loyaltyPosService'
  import { useQrScanner } from '@/composables/useQrScanner'

  const { t } = useI18n()
  const { smAndDown } = useDisplay()
  const { scanning, start: startScanner, stop: stopScanner } = useQrScanner()

  const props = defineProps({
    modelValue: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue', 'applied'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const code = ref('')
  const loading = ref(false)
  const error = ref(null)
  const preview = ref(null)

  const STATUS_MESSAGES = {
    redeemed: () => t('pos.loyalty.error_already_used'),
    expired: () => t('pos.loyalty.error_expired'),
    cancelled: () => t('pos.loyalty.error_cancelled')
  }

  watch(
    () => props.modelValue,
    open => {
      if (open) {
        code.value = ''
        error.value = null
        preview.value = null
      } else {
        stopScan()
      }
    }
  )

  async function lookup() {
    const value = code.value.trim().toUpperCase()
    if (!value) return

    loading.value = true
    error.value = null
    preview.value = null
    try {
      const { data } = await showLoyaltyRedemptionApi(value)
      const redemption = data.data

      if (redemption.status !== 'pending') {
        error.value = (STATUS_MESSAGES[redemption.status] || (() => t('pos.loyalty.error_not_valid')))()
        return
      }

      preview.value = { code: value, reward: redemption.reward, discount: redemption.discount }
    } catch (e) {
      error.value = e?.response?.status === 404
        ? t('pos.loyalty.error_not_found')
        : e?.response?.data?.message || t('pos.loyalty.error_not_found')
    } finally {
      loading.value = false
    }
  }

  async function startScan() {
    error.value = null
    await nextTick()
    await startScanner('loyalty-qr-reader', decodedText => {
      code.value = decodedText
      stopScan()
      lookup()
    })
  }

  async function stopScan() {
    await stopScanner()
  }

  function apply() {
    if (!preview.value) return
    emit('applied', preview.value)
    close()
  }

  function close() {
    model.value = false
  }
</script>

<style scoped>
  .loyalty-qr-reader {
    width: 100%;
    min-height: 220px;
    border-radius: 12px;
    overflow: hidden;
  }
</style>
