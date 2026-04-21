<template>
  <div class="period-tabs mb-5">
    <div class="branch-filter-wrap mb-4">
      <div class="d-flex align-center gap-2 mb-2">
        <v-icon icon="mdi-store-outline" size="16" color="primary" />
        <span
          class="text-caption font-weight-bold text-uppercase"
          style="letter-spacing: 1px"
        >
          {{t('menu.branches')}}
        </span>
        <v-spacer />
        <span class="text-caption text-medium-emphasis">
          {{ selectedCount }} / {{ totalCount }} {{t('common.selected')}}
        </span>
        <v-btn
          v-if="!allSelected"
          size="x-small"
          variant="tonal"
          color="primary"
          rounded="lg"
          @click="selectAll"
        >
          {{t('btn.select_all')}}
        </v-btn>
        <v-btn
          v-else
          size="x-small"
          variant="tonal"
          color="grey"
          rounded="lg"
          @click="clearAll"
        >
         {{t('btn.clear')}}
        </v-btn>
      </div>

      <div class="branch-chips">
        <v-chip
          v-for="branch in branches"
          :key="branch.id"
          :color="isSelected(branch.id) ? 'primary' : undefined"
          :variant="isSelected(branch.id) ? 'flat' : 'outlined'"
          rounded="lg"
          size="small"
          class="branch-chip"
          :class="{ 'chip-selected': isSelected(branch.id) }"
          @click="toggleBranch(branch.id)"
        >
          <v-icon
            start
            size="12"
            :icon="isSelected(branch.id) ? 'mdi-check' : 'mdi-store-outline'"
          />
          {{ branch.name }}
        </v-chip>
      </div>
    </div>

    <div class="d-flex align-center flex-wrap gap-3">
      <v-btn-toggle
        :model-value="period"
        mandatory
        rounded="lg"
        density="comfortable"
        color="primary"
        variant="outlined"
        @update:model-value="onPeriodClick"
      >
        <v-btn value="today" size="small">{{t('common.today')}}</v-btn>
        <v-btn value="yesterday" size="small">{{t('common.yesterday')}}</v-btn>
        <v-btn value="week" size="small">{{t('common.this_week')}}</v-btn>
        <v-btn value="month" size="small">{{t('common.this_month')}}</v-btn>
        <v-btn value="last_month" size="small">{{t('common.last_month')}}</v-btn>
        <v-btn value="custom" size="small">
          <v-icon start size="14">mdi-calendar-range</v-icon>
          {{t('common.custom_range')}}
        </v-btn>
      </v-btn-toggle>

      <template v-if="period === 'custom'">
        <v-date-input
          :model-value="dateFromValue"
          :label="t('common.from')"
          prepend-icon=""
          prepend-inner-icon="mdi-calendar"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          width="160"
          @update:model-value="val => onDateChange('from', val)"
        />
        <v-date-input
          :model-value="dateToValue"
          :label="t('common.to')"
          prepend-icon=""
          prepend-inner-icon="mdi-calendar"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          width="160"
          @update:model-value="val => onDateChange('to', val)"
        />
      </template>
    </div>
  </div>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()

  const props = defineProps({
    branches: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] }, // selected branch ids
    period: { type: String, default: 'month' }, // controlled by parent
    dateFrom: { type: String, default: null },
    dateTo: { type: String, default: null }
  })

  const emit = defineEmits([
    'update:modelValue',
    'period-change',
    'date-change'
  ])

  // --- Branch Logic ---

  // ── Branch selection ──────────────────────────────────────────────────────────
  const selectedIds = ref([...props.modelValue])

  const totalCount = computed(() => props.branches.length)
  const selectedCount = computed(() => selectedIds.value.length)
  const allSelected = computed(
    () =>
      props.branches.length > 0 &&
      selectedIds.value.length === props.branches.length
  )
  const isSelected = id => selectedIds.value.includes(id)

  const toggleBranch = id => {
    if (isSelected(id)) {
      if (selectedIds.value.length === 1) return // keep at least 1
      selectedIds.value = selectedIds.value.filter(x => x !== id)
    } else {
      selectedIds.value = [...selectedIds.value, id]
    }
    emit('update:modelValue', selectedIds.value)
    emit('period-change', props.period)
  }

  const selectAll = () => {
    selectedIds.value = props.branches.map(b => b.id)
    emit('update:modelValue', selectedIds.value)
    emit('period-change', props.period)
  }

  const clearAll = () => {
    if (props.branches.length) {
      selectedIds.value = [props.branches[0].id]
      emit('update:modelValue', selectedIds.value)
      emit('period-change', props.period)
    }
  }

  // Auto select-all when branches first load
  watch(
    () => props.branches,
    val => {
      if (val.length && selectedIds.value.length === 0) {
        selectedIds.value = val.map(b => b.id)
        emit('update:modelValue', selectedIds.value)
      }
    },
    { immediate: true }
  )

  // ── Period ────────────────────────────────────────────────────────────────────
  // Period is fully controlled by parent via :period prop
  // We just emit up — parent updates its own ref → flows back down
  // --- Date Handling ---
  // Convert String props to Date objects for v-date-input
  const dateFromValue = computed(() =>
    props.dateFrom ? new Date(props.dateFrom) : null
  )
  const dateToValue = computed(() =>
    props.dateTo ? new Date(props.dateTo) : null
  )

  const onPeriodClick = val => {
    emit('period-change', val)
  }

  const onDateChange = (which, val) => {
    if (!val) return

    // Format Date object back to YYYY-MM-DD string
    const formattedDate = val.toISOString().split('T')[0]

    const from = which === 'from' ? formattedDate : props.dateFrom
    const to = which === 'to' ? formattedDate : props.dateTo

    emit('date-change', { from, to })
  }
</script>

<style scoped>
  .branch-filter-wrap {
    background: rgba(var(--v-theme-surface-variant), 0.1);
    border: 1px solid rgba(0, 0, 0, 0.07);
    border-radius: 12px;
    padding: 12px 16px;
  }
  .branch-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }
  .branch-chip {
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .chip-selected {
    box-shadow: 0 2px 6px rgba(var(--v-theme-primary), 0.3);
  }
  .gap-3 {
    gap: 12px;
  }
</style>
