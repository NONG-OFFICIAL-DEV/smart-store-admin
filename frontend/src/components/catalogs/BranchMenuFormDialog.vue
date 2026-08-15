<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="560"
    :title="isEdit ? $t('menus.assign.titleEdit') : $t('menus.assign.titleCreate')"
    :subtitle="
      isEdit
        ? $t('menus.assign.subtitleEdit')
        : $t('menus.assign.subtitleCreate')
    "
    :icon="isEdit ? 'mdi-book-edit-outline' : 'mdi-link-variant'"
    :color="isEdit ? 'primary' : 'success'"
    :submit-text="isEdit ? $t('btn.save_changes') : $t('menus.assign.assign_menu')"
    :submit-icon="isEdit ? 'mdi-content-save' : 'mdi-link-variant'"
    @update:model-value="$emit('update:modelValue', $event)"
    @close="close"
    @submit="handleSubmit"
  >
    <!-- ── Form ────────────────────────────────────────────────────────────── -->
    <v-form ref="formRef" @submit.prevent="handleSubmit">
          <v-row dense>
            <!-- Branch -->
            <v-col cols="12">
              <v-select
                v-model="form.branch_id"
                :items="branches"
                item-title="name"
                item-value="id"
                :label="$t('form.branch')"
                variant="outlined"
                density="comfortable"
                :rules="[rules.required]"
                :disabled="isEdit"
                prepend-inner-icon="mdi-store"
                :hint="$t('menus.assign.branch_hint')"
                persistent-hint
                multiple
                chips
                closable-chips
              >
                <!-- <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template #append>
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
                </template> -->
              </v-select>
            </v-col>

            <!-- Menu -->
            <v-col cols="12">
              <v-select
                v-model="form.menu_id"
                :items="menus"
                item-title="name"
                item-value="id"
                :label="$t('menus.assign.menu')"
                variant="outlined"
                density="comfortable"
                :rules="[rules.required]"
                :disabled="isEdit"
                prepend-inner-icon="mdi-book-open-outline"
                :hint="$t('menus.assign.menu_hint')"
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
                        {{ $t('products.variant.default') }}
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
                {{ $t('menus.assign.time_window_hint') }}
              </p>
            </v-col>

            <!-- Available From -->
            <v-col cols="12" sm="6">
              <v-text-field
                ref="availableFromRef"
                v-model="form.available_from"
                :label="$t('menus.assign.available_from')"
                variant="outlined"
                density="comfortable"
                type="time"
                prepend-inner-icon="mdi-clock-start"
                clearable
                :hint="$t('menus.assign.available_from_hint')"
                persistent-hint
                :rules="[rules.timeBefore]"
              />
            </v-col>

            <!-- Available Until -->
            <v-col cols="12" sm="6">
              <v-text-field
                ref="availableUntilRef"
                v-model="form.available_until"
                :label="$t('menus.assign.available_until')"
                variant="outlined"
                density="comfortable"
                type="time"
                prepend-inner-icon="mdi-clock-end"
                clearable
                :hint="$t('menus.assign.available_until_hint')"
                persistent-hint
                :rules="[rules.timeAfter]"
              />
            </v-col>

            <!-- Days of Week -->
            <v-col cols="12">
              <div class="text-caption text-medium-emphasis mb-2">
                <v-icon icon="mdi-calendar-week" size="14" class="mr-1" />
                {{ $t('menus.assign.available_days_hint') }}
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
                  {{ $t(label) }}
                </v-btn>
              </div>
              <p class="text-caption text-medium-emphasis mt-2">
                {{ $t('menus.assign.selected_days', { days: selectedDaysLabel }) }}
              </p>
            </v-col>

            <!-- Sort Order -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.sort_order"
                :label="$t('categories.form.sort_order')"
                variant="outlined"
                density="comfortable"
                type="number"
                min="0"
                prepend-inner-icon="mdi-sort"
                :hint="$t('menus.assign.sort_order_hint')"
                persistent-hint
              />
            </v-col>
          </v-row>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()

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
  const DAY_LABELS = [
    'menus.assign.day_sun',
    'menus.assign.day_mon',
    'menus.assign.day_tue',
    'menus.assign.day_wed',
    'menus.assign.day_thu',
    'menus.assign.day_fri',
    'menus.assign.day_sat'
  ]

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
    if (!form.value.days_of_week.length) return t('menus.assign.every_day')
    return form.value.days_of_week
      .sort((a, b) => a - b)
      .map(d => t(DAY_LABELS[d]))
      .join(', ')
  })

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || t('validation.required'),
    timeBefore: v => {
      if (!v || !form.value.available_until) return true
      return (
        v < form.value.available_until ||
        t('menus.validation.time_before')
      )
    },
    timeAfter: v => {
      if (!v || !form.value.available_from) return true
      return (
        v > form.value.available_from || t('menus.validation.time_after')
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
  const stripSeconds = timeStr => (timeStr ? timeStr.slice(0, 5) : null)

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
