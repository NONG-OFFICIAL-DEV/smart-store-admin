<template>
  <v-dialog v-model="model" max-width="520" persistent scrollable>
    <v-card rounded="xl" border elevation="0">
      <v-card-title class="d-flex align-center justify-space-between pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar color="primary" variant="tonal" size="40" rounded="lg">
            <v-icon icon="mdi-package-variant" size="20" />
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold">
              {{ isEdit ? 'Edit Unit' : 'Add Unit' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              e.g. Can, 6-Pack, Box of 24
            </div>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-5">
        <v-form ref="formRef">
          <v-row dense>
            <!-- Unit name -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.unit_name"
                label="Unit Name *"
                placeholder="can, pack, box, pallet"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                hint="Internal identifier (lowercase)"
              />
            </v-col>
            <!-- Unit label -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.unit_label"
                label="Display Label"
                placeholder="Can, 6-Pack, Case of 24"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hint="Shown to cashier on POS"
              />
            </v-col>

            <!-- Qty per base -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.qty_per_base"
                type="number"
                label="Qty per Base Unit *"
                min="0.001"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required, r.positive]"
                hint="How many base units in this unit (e.g. 24 for a box)"
                prepend-inner-icon="mdi-numeric"
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
                prepend-inner-icon="mdi-barcode"
                hint="Scan or type barcode for this unit"
                clearable
              />
            </v-col>

            <!-- Retail price -->
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.retail_price"
                type="number"
                label="Retail Price *"
                min="0"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-currency-usd"
              />
            </v-col>

            <!-- Wholesale price -->
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.wholesale_price"
                type="number"
                label="Wholesale Price"
                min="0"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-currency-usd"
                clearable
              />
            </v-col>

            <!-- Cost price -->
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.cost_price"
                type="number"
                label="Cost Price"
                min="0"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-currency-usd"
                hint="For margin tracking"
                clearable
              />
            </v-col>

            <!-- Margin preview -->
            <v-col v-if="form.retail_price && form.cost_price" cols="12">
              <v-alert
                density="compact"
                variant="tonal"
                rounded="lg"
                :color="margin > 0 ? 'success' : 'error'"
              >
                Margin:
                <strong>{{ margin.toFixed(1) }}%</strong>
                (profit {{ fmt(form.retail_price - form.cost_price) }} per unit)
              </v-alert>
            </v-col>

            <v-col cols="12">
              <v-divider class="my-1" />
            </v-col>

            <!-- Is base unit -->
            <v-col cols="12" sm="6">
              <v-switch
                v-model="form.is_base_unit"
                color="success"
                label="This is the base unit"
                density="compact"
                hide-details
                hint="e.g. the individual can"
              />
            </v-col>

            <!-- Is active -->
            <v-col cols="12" sm="6">
              <v-switch
                v-model="form.is_active"
                color="primary"
                label="Active"
                density="compact"
                hide-details
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-5 gap-3">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
          Cancel
        </v-btn>
        <v-spacer />
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          :loading="loading"
          prepend-icon="mdi-content-save"
          @click="save"
        >
          {{ isEdit ? 'Save Changes' : 'Add Unit' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    unit: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const formRef = ref(null)
  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.unit?.id)

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

  const r = {
    required: v => !!v || 'Required',
    positive: v => v > 0 || 'Must be > 0'
  }

  const fmt = v =>
    new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(v ?? 0)

  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('save', {
      ...(isEdit.value ? { id: props.unit.id } : {}),
      ...form,
      wholesale_price: form.wholesale_price || null,
      cost_price: form.cost_price || null,
      barcode: form.barcode || null
    })
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
