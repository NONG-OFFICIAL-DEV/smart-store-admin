import { ref, watch } from 'vue'
import QRCode from 'qrcode'

/**
 * Renders a text payload (a real KHQR string, once this app ever generates
 * one) to a data URL. Nothing in the backend produces that payload today —
 * see useCustomerDisplay.js's buildCustomerDisplayState() — so this only
 * ever fires once `payment.qrPayload` is actually populated; until then
 * the QR screen just shows the tenant's static merchant image instead.
 */
export function useQrCodeImage(payloadRef) {
  const dataUrl = ref(null)

  watch(
    payloadRef,
    async payload => {
      if (!payload) {
        dataUrl.value = null
        return
      }
      try {
        dataUrl.value = await QRCode.toDataURL(payload, {
          width: 380,
          margin: 1,
          color: { dark: '#0F6E75', light: '#FFFFFF' }
        })
      } catch {
        dataUrl.value = null
      }
    },
    { immediate: true }
  )

  return { dataUrl }
}
