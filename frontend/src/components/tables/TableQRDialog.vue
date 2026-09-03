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
            </div>
          </div>

          <v-divider />

          <!-- QR URL (readonly) -->
          <v-text-field
            :model-value="qrData.url"
            :label="$t('tables.qr.url_label')"
            variant="outlined"
            rounded="lg"
            readonly
            hide-details
            prepend-inner-icon="mdi-link-variant"
            append-inner-icon="mdi-content-copy"
            @click:append-inner="copyUrl"
          />

          <!-- Size slider -->
          <div>
            <label
              class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
            >
              {{ $t('tables.qr.preview_size', { size: qrSize }) }}
            </label>
            <v-slider
              v-model="qrSize"
              :min="120"
              :max="300"
              :step="10"
              color="primary"
              hide-details
              thumb-label
            />
          </div>

          <!-- Colors -->
          <v-row dense>
            <v-col cols="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-2 d-block"
              >
                {{ $t('tables.qr.qr_color') }}
              </label>
              <div class="d-flex align-center gap-2 flex-wrap">
                <div
                  v-for="c in presetColors"
                  :key="c"
                  class="color-chip"
                  :class="{ 'color-chip--active': fgColor === c }"
                  :style="{ background: c }"
                  @click="fgColor = c"
                />
              </div>
            </v-col>
            <v-col cols="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-2 d-block"
              >
                {{ $t('tables.qr.background') }}
              </label>
              <div class="d-flex align-center gap-2 flex-wrap">
                <div
                  v-for="c in presetBgColors"
                  :key="c"
                  class="color-chip color-chip--bordered"
                  :class="{ 'color-chip--active': bgColor === c }"
                  :style="{ background: c }"
                  @click="bgColor = c"
                />
              </div>
            </v-col>
          </v-row>
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
      <v-btn variant="outlined" rounded="lg" @click="close">{{ $t('btn.close') }}</v-btn>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-download"
        :disabled="!qrData?.qr_image_url || qrLoading"
        @click="onDownloadQr(table)"
      >
        {{ $t('btn.download') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useTableStore } from '@/stores/tableStore'
  import { storeToRefs } from 'pinia'
  import { AppDialog } from '@nong-official-dev/core'

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

  const qrSize = ref(200)
  const fgColor = ref('#1a1a2e')
  const bgColor = ref('#ffffff')

  const presetColors = [
    '#1a1a2e',
    '#1b4332',
    '#7c3aed',
    '#b91c1c',
    '#0369a1',
    '#000000'
  ]
  const presetBgColors = [
    '#ffffff',
    '#f8f9fa',
    '#fef9c3',
    '#f0fdf4',
    '#eff6ff',
    '#fdf4ff'
  ]

  const copyUrl = () => {
    if (qrData.value?.url) navigator.clipboard.writeText(qrData.value.url)
  }

  const regenerate = () => {
    if (props.table?.id) tableStore.regenerateQr(props.table.id)
    tableStore.fetchQrCodeTable(props.table.id)
  }
  const onDownloadQr = (table) => {
    tableStore.downloadQr(table)
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
  .color-chip {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    cursor: pointer;
    transition:
      transform 0.15s,
      box-shadow 0.15s;
    flex-shrink: 0;
  }
  .color-chip:hover {
    transform: scale(1.15);
  }
  .color-chip--active {
    box-shadow:
      0 0 0 2px white,
      0 0 0 4px currentColor;
    transform: scale(1.1);
  }
  .color-chip--bordered {
    border: 1px solid rgba(0, 0, 0, 0.15);
  }
  .gap-3 {
    gap: 12px;
  }
  .gap-4 {
    gap: 16px;
  }
</style>
