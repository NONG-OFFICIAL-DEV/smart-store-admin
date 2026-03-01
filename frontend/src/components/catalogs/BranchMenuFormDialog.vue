<template>
  <v-dialog
    :model-value="modelValue"
    max-width="560"
    persistent
    scrollable
    @update:modelValue="$emit('update:modelValue', $event)"
  >
    <v-card rounded="lg" elevation="0" border>
      <!-- ── Header ──────────────────────────────────────────────────────────── -->
      <v-card-title>
        <div class="d-flex align-center justify-space-between">
          <div>
            {{ isEdit ? 'Update Assignment' : 'Assign Menu to Branch' }}
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <!-- ── Form ────────────────────────────────────────────────────────────── -->
      <v-card-text class="pa-6">
        <v-form ref="formRef" @submit.prevent="handleSubmit">
          <v-row dense>
            <!-- Branch -->
            <v-col cols="12">
              <v-select
                v-model="form.branch_id"
                :items="branches"
                item-title="name"
                item-value="id"
                label="Branch"
                variant="outlined"
                density="comfortable"
                :rules="[rules.required]"
                :disabled="isEdit"
                prepend-inner-icon="mdi-store"
                hint="Which branch location?"
                persistent-hint
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #prepend>
                      <v-avatar
                        color="primary"
                        size="32"
                        rounded="md"
                        class="mr-2"
                      >
                        <v-icon icon="mdi-store" size="16" />
                      </v-avatar>
                    </template>
                    <template #subtitle>
                      {{ item.raw?.city || '' }}
                    </template>
                  </v-list-item>
                </template>
              </v-select>
            </v-col>

            <!-- Menu -->
            <v-col cols="12">
              <v-select
                v-model="form.menu_id"
                :items="menus"
                item-title="name"
                item-value="id"
                label="Menu"
                variant="outlined"
                density="comfortable"
                :rules="[rules.required]"
                :disabled="isEdit"
                prepend-inner-icon="mdi-book-open-outline"
                hint="Which menu to assign?"
                persistent-hint
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #prepend>
                      <v-avatar
                        color="success"
                        size="32"
                        rounded="md"
                        class="mr-2"
                      >
                        <v-icon icon="mdi-book-open-outline" size="16" />
                      </v-avatar>
                    </template>
                    <template #append>
                      <v-chip
                        v-if="item.raw?.is_default"
                        size="x-small"
                        color="primary"
                        variant="tonal"
                      >
                        Default
                      </v-chip>
                    </template>
                  </v-list-item>
                </template>
              </v-select>
            </v-col>

            <!-- Divider -->
            <v-col cols="12">
              <v-divider class="my-1" />
              <p class="text-caption text-medium-emphasis mt-2 mb-1">
                <v-icon icon="mdi-clock-outline" size="14" class="mr-1" />
                Time Window — leave empty for all-day availability
              </p>
            </v-col>

            <!-- Available From -->
            <v-col cols="12" sm="6">
              <v-text-field
                ref="availableFromRef"
                v-model="form.available_from"
                label="Available From"
                variant="outlined"
                density="comfortable"
                type="time"
                prepend-inner-icon="mdi-clock-start"
                clearable
                hint="e.g. 08:00"
                persistent-hint
                :rules="[rules.timeBefore]"
              />
            </v-col>

            <!-- Available Until -->
            <v-col cols="12" sm="6">
              <v-text-field
                ref="availableUntilRef"
                v-model="form.available_until"
                label="Available Until"
                variant="outlined"
                density="comfortable"
                type="time"
                prepend-inner-icon="mdi-clock-end"
                clearable
                hint="e.g. 22:00"
                persistent-hint
                :rules="[rules.timeAfter]"
              />
            </v-col>

            <!-- Days of Week -->
            <v-col cols="12">
              <div class="text-caption text-medium-emphasis mb-2">
                <v-icon icon="mdi-calendar-week" size="14" class="mr-1" />
                Available Days — leave empty for every day
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <v-btn
                  v-for="(label, index) in DAY_LABELS"
                  :key="index"
                  :color="
                    form.days_of_week.includes(index) ? 'primary' : 'default'
                  "
                  :variant="
                    form.days_of_week.includes(index) ? 'flat' : 'tonal'
                  "
                  size="small"
                  rounded="lg"
                  min-width="48"
                  @click="toggleDay(index)"
                >
                  {{ label }}
                </v-btn>
              </div>
              <p class="text-caption text-medium-emphasis mt-2">
                Selected: {{ selectedDaysLabel }}
              </p>
            </v-col>

            <!-- Sort Order -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.sort_order"
                label="Sort Order"
                variant="outlined"
                density="comfortable"
                type="number"
                min="0"
                prepend-inner-icon="mdi-sort"
                hint="Display order (0 = first)"
                persistent-hint
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ── Actions ─────────────────────────────────────────────────────────── -->
      <v-card-actions>
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" @click="close">Cancel</v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-link-variant'"
          @click="handleSubmit"
        >
          {{ isEdit ? 'Save Changes' : 'Assign Menu' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'

  // ── Props & Emits ─────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editItem: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    menus: { type: Array, default: () => [] }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  // ── Store ─────────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  // ── Store ─────────────────────────────────────────────────────────────────────
  const availableFromRef = ref(null) // ← add
  const availableUntilRef = ref(null) // ← add

  // ── Constants ─────────────────────────────────────────────────────────────────
  const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

  // ── Form state ────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    id: null,
    branch_id: null,
    menu_id: null,
    available_from: null,
    available_until: null,
    days_of_week: [],
    sort_order: 0
  })

  const form = ref(defaultForm())

  // ── Computed ──────────────────────────────────────────────────────────────────
  const isEdit = computed(() => !!props.editItem)

  const selectedDaysLabel = computed(() => {
    if (!form.value.days_of_week.length) return 'Every day'
    return form.value.days_of_week
      .sort((a, b) => a - b)
      .map(d => DAY_LABELS[d])
      .join(', ')
  })

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || 'This field is required',
    timeBefore: v => {
      if (!v || !form.value.available_until) return true
      return (
        v < form.value.available_until ||
        'Must be before "Available Until" time'
      )
    },
    timeAfter: v => {
      if (!v || !form.value.available_from) return true
      return (
        v > form.value.available_from || 'Must be after "Available From" time'
      )
    }
  }

  // ── Watch editItem — populate form when editing ───────────────────────────────
  watch(
    () => props.editItem,
    item => {
      if (item) {
        form.value = {
          id: item.id,
          branch_id: item.branch_id,
          menu_id: item.menu_id,
          available_from: stripSeconds(item.available_from), // ← strip :ss
          available_until: stripSeconds(item.available_until), // ← strip :ss
          days_of_week: item.days_of_week ?? [],
          sort_order: item.sort_order ?? 0
        }
      } else {
        form.value = defaultForm()
      }
    },
    { immediate: true }
  )

  // When user changes From → re-validate Until field automatically
  watch(
    () => form.value.available_from,
    () => {
      availableUntilRef.value?.validate()
    }
  )

  // When user changes Until → re-validate From field automatically
  watch(
    () => form.value.available_until,
    () => {
      availableFromRef.value?.validate()
    }
  )
  const stripSeconds = t => (t ? t.slice(0, 5) : null)

  // ── Toggle day selection ──────────────────────────────────────────────────────
  const toggleDay = dayIndex => {
    const idx = form.value.days_of_week.indexOf(dayIndex)
    if (idx === -1) {
      form.value.days_of_week.push(dayIndex)
    } else {
      form.value.days_of_week.splice(idx, 1)
    }
  }

  // ── Submit ────────────────────────────────────────────────────────────────────
  const handleSubmit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    const payload = {
      id: form.value.id ?? null,
      branch_id: form.value.branch_id,
      menu_id: form.value.menu_id,
      available_from: stripSeconds(form.value.available_from), // ← strip :ss
      available_until: stripSeconds(form.value.available_until), // ← strip :ss
      days_of_week: form.value.days_of_week.length
        ? form.value.days_of_week
        : null,
      sort_order: form.value.sort_order ?? 0
    }

    emit('saved', payload)

    // Close dialog if successful
    close()
  }

  // ── Close ─────────────────────────────────────────────────────────────────────
  const close = () => {
    formRef.value?.reset()
    form.value = defaultForm()
    emit('update:modelValue', false)
  }
</script>
