<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDatePicker, AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()

  const props = defineProps({
    modelValue: Boolean,
    editing: { type: Object, default: null }
  })

  const emit = defineEmits(['update:modelValue', 'save'])

  const saving = ref(false)
  const step = ref(1)

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
    is_active: true
  })

  const form = ref(defaultForm())

  // Populate form when editing prop changes / dialog opens
  watch(
    () => props.editing,
    val => {
      form.value = val ? { ...defaultForm(), ...val } : defaultForm()
    },
    { immediate: true }
  )

  // Reset to step 1 every time the dialog opens
  watch(
    () => props.modelValue,
    val => {
      if (val) step.value = 1
    }
  )

  const promoTypes = [
    {
      value: 'percentage',
      title: t('promotions.types.percentage'),
      icon: 'mdi-percent-outline',
      hint: t('promotions.hints.percentage')
    },
    {
      value: 'fixed_amount',
      title: t('promotions.types.fixed'),
      icon: 'mdi-cash-minus',
      hint: t('promotions.hints.fixed')
    },
    {
      value: 'bogo',
      title: t('promotions.types.buy_x_get_y'),
      icon: 'mdi-tag-multiple-outline',
      hint: t('promotions.hints.buy_x_get_y')
    },
    {
      value: 'free_item',
      title: t('promotions.types.free_item'),
      icon: 'mdi-gift-outline',
      hint: t('promotions.hints.free_item')
    },
    {
      value: 'combo',
      title: t('promotions.types.combo'),
      icon: 'mdi-food-outline',
      hint: t('promotions.hints.combo')
    },
    {
      value: 'happy_hour',
      title: t('promotions.types.happy_hour'),
      icon: 'mdi-clock-fast',
      hint: t('promotions.hints.happy_hour')
    }
  ]
  const appliesToOptions = [
    {
      value: 'all',
      title: t('promotions.applies_to.all'),
      icon: 'mdi-shape-outline'
    },
    {
      value: 'categories',
      title: t('promotions.applies_to.specific_categories'),
      icon: 'mdi-shape-plus-outline'
    },
    {
      value: 'products',
      title: t('promotions.applies_to.specific_products'),
      icon: 'mdi-tag-outline'
    },
    {
      value: 'order',
      title: t('promotions.applies_to.entire_order'),
      icon: 'mdi-cart-outline'
    }
  ]

  const showDiscountValue = type =>
    ['percentage', 'fixed_amount', 'happy_hour'].includes(type)

  const discountLabel = computed(() => {
    if (form.value.type === 'percentage') return t('promotions.form.discount')
    return t('promotions.form.discount_amount')
  })

  const selectedTypeInfo = computed(() =>
    promoTypes.find(p => p.value === form.value.type)
  )

  // ── Step definitions ─────────────────────────────────────────────────────────
  const steps = [
    {
      value: 1,
      title: t('promotions.step.details'),
      subtitle: t('promotions.step.details_subtitle'),
      icon: 'mdi-information-outline'
    },
    {
      value: 2,
      title: t('promotions.step.conditions'),
      subtitle: t('promotions.step.conditions_subtitle'),
      icon: 'mdi-tune-variant'
    },
    {
      value: 3,
      title: t('promotions.step.schedule'),
      subtitle: t('promotions.step.schedule_subtitle'),
      icon: 'mdi-calendar-clock-outline'
    }
  ]

  // ── Per-step validation ──────────────────────────────────────────────────────
  const step1Valid = computed(
    () => !!form.value.name?.trim() && !!form.value.type
  )

  const step2Valid = computed(() => {
    if (showDiscountValue(form.value.type)) {
      return (
        form.value.discount_value !== null &&
        form.value.discount_value !== '' &&
        form.value.discount_value > 0
      )
    }
    return true
  })

  const canGoNext = computed(() => {
    if (step.value === 1) return step1Valid.value
    if (step.value === 2) return step2Valid.value
    if (step.value === 3) return step3Valid.value
    return true
  })

  const isLastStep = computed(() => step.value === steps.length)

  // start_at/end_at are "YYYY-MM-DD" strings from AppDatePicker, but may
  // also be a plain string or ISO datetime string pre-populated when
  // editing — normalize both through this before comparing, never compare
  // the raw value directly (a Date vs string comparison coerces via
  // Date#toString(), which doesn't sort chronologically at all).
  const dateOnlyStr = v => {
    if (!v) return null
    const d = v instanceof Date ? v : new Date(v)
    if (Number.isNaN(d.getTime())) return null
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }

  // Gates the final Save button — canGoNext returned `true` unconditionally
  // for step 3 before this, so an invalid date range showed an error under
  // the field but never actually blocked submission. start_at is also
  // required here since promotions.start_at is NOT NULL in the database —
  // submitting without it used to 500, now the API 422s cleanly, but the
  // dialog should stop the user before that round-trip.
  const step3Valid = computed(() => {
    const start = dateOnlyStr(form.value.start_at)
    const end = dateOnlyStr(form.value.end_at)
    return !!start && (!end || start <= end)
  })

  const goNext = () => {
    if (canGoNext.value && step.value < steps.length) step.value++
  }
  const goBack = () => {
    if (step.value > 1) step.value--
  }

  const close = () => emit('update:modelValue', false)

  const submit = async () => {
    saving.value = true
    try {
      await emit('save', { ...form.value })
    } finally {
      saving.value = false
    }
  }

  const handlePrimaryAction = () => {
    if (isLastStep.value) submit()
    else goNext()
  }
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="640"
    :title="editing ? t('promotions.dialog.edit_title') : t('promotions.dialog.new_title')"
    :loading="saving"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <!-- ── STEP INDICATOR ─────────────────────────────────────────────── -->
    <div class="pb-3">
      <div class="d-flex align-center step-track">
        <template v-for="(s, idx) in steps" :key="s.value">
          <div class="d-flex flex-column align-center step-node">
            <v-avatar
              size="36"
              :color="step >= s.value ? 'primary' : 'grey-lighten-2'"
              :variant="step >= s.value ? 'flat' : 'tonal'"
            >
              <v-icon
                :icon="step > s.value ? 'mdi-check' : s.icon"
                :color="step >= s.value ? 'white' : 'grey-darken-1'"
                size="18"
              />
            </v-avatar>
            <span
              class="text-caption mt-1 text-center"
              :class="
                step >= s.value
                  ? 'text-primary font-weight-medium'
                  : 'text-medium-emphasis'
              "
            >
              {{ s.title }}
            </span>
          </div>
          <div
            v-if="idx < steps.length - 1"
            class="step-line"
            :class="step > s.value ? 'step-line--active' : ''"
          />
        </template>
      </div>
    </div>

    <v-window v-model="step" class="py-2">
      <!-- STEP 1: BASICS -->
      <v-window-item :value="1">
        <v-row dense>
          <v-col cols="12">
            <v-text-field
              v-model="form.name"
              :label="t('promotions.form.name')"
              placeholder="e.g. Weekend Special 20% Off"
              variant="outlined"
              rounded="lg"
              autofocus
              :rules="[v => !!v?.trim() || t('promotions.form.required')]"
            />
          </v-col>

          <v-col cols="12">
            <div class="text-subtitle-2 font-weight-medium mb-2">
              {{ t('promotions.types.title') }}
            </div>
            <v-row dense>
              <v-col
                v-for="opt in promoTypes"
                :key="opt.value"
                cols="6"
                sm="4"
              >
                <v-card
                  :variant="form.type === opt.value ? 'flat' : 'outlined'"
                  :color="
                    form.type === opt.value ? 'brown-darken-3' : undefined
                  "
                  rounded="lg"
                  class="type-card pa-3 d-flex flex-column align-center text-center"
                  @click="form.type = opt.value"
                >
                  <v-icon
                    :icon="opt.icon"
                    :color="
                      form.type === opt.value ? 'white' : 'brown-darken-2'
                    "
                    size="22"
                    class="mb-1"
                  />
                  <span
                    class="text-caption font-weight-medium"
                    :class="form.type === opt.value ? 'text-white' : ''"
                  >
                    {{ opt.title }}
                  </span>
                </v-card>
              </v-col>
            </v-row>
            <div
              v-if="selectedTypeInfo"
              class="text-caption text-medium-emphasis mt-2"
            >
              <v-icon
                icon="mdi-information-outline"
                size="14"
                class="mr-1"
              />
              {{ selectedTypeInfo.hint }}
            </div>
          </v-col>

          <v-col cols="12">
            <div class="text-subtitle-2 font-weight-medium mb-2">
              {{ t('promotions.form.applies_to') }}
            </div>
            <v-select
              v-model="form.applies_to"
              :items="appliesToOptions"
              item-title="title"
              item-value="value"
              variant="outlined"
              rounded="lg"
              hide-details
            >
              <template #prepend-inner>
                <v-icon
                  :icon="
                    appliesToOptions.find(o => o.value === form.applies_to)
                      ?.icon
                  "
                  size="18"
                />
              </template>
            </v-select>
          </v-col>
        </v-row>
      </v-window-item>

      <!-- STEP 2: RULES -->
      <v-window-item :value="2">
        <v-row dense>
          <v-col v-if="showDiscountValue(form.type)" cols="12" sm="6">
            <v-text-field
              v-model.number="form.discount_value"
              :label="discountLabel"
              :suffix="form.type === 'percentage' ? '%' : '$'"
              type="number"
              min="0"
              variant="outlined"
              rounded="lg"
              :rules="[
                v => v > 0 || t('promotions.form.value_greater_than_zero')
              ]"
              :hint="t('promotions.form.required')"
              persistent-hint
            />
          </v-col>

          <v-col v-if="form.type === 'percentage'" cols="12" sm="6">
            <v-text-field
              v-model.number="form.max_discount_amount"
              :label="t('promotions.form.max_discount_cap')"
              type="number"
              min="0"
              variant="outlined"
              rounded="lg"
              :hint="t('promotions.form.max_discount_cap_hint')"
              persistent-hint
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.min_order_amount"
              :label="t('promotions.form.min_order_amount')"
              type="number"
              min="0"
              variant="outlined"
              rounded="lg"
              :hint="t('promotions.form.min_order_amount_hint')"
              persistent-hint
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.usage_limit"
              :label="t('promotions.form.total_limit_uses')"
              type="number"
              min="0"
              variant="outlined"
              rounded="lg"
              :hint="t('promotions.form.unlimited_hint')"
              persistent-hint
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              v-model.number="form.per_customer_limit"
              :label="t('promotions.form.per_customer_limit')"
              type="number"
              min="0"
              variant="outlined"
              rounded="lg"
              :hint="t('promotions.form.unlimited_hint')"
              persistent-hint
            />
          </v-col>
        </v-row>
      </v-window-item>

      <!-- STEP 3: SCHEDULE -->
      <v-window-item :value="3">
        <v-row dense>
          <v-col cols="12" sm="6">
            <AppDatePicker
              v-model="form.start_at"
              :label="t('promotions.form.start_date')"
              display-format="dd-MM-yyyy"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <AppDatePicker
              v-model="form.end_at"
              :label="t('promotions.form.end_date')"
              display-format="dd-MM-yyyy"
            />
          </v-col>

          <v-col cols="12">
            <v-card
              variant="tonal"
              color="brown-lighten-4"
              rounded="lg"
              class="pa-4 mt-2"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-subtitle-2 font-weight-medium">
                    {{ $t('promotions.form.active') }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ $t('promotions.form.active_hint') }}
                  </div>
                </div>
                <v-switch
                  v-model="form.is_active"
                  color="brown-darken-3"
                  inset
                  hide-details
                />
              </div>
            </v-card>
          </v-col>

          <!-- Review summary -->
          <v-col cols="12">
            <div class="text-subtitle-2 font-weight-medium mb-2">
              {{ t('promotions.form.summary') }}
            </div>
            <v-card variant="outlined" rounded="lg" class="pa-3">
              <div class="d-flex flex-wrap ga-2">
                <v-chip
                  size="small"
                  variant="tonal"
                  prepend-icon="mdi-tag-outline"
                >
                  {{ form.name || t('promotions.form.untitled_promotion') }}
                </v-chip>
                <v-chip
                  size="small"
                  variant="tonal"
                  :prepend-icon="selectedTypeInfo?.icon"
                >
                  {{ selectedTypeInfo?.title }}
                </v-chip>
                <v-chip
                  v-if="showDiscountValue(form.type) && form.discount_value"
                  size="small"
                  variant="tonal"
                  prepend-icon="mdi-sale-outline"
                >
                  {{ form.discount_value
                  }}{{ form.type === 'percentage' ? '%' : '$' }}
                  {{ t('promotions.form.off') }}
                </v-chip>
                <v-chip
                  size="small"
                  variant="tonal"
                  :prepend-icon="
                    appliesToOptions.find(o => o.value === form.applies_to)
                      ?.icon
                  "
                >
                  {{
                    appliesToOptions.find(o => o.value === form.applies_to)
                      ?.title
                  }}
                </v-chip>
                <v-chip
                  size="small"
                  :color="form.is_active ? 'green' : 'grey'"
                  variant="tonal"
                  prepend-icon="mdi-circle-medium"
                >
                  {{
                    form.is_active
                      ? t('promotions.form.active_status')
                      : t('promotions.form.inactive_status')
                  }}
                </v-chip>
              </div>
            </v-card>
          </v-col>
        </v-row>
      </v-window-item>
    </v-window>

    <template #actions="{ loading }">
      <v-btn
        v-if="step > 1"
        variant="text"
        :disabled="loading"
        prepend-icon="mdi-arrow-left"
        @click="goBack"
      >
        {{ $t('btn.back') }}
      </v-btn>
      <v-btn v-else variant="text" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>

      <v-spacer />

      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        :loading="loading"
        :disabled="!canGoNext"
        :append-icon="isLastStep ? undefined : 'mdi-arrow-right'"
        @click="handlePrimaryAction"
      >
        {{
          isLastStep
            ? editing
              ? $t('btn.save_changes')
              : $t('btn.create_promotion')
            : $t('btn.next')
        }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<style scoped>
  .step-track {
    width: 100%;
  }

  .step-node {
    flex: 0 0 auto;
    min-width: 64px;
  }

  .step-line {
    flex: 1 1 auto;
    height: 2px;
    background: rgba(var(--v-theme-on-surface), 0.12);
    margin: 0 4px;
    margin-bottom: 22px;
    transition: background 0.2s ease;
  }

  .step-line--active {
    background: rgb(var(--v-theme-brown-darken-3, 93, 64, 55));
  }

  .type-card {
    cursor: pointer;
    transition: all 0.15s ease;
  }

  .type-card:hover {
    transform: translateY(-1px);
  }
</style>
