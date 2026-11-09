<template>
  <div class="cd-qr">
    <div class="cd-qr__left">
      <div class="cd-qr__label">
        Amount to pay <span class="cd-qr__label-km">&middot; ចំនួនត្រូវបង់</span>
      </div>
      <div class="cd-qr__amount-usd">${{ totalUsd.toFixed(2) }}</div>
      <div class="cd-qr__amount-khr">{{ Math.round(totalKhr).toLocaleString() }} &#6107;</div>

      <ol class="cd-qr__steps">
        <li>Open your bank app</li>
        <li>Scan the KHQR code</li>
        <li>Show the confirmation to the cashier</li>
      </ol>
    </div>

    <div class="cd-qr__right">
      <div class="cd-qr__box">
        <img v-if="qrSrc" :src="qrSrc" class="cd-qr__image" alt="QR code" />
        <div v-else class="cd-qr__placeholder">QR not configured</div>
      </div>
      <div class="cd-qr__scan-label">
        Scan to pay <span class="cd-qr__label-km">&middot; ស្កេនដើម្បីបង់</span>
      </div>
      <div class="cd-qr__hint">Works with any KHQR bank app</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useQrCodeImage } from '@/composables/useQrCode'

const props = defineProps({
  totalUsd: { type: Number, default: 0 },
  totalKhr: { type: Number, default: 0 },
  payment: { type: Object, default: () => ({}) }
})

const payloadRef = computed(() => props.payment?.qrPayload ?? null)
const { dataUrl } = useQrCodeImage(payloadRef)

// Real KHQR payload (once generated) wins over the static merchant image —
// see buildCustomerDisplayState()'s docblock for why the static image is
// the only option today.
const qrSrc = computed(() => dataUrl.value ?? props.payment?.qrImageUrl ?? null)
</script>

<style scoped>
.cd-qr {
  display: flex;
  width: 100%;
  height: 100%;
  background: #f7f5f0;
}

.cd-qr__left {
  flex: 0 0 55%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: clamp(24px, 4vw, 64px);
  box-sizing: border-box;
}

.cd-qr__label {
  font-family: 'Inter', sans-serif;
  font-size: clamp(16px, 1.8vw, 24px);
  font-weight: 700;
  color: #333;
}

.cd-qr__label-km {
  font-family: 'Noto Sans Khmer', sans-serif;
  font-weight: 500;
  color: #777;
}

.cd-qr__amount-usd {
  font-family: 'Inter', sans-serif;
  font-weight: 800;
  font-size: clamp(52px, 7.5vw, 96px);
  line-height: 1.05;
  color: var(--cd-teal);
  margin-top: clamp(8px, 1.5vw, 16px);
}

.cd-qr__amount-khr {
  font-size: clamp(20px, 2.2vw, 32px);
  color: #555;
  margin-top: 4px;
}

.cd-qr__steps {
  margin-top: clamp(24px, 3.5vw, 48px);
  padding-left: 1.2em;
  display: flex;
  flex-direction: column;
  gap: clamp(8px, 1.2vw, 14px);
  font-size: clamp(15px, 1.5vw, 20px);
  color: #333;
}

.cd-qr__right {
  flex: 1 1 45%;
  background: var(--cd-teal);
  color: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: clamp(24px, 3vw, 40px);
  box-sizing: border-box;
  text-align: center;
}

.cd-qr__box {
  width: min(380px, 70%);
  aspect-ratio: 1 / 1;
  background: #fff;
  border-radius: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.cd-qr__image {
  width: 90%;
  height: 90%;
  object-fit: contain;
}

.cd-qr__placeholder {
  color: #999;
  font-size: 15px;
  padding: 16px;
}

.cd-qr__scan-label {
  margin-top: clamp(16px, 2.5vw, 28px);
  font-weight: 700;
  font-size: clamp(16px, 1.8vw, 24px);
}

.cd-qr__scan-label .cd-qr__label-km {
  color: rgba(255, 255, 255, 0.85);
}

.cd-qr__hint {
  margin-top: 6px;
  font-size: clamp(13px, 1.2vw, 16px);
  opacity: 0.85;
}
</style>
