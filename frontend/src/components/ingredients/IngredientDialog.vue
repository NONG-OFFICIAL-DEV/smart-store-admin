<template>
  <v-dialog v-model="model" max-width="620" persistent scrollable>
    <v-card rounded="xl" border elevation="0">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar
            :color="isEdit ? 'primary' : 'success'"
            variant="tonal"
            size="40"
            rounded="lg"
          >
            <v-icon
              :icon="isEdit ? 'mdi-flask-outline' : 'mdi-flask-plus-outline'"
              size="20"
            />
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold">
              {{ isEdit ? 'Edit Ingredient' : 'New Ingredient' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ isEdit ? ingredient?.name : 'Fill in the details below' }}
            </div>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-0">
        <v-form ref="formRef">
          <!-- ── Basic Info ──────────────────────────────────────────────── -->
          <div class="section-block">
            <div class="section-label">
              <v-icon icon="mdi-information-outline" size="14" class="mr-1" />
              Basic Info
            </div>
            <v-row dense>
              <!-- Name -->
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.name"
                  label="Name *"
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
                  label="Unit *"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  :rules="[r.required]"
                  hint="kg, g, L, ml, pcs..."
                  persistent-hint
                />
              </v-col>

              <!-- Category -->
              <v-col cols="12" sm="6">
                <v-combobox
                  v-model="form.category"
                  :items="categoryOptions"
                  label="Category"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-tag-outline"
                  hint="dairy, produce, packaging..."
                  persistent-hint
                />
              </v-col>

              <!-- Barcode -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.barcode"
                  label="Barcode"
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
              Cost & Reorder
            </div>
            <v-row dense>
              <!-- Unit Cost -->
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model.number="form.unit_cost"
                  type="number"
                  min="0"
                  step="0.0001"
                  label="Unit Cost"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-currency-usd"
                  :hint="`per ${form.unit || 'unit'}`"
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
                  label="Reorder Point"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-alert-outline"
                  :hint="`in ${form.unit || 'units'}`"
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
                  label="Reorder Qty"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-package-variant-plus"
                  :hint="`qty to order`"
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
              Supplier & Status
            </div>
            <v-row dense>
              <!-- Preferred Supplier -->
              <v-col cols="12" sm="8">
                <v-select
                  v-model="form.preferred_supplier_id"
                  :items="supplierStore.suppliers.data"
                  item-title="name"
                  item-value="id"
                  label="Preferred Supplier"
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
                  label="Active"
                  color="success"
                  inset
                  hide-details
                />
              </v-col>
            </v-row>
          </div>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-5 gap-3">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
          Cancel
        </v-btn>
        <v-spacer />
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :loading="loading"
          :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-plus'"
          @click="save"
        >
          {{ isEdit ? 'Save Changes' : 'Create Ingredient' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { useSupplierStore } from '@/stores/supplierStore'

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
    required: v => !!v || 'Required'
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
