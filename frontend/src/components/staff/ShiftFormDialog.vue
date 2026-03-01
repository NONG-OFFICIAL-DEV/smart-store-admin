<template>
  <v-dialog v-model="model" max-width="520" persistent scrollable>
    <v-card rounded="xl">
      <!-- ── Header ──────────────────────────────────────────────────────────── -->
      <v-card-title class="pa-6 pb-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar
              :color="isEdit ? 'primary' : 'success'"
              size="40"
              rounded="lg"
            >
              <v-icon :icon="isEdit ? 'mdi-pencil' : 'mdi-plus'" size="20" />
            </v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ isEdit ? 'Edit Shift' : 'New Shift' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  isEdit ? 'Update shift definition' : 'Define a reusable shift'
                }}
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-6">
        <v-form ref="formRef">
          <v-row dense>
            <!-- Tenant — shift belongs to tenant, shared across all branches -->
            <v-col cols="12">
              <v-select
                v-model="form.tenant_id"
                :items="tenants"
                item-value="id"
                item-title="name"
                label="Tenant (Business)"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                :disabled="isEdit"
                prepend-inner-icon="mdi-domain"
                hint="This shift will be available to all branches under this tenant"
                persistent-hint
              />
            </v-col>

            <!-- Name -->
            <v-col cols="12" sm="7">
              <v-text-field
                v-model="form.name"
                label="Shift Name"
                placeholder="e.g. Morning, Ca Sang, Night"
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
                label="Type"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                clearable
                prepend-inner-icon="mdi-shape-outline"
              />
            </v-col>

            <!-- Time divider -->
            <v-col cols="12">
              <v-divider class="my-1" />
              <p class="text-caption text-medium-emphasis mt-2 mb-1">
                <v-icon icon="mdi-clock-outline" size="14" class="mr-1" />
                Time Window
              </p>
            </v-col>

            <!-- Start Time -->
            <v-col cols="12" sm="6">
              <v-text-field
                ref="startTimeRef"
                v-model="form.start_time"
                type="time"
                label="Start Time"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-clock-start"
                hint="e.g. 09:00"
                persistent-hint
              />
            </v-col>

            <!-- End Time -->
            <v-col cols="12" sm="6">
              <v-text-field
                ref="endTimeRef"
                v-model="form.end_time"
                type="time"
                label="End Time"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-clock-end"
                hint="e.g. 17:00"
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
                    Duration:
                    <strong>{{ duration }}</strong>
                    <span v-if="isOvernight" class="ml-2 text-warning">
                      — Overnight shift (ends next day)
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
                label="Break (minutes)"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                min="0"
                max="480"
                :rules="[r.breakMinutes]"
                prepend-inner-icon="mdi-coffee-outline"
                hint="0 = no break"
                persistent-hint
              />
            </v-col>

            <!-- Active -->
            <v-col cols="12" sm="6" class="d-flex align-center">
              <v-switch
                v-model="form.is_active"
                label="Active"
                color="success"
                inset
                hide-details
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-6 pt-4 gap-3">
        <v-btn
          block
          variant="tonal"
          rounded="lg"
          size="large"
          :disabled="loading"
          @click="close"
        >
          Cancel
        </v-btn>
        <v-btn
          block
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          size="large"
          :loading="loading"
          :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-plus'"
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Create Shift' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useTenantStore } from '@/stores/tenantStore'

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

  const shiftTypes = [
    { value: 'morning', label: 'Morning' },
    { value: 'afternoon', label: 'Afternoon' },
    { value: 'evening', label: 'Evening' },
    { value: 'full_day', label: 'Full Day' },
    { value: 'split', label: 'Split' }
  ]

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
    required: v => !!v || 'Required',
    breakMinutes: v => {
      if (v === null || v === '' || v === undefined) return true
      if (v < 0) return 'Cannot be negative'
      if (v > 480) return 'Max 480 minutes'
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

  onMounted(() => tenantStore.fetchTenants())
</script>
