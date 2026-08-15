<template>
  <AppDialog
    v-model="model"
    :max-width="520"
    :icon="isEdit ? 'mdi-format-list-checks' : 'mdi-playlist-plus'"
    :color="isEdit ? 'primary' : 'success'"
    :title="isEdit ? $t('modifiers.group.edit_title') : $t('modifiers.group.add_title')"
    :subtitle="isEdit ? $t('modifiers.group.edit_subtitle') : $t('modifiers.group.add_subtitle')"
    :loading="loading"
    :submit-text="isEdit ? $t('btn.save_changes') : $t('modifiers.group.create_btn')"
    @close="close"
    @submit="submit"
  >
    <v-form ref="formRef" @submit.prevent="submit">
          <div class="d-flex flex-column gap-4">

            <!-- Name -->
            <div>
              <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block">
                {{ $t('modifiers.group.name_label') }} <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="form.name"
                :placeholder="$t('modifiers.group.name_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :rules="rules.name"
                :error-messages="serverErrors.name"
                maxlength="100"
                counter
              />
            </div>

            <!-- Selection Type -->
            <div>
              <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-2 d-block">
                {{ $t('modifiers.group.selection_type_label') }} <span class="text-error">*</span>
              </label>
              <v-btn-toggle
                v-model="form.selection_type"
                rounded="lg"
                density="comfortable"
                color="primary"
                mandatory
                class="w-100"
              >
                <v-btn value="single" class="flex-grow-1" variant="outlined">
                  <v-icon icon="mdi-radiobox-marked" size="18" class="mr-2" />
                  {{ $t('modifiers.group.single') }}
                </v-btn>
                <v-btn value="multiple" class="flex-grow-1" variant="outlined">
                  <v-icon icon="mdi-checkbox-marked-outline" size="18" class="mr-2" />
                  {{ $t('modifiers.group.multiple') }}
                </v-btn>
              </v-btn-toggle>
              <p class="text-caption text-grey mt-1 ml-1">
                {{ form.selection_type === 'single' ? $t('modifiers.group.single_hint') : $t('modifiers.group.multiple_hint') }}
              </p>
            </div>

            <!-- Min / Max Selections -->
            <v-row dense>
              <v-col cols="6">
                <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block">
                  {{ $t('modifiers.group.min_selections_label') }}
                </label>
                <v-text-field
                  v-model.number="form.min_selections"
                  placeholder="0"
                  type="number"
                  variant="outlined"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.min_selections"
                  :error-messages="serverErrors.min_selections"
                  min="0"
                  max="32767"
                />
              </v-col>
              <v-col cols="6">
                <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block">
                  {{ $t('modifiers.group.max_selections_label') }}
                  <span class="text-caption text-grey ml-1">({{ $t('form.optional') }})</span>
                </label>
                <v-text-field
                  v-model.number="form.max_selections"
                  :placeholder="$t('modifiers.group.max_placeholder')"
                  type="number"
                  variant="outlined"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.max_selections"
                  :error-messages="serverErrors.max_selections"
                  min="1"
                  max="32767"
                />
              </v-col>
            </v-row>

            <!-- Is Required -->
            <div class="d-flex align-center justify-space-between pa-4 rounded-lg bg-grey-lighten-5">
              <div>
                <p class="text-body-2 font-weight-medium text-grey-darken-2 mb-0">{{ $t('modifiers.group.required_toggle') }}</p>
                <p class="text-caption text-grey mb-0">
                  {{ $t('modifiers.group.required_hint') }}
                </p>
              </div>
              <v-switch v-model="form.is_required" color="error" hide-details inset />
            </div>

          </div>
        </v-form>
  </AppDialog>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'

const { t } = useI18n()

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  group:      { type: Object,  default: null  },
  loading:    { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const formRef     = ref(null)
const serverErrors = reactive({})

const model = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const isEdit = computed(() => !!props.group?.id)

// ── Form ──────────────────────────────────────────────────────────────────────
const defaultForm = () => ({
  name:            '',
  selection_type:  'single',
  min_selections:  0,
  max_selections:  null,
  is_required:     false,
})

const form = reactive(defaultForm())

// ── Rules — defined BEFORE watcher ───────────────────────────────────────────
const rules = {
  name: [
    v => !!v?.trim()            || t('modifiers.rule.name_required'),
    v => v.trim().length >= 2   || t('validation.min_length', { n: 2 }),
    v => v.trim().length <= 100 || t('validation.max_length', { n: 100 }),
  ],
  min_selections: [
    v => v === '' || v === null || !isNaN(Number(v))          || t('products.rule.isNumber'),
    v => v === '' || v === null || Number(v) >= 0             || t('products.rule.nonNegative'),
    v => v === '' || v === null || Number.isInteger(Number(v)) || t('modifiers.rule.whole_number'),
  ],
  max_selections: [
    v => {
      if (v === '' || v === null || v === undefined) return true
      if (!Number.isInteger(Number(v)))  return t('modifiers.rule.whole_number')
      if (Number(v) < 1)                 return t('validation.min_value', { n: 1 })
      if (form.min_selections !== null && Number(v) < Number(form.min_selections))
        return t('modifiers.rule.max_gte_min')
      return true
    },
  ],
}

// ── Helpers — BEFORE watcher ──────────────────────────────────────────────────
const resetForm = () => {
  Object.assign(form, defaultForm())
  formRef.value?.resetValidation()
}

const clearServerErrors = () => {
  Object.keys(serverErrors).forEach(key => delete serverErrors[key])
}

const close = () => {
  if (props.loading) return
  model.value = false
  resetForm()
}

// ── Watcher ───────────────────────────────────────────────────────────────────
watch(
  () => props.group,
  async val => {
    clearServerErrors()
    await nextTick()
    if (val) {
      Object.assign(form, {
        name:           val.name           ?? '',
        selection_type: val.selection_type ?? 'single',
        min_selections: val.min_selections ?? 0,
        max_selections: val.max_selections ?? null,
        is_required:    val.is_required    ?? false,
      })
    } else {
      resetForm()
    }
  },
  { immediate: true }
)

// ── Submit ────────────────────────────────────────────────────────────────────
const submit = async () => {
  if (!formRef.value) return
  const { valid } = await formRef.value.validate()
  if (!valid) return

  clearServerErrors()

  const payload = {
    ...(props.group?.id ? { id: props.group.id } : {}),
    name:           form.name.trim(),
    selection_type: form.selection_type,
    min_selections: form.min_selections ?? 0,
    max_selections: form.max_selections  || null,
    is_required:    form.is_required,
  }

  await new Promise((resolve, reject) => {
    emit('saved', payload, { resolve, reject })
  }).then(() => {
    close()
  }).catch(err => {
    if (err?.response?.status === 422) {
      const errors = err.response.data?.errors ?? {}
      Object.keys(errors).forEach(key => {
        serverErrors[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key]
      })
    }
  })
}
</script>