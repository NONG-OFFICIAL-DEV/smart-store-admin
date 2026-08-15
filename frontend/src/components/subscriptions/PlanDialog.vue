<template>
  <AppDialog
    v-model="model"
    :max-width="720"
    :title="
      editingPlan
        ? $t('subscription.plan_dialog.edit_title')
        : $t('subscription.plan_dialog.new_title')
    "
    :subtitle="
      editingPlan
        ? $t('subscription.plan_dialog.edit_subtitle')
        : $t('subscription.plan_dialog.new_subtitle')
    "
    :icon="editingPlan ? 'mdi-pencil-outline' : 'mdi-plus'"
    :color="editingPlan ? 'primary' : 'success'"
    :loading="saving"
    :submit-text="
      editingPlan ? $t('btn.save_changes') : $t('subscription.plan_dialog.create_plan')
    "
    body-class="pa-0"
    @submit="submit"
  >
    <template #header-extra>
      <v-tabs
        v-model="tab"
        color="primary"
        density="comfortable"
        class="px-3"
      >
        <v-tab value="basic">
          {{ $t('subscription.plan_dialog.tabs.basic_info') }}
          <v-icon
            v-if="tabErrors.basic"
            size="14"
            color="error"
            class="ms-1"
          >
            mdi-alert-circle
          </v-icon>
        </v-tab>
        <v-tab value="cycles">
          {{ $t('subscription.plan_dialog.tabs.billing_cycles') }}
          <v-chip
            v-if="form.billing_cycles?.length && !tabErrors.cycles"
            size="x-small"
            class="ms-1"
            color="primary"
            variant="tonal"
          >
            {{ form.billing_cycles.length }}
          </v-chip>
          <v-icon
            v-if="tabErrors.cycles"
            size="14"
            color="error"
            class="ms-1"
          >
            mdi-alert-circle
          </v-icon>
        </v-tab>
        <v-tab value="features">
          {{ $t('subscription.plan_dialog.tabs.features') }}
          <v-icon
            v-if="tabErrors.features"
            size="14"
            color="error"
            class="ms-1"
          >
            mdi-alert-circle
          </v-icon>
        </v-tab>
      </v-tabs>
    </template>

    <v-form ref="formRef" @submit.prevent="submit" v-model="isValid">
      <v-window v-model="tab" class="pa-5">
            <!-- Basic Info -->
            <v-window-item value="basic">
              <v-row dense>
                <v-col cols="8">
                  <v-text-field
                    v-model="form.name"
                    :label="$t('subscription.plan_dialog.fields.plan_name')"
                    :rules="[r.required]"
                    maxlength="80"
                    rounded="lg"
                    variant="outlined"
                  />
                </v-col>
                <v-col cols="4">
                  <v-text-field
                    v-model="form.code"
                    :label="$t('subscription.plan_dialog.fields.code')"
                    :rules="[r.required]"
                    maxlength="30"
                    :hint="$t('subscription.plan_dialog.fields.code_hint')"
                    persistent-hint
                    rounded="lg"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="6">
                  <v-text-field
                    v-model="form.price_usd"
                    :label="$t('subscription.plan_dialog.fields.price_usd')"
                    type="number"
                    :rules="[r.required]"
                    min="0"
                    prepend-inner-icon="mdi-currency-usd"
                    rounded="lg"
                    variant="outlined"
                  />
                </v-col>
                <v-col cols="6">
                  <v-text-field
                    v-model="form.price_khr"
                    :label="$t('subscription.plan_dialog.fields.price_khr')"
                    type="number"
                    min="0"
                    prepend-inner-icon="mdi-currency-sign"
                    rounded="lg"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="4">
                  <v-text-field
                    v-model="form.seats"
                    :label="$t('subscription.plan_dialog.fields.seats')"
                    type="number"
                    :rules="[r.required]"
                    min="1"
                    prepend-inner-icon="mdi-account-group-outline"
                    rounded="lg"
                    variant="outlined"
                  />
                </v-col>
                <v-col cols="4">
                  <v-text-field
                    v-model="form.storage_gb"
                    :label="$t('subscription.plan_dialog.fields.storage_gb')"
                    type="number"
                    :rules="[r.required]"
                    min="1"
                    prepend-inner-icon="mdi-database-outline"
                    rounded="lg"
                    variant="outlined"
                  />
                </v-col>
                <v-col cols="4">
                  <v-text-field
                    v-model="form.api_limit"
                    :label="$t('subscription.plan_dialog.fields.api_limit')"
                    type="number"
                    min="0"
                    :hint="$t('subscription.plan_dialog.fields.api_limit_hint')"
                    persistent-hint
                    prepend-inner-icon="mdi-api"
                    rounded="lg"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12">
                  <v-switch
                    v-model="form.is_active"
                    :label="$t('status.active')"
                    color="success"
                    inset
                  />
                </v-col>
              </v-row>
            </v-window-item>

            <!-- Billing Cycles Tab -->
            <v-window-item value="cycles">
              <div class="text-caption text-medium-emphasis mb-4">
                {{ $t('subscription.plan_dialog.cycles.description') }}
              </div>

              <div
                v-for="(cycle, i) in form.billing_cycles"
                :key="i"
                class="cycle-row mb-3"
              >
                <v-row dense align="center">
                  <v-col cols="12" sm="4">
                    <v-text-field
                      v-model="cycle.label"
                      :label="$t('subscription.plan_dialog.cycles.label')"
                      :rules="[r.required]"
                      rounded="lg"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="5" sm="3">
                    <v-select
                      v-model="cycle.months"
                      :items="monthOptions"
                      item-title="label"
                      item-value="value"
                      :label="$t('subscription.plan_dialog.cycles.months')"
                      :rules="[r.required]"
                      rounded="lg"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="5" sm="3">
                    <v-text-field
                      v-model="cycle.discount_percent"
                      :label="$t('subscription.plan_dialog.cycles.discount_percent')"
                      type="number"
                      min="0"
                      max="100"
                      suffix="%"
                      rounded="lg"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col
                    cols="2"
                    sm="1"
                    class="d-flex align-center justify-center"
                  >
                    <v-btn
                      icon="mdi-delete-outline"
                      size="small"
                      variant="text"
                      color="error"
                      class="mb-4"
                      @click="removeCycle(i)"
                    />
                  </v-col>
                  <v-col cols="12" sm="1">
                    <v-switch
                      v-model="cycle.is_active"
                      color="success"
                      inset
                      density="compact"
                    />
                  </v-col>
                </v-row>
              </div>

              <v-btn
                variant="tonal"
                rounded="lg"
                size="small"
                prepend-icon="mdi-plus"
                color="primary"
                @click="addCycle"
              >
                {{ $t('subscription.plan_dialog.cycles.add_cycle') }}
              </v-btn>
              <div
                v-if="submitted && form.billing_cycles.length === 0"
                class="text-error text-caption mt-3"
              >
                {{ $t('subscription.plan_dialog.cycles.required_error') }}
              </div>
              <div class="mt-4">
                <div class="text-caption text-medium-emphasis mb-2">
                  {{ $t('subscription.plan_dialog.cycles.quick_presets') }}
                </div>
                <div class="d-flex flex-wrap ga-2">
                  <v-chip
                    v-for="preset in cyclePresets"
                    :key="preset.months"
                    size="small"
                    variant="tonal"
                    color="primary"
                    @click="applyPreset(preset)"
                  >
                    {{ preset.title }}
                  </v-chip>
                </div>
              </div>
            </v-window-item>

            <!-- Features -->
            <v-window-item value="features">
              <div class="text-caption text-medium-emphasis mb-4">
                {{ $t('subscription.plan_dialog.features.description') }}
              </div>

              <div v-for="(feat, i) in form.features" :key="i" class="mb-3">
                <v-row dense>
                  <v-col cols="12" sm="3">
                    <v-text-field
                      v-model="feat.key"
                      :label="$t('subscription.plan_dialog.features.key')"
                      :rules="[r.required]"
                      rounded="lg"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="4">
                    <v-text-field
                      v-model="feat.en"
                      :label="$t('subscription.plan_dialog.features.english')"
                      :rules="[r.required]"
                      rounded="lg"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="10" sm="4">
                    <v-text-field
                      v-model="feat.km"
                      label="ខ្មែរ"
                      rounded="lg"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col
                    cols="2"
                    sm="1"
                    class="d-flex align-center justify-center"
                  >
                    <v-btn
                      icon="mdi-delete-outline"
                      size="small"
                      variant="text"
                      color="error"
                      @click="removeFeature(i)"
                    />
                  </v-col>
                </v-row>
              </div>

              <v-btn
                variant="tonal"
                rounded="lg"
                size="small"
                prepend-icon="mdi-plus"
                color="primary"
                @click="addFeature"
              >
                {{ $t('subscription.plan_dialog.features.add_feature') }}
              </v-btn>
            </v-window-item>
          </v-window>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()

  const props = defineProps({
    editingPlan: { type: Object, default: null },
    saving: { type: Boolean, default: false }
  })

  const emit = defineEmits(['submit'])
  const model = defineModel({ type: Boolean, default: false })

  const formRef = ref(null)
  const tab = ref('basic')
  const isValid = ref(false)
  const submitted = ref(false) // tracks whether submit was attempted

  const r = {
    required: v =>
      (v !== null && v !== undefined && v !== '') || t('form.required')
  }

  const monthOptions = computed(() => [
    { label: t('subscription.plan_dialog.months.one'), value: 1 },
    { label: t('subscription.plan_dialog.months.three'), value: 3 },
    { label: t('subscription.plan_dialog.months.six'), value: 6 },
    { label: t('subscription.plan_dialog.months.twelve'), value: 12 }
  ])

  const cyclePresets = computed(() => [
    {
      label: 'Monthly',
      title: t('subscription.plan_dialog.presets.monthly'),
      months: 1,
      discount_percent: 0,
      is_active: true
    },
    {
      label: 'Quarterly',
      title: t('subscription.plan_dialog.presets.quarterly'),
      months: 3,
      discount_percent: 5,
      is_active: true
    },
    {
      label: 'Semi-annual',
      title: t('subscription.plan_dialog.presets.semi_annual'),
      months: 6,
      discount_percent: 10,
      is_active: true
    },
    {
      label: 'Yearly',
      title: t('subscription.plan_dialog.presets.yearly'),
      months: 12,
      discount_percent: 20,
      is_active: true
    }
  ])

  const defaultForm = () => ({
    name: '',
    code: '',
    price_usd: 0,
    price_khr: 0,
    seats: 1,
    storage_gb: 1,
    api_limit: 0,
    is_active: true,
    billing_cycles: [],
    features: []
  })

  const form = ref(defaultForm())

  // ─── Per-tab error detection (data-level, not DOM) ───────────────────────────
  // Only show tab error badges after the user has attempted to submit once.
  const tabErrors = computed(() => {
    if (!submitted.value)
      return { basic: false, cycles: false, features: false }

    const isEmpty = v => v === null || v === undefined || v === ''

    const basic =
      isEmpty(form.value.name) ||
      isEmpty(form.value.code) ||
      isEmpty(form.value.price_usd) ||
      isEmpty(form.value.seats) ||
      isEmpty(form.value.storage_gb)

    const cycles = form.value.billing_cycles.some(
      c => isEmpty(c.label) || isEmpty(c.months)
    )

    const features = form.value.features.some(
      f => isEmpty(f.key) || isEmpty(f.en)
    )

    return { basic, cycles, features }
  })

  // Watch editing plan
  watch(
    () => props.editingPlan,
    plan => {
      tab.value = 'basic'
      submitted.value = false
      if (plan) {
        form.value = {
          name: plan.name ?? '',
          code: plan.code ?? '',
          price_usd: plan.price_usd ?? 0,
          price_khr: plan.price_khr ?? 0,
          seats: plan.seats ?? 1,
          storage_gb: plan.storage_gb ?? 1,
          api_limit: plan.api_limit ?? 0,
          is_active: plan.is_active ?? true,
          billing_cycles: Array.isArray(plan.billing_cycles)
            ? plan.billing_cycles.map(c => ({ ...c }))
            : [],
          features: Array.isArray(plan.features)
            ? plan.features.map(f => ({ ...f }))
            : []
        }
      } else {
        form.value = defaultForm()
      }
    },
    { immediate: true }
  )

  // Reset submitted state when dialog closes
  watch(model, open => {
    if (!open) submitted.value = false
  })

  // Methods
  const addCycle = () => {
    form.value.billing_cycles.push({
      label: '',
      months: 1,
      discount_percent: 0,
      is_active: true
    })
  }

  const removeCycle = i => {
    form.value.billing_cycles.splice(i, 1)
  }

  const applyPreset = preset => {
    const exists = form.value.billing_cycles.some(
      c => c.months === preset.months
    )
    if (!exists) {
      const { title, ...cycle } = preset
      form.value.billing_cycles.push({ ...cycle })
    }
  }

  const addFeature = () => {
    form.value.features.push({
      key: '',
      en: '',
      km: '',
      sort_order: form.value.features.length
    })
  }

  const removeFeature = i => {
    form.value.features.splice(i, 1)
  }

  const submit = async () => {
    if (!formRef.value) return

    submitted.value = true

    const { valid } = await formRef.value.validate()

    if (!valid) {
      if (tabErrors.value.basic) tab.value = 'basic'
      else if (tabErrors.value.cycles) tab.value = 'cycles'
      else if (tabErrors.value.features) tab.value = 'features'
      return
    }

    // ← Add this check
    if (form.value.billing_cycles.length === 0) {
      tab.value = 'cycles'
      return
    }

    emit('submit', { ...form.value })
  }
</script>

<style scoped>
  .cycle-row {
    background: rgba(var(--v-theme-surface-variant), 0.08);
    border: 1px solid rgba(var(--v-border-color), 0.15);
    border-radius: 12px;
    padding: 12px;
  }
</style>
