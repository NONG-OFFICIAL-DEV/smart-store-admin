<template>
  <AppDialog
    v-model="internalModel"
    :max-width="540"
    :title="`${actionTypeLabel} ${$t('form.stock')}`"
  >
    <!-- Current Stock -->
    <v-text-field
      :label="$t('stock.adjust.current_stock')"
      :model-value="form.currentQty"
      readonly
      prepend-inner-icon="mdi-database"
    />

    <!-- Adjustment Section -->
    <v-sheet class="pa-4 mb-4" rounded="lg" border>
      <v-text-field
        :label="$t('stock.adjust.adjustment_quantity')"
        v-model.number="form.adjustQty"
        type="number"
        prepend-inner-icon="mdi-swap-vertical"
        :hint="$t('stock.adjust.adjustment_quantity_hint')"
        persistent-hint
      />

      <v-select
        class="mt-3"
        :label="$t('stock.adjust.adjustment_reason')"
        v-model="form.reason"
        :items="reasons"
        item-title="title"
        item-value="value"
        prepend-inner-icon="mdi-alert-circle-outline"
        required
      />

      <v-textarea
        class="mt-3"
        :label="$t('stock.adjust.note_optional')"
        v-model="form.note"
        rows="2"
        auto-grow
      />
    </v-sheet>

    <!-- Result -->
    <v-alert variant="tonal" :type="resultType">
      {{ $t('stock.adjust.resulting_stock') }}
      <strong class="ml-2">
        {{ resultStock }}
      </strong>
    </v-alert>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        :loading="loading"
        :disabled="!canSubmit"
        @click="submit"
      >
        {{ $t('btn.confirm') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, watch, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()

  /* =======================
   Props & Emits
======================= */
  const props = defineProps({
    modelValue: Boolean,
    actionType: { type: String, required: true },
    stock: { type: Object, default: () => ({}) }
  })

  const emit = defineEmits(['update:modelValue', 'submitAction'])

  /* =======================
   Dialog Control
======================= */
  const internalModel = ref(false)

  watch(
    () => props.modelValue,
    val => (internalModel.value = val)
  )

  watch(internalModel, val => {
    emit('update:modelValue', val)

    if (val && props.stock) {
      const rawStock = props.stock.raw ?? props.stock

      form.value = {
        currentQty: Number(rawStock.quantity ?? 0),
        adjustQty: 0,
        reason: 'Damaged',
        note: ''
      }
    }
  })

  /* =======================
   Form State
======================= */
  const form = ref({
    currentQty: 0,
    adjustQty: 0,
    reason: 'Damaged',
    note: ''
  })

  /* =======================
   Static Data
======================= */
  const reasons = computed(() => [
    { value: 'Damaged', title: t('stock.adjust.reasons.damaged') },
    { value: 'Expired', title: t('stock.adjust.reasons.expired') },
    { value: 'Lost / Theft', title: t('stock.adjust.reasons.lost_theft') },
    {
      value: 'Count Correction',
      title: t('stock.adjust.reasons.count_correction')
    },
    { value: 'POS Error', title: t('stock.adjust.reasons.pos_error') }
  ])

  /* =======================
   Computed
======================= */
  const actionTypeLabel = computed(
    () => props.actionType.charAt(0).toUpperCase() + props.actionType.slice(1)
  )

  const resultStock = computed(() => {
    return form.value.currentQty + form.value.adjustQty
  })

  const canSubmit = computed(() => {
    return form.value.adjustQty !== 0 && !!form.value.reason
  })

  const resultType = computed(() => {
    if (form.value.adjustQty > 0) return 'success'
    if (form.value.adjustQty < 0) return 'warning'
    return 'info'
  })

  /* =======================
   Methods
======================= */
  function close() {
    internalModel.value = false
  }

  function submit() {
    const rawStock = props.stock.raw ?? props.stock

    emit('submitAction', {
      stockId: rawStock.product_id,
      quantity: form.value.adjustQty,
      reason: form.value.reason,
      note: form.value.note
    })

    close()
  }
</script>
