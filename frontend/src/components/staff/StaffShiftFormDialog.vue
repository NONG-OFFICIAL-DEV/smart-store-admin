<template>
  <AppDialog
    v-model="model"
    :max-width="620"
    :title="isEdit ? $t('shift_assignments.titleEdit') : $t('shift_assignments.titleCreate')"
    :loading="loading"
  >
        <v-form ref="formRef">
          <v-row dense>
            <!-- Shift -->
            <v-col cols="6">
              <v-select
                v-model="form.shift_id"
                :items="shiftList.filter(s => s.is_active)"
                item-value="id"
                item-title="name"
                :label="$t('shift_assignments.fields.shift.label')"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                :disabled="isEdit"
                prepend-inner-icon="mdi-clock-outline"
                :hint="$t('shift_assignments.fields.shift.hint')"
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

            <!-- Assign to (staff member) -->
            <v-col cols="6">
              <v-select
                v-model="form.staff_id"
                :items="staffList"
                item-value="id"
                item-title="full_name"
                :label="$t('shift_assignments.fields.staff.label')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required]"
                :disabled="isEdit"
                prepend-inner-icon="mdi-account-outline"
                :hint="$t('shift_assignments.fields.staff.hint')"
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

            <!-- Branch — only one tenant branch exists, nothing to pick. -->
            <v-col v-if="hasSingleBranch" cols="12">
              <v-text-field
                :model-value="branches[0]?.name"
                :label="$t('shift_assignments.fields.branch.label')"
                variant="outlined"
                rounded="lg"
                readonly
                prepend-inner-icon="mdi-store-outline"
              />
            </v-col>
            <v-col v-else cols="12">
              <v-select
                v-model="form.branch_id"
                :items="branches"
                item-value="id"
                item-title="name"
                :label="$t('shift_assignments.fields.branch.label')"
                variant="outlined"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-store-outline"
                :hint="$t('shift_assignments.fields.branch.hint')"
                persistent-hint
              />
            </v-col>

            <!-- Shift Date -->
            <v-col cols="12">
              <AppDatePicker
                v-model="form.shift_date"
                :label="$t('shift_assignments.fields.shiftDate.label')"
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
                    {{
                      $t('shift_assignments.preview', {
                        name: selectedShift.name,
                        date: formatDate(form.shift_date),
                        start: selectedShift.start_time,
                        end: selectedShift.end_time
                      })
                    }}
                    <span
                      v-if="selectedShift.break_minutes"
                      class="ml-1 text-grey"
                    >
                      {{
                        $t('shift_assignments.previewBreak', {
                          minutes: selectedShift.break_minutes
                        })
                      }}
                    </span>
                  </span>
                </div>
              </v-alert>
            </v-col>

            <!-- Actual Clock In/Out (edit mode only) -->
            <template v-if="isEdit">
              <v-col cols="12">
                <v-divider class="my-1" />
                <p class="text-caption text-medium-emphasis mt-2 mb-1">
                  <v-icon
                    icon="mdi-clock-check-outline"
                    size="14"
                    class="mr-1"
                  />
                  {{ $t('shift_assignments.clockInOut') }}
                </p>
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  ref="actualStartRef"
                  v-model="form.actual_start"
                  type="datetime-local"
                  :label="$t('shift_assignments.fields.actualStart.label')"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-login"
                  clearable
                  :hint="$t('shift_assignments.fields.actualStart.hint')"
                  persistent-hint
                />
              </v-col>

              <v-col cols="12" sm="6">
                <v-text-field
                  ref="actualEndRef"
                  v-model="form.actual_end"
                  type="datetime-local"
                  :label="$t('shift_assignments.fields.actualEnd.label')"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-logout"
                  clearable
                  :rules="[r.afterActualStart]"
                  :hint="$t('shift_assignments.fields.actualEnd.hint')"
                  persistent-hint
                />
              </v-col>
            </template>

            <!-- Notes -->
            <v-col cols="12">
              <v-textarea
                v-model="form.notes"
                :label="$t('shift_assignments.fields.notes.label')"
                variant="outlined"
                rounded="lg"
                rows="2"
                prepend-inner-icon="mdi-note-text-outline"
                :hint="$t('shift_assignments.fields.notes.hint')"
                persistent-hint
              />
            </v-col>
          </v-row>
        </v-form>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        :color="isEdit ? 'primary' : 'success'"
        variant="flat"
        rounded="lg"
        :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-account-plus'"
        :loading="loading"
        @click="submit"
      >
        {{ isEdit ? $t('btn.save') : $t('btn.assign') }}
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
  import { useAvatar } from '@/composables/useAvatar'
  import { AppDatePicker, AppDialog } from '@nong-official-dev/core'

  const { formatWeekdayDate: formatDate } = useDate()
  const { getInitials, getAvatarColor } = useAvatar()

  const { t } = useI18n()

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
  // A single-branch tenant has nothing to pick — auto-assign it instead of
  // showing a one-item dropdown (same pattern as BranchMenuFormDialog).
  const hasSingleBranch = computed(() => branches.value.length === 1)

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

  // Re-applied on every open (not just once) — props.item stays null across
  // consecutive "create" opens, so a watcher on it alone won't refire.
  watch(
    () => props.modelValue,
    open => {
      if (open && !isEdit.value && hasSingleBranch.value) {
        form.branch_id = branches.value[0].id
      } else if (!open) {
        formRef.value?.reset()
        Object.assign(form, defaultForm())
      }
    }
  )

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || t('form.required'),
    afterActualStart: v => {
      if (!v || !form.actual_start) return true
      return (
        new Date(v) > new Date(form.actual_start) ||
        t('shift_assignments.rules.afterActualStart')
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
  const initials = n => getInitials(n)
  const avatarColor = n =>
    getAvatarColor(n, {
      palette: ['#3b5bdb', '#2f9e44', '#e67700', '#c92a2a'],
      fallback: '#808080'
    })
  onMounted(async () => {
    await branchStore.fetchBranches({ perPage: 100 })
  })
</script>
