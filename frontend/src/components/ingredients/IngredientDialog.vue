<template>
  <AppDialog
    v-model="model"
    :max-width="620"
    :title="isEdit ? $t('ingredients.dialog.edit_title') : $t('ingredients.dialog.new_title')"
    :subtitle="isEdit ? ingredient?.name : $t('ingredients.dialog.fill_details')"
    :icon="isEdit ? 'mdi-flask-outline' : 'mdi-flask-plus-outline'"
    :color="isEdit ? 'primary' : 'success'"
    :loading="loading"
    body-class="pa-0"
    :submit-text="isEdit ? $t('btn.save_changes') : $t('ingredients.dialog.create_btn')"
    :submit-icon="isEdit ? 'mdi-content-save' : 'mdi-plus'"
    @close="close"
    @submit="save"
  >
        <v-form ref="formRef">
          <!-- ── Basic Info ──────────────────────────────────────────────── -->
          <div class="section-block">
            <div class="section-label">
              <v-icon icon="mdi-information-outline" size="14" class="mr-1" />
              {{ $t('ingredients.section.basic_info') }}
            </div>
            <v-row dense>
              <!-- Name -->
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.name"
                  :label="$t('ingredients.field.name')"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  :rules="[r.required]"
                  prepend-inner-icon="mdi-flask-outline"
                  maxlength="150"
                />
              </v-col>

              <!-- Unit -->
              <v-col cols="12" sm="4">
                <v-combobox
                  v-model="form.unit"
                  :items="unitOptions"
                  :label="$t('ingredients.field.unit')"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  :rules="[r.required]"
                  :hint="$t('ingredients.field.unit_hint')"
                  persistent-hint
                />
              </v-col>

              <!-- Category -->
              <v-col cols="12" sm="6">
                <v-combobox
                  v-model="form.category"
                  :items="categoryOptions"
                  :label="$t('form.category')"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-tag-outline"
                  :hint="$t('ingredients.field.category_hint')"
                  persistent-hint
                />
              </v-col>

              <!-- Barcode -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.barcode"
                  :label="$t('form.barcode')"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-barcode-scan"
                  maxlength="60"
                />
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- ── Cost & Reorder ──────────────────────────────────────────── -->
          <div class="section-block">
            <div class="section-label">
              <v-icon icon="mdi-currency-usd" size="14" class="mr-1" />
              {{ $t('ingredients.section.cost_reorder') }}
            </div>
            <v-row dense>
              <!-- Unit Cost -->
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model.number="form.unit_cost"
                  type="number"
                  min="0"
                  step="0.0001"
                  :label="$t('po.unit_cost')"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-currency-usd"
                  :hint="$t('ingredients.field.unit_cost_hint', { unit: form.unit || $t('form.unit') })"
                  persistent-hint
                />
              </v-col>

              <!-- Reorder Point -->
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model.number="form.reorder_point"
                  type="number"
                  min="0"
                  step="0.001"
                  :label="$t('ingredients.field.reorder_point')"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-alert-outline"
                  :hint="$t('ingredients.field.reorder_point_hint', { unit: form.unit || $t('form.unit') })"
                  persistent-hint
                />
              </v-col>

              <!-- Reorder Quantity -->
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model.number="form.reorder_quantity"
                  type="number"
                  min="0"
                  step="0.001"
                  :label="$t('ingredients.field.reorder_qty')"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-package-variant-plus"
                  :hint="$t('ingredients.field.reorder_qty_hint')"
                  persistent-hint
                />
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- ── Supplier & Status ───────────────────────────────────────── -->
          <div class="section-block">
            <div class="section-label">
              <v-icon icon="mdi-truck-outline" size="14" class="mr-1" />
              {{ $t('ingredients.section.supplier_status') }}
            </div>
            <v-row dense>
              <!-- Preferred Supplier -->
              <v-col cols="12" sm="8">
                <v-select
                  v-model="form.preferred_supplier_id"
                  :items="supplierStore.suppliers.data"
                  item-title="name"
                  item-value="id"
                  :label="$t('ingredients.field.preferred_supplier')"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-truck-outline"
                  clearable
                />
              </v-col>

              <!-- Active Toggle -->
              <v-col cols="12" sm="4" class="d-flex align-center">
                <v-switch
                  v-model="form.is_active"
                  :label="$t('status.active')"
                  color="success"
                  inset
                  hide-details
                />
              </v-col>
            </v-row>
          </div>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useSupplierStore } from '@/stores/supplierStore'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    ingredient: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const supplierStore = useSupplierStore()

  const formRef = ref(null)
  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.ingredient?.id)

  // ── Options ───────────────────────────────────────────────────────────────────
  const unitOptions = [
    'kg',
    'g',
    'L',
    'ml',
    'pcs',
    'box',
    'bag',
    'bottle',
    'can',
    'pack'
  ]
  const categoryOptions = [
    'dairy',
    'produce',
    'meat',
    'seafood',
    'dry goods',
    'beverages',
    'packaging',
    'spices',
    'bakery',
    'frozen'
  ]

  // ── Form ──────────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    name: '',
    category: null,
    unit: null,
    unit_cost: null,
    reorder_point: null,
    reorder_quantity: null,
    preferred_supplier_id: null,
    barcode: '',
    is_active: true
  })

  const form = reactive(defaultForm())

  watch(
    () => props.ingredient,
    val => {
      Object.assign(
        form,
        val
          ? {
              name: val.name ?? '',
              category: val.category ?? null,
              unit: val.unit ?? null,
              unit_cost: val.unit_cost ?? null,
              reorder_point: val.reorder_point ?? null,
              reorder_quantity: val.reorder_quantity ?? null,
              preferred_supplier_id: val.preferred_supplier_id ?? null,
              barcode: val.barcode ?? '',
              is_active: val.is_active ?? true
            }
          : defaultForm()
      )
    },
    { immediate: true }
  )

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || t('products.rule.required')
  }

  // ── Submit ────────────────────────────────────────────────────────────────────
  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    const payload = {
      ...(isEdit.value ? { id: props.ingredient.id } : {}),
      name: form.name,
      category: form.category || null,
      unit: form.unit,
      unit_cost: form.unit_cost || null,
      reorder_point: form.reorder_point || null,
      reorder_quantity: form.reorder_quantity || null,
      preferred_supplier_id: form.preferred_supplier_id || null,
      barcode: form.barcode || null,
      is_active: form.is_active
    }

    emit('save', payload)
  }

  const close = () => {
    model.value = false
    formRef.value?.reset()
    Object.assign(form, defaultForm())
  }

  onMounted(()=>{
    supplierStore.fetchSuppliers()
  })
</script>

<style scoped>
  .section-block {
    padding: 20px 24px;
  }
  .section-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgb(var(--v-theme-primary));
    margin-bottom: 14px;
    display: flex;
    align-items: center;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
