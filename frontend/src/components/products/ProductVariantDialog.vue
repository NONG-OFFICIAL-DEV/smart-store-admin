<template>
  <AppDialog
    v-model="model"
    :max-width="480"
    :title="isEdit ? t('products.variant.editTitle') : t('products.variant.addTitle')"
    :subtitle="t('products.variant.subtitle')"
    icon="mdi-shape-plus"
    color="primary"
    :loading="loading"
    :submit-text="isEdit ? t('btn.save') : t('btn.create')"
    submit-icon="mdi-content-save"
    @close="close"
    @submit="save"
  >
        <v-form ref="formRef">
          <v-row dense>
            <!-- Variant Name -->
            <v-col cols="12">
              <v-text-field
                v-model="form.name"
                :label="t('products.variant.name')"
                placeholder="e.g. Small, Medium, Large / Red, Blue"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-tag-outline"
                :rules="[r.required, r.maxLen(80)]"
                maxlength="80"
                clearable
              />
            </v-col>

            <!-- Price Adjustment -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.price_adjustment"
                type="number"
                :label="t('products.variant.priceAdjustment')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-plus-minus"
                step="0.01"
                :hint="t('products.variant.priceAdjustmentHint')"
                :rules="[r.isNumber]"
              />
            </v-col>

            <!-- SKU Suffix -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.sku_suffix"
                :label="t('products.variant.skuSuffix')"
                placeholder="e.g. -SM, -RED"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-identifier"
                :rules="[r.maxLen(20)]"
                maxlength="20"
                clearable
                :hint="t('products.variant.skuSuffixHint')"
              />
            </v-col>

            <!-- Price adjustment preview -->
            <v-col v-if="basePrice && form.price_adjustment !== 0" cols="12">
              <v-alert
                density="compact"
                variant="tonal"
                rounded="lg"
                :color="form.price_adjustment >= 0 ? 'success' : 'warning'"
              >
                {{ t('products.variant.finalPrice') }}:
                <strong>{{ format(finalPrice) }}</strong>
                <span class="text-caption ml-1">
                  ({{ t('products.variant.base') }} {{ format(basePrice) }}
                  {{ form.price_adjustment >= 0 ? '+' : ''
                  }}{{ format(form.price_adjustment) }})
                </span>
              </v-alert>
            </v-col>

            <!-- Sort Order -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.sort_order"
                type="number"
                :label="t('products.variant.sortOrder')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-sort"
                min="0"
                hide-details
              />
            </v-col>

            <v-col cols="12"><v-divider class="my-1" /></v-col>

            <!-- Is Default -->
            <v-col cols="12" sm="6">
              <v-switch
                v-model="form.is_default"
                color="primary"
                :label="t('products.variant.isDefault')"
                density="compact"
                hide-details
              />
            </v-col>
          </v-row>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import AppDialog from '@/components/common/AppDialog.vue'
  const { format } = useCurrency()

  const { t } = useI18n({ useScope: 'global' })

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    variant: { type: Object, default: null }, // existing variant for edit
    basePrice: { type: String, default: null }, // product base_price for preview
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'save'])

  const formRef = ref(null)

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const isEdit = computed(() => !!props.variant?.id)

  // ── Form ───────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    name: '',
    price_adjustment: 0,
    sku_suffix: null,
    is_default: false,
    sort_order: 0
  })

  const form = reactive(defaultForm())

  // ── Final price preview ────────────────────────────────────────────────────
  const finalPrice = computed(
    () => Number(props.basePrice ?? 0) + Number(form.price_adjustment ?? 0)
  )

  // ── Watch variant prop (edit mode) ─────────────────────────────────────────
  watch(
    () => props.variant,
    val => {
      if (val) {
        Object.assign(form, { ...defaultForm(), ...val })
      } else {
        Object.assign(form, defaultForm())
      }
    },
    { immediate: true }
  )

  // ── Rules ──────────────────────────────────────────────────────────────────
  const r = {
    required: v =>
      (v !== null && v !== '' && v !== undefined) ||
      t('products.rule.required'),
    isNumber: v =>
      (!v && v !== 0) || !isNaN(Number(v)) || t('products.rule.isNumber'),
    maxLen: n => v => !v || v.length <= n || t('products.rule.maxLen', { n })
  }

  // ── Close ──────────────────────────────────────────────────────────────────
  const close = () => {
    model.value = false
    Object.assign(form, defaultForm())
    formRef.value?.reset()
  }

  // ── Save ───────────────────────────────────────────────────────────────────
  // ProductVariantDialog — save method
  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    await new Promise((resolve, reject) => {
      emit(
        'saved',
        {
          ...(isEdit.value ? { id: props.variant.id } : {}),
          name: form.name,
          price_adjustment: Number(form.price_adjustment ?? 0),
          sku_suffix: form.sku_suffix || null,
          is_default: form.is_default || false,
          sort_order: Number(form.sort_order ?? 0)
        },
        { resolve, reject } // ← pass callbacks
      )
    })
      .then(() => close()) // ← close only on success
      .catch(() => {}) // ← stay open on error
  }
</script>

<style scoped>
  .gap-3 {
    gap: 12px;
  }
</style>
