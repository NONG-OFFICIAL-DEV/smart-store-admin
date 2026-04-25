<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  editing: { type: Object, default: null }
})
const emit = defineEmits(['update:modelValue', 'save'])

const saving  = ref(false)
const formRef = ref(null)

const defaultForm = () => ({
  label:         '',
  address_line1: '',
  address_line2: '',
  city:          '',
  latitude:      null,
  longitude:     null,
  is_default:    false,
})

const form = ref(defaultForm())

watch(
  () => props.editing,
  val => { form.value = val ? { ...val } : defaultForm() },
  { immediate: true }
)

// ── Validation rules ──────────────────────────────────────────────────────────
const rules = {
  required:    v => !!v?.toString().trim()              || 'This field is required',
  maxLen: n => v => !v || v.length <= n                 || `Max ${n} characters`,
  countryCode: v => !v || /^[A-Za-z]{2}$/.test(v.trim()) || 'Use a 2-letter ISO code (e.g. US, KH)',
  numeric:     v => !v || !isNaN(v)                     || 'Must be a number',
  lat:         v => !v || (v >= -90  && v <= 90)        || 'Must be between -90 and 90',
  lng:         v => !v || (v >= -180 && v <= 180)       || 'Must be between -180 and 180',
}

const labelOptions = ['Home', 'Work', 'Other']


const close = () => emit('update:modelValue', false)

const submit = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  saving.value = true
  try {
    await emit('save', { ...form.value })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
    max-width="520"
    scrollable
  >
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 text-h6 font-weight-bold text-brown-darken-4">
        {{ editing ? 'Edit Address' : 'New Address' }}
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-5">
        <v-form ref="formRef" validate-on="submit">
          <v-row dense>

            <v-col cols="12" sm="6">
              <v-combobox
                v-model="form.label"
                :items="labelOptions"
                :rules="[rules.required, rules.maxLen(50)]"
                label="Label *"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hint="e.g. Home, Work"
                persistent-hint
              />
            </v-col>

            <v-col cols="12">
              <v-text-field
                v-model="form.address_line1"
                :rules="[rules.required, rules.maxLen(255)]"
                label="Address Line 1 *"
                variant="outlined"
                density="comfortable"
                rounded="lg"
              />
            </v-col>

            <v-col cols="12">
              <v-text-field
                v-model="form.address_line2"
                :rules="[rules.maxLen(255)]"
                label="Address Line 2 (optional)"
                variant="outlined"
                density="comfortable"
                rounded="lg"
              />
            </v-col>

            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.city"
                :rules="[rules.required, rules.maxLen(100)]"
                label="City *"
                variant="outlined"
                density="comfortable"
                rounded="lg"
              />
            </v-col>
    
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.latitude"
                :rules="[rules.numeric, rules.lat]"
                label="Latitude (optional)"
                type="number"
                variant="outlined"
                density="comfortable"
                rounded="lg"
              />
            </v-col>

            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.longitude"
                :rules="[rules.numeric, rules.lng]"
                label="Longitude (optional)"
                type="number"
                variant="outlined"
                density="comfortable"
                rounded="lg"
              />
            </v-col>

            <v-col cols="12">
              <v-switch
                v-model="form.is_default"
                label="Set as default address"
                color="brown-darken-3"
                inset
                hide-details
              />
            </v-col>

          </v-row>
        </v-form>
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
          {{ editing ? 'Save Changes' : 'Add Address' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>