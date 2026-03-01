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
              <v-icon
                :icon="isEdit ? 'mdi-pencil' : 'mdi-account-plus'"
                size="20"
              />
            </v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ isEdit ? 'Edit Assignment' : 'Assign Staff to Shift' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  isEdit
                    ? 'Update assignment details'
                    : 'Assign a staff member to a shift on a date'
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
            <!-- Shift — which shift definition -->
            <v-col cols="12">
              <v-select
                v-model="form.shift_id"
                :items="shiftList.filter(s => s.is_active)"
                item-value="id"
                item-title="name"
                label="Shift"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                :disabled="isEdit"
                prepend-inner-icon="mdi-clock-outline"
                hint="Which shift?"
                persistent-hint
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #append>
                      <span class="text-caption text-grey font-mono ml-2">
                        {{ item.raw?.start_time }} → {{ item.raw?.end_time }}
                      </span>
                    </template>
                  </v-list-item>
                </template>
              </v-select>
            </v-col>

            <!-- Staff member -->
            <v-col cols="12">
              <v-select
                v-model="form.staff_id"
                :items="staffList"
                item-value="id"
                item-title="full_name"
                label="Staff Member"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                :disabled="isEdit"
                prepend-inner-icon="mdi-account-outline"
                hint="Who works this shift?"
                persistent-hint
              >
                <template #item="{ props, item }">
                  <v-list-item
                    v-bind="props"
                    :subtitle="item.raw?.role_name || ''"
                  >
                    <template #prepend>
                      <v-avatar
                        :color="avatarColor(item.raw?.full_name)"
                        size="32"
                        rounded="md"
                        class="mr-2"
                      >
                        <span class="text-white text-caption">
                          {{ initials(item.raw?.full_name) }}
                        </span>
                      </v-avatar>
                    </template>
                  </v-list-item>
                </template>
              </v-select>
            </v-col>

            <!-- Branch -->
            <v-col cols="12">
              <v-select
                v-model="form.branch_id"
                :items="branches.data"
                item-value="id"
                item-title="name"
                label="Branch"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-store-outline"
                hint="Which branch?"
                persistent-hint
              />
            </v-col>

            <!-- Shift Date -->
            <v-col cols="12">
              <v-text-field
                v-model="form.shift_date"
                type="date"
                label="Shift Date"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-calendar"
                hint="Which day is this shift?"
                persistent-hint
              />
            </v-col>

            <!-- Shift preview -->
            <v-col v-if="selectedShift && form.shift_date" cols="12">
              <v-alert
                type="info"
                variant="tonal"
                density="compact"
                rounded="lg"
                :icon="false"
              >
                <div class="d-flex align-center gap-2">
                  <v-icon icon="mdi-information-outline" size="16" />
                  <span>
                    <strong>{{ selectedShift.name }}</strong>
                    on
                    <strong>{{ formatDate(form.shift_date) }}</strong>
                    : {{ selectedShift.start_time }} →
                    {{ selectedShift.end_time }}
                    <span
                      v-if="selectedShift.break_minutes"
                      class="ml-1 text-grey"
                    >
                      ({{ selectedShift.break_minutes }}m break)
                    </span>
                  </span>
                </div>
              </v-alert>
            </v-col>

            <!-- Actual time divider (edit mode only) -->
            <template v-if="isEdit">
              <v-col cols="12">
                <v-divider class="my-1" />
                <p class="text-caption text-medium-emphasis mt-2 mb-1">
                  <v-icon
                    icon="mdi-clock-check-outline"
                    size="14"
                    class="mr-1"
                  />
                  Actual Clock In/Out
                </p>
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  ref="actualStartRef"
                  v-model="form.actual_start"
                  type="datetime-local"
                  label="Actual Start"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-login"
                  clearable
                  hint="When staff clocked in"
                  persistent-hint
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  ref="actualEndRef"
                  v-model="form.actual_end"
                  type="datetime-local"
                  label="Actual End"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-logout"
                  clearable
                  :rules="[r.afterActualStart]"
                  hint="When staff clocked out"
                  persistent-hint
                />
              </v-col>
            </template>

            <!-- Notes -->
            <v-col cols="12">
              <v-textarea
                v-model="form.notes"
                label="Notes"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                rows="2"
                prepend-inner-icon="mdi-note-text-outline"
                hint="Optional"
                persistent-hint
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
          :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-account-plus'"
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Assign' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useBranchStore } from '@/stores/branchStore'

  const props = defineProps({
    modelValue: Boolean,
    item: Object,
    loading: Boolean,
    shiftList: { type: Array, default: () => [] },
    staffList: { type: Array, default: () => [] }
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const branchStore = useBranchStore()
  const { branches } = storeToRefs(branchStore)

  const formRef = ref(null)
  const actualEndRef = ref(null)

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.item?.id)

  // Selected shift preview
  const selectedShift = computed(
    () => props.shiftList.find(s => s.id === form.shift_id) || null
  )

  const defaultForm = () => ({
    shift_id: null,
    staff_id: null,
    branch_id: null,
    shift_date: null,
    actual_start: null,
    actual_end: null,
    notes: ''
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

  // Re-validate actual_end when actual_start changes
  watch(
    () => form.actual_start,
    () => actualEndRef.value?.validate()
  )

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || 'Required',
    afterActualStart: v => {
      if (!v || !form.actual_start) return true
      return (
        new Date(v) > new Date(form.actual_start) ||
        'Must be after actual start'
      )
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

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const initials = n =>
    n
      ? n
          .split(' ')
          .map(x => x[0])
          .join('')
          .toUpperCase()
          .slice(0, 2)
      : '?'
  const avatarColor = n =>
    n ? ['#3b5bdb', '#2f9e44', '#e67700', '#c92a2a'][n.length % 4] : '#808080'
  const formatDate = d =>
    d
      ? new Date(d).toLocaleDateString([], {
          weekday: 'short',
          month: 'short',
          day: 'numeric'
        })
      : '—'

  onMounted(async () => {
    await branchStore.fetchBranches()
  })
</script>
