<template>
  <AppDialog
    v-model="model"
    :max-width="460"
    :title="isEdit ? $t('modifiers.option.edit_title') : $t('modifiers.option.add_title')"
    :loading="loading"
  >
    <v-form ref="formRef" @submit.prevent="submit">
          <div class="d-flex flex-column gap-4">

            <!-- Name -->
            <div>
              <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block">
                {{ $t('modifiers.option.name_label') }} <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="form.name"
                :placeholder="$t('modifiers.option.name_placeholder')"
                variant="outlined"
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
                {{ $t('products.variant.priceAdjustment') }}
              </label>
              <v-text-field
                v-model.number="form.price_adjustment"
                placeholder="0.00"
                type="number"
                variant="outlined"
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
                {{ $t('modifiers.option.price_hint_prefix') }} <strong>0.00</strong> {{ $t('modifiers.option.price_hint_suffix') }}
              </p>
            </div>

            <!-- Sort Order -->
            <div>
              <label class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block">
                {{ $t('categories.form.sort_order') }}
              </label>
              <v-text-field
                v-model.number="form.sort_order"
                placeholder="0"
                type="number"
                variant="outlined"
                rounded="lg"
                hide-details="auto"
                :rules="rules.sort_order"
                :error-messages="serverErrors.sort_order"
                min="-32768"
                max="32767"
              />
              <p class="text-caption text-grey mt-1 ml-1">{{ $t('modifiers.option.sort_order_hint') }}</p>
            </div>

            <!-- Available -->
            <div class="d-flex align-center justify-space-between pa-4 rounded-lg bg-grey-lighten-5">
              <div>
                <p class="text-body-2 font-weight-medium text-grey-darken-2 mb-0">{{ $t('products.table.available') }}</p>
                <p class="text-caption text-grey mb-0">
                  {{ $t('modifiers.option.available_hint') }}
                </p>
              </div>
              <v-switch v-model="form.is_available" color="success" hide-details inset />
            </div>

          </div>
        </v-form>
    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        :color="isEdit ? 'primary' : 'success'"
        variant="flat"
        rounded="lg"
        :loading="loading"
        @click="submit"
      >
        {{ isEdit ? $t('btn.save_changes') : $t('modifiers.option.add_title') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { AppDialog } from '@nong-official-dev/core'

const { t } = useI18n()

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
    v => !!v?.trim()            || t('modifiers.rule.name_required'),
    v => v.trim().length >= 2   || t('validation.min_length', { n: 2 }),
    v => v.trim().length <= 100 || t('validation.max_length', { n: 100 }),
  ],
  price_adjustment: [
    v => v === '' || v === null || !isNaN(Number(v)) || t('modifiers.rule.valid_number'),
    v => {
      const n = Number(v)
      if (isNaN(n)) return true
      // decimal(10,2): absolute value < 100,000,000
      if (Math.abs(n) >= 100_000_000) return t('modifiers.rule.value_out_of_range')
      // max 2 decimal places
      if (!/^-?\d+(\.\d{1,2})?$/.test(String(v))) return t('modifiers.rule.max_decimal_places')
      return true
    },
  ],
  sort_order: [
    v => v === '' || v === null || !isNaN(Number(v))          || t('products.rule.isNumber'),
    v => v === '' || v === null || Number.isInteger(Number(v)) || t('modifiers.rule.whole_number'),
    v => v === '' || v === null || Number(v) >= -32768         || t('validation.min_value', { n: -32768 }),
    v => v === '' || v === null || Number(v) <= 32767          || t('validation.max_value', { n: 32767 }),
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

// ── Reset form when dialog closes via the built-in × / backdrop ────────────────
watch(
  () => props.modelValue,
  open => {
    if (!open) resetForm()
  }
)

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