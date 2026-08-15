<template>
  <AppDialog
    v-model="model"
    :max-width="520"
    icon="mdi-package-variant"
    color="primary"
    :title="isEdit ? t('unit.title_edit') : t('unit.title_add')"
    :subtitle="t('unit.subtitle_add')"
    :loading="loading"
    submit-icon="mdi-content-save"
    :submit-text="isEdit ? t('unit.save') : t('unit.add')"
    body-class="pa-5"
    @close="close"
    @submit="save"
  >
    <v-form ref="formRef">
          <v-row dense>
            <!-- Unit name — combobox from DB + fallback to common -->
            <v-col cols="12" sm="6">
              <v-combobox
                v-model="form.unit_name"
                :items="unitNameOptions"
                :loading="loadingNames"
                item-title="title"
                item-value="title"
                :label="t('unit.unit_name') + ' *'"
                placeholder="can, pack, box, kg..."
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                clearable
                @update:model-value="onUnitNameChange"
              >
                <!-- Item row in dropdown -->
                <template #item="{ item, props: iProps }">
                  <v-list-item v-bind="iProps" :subtitle="item.raw?.subtitle">
                    <template #append>
                      <v-chip
                        v-if="item.raw?.source === 'db'"
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        rounded="lg"
                      >
                        {{ t('unit.existing') }}
                      </v-chip>
                      <v-chip
                        v-else
                        size="x-small"
                        color="grey"
                        variant="tonal"
                        rounded="lg"
                      >
                        {{ t('unit.common') }}
                      </v-chip>
                    </template>
                  </v-list-item>
                </template>

                <!-- When user typed something not in list -->
                <template #no-data>
                  <v-list-item>
                    <v-list-item-title class="text-caption">
                      {{ t('unit.press_enter_before') }}
                      <kbd>Enter</kbd>
                      {{ t('unit.press_enter_after') }} "
                      <strong>{{ form.unit_name }}</strong>
                      "
                    </v-list-item-title>
                  </v-list-item>
                </template>
              </v-combobox>
            </v-col>

            <!-- Unit label -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.unit_label"
                :label="t('unit.display_label')"
                placeholder="Can, 6-Pack, Case of 24"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :hint="t('unit.shown_to_cashier_hint')"
              />
            </v-col>

            <!-- Qty per base -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.qty_per_base"
                type="number"
                :label="t('unit.qty_per_base') + ' *'"
                min="0.001"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required, r.positive]"
                :hint="t('unit.qty_per_base_hint')"
                prepend-inner-icon="mdi-numeric"
              />
            </v-col>

            <!-- Barcode -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.barcode"
                :label="t('unit.barcode')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-barcode"
                :hint="t('unit.barcode_hint')"
                clearable
              />
            </v-col>

            <!-- Retail price -->
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.retail_price"
                type="number"
                :label="t('unit.retail_price') + ' *'"
                min="0"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
              >
                <template #prepend-inner>
                  <span>
                    {{ currencySymbol() }}
                  </span>
                </template>
              </v-text-field>
            </v-col>

            <!-- Wholesale price -->
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.wholesale_price"
                type="number"
                :label="t('unit.wholesale_price')"
                min="0"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                clearable
              >
                <template #prepend-inner>
                  <span>
                    {{ currencySymbol() }}
                  </span>
                </template>
              </v-text-field>
            </v-col>

            <!-- Cost price -->
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.cost_price"
                type="number"
                :label="t('unit.cost_price')"
                min="0"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :hint="t('unit.cost_price_hint')"
                clearable
              >
                <template #prepend-inner>
                  <span>
                    {{ currencySymbol() }}
                  </span>
                </template>
              </v-text-field>
            </v-col>

            <!-- Margin preview -->
            <v-col v-if="form.retail_price && form.cost_price" cols="12">
              <v-alert
                density="compact"
                variant="tonal"
                rounded="lg"
                :color="margin > 0 ? 'success' : 'error'"
              >
                {{t('unit.margin')}}:
                <strong>{{ margin.toFixed(1) }}%</strong>
                ({{ t('unit.profit_unit', { value: format(form.retail_price - form.cost_price) }) }})
              </v-alert>
            </v-col>

            <v-col cols="12"><v-divider class="my-1" /></v-col>

            <!-- Is base unit -->
            <v-col cols="12" sm="6">
              <v-switch
                v-model="form.is_base_unit"
                color="success"
                :label="t('unit.base_unit')"
                density="compact"
                hide-details
              />
            </v-col>

            <!-- Is active -->
            <v-col cols="12" sm="6">
              <v-switch
                v-model="form.is_active"
                color="primary"
                :label="t('unit.active')"
                density="compact"
                hide-details
              />
            </v-col>
          </v-row>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { useProductUnitStore } from '@/stores/productUnitStore'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import AppDialog from '@/components/common/AppDialog.vue'
  const { currencySymbol ,format } = useCurrency()

  const { t } = useI18n()
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    unit: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const formRef = ref(null)
  const loadingNames = ref(false)
  const dbUnits = ref([]) // from API
  const productUnitStore = useProductUnitStore()

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.unit?.id)

  // ── Fallback common units (used when DB is empty or as extra suggestions) ──
  const commonUnits = [
    { title: 'pcs', unit_label: 'Pcs', qty_per_base: 1, source: 'common' },
    { title: 'can', unit_label: 'Can', qty_per_base: 1, source: 'common' },
    {
      title: 'bottle',
      unit_label: 'Bottle',
      qty_per_base: 1,
      source: 'common'
    },
    { title: 'pack', unit_label: 'Pack', qty_per_base: 6, source: 'common' },
    { title: 'box', unit_label: 'Box', qty_per_base: 24, source: 'common' },
    {
      title: 'carton',
      unit_label: 'Carton',
      qty_per_base: 24,
      source: 'common'
    },
    { title: 'case', unit_label: 'Case', qty_per_base: 12, source: 'common' },
    { title: 'kg', unit_label: 'kg', qty_per_base: 1, source: 'common' },
    { title: 'g', unit_label: 'g', qty_per_base: 1, source: 'common' },
    { title: 'litre', unit_label: 'Litre', qty_per_base: 1, source: 'common' },
    { title: 'dozen', unit_label: 'Dozen', qty_per_base: 12, source: 'common' },
    { title: 'bag', unit_label: 'Bag', qty_per_base: 1, source: 'common' }
  ]

  // ── Merge DB units first, then fill in common ones not already in DB ───────
  const unitNameOptions = computed(() => {
    const dbTitles = new Set(dbUnits.value.map(u => u.title))
    const extras = commonUnits.filter(u => !dbTitles.has(u.title))

    return [
      // DB units shown first with "existing" badge
      ...dbUnits.value.map(u => ({
        ...u,
        source: 'db',
        subtitle: `× ${u.qty_per_base} · used in your products`
      })),
      // Common units not yet in DB shown as fallback
      ...extras.map(u => ({
        ...u,
        source: 'common',
        subtitle: `× ${u.qty_per_base}`
      }))
    ]
  })

  // ── Fetch distinct unit names from DB ─────────────────────────────────────
  const fetchUnitNames = async () => {
    loadingNames.value = true
    try {
      const res = await productUnitStore.fetchUnitName()
      dbUnits.value = (res.data.data ?? []).map(u => ({
        title: u.title,
        unit_label: u.unit_label,
        qty_per_base: u.qty_per_base
      }))
    } catch {
      // silently fall back to commonUnits
      dbUnits.value = []
    } finally {
      loadingNames.value = false
    }
  }

  // ── When user picks or types a unit name ──────────────────────────────────
  const onUnitNameChange = val => {
    const selected = typeof val === 'object' && val !== null ? val : null

    if (selected) {
      // Auto-fill label if blank
      if (!form.unit_label && selected.unit_label) {
        form.unit_label = selected.unit_label
      }
      // Auto-fill qty if still at 1 and selected has better default
      if (form.qty_per_base === 1 && selected.qty_per_base > 1) {
        form.qty_per_base = selected.qty_per_base
      }
      // Always store plain string
      form.unit_name = selected.title
    }
  }

  // ── Form ──────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    unit_name: '',
    unit_label: '',
    qty_per_base: 1,
    barcode: null,
    retail_price: 0,
    wholesale_price: null,
    cost_price: null,
    is_base_unit: false,
    is_active: true,
    sort_order: 0
  })

  const form = reactive(defaultForm())

  const margin = computed(() => {
    if (!form.retail_price || !form.cost_price) return 0
    return ((form.retail_price - form.cost_price) / form.retail_price) * 100
  })

  watch(
    () => props.unit,
    val => {
      if (val) Object.assign(form, { ...defaultForm(), ...val })
      else Object.assign(form, defaultForm())
    },
    { immediate: true }
  )

  // Fetch names when dialog opens
  watch(
    () => props.modelValue,
    open => {
      if (open) fetchUnitNames()
    }
  )

  const r = {
    required: v => !!v || t('products.rule.required'),
    positive: v => v > 0 || t('validation.positive')
  }

  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    emit('save', {
      ...(isEdit.value ? { id: props.unit.id } : {}),
      ...form,
      unit_name:
        typeof form.unit_name === 'object'
          ? (form.unit_name?.title ?? '')
          : form.unit_name,
      wholesale_price: form.wholesale_price || null,
      cost_price: form.cost_price || null,
      barcode: form.barcode || null
    })

    close() // ✅ FIX: close dialog after save
  }

  const close = () => {
    model.value = false
    formRef.value?.reset()
    Object.assign(form, defaultForm())
  }
</script>

<style scoped>
  .gap-3 {
    gap: 12px;
  }
</style>
