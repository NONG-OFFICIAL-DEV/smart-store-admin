<template>
  <v-dialog v-model="model" max-width="520" persistent>
    <v-card rounded="lg">

      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between px-6 pt-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar :color="isEdit ? 'primary' : 'success'" variant="tonal" size="40" rounded="md">
            <v-icon :icon="isEdit ? 'mdi-pencil-outline' : 'mdi-plus'" size="20" />
          </v-avatar>
          <div>
            <p class="text-body-1 font-weight-semibold text-grey-darken-3 mb-0">
              {{ isEdit ? 'Edit Modifier Group' : 'Add Modifier Group' }}
            </p>
            <p class="text-caption text-grey mb-0">
              {{ isEdit ? 'Update group settings' : 'e.g. Sugar Level, Add-ons, Spice Level' }}
            </p>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" :disabled="loading" @click="close" />
      </v-card-title>

      <v-divider />

      <v-card-text class="px-6 py-5">
        <v-form ref="formRef" @submit.prevent="submit">
          <div class="d-flex flex-column gap-4">

            <!-- Name -->
            <div>
              <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block">
                Group Name <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="form.name"
                placeholder="e.g. Sugar Level"
                variant="outlined"
                density="comfortable"
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
                Selection Type <span class="text-error">*</span>
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
                  Single
                </v-btn>
                <v-btn value="multiple" class="flex-grow-1" variant="outlined">
                  <v-icon icon="mdi-checkbox-marked-outline" size="18" class="mr-2" />
                  Multiple
                </v-btn>
              </v-btn-toggle>
              <p class="text-caption text-grey mt-1 ml-1">
                {{ form.selection_type === 'single' ? 'Customer picks exactly one option' : 'Customer can pick one or more options' }}
              </p>
            </div>

            <!-- Min / Max Selections -->
            <v-row dense>
              <v-col cols="6">
                <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block">
                  Min Selections
                </label>
                <v-text-field
                  v-model.number="form.min_selections"
                  placeholder="0"
                  type="number"
                  variant="outlined"
                  density="comfortable"
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
                  Max Selections
                  <span class="text-caption text-grey ml-1">(optional)</span>
                </label>
                <v-text-field
                  v-model.number="form.max_selections"
                  placeholder="Unlimited"
                  type="number"
                  variant="outlined"
                  density="comfortable"
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
                <p class="text-body-2 font-weight-medium text-grey-darken-2 mb-0">Required</p>
                <p class="text-caption text-grey mb-0">
                  Customer must make a selection before ordering
                </p>
              </div>
              <v-switch v-model="form.is_required" color="error" hide-details inset />
            </div>

          </div>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="px-6 py-4 gap-3">
        <v-btn variant="outlined" rounded="lg" class="flex-grow-1" :disabled="loading" @click="close">
          Cancel
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          class="flex-grow-1"
          :loading="loading"
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Create Group' }}
        </v-btn>
      </v-card-actions>

    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'

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
    v => !!v?.trim()            || 'Name is required',
    v => v.trim().length >= 2   || 'At least 2 characters',
    v => v.trim().length <= 100 || 'Max 100 characters',
  ],
  min_selections: [
    v => v === '' || v === null || !isNaN(Number(v))          || 'Must be a number',
    v => v === '' || v === null || Number(v) >= 0             || 'Must be 0 or more',
    v => v === '' || v === null || Number.isInteger(Number(v)) || 'Must be a whole number',
  ],
  max_selections: [
    v => {
      if (v === '' || v === null || v === undefined) return true
      if (!Number.isInteger(Number(v)))  return 'Must be a whole number'
      if (Number(v) < 1)                 return 'Must be at least 1'
      if (form.min_selections !== null && Number(v) < Number(form.min_selections))
        return 'Must be ≥ Min selections'
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