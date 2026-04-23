<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  editing: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'save'])

const saving = ref(false)

const defaultForm = () => ({
  name: '',
  type: 'percentage',
  discount_value: null,
  min_order_amount: null,
  max_discount_amount: null,
  applies_to: 'all',
  start_at: '',
  end_at: '',
  usage_limit: null,
  per_customer_limit: null,
  is_active: true,
})

const form = ref(defaultForm())

// Populate form when editing prop changes
watch(
  () => props.editing,
  val => {
    form.value = val ? { ...val } : defaultForm()
  },
  { immediate: true }
)

const promoTypes = [
  { value: 'percentage',   title: 'Percentage Off' },
  { value: 'fixed_amount', title: 'Fixed Amount' },
  { value: 'bogo',         title: 'Buy 1 Get 1' },
  { value: 'free_item',    title: 'Free Item' },
  { value: 'combo',        title: 'Combo Deal' },
  { value: 'happy_hour',   title: 'Happy Hour' },
]

const appliesToOptions = [
  { value: 'all',        title: 'All Items' },
  { value: 'categories', title: 'Specific Categories' },
  { value: 'products',   title: 'Specific Products' },
  { value: 'order',      title: 'Whole Order' },
]

const showDiscountValue = type => ['percentage', 'fixed_amount', 'happy_hour'].includes(type)

const close = () => emit('update:modelValue', false)

const submit = async () => {
  saving.value = true
  try {
    await emit('save', { ...form.value })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" max-width="580" scrollable>
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 text-h6 font-weight-bold text-brown-darken-4">
        {{ editing ? 'Edit Promotion' : 'New Promotion' }}
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-5">
        <v-row dense>
          <v-col cols="12">
            <v-text-field
              v-model="form.name"
              label="Promotion Name"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-select
              v-model="form.type"
              :items="promoTypes"
              item-title="title"
              item-value="value"
              label="Type"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-select
              v-model="form.applies_to"
              :items="appliesToOptions"
              item-title="title"
              item-value="value"
              label="Applies To"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col v-if="showDiscountValue(form.type)" cols="12" sm="6">
            <v-text-field
              v-model.number="form.discount_value"
              :label="form.type === 'percentage' ? 'Discount (%)' : 'Discount Amount ($)'"
              type="number"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.min_order_amount"
              label="Min Order Amount ($)"
              type="number"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.max_discount_amount"
              label="Max Discount Cap ($)"
              type="number"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.usage_limit"
              label="Usage Limit (leave blank = unlimited)"
              type="number"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.per_customer_limit"
              label="Per Customer Limit"
              type="number"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model="form.start_at"
              label="Start Date & Time"
              type="datetime-local"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model="form.end_at"
              label="End Date & Time (optional)"
              type="datetime-local"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12">
            <v-switch
              v-model="form.is_active"
              label="Active"
              color="brown-darken-3"
              inset
              hide-details
            />
          </v-col>
        </v-row>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" :disabled="saving" @click="close">Cancel</v-btn>
        <v-btn
          color="brown-darken-3"
          variant="flat"
          rounded="xl"
          :loading="saving"
          @click="submit"
        >
          {{ editing ? 'Save Changes' : 'Create Promotion' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>