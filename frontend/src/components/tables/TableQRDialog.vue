<template>
  <v-dialog v-model="model" max-width="480" persistent>
    <v-card rounded="lg">
      <!-- Header -->
      <v-card-title
        class="d-flex align-center justify-space-between px-6 pt-5 pb-4"
      >
        <div class="d-flex align-center gap-3">
          <v-avatar color="primary" variant="tonal" size="40" rounded="md">
            <v-icon icon="mdi-qrcode" size="20" />
          </v-avatar>
          <div>
            <p class="text-body-1 font-weight-semibold text-grey-darken-3 mb-0">
              Table {{ table?.table_number }} — QR Code
            </p>
            <p class="text-caption text-grey mb-0">
              Preview, customize and download
            </p>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
      </v-card-title>

      <v-divider />

      <v-card-text class="px-6 py-5">
        <div class="d-flex flex-column gap-4">
          <!-- QR Preview -->
          <div class="qr-preview-wrapper d-flex justify-center">
            <div class="qr-card" :style="qrCardStyle">
              <!-- Optional logo/brand top -->
              <div class="qr-brand text-center mb-2">
                <span class="qr-restaurant-name" :style="{ color: fgColor }">
                  {{ branchName || 'Restaurant' }}
                </span>
              </div>

              <!-- Canvas where QR renders -->
              <canvas ref="qrCanvas" class="qr-canvas" />

              <!-- Table label below -->
              <div class="qr-table-label mt-2 text-center">
                <span
                  :style="{
                    color: fgColor,
                    fontSize: `${labelSize}px`,
                    fontWeight: 700
                  }"
                >
                  Table {{ table?.table_number }}
                </span>
              </div>

              <!-- Scan hint -->
              <div class="qr-scan-hint text-center mt-1">
                <span
                  :style="{ color: fgColor, opacity: 0.6, fontSize: '10px' }"
                >
                  Scan to order
                </span>
              </div>
            </div>
          </div>

          <!-- Customization Controls -->
          <v-divider />

          <!-- QR Size -->
          <div>
            <label
              class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
            >
              QR Size: {{ qrSize }}px
            </label>
            <v-slider
              v-model="qrSize"
              :min="120"
              :max="400"
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
                QR Color
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
                <v-menu :close-on-content-click="false">
                  <template #activator="{ props }">
                    <div
                      v-bind="props"
                      class="color-chip color-chip--custom"
                      :style="{ background: fgColor }"
                    >
                      <v-icon
                        icon="mdi-eyedropper-variant"
                        size="12"
                        color="white"
                      />
                    </div>
                  </template>
                  <v-color-picker
                    v-model="fgColor"
                    :modes="['hex']"
                    hide-inputs
                    elevation="4"
                  />
                </v-menu>
              </div>
            </v-col>

            <v-col cols="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-2 d-block"
              >
                Background
              </label>
              <div class="d-flex align-center gap-2 flex-wrap">
                <div
                  v-for="c in presetBgColors"
                  :key="c"
                  class="color-chip"
                  :class="[
                    'color-chip--bordered',
                    { 'color-chip--active': bgColor === c }
                  ]"
                  :style="{ background: c }"
                  @click="bgColor = c"
                />
                <v-menu :close-on-content-click="false">
                  <template #activator="{ props }">
                    <div
                      v-bind="props"
                      class="color-chip color-chip--custom color-chip--bordered"
                      :style="{ background: bgColor }"
                    >
                      <v-icon
                        icon="mdi-eyedropper-variant"
                        size="12"
                        :color="bgColor === '#ffffff' ? 'grey' : 'white'"
                      />
                    </div>
                  </template>
                  <v-color-picker
                    v-model="bgColor"
                    :modes="['hex']"
                    hide-inputs
                    elevation="4"
                  />
                </v-menu>
              </div>
            </v-col>
          </v-row>

          <!-- Download size -->
          <div>
            <label
              class="text-body-2 font-weight-medium text-grey-darken-2 mb-2 d-block"
            >
              Download Size
            </label>
            <v-btn-toggle
              v-model="downloadSize"
              color="primary"
              variant="outlined"
              density="compact"
              rounded="lg"
              mandatory
            >
              <v-btn value="512" class="text-none px-3">512px</v-btn>
              <v-btn value="1024" class="text-none px-3">1024px</v-btn>
              <v-btn value="2048" class="text-none px-3">2048px (Print)</v-btn>
            </v-btn-toggle>
          </div>
        </div>
      </v-card-text>

      <v-divider />

      <v-card-actions class="px-6 py-4 gap-3">
        <v-btn
          variant="outlined"
          rounded="lg"
          class="flex-grow-1"
          @click="close"
        >
          Close
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          class="flex-grow-1"
          prepend-icon="mdi-download"
          @click="downloadQR"
        >
          Download PNG
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch, nextTick, onMounted } from 'vue'

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    table: { type: Object, default: null },
    // Base URL for the QR — e.g. https://menu.yourapp.com/table/
    menuBaseUrl: {
      type: String,
      default: () => window.location.origin + '/menu/table/'
    },
    branchName: { type: String, default: '' }
  })

  const emit = defineEmits(['update:modelValue'])

  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  // ── State ─────────────────────────────────────────────────────────────────────
  const qrCanvas = ref(null)
  const qrSize = ref(200)
  const fgColor = ref('#1a1a2e')
  const bgColor = ref('#ffffff')
  const downloadSize = ref('1024')
  const labelSize = ref(14)

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

  // ── QR URL ────────────────────────────────────────────────────────────────────
  const qrUrl = computed(() =>
    props.table ? `${props.menuBaseUrl}${props.table.id}` : ''
  )

  // ── Card style ────────────────────────────────────────────────────────────────
  const qrCardStyle = computed(() => ({
    background: bgColor.value,
    padding: '20px',
    borderRadius: '16px',
    boxShadow: '0 4px 24px rgba(0,0,0,0.10)',
    display: 'inline-flex',
    flexDirection: 'column',
    alignItems: 'center'
  }))

  // ── QRCode library (loaded from CDN) ─────────────────────────────────────────
  let QRCode = null

  const loadQRLib = () =>
    new Promise(resolve => {
      if (window.QRCode) {
        QRCode = window.QRCode
        resolve()
        return
      }
      const script = document.createElement('script')
      script.src =
        'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'
      script.onload = () => {
        QRCode = window.QRCode
        resolve()
      }
      document.head.appendChild(script)
    })

  // ── Render QR to canvas ───────────────────────────────────────────────────────
  const renderQR = async () => {
    if (!qrCanvas.value || !qrUrl.value) return

    await loadQRLib()

    // QRCode.js targets a div, so we use a temp div then copy to our canvas
    const tempDiv = document.createElement('div')
    document.body.appendChild(tempDiv)
    tempDiv.style.visibility = 'hidden'
    tempDiv.style.position = 'fixed'

    new QRCode(tempDiv, {
      text: qrUrl.value,
      width: qrSize.value,
      height: qrSize.value,
      colorDark: fgColor.value,
      colorLight: bgColor.value,
      correctLevel: QRCode.CorrectLevel.H
    })

    await nextTick()

    const srcCanvas = tempDiv.querySelector('canvas')
    if (srcCanvas) {
      const ctx = qrCanvas.value.getContext('2d')
      qrCanvas.value.width = qrSize.value
      qrCanvas.value.height = qrSize.value
      ctx.drawImage(srcCanvas, 0, 0)
    }

    document.body.removeChild(tempDiv)
  }

  // ── Download ──────────────────────────────────────────────────────────────────
  const downloadQR = async () => {
    await loadQRLib()

    const size = Number(downloadSize.value)

    // Create high-res off-screen canvas with full card (brand + qr + label)
    const padding = Math.round(size * 0.08)
    const labelH = Math.round(size * 0.12)
    const brandH = Math.round(size * 0.08)
    const hintH = Math.round(size * 0.06)
    const totalH = size + padding * 2 + brandH + labelH + hintH
    const totalW = size + padding * 2

    const offscreen = document.createElement('canvas')
    offscreen.width = totalW
    offscreen.height = totalH
    const ctx = offscreen.getContext('2d')

    // Background
    ctx.fillStyle = bgColor.value
    ctx.roundRect ? ctx.roundRect(0, 0, totalW, totalH, 24) : null
    ctx.fill()

    // Brand name
    ctx.fillStyle = fgColor.value
    ctx.font = `bold ${brandH * 0.65}px sans-serif`
    ctx.textAlign = 'center'
    ctx.fillText(
      props.branchName || 'Restaurant',
      totalW / 2,
      padding + brandH * 0.75
    )

    // QR code
    const tempDiv = document.createElement('div')
    document.body.appendChild(tempDiv)
    tempDiv.style.visibility = 'hidden'
    tempDiv.style.position = 'fixed'

    new QRCode(tempDiv, {
      text: qrUrl.value,
      width: size,
      height: size,
      colorDark: fgColor.value,
      colorLight: bgColor.value,
      correctLevel: QRCode.CorrectLevel.H
    })

    await nextTick()

    const srcCanvas = tempDiv.querySelector('canvas')
    if (srcCanvas) {
      ctx.drawImage(srcCanvas, padding, padding + brandH)
    }
    document.body.removeChild(tempDiv)

    // Table label
    ctx.fillStyle = fgColor.value
    ctx.font = `bold ${labelH * 0.55}px sans-serif`
    ctx.textAlign = 'center'
    ctx.fillText(
      `Table ${props.table?.table_number}`,
      totalW / 2,
      padding + brandH + size + labelH * 0.7
    )

    // Scan hint
    ctx.font = `${hintH * 0.55}px sans-serif`
    ctx.globalAlpha = 0.6
    ctx.fillText(
      'Scan to order',
      totalW / 2,
      padding + brandH + size + labelH + hintH * 0.65
    )
    ctx.globalAlpha = 1

    // Trigger download
    const link = document.createElement('a')
    link.download = `table-${props.table?.table_number}-qr-${size}px.png`
    link.href = offscreen.toDataURL('image/png')
    link.click()
  }

  // ── Watchers ──────────────────────────────────────────────────────────────────
  watch([qrSize, fgColor, bgColor, () => props.table], async () => {
    await nextTick()
    renderQR()
  })

  watch(
    () => props.modelValue,
    async val => {
      if (val) {
        await nextTick()
        renderQR()
      }
    }
  )

  const close = () => {
    model.value = false
  }
</script>

<style scoped>
  .qr-preview-wrapper {
    padding: 16px 0;
  }

  .qr-canvas {
    display: block;
    border-radius: 4px;
  }

  .qr-brand {
    width: 100%;
  }

  .qr-restaurant-name {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }

  /* Color chips */
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

  .color-chip--custom {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px dashed rgba(255, 255, 255, 0.5);
  }
</style>
