<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'

const { t } = useI18n()

const props = defineProps({
  modelValue: Boolean,
  editing: { type: Object, default: null }
})
const emit = defineEmits(['update:modelValue', 'save'])

const saving  = ref(false)
const formRef = ref(null)

const defaultForm = () => ({
  label:         null,
  address_line1: '',
  address_line2: '',
  city:          '',
  latitude:      null,
  longitude:     null,
  is_default:    false,
})

const form = ref(defaultForm())

watch(
  () => props.modelValue,
  open => {
    if (!open) return
    form.value = props.editing ? { ...props.editing } : defaultForm()
    formRef.value?.resetValidation()
  },
  { immediate: true }
)

// ── Validation rules ──────────────────────────────────────────────────────────
const rules = {
  required:    v => !!v?.toString().trim()              || t('validation.required'),
  maxLen: n => v => !v || v.length <= n                 || t('validation.max_length', { n }),
  countryCode: v => !v || /^[A-Za-z]{2}$/.test(v.trim()) || t('validation.country_code'),
  numeric:     v => !v || !isNaN(v)                     || t('products.rule.isNumber'),
  lat:         v => !v || (v >= -90  && v <= 90)        || t('validation.latitude_range'),
  lng:         v => !v || (v >= -180 && v <= 180)       || t('validation.longitude_range'),
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
  <AppDialog
    :model-value="modelValue"
    :max-width="520"
    :persistent="false"
    :title="editing ? $t('customers.address_dialog.title_edit') : $t('customers.address_dialog.title_new')"
    :subtitle="editing ? $t('customers.address_dialog.subtitle_edit') : $t('customers.address_dialog.subtitle_new')"
    :icon="editing ? 'mdi-map-marker-outline' : 'mdi-map-marker-plus-outline'"
    :color="editing ? 'primary' : 'success'"
    :loading="saving"
    :submit-text="editing ? $t('btn.save_changes') : $t('customers.address_dialog.add')"
    @update:model-value="emit('update:modelValue', $event)"
    @close="close"
    @submit="submit"
  >
    <v-form ref="formRef" validate-on="submit">
          <v-row dense>

            <v-col cols="12" sm="6">
              <v-combobox
                v-model="form.label"
                :items="labelOptions"
                :rules="[rules.required, rules.maxLen(50)]"
                :label="$t('customers.address_dialog.label')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :hint="$t('customers.address_dialog.label_hint')"
                persistent-hint
              />
            </v-col>

            <v-col cols="12">
              <v-text-field
                v-model="form.address_line1"
                :rules="[rules.required, rules.maxLen(255)]"
                :label="$t('customers.address_dialog.address_line1')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
              />
            </v-col>

            <v-col cols="12">
              <v-text-field
                v-model="form.address_line2"
                :rules="[rules.maxLen(255)]"
                :label="$t('customers.address_dialog.address_line2')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
              />
            </v-col>

            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.city"
                :rules="[rules.required, rules.maxLen(100)]"
                :label="$t('customers.address_dialog.city')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
              />
            </v-col>
    
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.latitude"
                :rules="[rules.numeric, rules.lat]"
                :label="$t('customers.address_dialog.latitude')"
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
                :label="$t('customers.address_dialog.longitude')"
                type="number"
                variant="outlined"
                density="comfortable"
                rounded="lg"
              />
            </v-col>

            <v-col cols="12">
              <v-switch
                v-model="form.is_default"
                :label="$t('customers.address_dialog.set_default')"
                color="primary"
                inset
                hide-details
              />
            </v-col>

          </v-row>
        </v-form>
  </AppDialog>
</template>