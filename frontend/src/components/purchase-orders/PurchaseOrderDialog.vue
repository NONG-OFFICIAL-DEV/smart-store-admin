<template>
  <v-dialog v-model="model" max-width="720" persistent scrollable>
    <v-card rounded="xl" border elevation="0">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar color="primary" variant="tonal" size="40" rounded="lg">
            <v-icon icon="mdi-clipboard-plus-outline" size="20" />
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold">{{ isEdit ? 'Edit PO' : 'New Purchase Order' }}</div>
            <div class="text-caption text-medium-emphasis">{{ isEdit ? purchaseOrder?.po_number : 'PO number auto-generated' }}</div>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-5">
        <v-form ref="formRef">
          <v-row dense>
            <!-- Branch -->
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.branch_id"
                :items="branchStore.branches?.data || branchStore.branches || []"
                item-title="name" item-value="id"
                label="Branch *"
                variant="outlined" density="comfortable" rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-store-outline"
              />
            </v-col>

            <!-- Supplier -->
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.supplier_id"
                :items="supplierStore.suppliers.data" item-title="name" item-value="id"
                label="Supplier *"
                variant="outlined" density="comfortable" rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-truck-outline"
              />
            </v-col>

            <!-- Expected Delivery -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.expected_delivery"
                type="date" label="Expected Delivery"
                variant="outlined" density="comfortable" rounded="lg"
                prepend-inner-icon="mdi-calendar-outline"
              />
            </v-col>

            <!-- Notes -->
            <v-col cols="12">
              <v-textarea
                v-model="form.notes"
                label="Notes"
                variant="outlined" density="comfortable" rounded="lg"
                rows="2" auto-grow
                prepend-inner-icon="mdi-note-outline"
              />
            </v-col>
          </v-row>

          <!-- ── Line Items ──────────────────────────────────────────────── -->
          <div class="d-flex align-center justify-space-between mb-3 mt-2">
            <div class="text-body-2 font-weight-bold">Items</div>
            <v-btn size="small" variant="tonal" color="primary" rounded="lg"
              prepend-icon="mdi-plus" @click="addItem">
              Add Item
            </v-btn>
          </div>

          <!-- Item rows -->
          <div v-for="(item, i) in form.items" :key="i" class="mb-3">
            <v-row dense align="center">
              <v-col cols="12" sm="5">
                <v-autocomplete
                  v-model="item.ingredient_id"
                  :items="ingredients" item-title="name" item-value="id"
                  :label="`Ingredient ${i + 1} *`"
                  variant="outlined" density="compact" rounded="lg"
                  hide-details
                  :rules="[r.required]"
                />
              </v-col>
              <v-col cols="5" sm="3">
                <v-text-field
                  v-model.number="item.quantity_ordered"
                  type="number" label="Qty *" min="0.001"
                  variant="outlined" density="compact" rounded="lg"
                  hide-details
                  :rules="[r.required, r.positive]"
                />
              </v-col>
              <v-col cols="5" sm="3">
                <v-text-field
                  v-model.number="item.unit_price"
                  type="number" label="Unit Price *" min="0"
                  variant="outlined" density="compact" rounded="lg"
                  hide-details
                  prepend-inner-icon="mdi-currency-usd"
                  :rules="[r.required]"
                />
              </v-col>
              <v-col cols="2" sm="1" class="d-flex justify-center">
                <v-btn
                  icon="mdi-delete-outline" size="small" variant="text" color="error"
                  :disabled="form.items.length === 1"
                  @click="removeItem(i)"
                />
              </v-col>
            </v-row>
            <!-- Row subtotal -->
            <div class="text-caption text-right text-medium-emphasis mt-1 pr-10">
              Subtotal: {{ fmt(item.quantity_ordered * item.unit_price) }}
            </div>
          </div>

          <!-- Total -->
          <v-divider class="my-3" />
          <div class="d-flex justify-end">
            <div class="text-body-1 font-weight-bold">
              Total: <span class="text-primary">{{ fmt(totalAmount) }}</span>
            </div>
          </div>
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-5 gap-3">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">Cancel</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" rounded="lg" :loading="loading"
          prepend-icon="mdi-content-save" @click="save">
          {{ isEdit ? 'Save Changes' : 'Create PO' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useBranchStore }   from '@/stores/branchStore'
import { useSupplierStore }  from '@/stores/supplierStore'
import { useIngredientStore } from '@/stores/ingredientStore'

const props = defineProps({
  modelValue:    { type: Boolean, default: false },
  purchaseOrder: { type: Object,  default: null  },
  loading:       { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'save'])

const branchStore     = useBranchStore()
const supplierStore   = useSupplierStore()
const ingredientStore = useIngredientStore()
const ingredients     = computed(() => ingredientStore.ingredients ?? [])

const formRef = ref(null)
const model   = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })
const isEdit  = computed(() => !!props.purchaseOrder?.id)

const defaultItem = () => ({ ingredient_id: null, quantity_ordered: 1, unit_price: 0 })
const defaultForm = () => ({
  branch_id:         null,
  supplier_id:       null,
  expected_delivery: null,
  notes:             '',
  items:             [defaultItem()],
})

const form = reactive(defaultForm())

const totalAmount = computed(() =>
  form.items.reduce((sum, i) => sum + (i.quantity_ordered || 0) * (i.unit_price || 0), 0)
)

watch(() => props.purchaseOrder, val => {
  if (val) {
    Object.assign(form, {
      branch_id:         val.branch_id,
      supplier_id:       val.supplier_id,
      expected_delivery: val.expected_delivery ?? null,
      notes:             val.notes ?? '',
      items: val.items?.map(i => ({
        ingredient_id:   i.ingredient_id,
        quantity_ordered: parseFloat(i.quantity_ordered),
        unit_price:       parseFloat(i.unit_price),
      })) ?? [defaultItem()],
    })
  } else {
    Object.assign(form, defaultForm())
  }
}, { immediate: true })

const r = {
  required: v => !!v || 'Required',
  positive: v => v > 0 || 'Must be > 0',
}

const addItem    = ()  => form.items.push(defaultItem())
const removeItem = (i) => form.items.splice(i, 1)

const fmt = v => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)

const save = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  emit('save', {
    ...(isEdit.value ? { id: props.purchaseOrder.id } : {}),
    branch_id:         form.branch_id,
    supplier_id:       form.supplier_id,
    expected_delivery: form.expected_delivery,
    notes:             form.notes,
    items:             form.items,
  })
}

const close = () => {
  model.value = false
  formRef.value?.reset()
  Object.assign(form, defaultForm())
}
</script>