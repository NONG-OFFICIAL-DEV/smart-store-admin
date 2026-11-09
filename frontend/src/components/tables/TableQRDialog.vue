<template>
  <AppDialog
    v-model="model"
    :max-width="480"
    :title="$t('tables.qr.title', { number: table?.table_number })"
  >
        <!-- Loading -->
        <div v-if="qrLoading" class="d-flex justify-center py-10">
          <v-progress-circular indeterminate color="primary" size="48" />
        </div>

        <!-- No QR yet -->
        <v-alert
          v-else-if="!qrData?.qr_image_url"
          type="warning"
          variant="tonal"
          rounded="lg"
          density="compact"
          :text="$t('tables.qr.not_generated')"
        >
          <template #append>
            <v-btn
              size="x-small"
              variant="tonal"
              color="warning"
              @click="regenerate"
            >
              {{ $t('tables.qr.generate') }}
            </v-btn>
          </template>
        </v-alert>

        <div v-else class="d-flex flex-column gap-4">
          <!-- QR Preview -->
          <div class="qr-preview-wrapper d-flex justify-center">
            <div class="qr-card" :style="{ background: bgColor }">
              <div class="qr-brand text-center mb-2">
                <span class="qr-restaurant-name" :style="{ color: fgColor }">
                  {{ qrData.branch_name || $t('tables.qr.restaurant_fallback') }}
                </span>
              </div>
              <v-img
                :src="qrData.qr_image_url"
                :width="qrSize"
                :height="qrSize"
              />

              <div class="mt-2 text-center">
                <span
                  :style="{ color: fgColor, fontSize: '14px', fontWeight: 700 }"
                >
                  {{ $t('tables.qr.table_label', { number: table?.table_number }) }}
                </span>
              </div>
              <div class="text-center mt-1">
                <span
                  :style="{ color: fgColor, opacity: 0.6, fontSize: '10px' }"
                >
                  {{ $t('tables.qr.scan_to_order') }}
                </span>
              </div>
              <div v-if="promoText" class="text-center mt-1">
                <span
                  :style="{ color: fgColor, opacity: 0.45, fontSize: '9px' }"
                >
                  {{ promoText }}
                </span>
              </div>
            </div>
          </div>

          <v-divider />

          <!-- Promo tagline (optional, printed below "Scan to order") -->
          <v-text-field
            v-model="promoText"
            :label="$t('tables.qr.promo_text_label')"
            :placeholder="$t('tables.qr.promo_text_placeholder')"
            :hint="$t('tables.qr.promo_text_hint')"
            persistent-hint
            variant="outlined"
            rounded="lg"
            density="comfortable"
            clearable
          />

        </div>

    <template #actions="{ loading }">
      <v-btn
        variant="tonal"
        rounded="lg"
        prepend-icon="mdi-refresh"
        :loading="qrLoading || loading"
        @click="regenerate"
      >
        {{ $t('btn.refresh') }}
      </v-btn>
      <v-spacer />
      <v-btn variant="tonal" rounded="lg" @click="close">{{ $t('btn.close') }}</v-btn>
      <v-btn-group color="primary" variant="flat" rounded="lg" divided size="default">
        <v-btn
          prepend-icon="mdi-download"
          :disabled="!qrData?.qr_image_url || qrLoading"
          @click="onDownloadQr(table)"
        >
          {{ $t('btn.download') }}
        </v-btn>
        <v-menu>
          <template #activator="{ props: menuProps }">
            <v-btn
              icon="mdi-chevron-down"
              :disabled="!qrData?.qr_image_url || qrLoading"
              v-bind="menuProps"
            />
          </template>
          <v-list density="compact">
            <v-list-item @click="onDownloadQr(table)">
              <template #prepend>
                <v-icon icon="mdi-image-outline" class="me-2" />
              </template>
              <v-list-item-title>{{ $t('tables.qr.download_card') }}</v-list-item-title>
              <v-list-item-subtitle>{{ $t('tables.qr.download_card_hint') }}</v-list-item-subtitle>
            </v-list-item>
            <v-list-item @click="onDownloadQrOnly(table)">
              <template #prepend>
                <v-icon icon="mdi-qrcode" class="me-2" />
              </template>
              <v-list-item-title>{{ $t('tables.qr.download_plain') }}</v-list-item-title>
              <v-list-item-subtitle>{{ $t('tables.qr.download_plain_hint') }}</v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-menu>
      </v-btn-group>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, onMounted, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTableStore } from '@/stores/tableStore'
  import { storeToRefs } from 'pinia'
  import { AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    table: { type: Object, default: null }
  })
  const emit = defineEmits(['update:modelValue'])

  const tableStore = useTableStore()
  const { qrData, qrLoading } = storeToRefs(tableStore)

  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  // Card is no longer user-styleable (size/colors were removed) — fixed
  // look for every tenant, only the promo tagline is customizable.
  const qrSize = 200
  const fgColor = '#1a1a2e'
  const bgColor = '#ffffff'

  // Promo text is store-wide branding, not per-table — and the dialog is
  // `v-if`-toggled in TableManagement.vue, so it's destroyed and recreated
  // on every open. Without this it would silently reset to blank each
  // time, which looks like the QR "regenerated" even though the actual QR
  // code/table on the backend never changed.
  const PROMO_TEXT_KEY = 'tableQrPromoText'
  const promoText = ref(localStorage.getItem(PROMO_TEXT_KEY) ?? '')

  watch(promoText, value => {
    localStorage.setItem(PROMO_TEXT_KEY, value ?? '')
  })

  const regenerate = () => {
    if (props.table?.id) tableStore.regenerateQr(props.table.id)
    tableStore.fetchQrCodeTable(props.table.id)
  }
  const onDownloadQr = (table) => {
    tableStore.downloadQr(table, {
      branchName: qrData.value?.branch_name || t('tables.qr.restaurant_fallback'),
      promoText: promoText.value,
      tableLabel: t('tables.qr.table_label', { number: table?.table_number }),
      scanLabel: t('tables.qr.scan_to_order'),
      size: qrSize,
      fgColor,
      bgColor
    })
  }
  const onDownloadQrOnly = (table) => {
    tableStore.downloadQrOnly(table)
  }

  const close = () => {
    model.value = false
  }

  onMounted(() => {
    if (props.table?.id) tableStore.fetchQrCodeTable(props.table.id)
  })
</script>

<style scoped>
  .qr-preview-wrapper {
    padding: 16px 0;
  }
  .qr-card {
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
    display: inline-flex;
    flex-direction: column;
    align-items: center;
  }
  .qr-restaurant-name {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }
  .gap-3 {
    gap: 12px;
  }
  .gap-4 {
    gap: 16px;
  }
</style>
