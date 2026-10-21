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
</template>

<script setup>
  import { watch } from 'vue'
  import { AppDialog } from '@nong-official-dev/core'
  import { useReceipt, printReceiptBrowser } from '@/utils/printReceipt'
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
</script>
