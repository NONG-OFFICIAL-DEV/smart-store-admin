<template>
  <AppDialog
    v-model="model"
    :max-width="520"
    :title="isEdit ? $t('shifts.form.titleEdit') : $t('shifts.form.titleCreate')"
    :subtitle="isEdit ? $t('shifts.form.subtitleEdit') : $t('shifts.form.subtitleCreate')"
    :icon="isEdit ? 'mdi-pencil' : 'mdi-plus'"
    :color="isEdit ? 'primary' : 'success'"
    :loading="loading"
    :submit-text="isEdit ? $t('btn.save') : $t('btn.create')"
    :submit-icon="isEdit ? 'mdi-content-save' : 'mdi-plus'"
    @close="close"
    @submit="submit"
  >
        <v-form ref="formRef">
          <v-row dense>
            <!-- Tenant -->
            <v-col cols="12">
              <v-select
                v-if="isSuperAdmin()"
                v-model="form.tenant_id"
                :items="tenants"
                item-value="id"
                item-title="name"
                :label="$t('shifts.form.fields.tenant.label')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :disabled="isEdit"
                prepend-inner-icon="mdi-domain"
                :hint="$t('shifts.form.fields.tenant.hint')"
                persistent-hint
              />
            </v-col>

            <!-- Name -->
            <v-col cols="12" sm="7">
              <v-text-field
                v-model="form.name"
                :label="$t('shifts.form.fields.name.label')"
                :placeholder="$t('shifts.form.fields.name.placeholder')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-tag-outline"
                maxlength="60"
              />
            </v-col>

            <!-- Shift Type -->
            <v-col cols="12" sm="5">
              <v-select
                v-model="form.shift_type"
                :items="shiftTypes"
                item-title="label"
                item-value="value"
                :label="$t('shifts.form.fields.type.label')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                clearable
                prepend-inner-icon="mdi-shape-outline"
              />
            </v-col>

            <!-- Time Window divider -->
            <v-col cols="12">
              <v-divider class="my-1" />
              <p class="text-caption text-medium-emphasis mt-2 mb-1">
                <v-icon icon="mdi-clock-outline" size="14" class="mr-1" />
                {{ $t('shifts.form.fields.timeWindow') }}
              </p>
            </v-col>

            <!-- Start Time -->
            <v-col cols="12" sm="6">
              <v-text-field
                ref="startTimeRef"
                v-model="form.start_time"
                type="time"
                :label="$t('shifts.form.fields.startTime.label')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-clock-start"
                :hint="$t('shifts.form.fields.startTime.hint')"
                persistent-hint
              />
            </v-col>

            <!-- End Time -->
            <v-col cols="12" sm="6">
              <v-text-field
                ref="endTimeRef"
                v-model="form.end_time"
                type="time"
                :label="$t('shifts.form.fields.endTime.label')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-clock-end"
                :hint="$t('shifts.form.fields.endTime.hint')"
                persistent-hint
              />
            </v-col>

            <!-- Duration + overnight preview -->
            <v-col v-if="form.start_time && form.end_time" cols="12">
              <v-alert
                :type="isOvernight ? 'warning' : 'info'"
                variant="tonal"
                density="compact"
                rounded="lg"
                :icon="false"
              >
                <div class="d-flex align-center gap-2">
                  <v-icon
                    :icon="
                      isOvernight ? 'mdi-weather-night' : 'mdi-timer-outline'
                    "
                    size="16"
                  />
                  <span>
                    {{ $t('shifts.form.preview.duration') }}
                    <strong>{{ duration }}</strong>
                    <span v-if="isOvernight" class="ml-2 text-warning">
                      {{ $t('shifts.form.preview.overnight') }}
                    </span>
                  </span>
                </div>
              </v-alert>
            </v-col>

            <!-- Break Minutes -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.break_minutes"
                type="number"
                :label="$t('shifts.form.fields.breakMinutes.label')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                min="0"
                max="480"
                :rules="[r.breakMinutes]"
                prepend-inner-icon="mdi-coffee-outline"
                :hint="$t('shifts.form.fields.breakMinutes.hint')"
                persistent-hint
              />
            </v-col>

            <!-- Active -->
            <v-col cols="12" sm="6" class="d-flex align-center">
              <v-switch
                v-model="form.is_active"
                :label="$t('shifts.form.fields.active')"
                color="success"
                inset
                hide-details
              />
            </v-col>
          </v-row>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useI18n } from 'vue-i18n'
  import { useTenantStore } from '@/stores/tenantStore'
  import { usePermission } from '@/composables/usePermission'
  import AppDialog from '@/components/common/AppDialog.vue'
  const { isSuperAdmin } = usePermission()
  const { t } = useI18n()

  const props = defineProps({
    modelValue: Boolean,
    item: Object,
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const tenantStore = useTenantStore()
  const { tenants } = storeToRefs(tenantStore)

  const formRef = ref(null)
  const startTimeRef = ref(null)
  const endTimeRef = ref(null)

  const shiftTypes = computed(() => [
    { value: 'morning', label: t('shifts.form.shiftTypes.morning') },
    { value: 'afternoon', label: t('shifts.form.shiftTypes.afternoon') },
    { value: 'evening', label: t('shifts.form.shiftTypes.evening') },
    { value: 'full_day', label: t('shifts.form.shiftTypes.full_day') },
    { value: 'split', label: t('shifts.form.shiftTypes.split') }
  ])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.item?.id)

  const defaultForm = () => ({
    tenant_id: null,
    name: '',
    shift_type: null,
    start_time: null,
    end_time: null,
    break_minutes: 0,
    is_active: true
  })

  const form = reactive(defaultForm())

  watch(
    () => props.item,
    val => {
      Object.keys(form).forEach(k => delete form[k])
      Object.assign(form, val ? { ...val } : defaultForm())
    },
    { immediate: true }
  )

  // ── Overnight + duration preview ──────────────────────────────────────────────
  const isOvernight = computed(() => {
    if (!form.start_time || !form.end_time) return false
    return form.end_time < form.start_time
  })

  const duration = computed(() => {
    if (!form.start_time || !form.end_time) return null
    const [sh, sm] = form.start_time.split(':').map(Number)
    let [eh, em] = form.end_time.split(':').map(Number)
    let totalMins = eh * 60 + em - (sh * 60 + sm)
    if (totalMins <= 0) totalMins += 24 * 60
    totalMins -= form.break_minutes ?? 0
    const h = Math.floor(totalMins / 60)
    const m = totalMins % 60
    return `${h}h ${String(m).padStart(2, '0')}m`
  })

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || t('shifts.form.rules.required'),
    breakMinutes: v => {
      if (v === null || v === '' || v === undefined) return true
      if (v < 0) return t('shifts.form.rules.breakNegative')
      if (v > 480) return t('shifts.form.rules.breakMax')
      return true
    }
  }

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('save', { ...form })
  }

  const close = () => {
    formRef.value?.reset()
    Object.assign(form, defaultForm())
    model.value = false
  }

  onMounted(() => {
    if (isSuperAdmin()) {
      tenantStore.fetchTenants()
    }
  })
</script>
