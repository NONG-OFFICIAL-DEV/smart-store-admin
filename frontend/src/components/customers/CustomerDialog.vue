<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AppDialog } from '@nong-official-dev/core'

const { t } = useI18n()

const props = defineProps({
  modelValue: Boolean,
  editing:    { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'save'])

const saving = ref(false)
const formRef = ref(null)

const rules = {
  required: v => !!v || t('customers.validation.first_name_required'),
  email:    v => !v || /.+@.+\..+/.test(v) || t('customers.validation.invalid_email'),
  phone:    v => !v || /^[+\d\s().-]+$/.test(v) || t('customers.validation.invalid_phone'),
}

const defaultForm = () => ({
  first_name:          '',
  last_name:           '',
  email:               '',
  phone:               '',
  notes:               '',
  marketing_opt_in:    false,
  source:              'walk_in',
  is_active:           true,
})

const form = ref(defaultForm())

watch(() => props.modelValue, open => {
  if (!open) return
  form.value = props.editing ? { ...props.editing } : defaultForm()
  formRef.value?.resetValidation()
}, { immediate: true })

// 'social' was previously offered here but isn't a valid value for the
// customers.source enum (walk_in/online/referral/import) — picking it
// always failed with a 422. 'import' is set programmatically by bulk
// imports, not something a staff member picks manually, so it's left out.
const sourceOptions = [
  { value: 'walk_in',  title: t('customers.source.walk_in') },
  { value: 'online',   title: t('order.type.online') },
  { value: 'referral', title: t('customers.source.referral') },
]

const close  = () => emit('update:modelValue', false)
const submit = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  saving.value = true
  try {
    await emit('save', { ...form.value })
  } finally { saving.value = false }
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="580"
    :persistent="false"
    :title="editing ? $t('customers.dialog.title_edit') : $t('customers.dialog.title_new')"
    :loading="saving"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-form ref="formRef">
        <p class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-3" style="letter-spacing:.08em">
          {{ $t('customers.dialog.personal_info') }}
        </p>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.first_name" :label="$t('form.first_name') + ' *'" variant="outlined" rounded="lg" :rules="[rules.required]" maxlength="80" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.last_name"  :label="$t('form.last_name')"  variant="outlined" rounded="lg" maxlength="80" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.email" :label="$t('form.email')" type="email" variant="outlined" rounded="lg" :rules="[rules.email]" maxlength="255" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.phone" :label="$t('form.phone')" variant="outlined" rounded="lg" :rules="[rules.phone]" maxlength="30" />
          </v-col>
        </v-row>

        <v-divider class="my-4 opacity-30" />
        <p class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-3" style="letter-spacing:.08em">
          {{ $t('customers.dialog.preferences_source') }}
        </p>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select v-model="form.source" :items="sourceOptions" item-title="title" item-value="value"
              :label="$t('customers.field.source')" variant="outlined" rounded="lg" clearable />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.notes" :label="$t('form.notes')" variant="outlined" rounded="lg" rows="2" auto-grow />
          </v-col>
        </v-row>

        <v-divider class="my-4 opacity-30" />
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-switch v-model="form.is_active"          :label="$t('status.active')"              color="brown-darken-3" inset hide-details />
          </v-col>
          <v-col cols="12" sm="6">
            <v-switch v-model="form.marketing_opt_in"   :label="$t('customers.field.marketing_opt_in')"    color="blue-darken-2"  inset hide-details />
          </v-col>
        </v-row>
    </v-form>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        :color="editing ? 'primary' : 'success'"
        variant="flat"
        rounded="lg"
        :prepend-icon="editing ? 'mdi-content-save-outline' : 'mdi-plus'"
        :loading="loading"
        @click="submit"
      >
        {{ editing ? $t('btn.save_changes') : $t('btn.add_customer') }}
      </v-btn>
    </template>
  </AppDialog>
</template>