<template>
  <v-dialog v-model="model" max-width="560" persistent scrollable>
    <v-card rounded="xl">
      <v-card-title class="d-flex align-center justify-space-between">
        <div class="text-h6 font-weight-bold">
          {{ isEdit ? 'Edit Reservation' : 'New Reservation' }}
        </div>

        <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-6">
        <v-form ref="formRef">
          <v-row dense>
            <!-- Branch — required FK -->
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
              />
            </v-col>

            <v-col cols="12">
              <p class="text-overline text-primary mb-1">Guest Info</p>
            </v-col>

            <v-col cols="12" sm="7">
              <v-text-field
                v-model="form.customer_name"
                label="Guest Name"
                placeholder="Full name"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-account-outline"
                maxlength="150"
              />
            </v-col>

            <v-col cols="12" sm="5">
              <v-text-field
                v-model="form.customer_phone"
                label="Phone"
                placeholder="+855 ..."
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-phone-outline"
                maxlength="30"
              />
            </v-col>

            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.party_size"
                type="number"
                label="Party Size"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required, r.positive]"
                prepend-inner-icon="mdi-account-group-outline"
                min="1"
              />
            </v-col>

            <v-col cols="12" sm="8">
              <v-select
                v-model="form.table_id"
                :items="availableTables"
                item-value="id"
                label="Table"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-table-chair"
                clearable
                hint="Optional — assign later if needed"
                persistent-hint
              >
                <template #item="{ props, item }">
                  <v-list-item
                    v-bind="props"
                    :title="`Table ${item.raw.table_number}`"
                  >
                    <template #subtitle>
                      <span class="text-caption">
                        {{ item.raw.capacity }} seats ·
                        <span :style="{ color: statusBg(item.raw.status) }">
                          {{ item.raw.status }}
                        </span>
                      </span>
                    </template>
                  </v-list-item>
                </template>
                <template #selection="{ item }">
                  Table {{ item.raw?.table_number }} ({{ item.raw?.capacity }}
                  seats)
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
                Party of
                <strong>{{ form.party_size }}</strong>
                exceeds table capacity of
                <strong>{{ selectedTable.capacity }}</strong>
              </v-alert>
            </v-col>

            <v-col cols="12">
              <v-divider class="mb-2 mt-1" />
              <p class="text-overline text-primary mb-1">Date & Time</p>
            </v-col>

            <v-col cols="12" sm="8">
              <v-text-field
                v-model="form.reserved_at"
                type="datetime-local"
                label="Reservation Date & Time"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-calendar-clock"
              />
            </v-col>

            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="form.duration_minutes"
                type="number"
                label="Duration (mins)"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-timer-outline"
                min="0"
                step="15"
                hint="e.g. 90"
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
                Table released at:
                <strong>{{ endTime }}</strong>
              </v-alert>
            </v-col>

            <v-col cols="12">
              <v-divider class="mb-2 mt-1" />
              <p class="text-overline text-primary mb-1">Status & Notes</p>
            </v-col>

            <v-col cols="12" sm="6">
              <v-select
                v-model="form.status"
                :items="statusOptions"
                item-title="label"
                item-value="value"
                label="Status"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-information-outline"
              />
            </v-col>

            <v-col cols="12" sm="6" class="d-flex align-items-center">
              <!-- Just spacing -->
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="form.notes"
                label="Notes"
                placeholder="Special requests, allergies, occasion..."
                variant="outlined"
                density="comfortable"
                rounded="lg"
                rows="2"
                prepend-inner-icon="mdi-note-text-outline"
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-6 pt-4 gap-3">
        <v-btn
          variant="tonal"
          rounded="lg"
          size="large"
          :disabled="loading"
          @click="close"
        >
          Cancel
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          size="large"
          :loading="loading"
          :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-calendar-plus'"
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Book Table' }}
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
    tables: { type: Array, default: () => [] },
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const branchStore = useBranchStore()
  const { branches } = storeToRefs(branchStore)

  const formRef = ref(null)
  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.item?.id)

  const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'seated', label: 'Seated' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'no_show', label: 'No Show' }
  ]

  const defaultForm = () => ({
    branch_id: null,
    customer_name: '',
    customer_phone: '',
    party_size: 2,
    table_id: null,
    reserved_at: null,
    duration_minutes: 90,
    status: 'pending',
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

  // Tables filtered by selected branch
  const availableTables = computed(() =>
    props.tables.filter(
      t => t.is_active && (!form.branch_id || t.branch_id === form.branch_id)
    )
  )

  // Selected table details
  const selectedTable = computed(
    () => props.tables.find(t => t.id === form.table_id) || null
  )

  // End time calculation
  const endTime = computed(() => {
    if (!form.reserved_at || !form.duration_minutes) return null
    const end = new Date(form.reserved_at)
    end.setMinutes(end.getMinutes() + Number(form.duration_minutes))
    return end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  })

  const statusBg = s =>
    ({
      available: '#4caf50',
      occupied: '#f44336',
      reserved: '#ff9800',
      cleaning: '#2196f3',
      inactive: '#9e9e9e'
    })[s] || '#9e9e9e'

  const r = {
    required: v => !!v || v === 0 || 'Required',
    positive: v => !v || Number(v) > 0 || 'Must be at least 1'
  }

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('save', { ...form })
  }

  const close = () => {
    formRef.value?.reset()
    Object.assign(form, defaultForm())
    emit('update:modelValue', false)
  }

  onMounted(() => branchStore.fetchBranches?.())
</script>
