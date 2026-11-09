<template>
  <AppDialog
    :model-value="modelValue"
    :title="qrPending ? $t('pos.checkout.qr_dialog_title') : $t('pos.receipt.title')"
    :max-width="qrPending ? 640 : 420"
    :persistent="false"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #actions="{ loading }">
      <template v-if="qrPending">
        <v-spacer />
        <v-btn
          variant="tonal"
          rounded="lg"
          prepend-icon="mdi-printer-outline"
          :loading="submitting"
          @click="$emit('confirm', 'print')"
        >
          {{ $t('pos.checkout.qr_dialog_confirm_print') }}
        </v-btn>
        <v-btn
          color="success"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-check-circle-outline"
          :loading="submitting"
          @click="$emit('confirm', 'new_sale')"
        >
          {{ $t('pos.checkout.qr_dialog_confirm_new_sale') }}
        </v-btn>
      </template>
      <template v-else>
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" prepend-icon="mdi-printer-outline" @click="print">
          {{ $t('pos.receipt.print') }}
        </v-btn>
        <v-btn color="success" variant="flat" rounded="lg" @click="$emit('close')">
          {{ $t('pos.receipt.new_sale') }}
        </v-btn>
      </template>
    </template>

    <template v-if="qrPending">
      <div class="d-flex ga-4" :class="{ 'flex-column': smAndDown }">
        <!-- Order list — the same items still sitting in the cart; there's
             no receipt yet since the order hasn't submitted. -->
        <div class="flex-grow-1" style="min-width: 0">
          <div class="pos-receipt-qr__items mb-2">
            <div
              v-for="item in qrItems"
              :key="item.key"
              class="d-flex justify-space-between text-body-2 mb-2"
            >
              <span>{{ item.qty }} × {{ item.name }}</span>
              <span class="font-weight-medium">{{ formatMoney(item.unit_price * item.qty) }}</span>
            </div>
          </div>
          <v-divider class="mb-2" />
          <div class="d-flex justify-space-between text-h6 font-weight-bold">
            <span>{{ $t('order.total') }}</span>
            <span>{{ formatMoney(qrTotal) }}</span>
          </div>
        </div>

        <!-- QR code — to the right of the order list on desktop/tablet,
             stacked below it on mobile (fullscreen) where there's no room
             for two columns. -->
        <div class="d-flex flex-column align-center flex-shrink-0">
          <div class="pos-receipt-qr__box mb-2">
            <img v-if="qrImageUrl" :src="qrImageUrl" alt="QR code" class="pos-receipt-qr__image" />
            <div v-else class="pos-receipt-qr__placeholder text-caption text-medium-emphasis">
              {{ $t('pos.checkout.qr_dialog_not_configured') }}
            </div>
          </div>
          <div class="text-caption text-medium-emphasis text-center" style="max-width: 220px">
            {{ $t('pos.checkout.qr_dialog_hint') }}
          </div>
        </div>
      </div>
    </template>
    <PosReceiptContent v-else :receipt="receipt" />
  </AppDialog>
</template>

<script setup>
  import { watch } from 'vue'
  import { useDisplay } from 'vuetify'
  import { AppDialog } from '@nong-official-dev/core'
  import { useReceipt, printReceiptBrowser } from '@/utils/printReceipt'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import PosReceiptContent from './PosReceiptContent.vue'

  const { print: printThermal } = useReceipt()
  const { format: formatMoney } = useCurrency()
  const { smAndDown } = useDisplay()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    receipt: { type: Object, default: null },
    // The branch's registered QZ Tray printer name — when set, printing
    // goes to the real thermal printer instead of the browser print dialog.
    printerName: { type: String, default: null },
    // QR payment mode — while true this dialog shows the order's items +
    // QR code + total with "Confirm & Print"/"Confirm & New Sale" buttons,
    // instead of the printed receipt. This view never swaps to the normal
    // receipt layout mid-dialog: the caller (see FoodPos/RetailPos's
    // submitOrder) submits the order, optionally prints in the background
    // via the exposed print(), then closes the dialog outright — so
    // nothing visibly changes between "before confirm" and "after confirm."
    qrPending: { type: Boolean, default: false },
    qrImageUrl: { type: String, default: null },
    qrTotal: { type: Number, default: 0 },
    // The cart's own items (posStore.items) — shown alongside the QR code
    // since there's no receipt yet to pull a line-item list from.
    qrItems: { type: Array, default: () => [] },
    // Loading state for the "Confirm receive" button, owned by the caller
    // (it's the same `submitting` flag gating the rest of checkout).
    submitting: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'close', 'confirm', 'cancel'])

  // The old wrapper's dismiss path (X button / Esc / backdrop) used to also
  // emit 'close'. The new AppDialog only emits update:modelValue, so
  // replicate that side effect here — but while qrPending, dismissing
  // means "never confirmed", not "done viewing the receipt", so it needs
  // its own event (the caller reverts the customer display for 'cancel',
  // not for 'close').
  watch(
    () => props.modelValue,
    val => {
      if (!val) emit(props.qrPending ? 'cancel' : 'close')
    }
  )

  // Thermal print when the branch has a printer configured; otherwise (or
  // if the thermal print fails — e.g. QZ Tray not running) fall back to a
  // hidden-iframe browser print (see printReceiptBrowser() — never
  // window.print() on the main page, which would print this still-open
  // AppDialog's own overlay along with the receipt). The receipt here is
  // always already paid (checkout completed before this dialog opens).
  async function print() {
    if (props.printerName) {
      const ok = await printThermal(props.receipt, props.printerName)
      if (ok) return
    }
    await printReceiptBrowser(props.receipt, 'receipt')
  }

  // Lets the caller trigger a print right after a QR "Confirm & Print"
  // click, once the order has actually submitted and `receipt` is
  // populated — the dialog itself doesn't know when that happens, since
  // submission lives in the parent page.
  defineExpose({ print })
</script>

<style scoped>
  .pos-receipt-qr__box {
    width: 220px;
    aspect-ratio: 1 / 1;
    border-radius: 16px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .pos-receipt-qr__image {
    width: 92%;
    height: 92%;
    object-fit: contain;
  }

  .pos-receipt-qr__placeholder {
    padding: 16px;
    text-align: center;
  }
</style>
