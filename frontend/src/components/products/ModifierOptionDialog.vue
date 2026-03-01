<template>
  <v-dialog v-model="model" max-width="460" persistent>
    <v-card rounded="lg">

      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between px-6 pt-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar :color="isEdit ? 'primary' : 'success'" variant="tonal" size="40" rounded="md">
            <v-icon :icon="isEdit ? 'mdi-pencil-outline' : 'mdi-plus'" size="20" />
          </v-avatar>
          <div>
            <p class="text-body-1 font-weight-semibold text-grey-darken-3 mb-0">
              {{ isEdit ? 'Edit Option' : 'Add Option' }}
            </p>
            <p class="text-caption text-grey mb-0">
              {{ isEdit ? 'Update option details' : 'e.g. Extra Shot, No Sugar, Spicy' }}
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
                Option Name <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="form.name"
                placeholder="e.g. Extra Shot"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details="auto"
                :rules="rules.name"
                :error-messages="serverErrors.name"
                maxlength="100"
                counter
                autofocus
              />
            </div>

            <!-- Price Adjustment -->
            <div>
              <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block">
                Price Adjustment
              </label>
              <v-text-field
                v-model.number="form.price_adjustment"
                placeholder="0.00"
                type="number"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details="auto"
                :rules="rules.price_adjustment"
                :error-messages="serverErrors.price_adjustment"
                step="0.01"
              >
                <template #prepend-inner>
                  <span
                    class="text-body-2 font-weight-bold"
                    :class="priceAdjustmentColor"
                  >
                    {{ pricePrefix }}
                  </span>
                </template>
              </v-text-field>
              <p class="text-caption text-grey mt-1 ml-1">
                Use <strong>0.00</strong> for no charge · positive to add · negative to subtract
              </p>
            </div>

            <!-- Sort Order -->
            <div>
              <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block">
                Sort Order
              </label>
              <v-text-field
                v-model.number="form.sort_order"
                placeholder="0"
                type="number"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                hide-details="auto"
                :rules="rules.sort_order"
                :error-messages="serverErrors.sort_order"
                min="-32768"
                max="32767"
              />
              <p class="text-caption text-grey mt-1 ml-1">Lower numbers appear first</p>
            </div>

            <!-- Available -->
            <div class="d-flex align-center justify-space-between pa-4 rounded-lg bg-grey-lighten-5">
              <div>
                <p class="text-body-2 font-weight-medium text-grey-darken-2 mb-0">Available</p>
                <p class="text-caption text-grey mb-0">
                  Show this option to customers
                </p>
              </div>
              <v-switch v-model="form.is_available" color="success" hide-details inset />
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
          {{ isEdit ? 'Save Changes' : 'Add Option' }}
        </v-btn>
      </v-card-actions>

    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  option:     { type: Object,  default: null  },
  groupId:    { type: String,  default: null  },
  loading:    { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const formRef      = ref(null)
const serverErrors = reactive({})

const model = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const isEdit = computed(() => !!props.option?.id)

// ── Price prefix indicator ────────────────────────────────────────────────────
const pricePrefix = computed(() => {
  const v = Number(form.price_adjustment)
  if (isNaN(v) || v === 0) return '$'
  return v > 0 ? '+$' : '-$'
})

const priceAdjustmentColor = computed(() => {
  const v = Number(form.price_adjustment)
  if (isNaN(v) || v === 0) return 'text-grey'
  return v > 0 ? 'text-success' : 'text-error'
})

// ── Form ──────────────────────────────────────────────────────────────────────
const defaultForm = () => ({
  name:             '',
  price_adjustment: 0,
  sort_order:       0,
  is_available:     true,
})

const form = reactive(defaultForm())

// ── Rules — defined BEFORE watcher ───────────────────────────────────────────
const rules = {
  name: [
    v => !!v?.trim()            || 'Name is required',
    v => v.trim().length >= 2   || 'At least 2 characters',
    v => v.trim().length <= 100 || 'Max 100 characters',
  ],
  price_adjustment: [
    v => v === '' || v === null || !isNaN(Number(v)) || 'Must be a valid number',
    v => {
      const n = Number(v)
      if (isNaN(n)) return true
      // decimal(10,2): absolute value < 100,000,000
      if (Math.abs(n) >= 100_000_000) return 'Value out of range'
      // max 2 decimal places
      if (!/^-?\d+(\.\d{1,2})?$/.test(String(v))) return 'Max 2 decimal places'
      return true
    },
  ],
  sort_order: [
    v => v === '' || v === null || !isNaN(Number(v))          || 'Must be a number',
    v => v === '' || v === null || Number.isInteger(Number(v)) || 'Must be a whole number',
    v => v === '' || v === null || Number(v) >= -32768         || 'Min -32768',
    v => v === '' || v === null || Number(v) <= 32767          || 'Max 32767',
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
  () => props.option,
  async val => {
    clearServerErrors()
    await nextTick()
    if (val) {
      Object.assign(form, {
        name:             val.name             ?? '',
        price_adjustment: val.price_adjustment ?? 0,
        sort_order:       val.sort_order       ?? 0,
        is_available:     val.is_available     ?? true,
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
    ...(props.option?.id ? { id: props.option.id } : {}),
    group_id:         props.groupId,
    name:             form.name.trim(),
    price_adjustment: Number(form.price_adjustment) || 0,
    sort_order:       form.sort_order ?? 0,
    is_available:     form.is_available,
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