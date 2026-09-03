<template>
  <AppDialog
    v-model="model"
    :max-width="620"
    :title="t('stock.adjust.title')"
    :loading="loading"
  >
        <v-form ref="formRef">
          <!-- Type selector -->
          <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
            {{ t('stock.adjust.type') }}
          </div>
          <div class="d-flex gap-2 flex-wrap mb-3">
            <v-btn
              v-for="ty in types"
              :key="ty.value"
              size="small"
              rounded="lg"
              :variant="form.movement_type === ty.value ? 'flat' : 'tonal'"
              :color="form.movement_type === ty.value ? ty.color : 'default'"
              @click="form.movement_type = ty.value"
            >
              <v-icon start size="13" :icon="ty.icon" />
              {{ t(ty.labelKey) }}
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
            {{ t(currentType?.descKey ?? '') }}
          </v-alert>

          <v-row dense>
            <!-- Branch -->
            <v-col cols="12" sm="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ t('stock.adjust.branch') }}
                <span class="text-error">*</span>
              </label>
              <v-select
                v-model="form.branch_id"
                :items="branchList"
                item-title="name"
                item-value="id"
                :placeholder="t('stock.adjust.branch_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :rules="[r.required]"
              />
            </v-col>

            <!-- Product -->
            <v-col cols="12" sm="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ t('stock.adjust.product') }}
                <span class="text-error">*</span>
              </label>
              <v-autocomplete
                v-model="form.product_id"
                :items="productList"
                item-title="name"
                item-value="id"
                :placeholder="t('stock.adjust.product_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :readonly="!!presetProduct"
                :bg-color="presetProduct ? 'grey-lighten-4' : undefined"
                :rules="[r.required]"
                @update:model-value="onProductSelect"
              />
            </v-col>

            <!-- Unit -->
            <v-col v-if="availableUnits.length > 0" cols="12" sm="6">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ t('stock.adjust.unit') }}
              </label>
              <v-select
                v-model="form.product_unit_id"
                :items="availableUnits"
                item-title="unit_label"
                item-value="id"
                :placeholder="t('stock.adjust.unit_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                clearable
                :no-data-text="t('stock.adjust.no_units')"
              />
            </v-col>

            <!-- Quantity -->
            <v-col cols="12" :sm="availableUnits.length > 0 ? 6 : 12">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{
                  form.movement_type === 'count'
                    ? t('stock.adjust.new_count')
                    : t('stock.adjust.quantity')
                }}
                <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model.number="form.quantity"
                type="number"
                min="0.001"
                :placeholder="form.movement_type === 'count' ? '0' : '1'"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :rules="[r.required, r.positive]"
              />
              <p
                v-if="form.movement_type === 'count'"
                class="text-caption text-grey mt-1 ml-1"
              >
                {{ t('stock.adjust.count_hint') }}
              </p>
            </v-col>

            <!-- Stock preview -->
            <v-col v-if="selectedProduct && form.quantity" cols="12">
              <div class="stock-preview">
                <v-icon icon="mdi-package-variant" size="13" class="mr-1" />
                {{ t('stock.adjust.current_stock') }}:
                <strong>
                  {{ selectedProduct.stock_quantity }}
                  {{ selectedProduct.unit ?? 'pcs' }}
                </strong>
                <span class="mx-1">→</span>
                <strong :class="afterClass">{{ afterQty }}</strong>
              </div>
            </v-col>

            <!-- Notes -->
            <v-col cols="12">
              <label
                class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
              >
                {{ t('stock.adjust.notes') }}
              </label>
              <v-text-field
                v-model="form.notes"
                :placeholder="t('stock.adjust.notes_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details
              />
            </v-col>
          </v-row>
        </v-form>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ t('btn.cancel') }}
      </v-btn>
      <v-btn
        :color="currentType?.color ?? 'primary'"
        variant="flat"
        rounded="lg"
        :loading="loading"
        @click="save"
      >
        {{ t('stock.adjust.apply') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useMartProductStore } from '@/stores/martProductStore'
  import { AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()
  const martProductStore = useMartProductStore()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    presetProduct: { type: Object, default: null },
    presetType: { type: String, default: null },
    branchList: { type: Array, default: () => [] }
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const formRef = ref(null)

  const form = reactive({
    branch_id: null,
    product_id: null,
    product_unit_id: null,
    movement_type: 'adjustment_in',
    quantity: null,
    notes: ''
  })

  // ── Types ──────────────────────────────────────────────────────────────────
  const types = [
    {
      value: 'adjustment_in',
      labelKey: 'stock.adjust.types.add',
      color: 'success',
      icon: 'mdi-plus-circle',
      descKey: 'stock.adjust.desc.add'
    },
    {
      value: 'adjustment_out',
      labelKey: 'stock.adjust.types.remove',
      color: 'orange',
      icon: 'mdi-minus-circle',
      descKey: 'stock.adjust.desc.remove'
    },
    {
      value: 'waste',
      labelKey: 'stock.adjust.types.waste',
      color: 'error',
      icon: 'mdi-trash-can',
      descKey: 'stock.adjust.desc.waste'
    },
    {
      value: 'count',
      labelKey: 'stock.adjust.types.count',
      color: 'purple',
      icon: 'mdi-clipboard-check',
      descKey: 'stock.adjust.desc.count'
    }
  ]

  const currentType = computed(() =>
    types.find(t => t.value === form.movement_type)
  )

  // ── Lists ──────────────────────────────────────────────────────────────────
  const productList = computed(() => {
    const p = martProductStore.options
    return Array.isArray(p) ? p : (p?.data ?? [])
  })

  const selectedProduct = computed(() =>
    form.product_id
      ? (productList.value.find(p => p.id === form.product_id) ?? null)
      : null
  )

  const availableUnits = computed(() => {
    if (!selectedProduct.value) return []
    return (selectedProduct.value.active_units ?? []).map(u => ({
      ...u,
      unit_label: u.unit_label || u.unit_name
    }))
  })

  // ── Handlers ───────────────────────────────────────────────────────────────
  const onProductSelect = () => {
    form.product_unit_id = null
    form.quantity = null
    const baseUnit = availableUnits.value.find(u => u.is_base_unit)
    if (baseUnit) form.product_unit_id = baseUnit.id
  }

  // ── Stock preview ──────────────────────────────────────────────────────────
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
    if (afterQty.value <= (selectedProduct.value?.reorder_level ?? 0))
      return 'text-warning'
    return 'text-success'
  })

  // ── Preset watcher ─────────────────────────────────────────────────────────
  watch(
    () => props.modelValue,
    open => {
      if (open) {
        form.product_id = props.presetProduct?.id ?? null
        form.branch_id = props.branchList?.[0]?.id ?? null
        form.movement_type = props.presetType ?? 'adjustment_in'
        form.quantity = null
        form.notes = ''
        form.product_unit_id = null

        if (props.presetProduct?.active_units?.length) {
          const base = props.presetProduct.active_units.find(
            u => u.is_base_unit
          )
          if (base) form.product_unit_id = base.id
        }
      } else {
        formRef.value?.reset()
      }
    }
  )

  // ── Validation ─────────────────────────────────────────────────────────────
  const r = {
    required: v =>
      (v !== null && v !== undefined && v !== '') || t('validation.required'),
    positive: v => v > 0 || t('validation.positive')
  }

  // ── Actions ────────────────────────────────────────────────────────────────
  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('save', { ...form })
  }

  const close = () => {
    model.value = false
    formRef.value?.reset()
  }

  onMounted(() => {
    martProductStore.fetchMartProductOptions({
      per_page: -1
    })
  })
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
