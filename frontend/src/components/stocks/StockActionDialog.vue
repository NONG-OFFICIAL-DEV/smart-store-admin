<template>
  <v-dialog v-model="internalModel" max-width="540px" persistent>
    <v-card rounded="lg">
      <!-- Header -->
      <v-card-title class="d-flex align-center bg-primary text-white">
        <v-icon class="mr-2">mdi-tune</v-icon>
        {{ actionTypeLabel }} Stock
        <v-spacer />
        <v-btn icon variant="text" @click="close">
          <v-icon color="white">mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <!-- Content -->
      <v-card-text class="pt-6">
        <!-- Current Stock -->
        <v-text-field
          label="Current Stock"
          :model-value="form.currentQty"
          readonly
          prepend-inner-icon="mdi-database"
        />

        <!-- Adjustment Section -->
        <v-sheet class="pa-4 mb-4" rounded="lg" border>
          <v-text-field
            label="Adjustment Quantity"
            v-model.number="form.adjustQty"
            type="number"
            prepend-inner-icon="mdi-swap-vertical"
            hint="Negative (-) to decrease, Positive (+) to increase"
            persistent-hint
          />

          <v-select
            class="mt-3"
            label="Adjustment Reason"
            v-model="form.reason"
            :items="reasons"
            prepend-inner-icon="mdi-alert-circle-outline"
            required
          />

          <v-textarea
            class="mt-3"
            label="Note (optional)"
            v-model="form.note"
            rows="2"
            auto-grow
          />
        </v-sheet>

        <!-- Result -->
        <v-alert variant="tonal" :type="resultType">
          Resulting Stock:
          <strong class="ml-2">
            {{ resultStock }}
          </strong>
        </v-alert>
      </v-card-text>

      <!-- Actions -->
      <v-card-actions class="px-6 pb-4">
        <v-spacer />
        <v-btn variant="outlined" @click="close">Cancel</v-btn>
        <v-btn color="primary" :disabled="!canSubmit" @click="submit">
          Confirm
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, watch, computed } from 'vue'

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
  const reasons = [
    'Damaged',
    'Expired',
    'Lost / Theft',
    'Count Correction',
    'POS Error'
  ]

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
