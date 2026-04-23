<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue:  Boolean,
  editing:     { type: Object, default: null },
  promotions:  { type: Array,  default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'save'])

const saving = ref(false)

const defaultForm = () => ({
  promotion_id: '',
  code: '',
  usage_limit: null,
  is_active: true,
  expires_at: '',
})

const form = ref(defaultForm())

watch(
  () => props.editing,
  val => {
    form.value = val ? { ...val } : defaultForm()
  },
  { immediate: true }
)

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
  <v-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" max-width="480">
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 text-h6 font-weight-bold text-brown-darken-4">
        {{ editing ? 'Edit Coupon' : 'New Coupon' }}
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-5">
        <v-row dense>
          <v-col cols="12">
            <v-select
              v-model="form.promotion_id"
              :items="promotions"
              item-title="name"
              item-value="id"
              label="Linked Promotion"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12">
            <v-text-field
              v-model="form.code"
              label="Coupon Code"
              variant="outlined"
              density="comfortable"
              rounded="lg"
              hint="e.g. SUMMER20"
              persistent-hint
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.usage_limit"
              label="Usage Limit (blank = unlimited)"
              type="number"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model="form.expires_at"
              label="Expires At (optional)"
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
          {{ editing ? 'Save Changes' : 'Create Coupon' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>