<template>
  <AppDialog
    v-model="model"
    :max-width="620"
    :title="isEdit ? $t('reservations.dialog.edit') : $t('reservations.dialog.new')"
    :loading="loading"
  >
    <div class="pb-4">
      <div class="d-flex align-center justify-space-between mb-2">
        <span class="text-caption font-weight-medium text-medium-emphasis d-flex align-center ga-1">
          <v-icon :icon="steps[currentStep - 1].icon" size="14" />
          {{ steps[currentStep - 1].label }}
        </span>
        <span class="text-caption text-medium-emphasis">
          {{ currentStep }} / {{ steps.length }}
        </span>
      </div>
      <v-progress-linear
        :model-value="(currentStep / steps.length) * 100"
        :color="isEdit ? 'primary' : 'success'"
        height="4"
        rounded
      />
    </div>

    <v-window v-model="currentStep" :touch="false">
      <!-- ════ Step 1 — Guest & Branch ════ -->
      <v-window-item :value="1">
        <v-form ref="step1Ref" class="mt-2">
          <v-row dense>
            <v-col cols="12">
              <v-select
                v-model="form.branch_id"
                :items="branches"
                item-value="id"
                item-title="name"
                :label="$t('form.branch')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-store-outline"
              />
            </v-col>

            <v-col cols="12" sm="7">
              <v-text-field
                v-model="form.customer_name"
                :label="$t('reservations.field.guest_name')"
                :placeholder="$t('reservations.field.guest_name_placeholder')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-account-outline"
                maxlength="150"
                autofocus
              />
            </v-col>

            <v-col cols="12" sm="5">
              <v-text-field
                v-model="form.customer_phone"
                :label="$t('form.phone')"
                placeholder="+855 ..."
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-phone-outline"
                maxlength="30"
              />
            </v-col>

            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.party_size"
                type="number"
                :label="$t('reservations.field.party_size')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required, r.positive]"
                prepend-inner-icon="mdi-account-group-outline"
                min="1"
              />
            </v-col>
          </v-row>
        </v-form>
      </v-window-item>

      <!-- ════ Step 2 — Table & Time ════ -->
      <v-window-item :value="2">
        <v-form ref="step2Ref" class="mt-2">
          <v-row dense>
            <v-col cols="12">
              <v-select
                v-model="form.table_id"
                :items="availableTables"
                item-value="id"
                :label="$t('reservations.field.table')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-table-chair"
                clearable
                :hint="$t('reservations.table_hint')"
                persistent-hint
              >
                <template #item="{ props: p, item }">
                  <v-list-item
                    v-bind="p"
                    :title="$t('reservations.table_n', { n: item.raw.table_number })"
                  >
                    <template #subtitle>
                      <span class="text-caption">
                        {{ item.raw.capacity }} {{ $t('reservations.seats') }} ·
                        <span :style="{ color: statusBg(item.raw.status) }">
                          {{ item.raw.status }}
                        </span>
                      </span>
                    </template>
                  </v-list-item>
                </template>
                <template #selection="{ item }">
                  {{ $t('reservations.table_selection', { number: item.raw?.table_number, capacity: item.raw?.capacity }) }}
                </template>
              </v-select>
            </v-col>

            <!-- Party vs capacity warning -->
            <v-col
              v-if="selectedTable && form.party_size > selectedTable.capacity"
              cols="12"
            >
              <v-alert
                type="warning"
                variant="tonal"
                density="compact"
                rounded="lg"
                :icon="false"
              >
                <v-icon icon="mdi-alert-outline" size="16" class="mr-1" />
                {{ $t('reservations.party_of') }}
                <strong>{{ form.party_size }}</strong>
                {{ $t('reservations.exceeds_capacity') }}
                <strong>{{ selectedTable.capacity }}</strong>
              </v-alert>
            </v-col>

            <v-col cols="12" sm="5">
              <AppDatePicker
                v-model="form.reserved_date"
                :label="$t('reservations.field.reserved_date')"
              />
            </v-col>

            <v-col cols="12" sm="3">
              <v-text-field
                v-model="form.reserved_time"
                type="time"
                :label="$t('reservations.field.reserved_time')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required, r.notPastTime]"
              />
            </v-col>

            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.duration_minutes"
                type="number"
                :label="$t('reservations.field.duration')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-timer-outline"
                min="0"
                step="15"
                :hint="$t('reservations.duration_hint')"
                persistent-hint
                clearable
              />
            </v-col>

            <!-- End time preview -->
            <v-col v-if="endTime" cols="12">
              <v-alert
                type="info"
                variant="tonal"
                density="compact"
                rounded="lg"
                :icon="false"
              >
                <v-icon icon="mdi-clock-check-outline" size="16" class="mr-1" />
                {{ $t('reservations.table_released_at') }}
                <strong>{{ endTime }}</strong>
              </v-alert>
            </v-col>
          </v-row>
        </v-form>
      </v-window-item>

      <!-- ════ Step 3 — Confirm ════ -->
      <v-window-item :value="3" class="mt-2">
        <v-form ref="step3Ref">
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.status"
                :items="statusOptions"
                item-title="label"
                item-value="value"
                :label="$t('form.status')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-information-outline"
              />
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="form.notes"
                :label="$t('form.notes')"
                :placeholder="$t('reservations.notes_placeholder')"
                variant="outlined"
                rounded="lg"
                rows="2"
                prepend-inner-icon="mdi-note-text-outline"
              />
            </v-col>

            <v-col cols="12">
              <v-divider class="mb-3 mt-1" />
              <p class="text-overline text-medium-emphasis mb-2">
                {{ $t('reservations.dialog.review') }}
              </p>
              <v-sheet rounded="lg" border class="pa-3">
                <div class="review-row">
                  <span class="review-label">{{ $t('reservations.field.guest_name') }}</span>
                  <span class="review-value">{{ form.customer_name || '—' }}</span>
                </div>
                <div class="review-row">
                  <span class="review-label">{{ $t('form.phone') }}</span>
                  <span class="review-value">{{ form.customer_phone || '—' }}</span>
                </div>
                <div class="review-row">
                  <span class="review-label">{{ $t('form.branch') }}</span>
                  <span class="review-value">{{ selectedBranch?.name || '—' }}</span>
                </div>
                <div class="review-row">
                  <span class="review-label">{{ $t('reservations.field.party_size') }}</span>
                  <span class="review-value">{{ form.party_size || '—' }}</span>
                </div>
                <div class="review-row">
                  <span class="review-label">{{ $t('reservations.field.table') }}</span>
                  <span class="review-value">
                    {{ selectedTable ? $t('reservations.table_n', { n: selectedTable.table_number }) : $t('reservations.table_hint') }}
                  </span>
                </div>
                <div class="review-row">
                  <span class="review-label">{{ $t('reservations.field.reserved_at') }}</span>
                  <span class="review-value">{{ reservedAtDisplay || '—' }}</span>
                </div>
                <div v-if="endTime" class="review-row">
                  <span class="review-label">{{ $t('reservations.table_released_at') }}</span>
                  <span class="review-value">{{ endTime }}</span>
                </div>
              </v-sheet>
            </v-col>
          </v-row>
        </v-form>
      </v-window-item>
    </v-window>

    <template #actions="{ loading }">
      <v-btn
        v-if="currentStep > 1"
        variant="tonal"
        rounded="lg"
        :disabled="loading"
        prepend-icon="mdi-arrow-left"
        @click="prevStep"
      >
        {{ $t('btn.back') }}
      </v-btn>
      <v-spacer />
      <v-btn variant="text" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        v-if="currentStep < steps.length"
        color="primary"
        variant="flat"
        rounded="lg"
        append-icon="mdi-arrow-right"
        @click="nextStep"
      >
        {{ $t('btn.next') }}
      </v-btn>
      <v-btn
        v-else
        :color="isEdit ? 'primary' : 'success'"
        variant="flat"
        rounded="lg"
        :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-calendar-plus'"
        :loading="loading"
        @click="submit"
      >
        {{ isEdit ? $t('btn.save_changes') : $t('reservations.book_table') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useI18n } from 'vue-i18n'
  import { useBranchStore } from '@/stores/branchStore'
  import { useDate } from '@/composables/useDate'
  import { AppDialog, AppDatePicker } from '@nong-official-dev/core'

  const { formatTime, formatShortDateTime } = useDate()

  const { t } = useI18n()

  const props = defineProps({
    modelValue: Boolean,
    item: Object,
    tables: { type: Array, default: () => [] },
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const branchStore = useBranchStore()
  const { branches } = storeToRefs(branchStore)

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.item?.id)

  // ── Steps ───────────────────────────────────────────────────────────────
  const currentStep = ref(1)
  const step1Ref = ref(null)
  const step2Ref = ref(null)
  const step3Ref = ref(null)
  const stepRefs = [step1Ref, step2Ref, step3Ref]

  const steps = computed(() => [
    { label: t('reservations.dialog.steps.guest'), icon: 'mdi-account-group-outline' },
    { label: t('reservations.dialog.steps.table_time'), icon: 'mdi-calendar-clock-outline' },
    { label: t('reservations.dialog.steps.confirm'), icon: 'mdi-check-decagram-outline' }
  ])

  const statusOptions = [
    { value: 'pending', label: t('status.pending') },
    { value: 'confirmed', label: t('status.confirmed') },
    { value: 'seated', label: t('status.seated') },
    { value: 'completed', label: t('status.completed') },
    { value: 'cancelled', label: t('status.cancelled') },
    { value: 'no_show', label: t('status.no_show') }
  ]

  const defaultForm = () => ({
    branch_id: null,
    customer_name: '',
    customer_phone: '',
    party_size: 2,
    table_id: null,
    reserved_date: null,
    reserved_time: null,
    duration_minutes: 90,
    status: 'pending',
    notes: ''
  })

  const form = reactive(defaultForm())

  // ── Local date/time helpers ──────────────────────────────────────────────
  // Built from local getters (not toISOString(), which is UTC-based and can
  // land on the wrong calendar day near midnight) so "today"/"now" always
  // match the venue's own clock, not UTC.
  const localDateStr = d =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  const localTimeStr = d =>
    `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`

  const todayStr = localDateStr(new Date())
  // AppDatePicker emits a plain "YYYY-MM-DD" string, but the form is also
  // pre-populated with one when editing — every read of form.reserved_date
  // must go through this, never compare the raw value directly (a Date
  // compared to a string, if one ever creeps in, coerces via
  // Date#toString(), which doesn't sort chronologically at all).
  const dateOnlyStr = v => {
    if (!v) return null
    const d = v instanceof Date ? v : new Date(v)
    return Number.isNaN(d.getTime()) ? null : localDateStr(d)
  }

  // Splits a reserved_at value from the API (ISO datetime) into the two
  // local inputs this form actually edits.
  const splitDateTime = iso => {
    if (!iso) return { date: null, time: null }
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return { date: null, time: null }
    return { date: localDateStr(d), time: localTimeStr(d) }
  }

  watch(
    () => props.item,
    val => {
      Object.keys(form).forEach(k => delete form[k])
      if (val) {
        const { date, time } = splitDateTime(val.reserved_at)
        Object.assign(form, { ...val, reserved_date: date, reserved_time: time })
      } else {
        Object.assign(form, defaultForm())
      }
      currentStep.value = 1
    },
    { immediate: true }
  )

  // Tables filtered by selected branch
  const availableTables = computed(() =>
    props.tables.filter(
      t => t.is_active && (!form.branch_id || t.branch_id === form.branch_id)
    )
  )

  // Selected table/branch details
  const selectedTable = computed(
    () => props.tables.find(t => t.id === form.table_id) || null
  )
  const selectedBranch = computed(
    () => branches.value.find(b => b.id === form.branch_id) || null
  )

  // Combined reserved_at — the only place the two inputs are joined back
  // into the single datetime value the backend actually stores.
  const combinedReservedAt = computed(() => {
    const dateStr = dateOnlyStr(form.reserved_date)
    if (!dateStr || !form.reserved_time) return null
    return `${dateStr}T${form.reserved_time}`
  })

  // End time calculation
  const endTime = computed(() => {
    if (!combinedReservedAt.value || !form.duration_minutes) return null
    const end = new Date(combinedReservedAt.value)
    end.setMinutes(end.getMinutes() + Number(form.duration_minutes))
    return formatTime(end)
  })

  const reservedAtDisplay = computed(() =>
    combinedReservedAt.value ? formatShortDateTime(new Date(combinedReservedAt.value)) : null
  )

  const statusBg = s =>
    ({
      available: '#4caf50',
      occupied: '#f44336',
      reserved: '#ff9800',
      cleaning: '#2196f3',
      inactive: '#9e9e9e'
    })[s] || '#9e9e9e'

  const r = {
    required: v => !!v || v === 0 || t('validation.required'),
    positive: v => !v || Number(v) > 0 || t('validation.positive'),
    // Only meaningful when the date is today — a past date itself isn't
    // blocked (AppDatePicker has no rules/min support), so this only
    // catches picking an earlier time slot on today's date.
    notPastTime: v => {
      const dateStr = dateOnlyStr(form.reserved_date)
      if (!v || !dateStr || dateStr !== todayStr) return true
      return v >= localTimeStr(new Date()) || t('reservations.rule.past_time')
    }
  }

  // ── Navigation — each step validates only its own fields, so an
  // unfilled later step can never block progress on an earlier one.
  const nextStep = async () => {
    const { valid } = await stepRefs[currentStep.value - 1].value.validate()
    if (!valid) return
    if (currentStep.value < steps.value.length) currentStep.value++
  }

  const prevStep = () => {
    if (currentStep.value > 1) currentStep.value--
  }

  const submit = async () => {
    const { valid } = await step3Ref.value.validate()
    if (!valid) return
    emit('save', { ...form, reserved_at: combinedReservedAt.value })
  }

  const close = () => {
    stepRefs.forEach(r => r.value?.reset())
    Object.assign(form, defaultForm())
    currentStep.value = 1
    emit('update:modelValue', false)
  }

  // Reset the wizard when the dialog closes via the built-in × / backdrop
  // (paths that don't go through close() above).
  watch(
    () => props.modelValue,
    open => {
      if (!open) {
        stepRefs.forEach(r => r.value?.reset())
        Object.assign(form, defaultForm())
        currentStep.value = 1
      }
    }
  )

  onMounted(() => branchStore.fetchBranches?.({ perPage: -1 }))
</script>

<style scoped>
  .review-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    padding: 6px 0;
  }
  .review-row + .review-row {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .review-label {
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.6);
  }
  .review-value {
    font-size: 13px;
    font-weight: 500;
    text-align: right;
  }
</style>
