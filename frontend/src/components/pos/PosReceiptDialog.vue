<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('pos.receipt.title')"
    :max-width="420"
    :persistent="false"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #actions="{ loading }">
      <v-spacer />
      <v-btn variant="tonal" rounded="lg" prepend-icon="mdi-printer-outline" @click="print">
        {{ $t('pos.receipt.print') }}
      </v-btn>
      <v-btn color="success" variant="flat" rounded="lg" @click="$emit('close')">
        {{ $t('pos.receipt.new_sale') }}
      </v-btn>
    </template>

    <PosReceiptContent :receipt="receipt" />
  </AppDialog>

  <!-- Print-only copy, teleported out of the dialog's scrollable card —
       the dialog's `scrollable` v-card-text clips an absolutely-positioned
       descendant at its own overflow boundary, so printing from inside it
       cut the receipt off after whatever fit in the on-screen scroll area.
       A body-level sibling has no such ancestor to be clipped by. -->
  <Teleport to="body">
    <div v-if="modelValue && receipt" id="receipt-print-root" class="pos-receipt-printable">
      <PosReceiptContent :receipt="receipt" />
    </div>
  </Teleport>
</template>

<script setup>
  import { watch } from 'vue'
  import { AppDialog } from '@nong-official-dev/core'
  import { useReceipt } from '@/utils/printReceipt'
  import PosReceiptContent from './PosReceiptContent.vue'

  const { print: printThermal } = useReceipt()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    receipt: { type: Object, default: null },
    // The branch's registered QZ Tray printer name — when set, printing
    // goes to the real thermal printer instead of the browser print dialog.
    printerName: { type: String, default: null }
  })

  const emit = defineEmits(['update:modelValue', 'close'])

  // The old wrapper's dismiss path (X button / Esc / backdrop) used to also
  // emit 'close'. The new AppDialog only emits update:modelValue, so
  // replicate that side effect here.
  watch(
    () => props.modelValue,
    val => {
      if (!val) emit('close')
    }
  )

  // Thermal print when the branch has a printer configured; otherwise (or
  // if the thermal print fails — e.g. QZ Tray not running) fall back to the
  // browser's print dialog so printing never silently does nothing.
  async function print() {
    if (props.printerName) {
      const ok = await printThermal(props.receipt, props.printerName)
      if (ok) return
    }
    window.print()
  }
</script>

<style>
  /* Off-screen at all times outside of print — this is a body-level
     sibling (see the Teleport above), not something the dialog's own
     v-if hides for us. The actual print layout for #receipt-print-root
     is shared across every receipt-printing dialog, see src/style.css. */
  .pos-receipt-printable {
    position: fixed;
    top: 0;
    left: -9999px;
  }
</style>
