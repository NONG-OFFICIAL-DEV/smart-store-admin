<template>
  <v-dialog v-model="model" max-width="460" persistent>
    <v-card rounded="xl" border elevation="0">
      <v-card-title class="d-flex align-center justify-space-between pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar
            :color="currentType?.color ?? 'primary'"
            variant="tonal"
            size="40"
            rounded="lg"
          >
            <v-icon :icon="currentType?.icon ?? 'mdi-tune'" size="20" />
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold">Stock Adjustment</div>
            <div class="text-caption text-medium-emphasis">
              Manual stock correction
            </div>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-5">
        <v-form ref="formRef">
          <!-- Type selector -->
          <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
            TYPE
          </div>
          <div class="d-flex gap-2 flex-wrap mb-3">
            <v-btn
              v-for="t in types"
              :key="t.value"
              size="small"
              rounded="lg"
              :variant="form.movement_type === t.value ? 'flat' : 'tonal'"
              :color="form.movement_type === t.value ? t.color : 'default'"
              @click="form.movement_type = t.value"
            >
              <v-icon start size="13" :icon="t.icon" />
              {{ t.label }}
            </v-btn>
          </div>

          <!-- Type description -->
          <v-alert
            :color="currentType?.color"
            variant="tonal"
            density="compact"
            rounded="lg"
            class="mb-4 text-caption"
          >
            {{ currentType?.desc }}
          </v-alert>

          <!-- Product (read-only if preset) -->
          <v-autocomplete
            v-model="form.product_id"
            :items="productList"
            item-title="name"
            item-value="id"
            label="Product *"
            variant="outlined"
            density="comfortable"
            rounded="lg"
            :rules="[r.required]"
            class="mb-2"
            :readonly="!!presetProduct"
            :bg-color="presetProduct ? 'grey-lighten-4' : undefined"
            @update:model-value="onProductChange"
          />

          <!-- Current stock preview -->
          <div v-if="selectedProduct" class="stock-preview mb-4">
            <v-icon icon="mdi-package-variant" size="13" class="mr-1" />
            Current stock:
            <strong>
              {{ selectedProduct.stock_quantity }}
              {{ selectedProduct.unit ?? 'pcs' }}
            </strong>
            <template v-if="form.quantity">
              <span class="mx-1">→</span>
              <strong :class="afterClass">{{ afterQty }}</strong>
            </template>
          </div>

          <!-- Quantity -->
          <v-text-field
            v-model.number="form.quantity"
            type="number"
            min="0.001"
            :label="
              form.movement_type === 'count'
                ? 'New Stock Count *'
                : 'Quantity *'
            "
            variant="outlined"
            density="comfortable"
            rounded="lg"
            :rules="[r.required, r.positive]"
            :hint="
              form.movement_type === 'count'
                ? 'Sets stock to this exact value from physical count'
                : undefined
            "
            persistent-hint
          />

          <!-- Notes -->
          <v-text-field
            v-model="form.notes"
            label="Notes / Reason"
            variant="outlined"
            density="comfortable"
            rounded="lg"
            placeholder="e.g. Physical count, damaged goods..."
            class="mt-3"
            hide-details
          />
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-5 gap-3">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
          Cancel
        </v-btn>
        <v-spacer />
        <v-btn
          :color="currentType?.color ?? 'primary'"
          variant="flat"
          rounded="lg"
          :loading="loading"
          @click="save"
        >
          Apply Adjustment
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    presetProduct: { type: Object, default: null }, // pre-select a product
    products: { type: Object, default: [] }, // all products
    presetType: { type: String, default: null } // pre-select a type
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const formRef = ref(null)

  const productList = computed(() => {
    const p = props.products
    return Array.isArray(p) ? p : (p?.data ?? [])
  })

  const form = reactive({
    product_id: null,
    movement_type: 'adjustment_in',
    quantity: null,
    notes: ''
  })

  const types = [
    {
      value: 'adjustment_in',
      label: 'Add',
      color: 'success',
      icon: 'mdi-plus-circle',
      desc: 'Add stock found, received without PO, or miscounted.'
    },
    {
      value: 'adjustment_out',
      label: 'Remove',
      color: 'orange',
      icon: 'mdi-minus-circle',
      desc: 'Remove stock due to discrepancy, theft, or other loss.'
    },
    {
      value: 'waste',
      label: 'Waste',
      color: 'error',
      icon: 'mdi-trash-can',
      desc: 'Write off expired, damaged or unsellable stock.'
    },
    {
      value: 'count',
      label: 'Stock Count',
      color: 'purple',
      icon: 'mdi-clipboard-check',
      desc: 'Set exact stock level from a physical count.'
    }
  ]

  const currentType = computed(() =>
    types.find(t => t.value === form.movement_type)
  )
  const selectedProduct = computed(() =>
    form.product_id
      ? productList.value.find(p => p.id === form.product_id)
      : null
  )

  const afterQty = computed(() => {
    if (!selectedProduct.value || !form.quantity) return null
    const current = parseFloat(selectedProduct.value.stock_quantity ?? 0)
    if (form.movement_type === 'count') return form.quantity
    if (form.movement_type === 'adjustment_in')
      return +(current + form.quantity).toFixed(3)
    return +(current - form.quantity).toFixed(3)
  })

  const afterClass = computed(() => {
    if (afterQty.value === null) return ''
    if (afterQty.value < 0) return 'text-error'
    if (afterQty.value <= (selectedProduct.value?.reorder_level ?? 5))
      return 'text-warning'
    return 'text-success'
  })

  // Apply presets when dialog opens
  watch(
    () => props.modelValue,
    open => {
      if (open) {
        form.product_id = props.presetProduct?.id ?? null
        form.movement_type = props.presetType ?? 'adjustment_in'
        form.quantity = null
        form.notes = ''
      }
    }
  )

  const onProductChange = () => {
    form.quantity = null
  }

  const r = {
    required: v => (v !== null && v !== undefined && v !== '') || 'Required',
    positive: v => v > 0 || 'Must be greater than 0'
  }

  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('save', { ...form })
  }

  const close = () => {
    model.value = false
    formRef.value?.reset()
  }
</script>

<style scoped>
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
  .stock-preview {
    font-size: 12px;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 12px;
  }
</style>
