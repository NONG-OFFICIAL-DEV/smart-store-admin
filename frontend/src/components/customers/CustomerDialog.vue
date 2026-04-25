<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  editing:    { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'save'])

const saving = ref(false)

const defaultForm = () => ({
  first_name:          '',
  last_name:           '',
  email:               '',
  phone:               '',
  date_of_birth:       '',
  gender:              '',
  notes:               '',
  marketing_opt_in:    false,
  preferred_language:  'en',
  source:              '',
  is_active:           true,
})

const form = ref(defaultForm())

watch(() => props.editing, val => {
  form.value = val ? { ...val } : defaultForm()
}, { immediate: true })

const genderOptions = [
  { value: 'male',   title: 'Male' },
  { value: 'female', title: 'Female' },
  { value: 'other',  title: 'Other' },
]

const sourceOptions = [
  { value: 'walk_in',  title: 'Walk-in' },
  { value: 'online',   title: 'Online' },
  { value: 'referral', title: 'Referral' },
  { value: 'social',   title: 'Social Media' },
]

const languageOptions = [
  { value: 'en', title: 'English' },
  { value: 'km', title: 'Khmer' },
  { value: 'zh', title: 'Chinese' },
]

const close  = () => emit('update:modelValue', false)
const submit = async () => {
  saving.value = true
  try { await emit('save', { ...form.value }) }
  finally { saving.value = false }
}
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
    max-width="580" scrollable
  >
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-3 text-h6 font-weight-bold text-brown-darken-4">
        {{ editing ? 'Edit Customer' : 'New Customer' }}
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-5">
        <p class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-3" style="letter-spacing:.08em">
          Personal Info
        </p>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.first_name" label="First Name" variant="outlined" density="comfortable" rounded="lg" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.last_name"  label="Last Name"  variant="outlined" density="comfortable" rounded="lg" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.email" label="Email" type="email" variant="outlined" density="comfortable" rounded="lg" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.phone" label="Phone" variant="outlined" density="comfortable" rounded="lg" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.date_of_birth" label="Date of Birth" type="date" variant="outlined" density="comfortable" rounded="lg" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select v-model="form.gender" :items="genderOptions" item-title="title" item-value="value"
              label="Gender" variant="outlined" density="comfortable" rounded="lg" clearable />
          </v-col>
        </v-row>

        <v-divider class="my-4 opacity-30" />
        <p class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-3" style="letter-spacing:.08em">
          Preferences & Source
        </p>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select v-model="form.source" :items="sourceOptions" item-title="title" item-value="value"
              label="Source" variant="outlined" density="comfortable" rounded="lg" clearable />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select v-model="form.preferred_language" :items="languageOptions" item-title="title" item-value="value"
              label="Preferred Language" variant="outlined" density="comfortable" rounded="lg" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.notes" label="Notes" variant="outlined" density="comfortable" rounded="lg" rows="2" auto-grow />
          </v-col>
        </v-row>

        <v-divider class="my-4 opacity-30" />
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-switch v-model="form.is_active"          label="Active"              color="brown-darken-3" inset hide-details />
          </v-col>
          <v-col cols="12" sm="6">
            <v-switch v-model="form.marketing_opt_in"   label="Marketing Opt-in"    color="blue-darken-2"  inset hide-details />
          </v-col>
        </v-row>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" :disabled="saving" @click="close">Cancel</v-btn>
        <v-btn color="brown-darken-3" variant="flat" rounded="xl" :loading="saving" @click="submit">
          {{ editing ? 'Save Changes' : 'Create Customer' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>