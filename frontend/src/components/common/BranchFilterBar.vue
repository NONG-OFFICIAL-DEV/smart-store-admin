<template>
  <div class="period-tabs mb-5">

    <!-- ── Branch Filter ──────────────────────────────────────────────────── -->
    <div class="branch-filter-wrap mb-4">
      <div class="d-flex align-center gap-2 mb-2">
        <v-icon icon="mdi-store-outline" size="16" color="primary" />
        <span class="text-caption font-weight-bold text-uppercase" style="letter-spacing:1px">
          Branches
        </span>
        <v-spacer />
        <span class="text-caption text-medium-emphasis">
          {{ selectedCount }} / {{ totalCount }} selected
        </span>
        <v-btn
          v-if="!allSelected"
          size="x-small" variant="tonal" color="primary" rounded="lg"
          @click="selectAll"
        >
          Select All
        </v-btn>
        <v-btn
          v-else
          size="x-small" variant="tonal" color="grey" rounded="lg"
          @click="clearAll"
        >
          Clear
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
          <v-icon start size="12" :icon="isSelected(branch.id) ? 'mdi-check' : 'mdi-store-outline'" />
          {{ branch.name }}
        </v-chip>
        <span v-if="!branches.length" class="text-caption text-medium-emphasis">
          No branches available
        </span>
      </div>

      <v-alert
        v-if="selectedCount === 0"
        type="warning" variant="tonal" density="compact" rounded="lg"
        class="mt-2" icon="mdi-alert-outline"
      >
        No branch selected — select at least one to see data
      </v-alert>
    </div>

    <!-- ── Period Toggle ──────────────────────────────────────────────────── -->
    <div class="d-flex align-center gap-3 flex-wrap">
      <v-btn-toggle
        :model-value="period"
        mandatory
        rounded="lg"
        density="comfortable"
        color="primary"
        variant="outlined"
        @update:model-value="onPeriodClick"
      >
        <v-btn value="today"      size="small">Today</v-btn>
        <v-btn value="yesterday"  size="small">Yesterday</v-btn>
        <v-btn value="week"       size="small">This Week</v-btn>
        <v-btn value="month"      size="small">This Month</v-btn>
        <v-btn value="last_month" size="small">Last Month</v-btn>
        <v-btn value="custom"     size="small">
          <v-icon start size="14">mdi-calendar-range</v-icon>
          Custom
        </v-btn>
      </v-btn-toggle>
    </div>

    <!-- Custom date pickers -->
    <div v-if="period === 'custom'" class="d-flex gap-3 mt-3">
      <v-text-field
        :model-value="dateFrom"
        type="date"
        label="From"
        variant="outlined"
        density="compact"
        rounded="lg"
        hide-details
        style="max-width: 180px"
        @update:model-value="onDateChange('from', $event)"
      />
      <v-text-field
        :model-value="dateTo"
        type="date"
        label="To"
        variant="outlined"
        density="compact"
        rounded="lg"
        hide-details
        style="max-width: 180px"
        @update:model-value="onDateChange('to', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  branches:    { type: Array,  default: () => [] },
  modelValue:  { type: Array,  default: () => [] }, // selected branch ids
  period:      { type: String, default: 'today'  }, // controlled by parent
  dateFrom:    { type: String, default: null      },
  dateTo:      { type: String, default: null      },
})

const emit = defineEmits([
  'update:modelValue',  // branch ids changed
  'period-change',      // period tab clicked → parent calls loadAll
  'date-change',        // custom dates changed → parent calls loadAll
])

// ── Branch selection ──────────────────────────────────────────────────────────
const selectedIds = ref([...props.modelValue])

const totalCount    = computed(() => props.branches.length)
const selectedCount = computed(() => selectedIds.value.length)
const allSelected   = computed(() =>
  props.branches.length > 0 && selectedIds.value.length === props.branches.length
)

const isSelected   = id => selectedIds.value.includes(id)

const toggleBranch = id => {
  if (isSelected(id)) {
    if (selectedIds.value.length === 1) return // keep at least 1
    selectedIds.value = selectedIds.value.filter(x => x !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
  emit('update:modelValue', selectedIds.value)
  emit('period-change', props.period) // reload with new branch filter
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
watch(() => props.branches, val => {
  if (val.length && selectedIds.value.length === 0) {
    selectedIds.value = val.map(b => b.id)
    emit('update:modelValue', selectedIds.value)
  }
}, { immediate: true })

// ── Period ────────────────────────────────────────────────────────────────────
// Period is fully controlled by parent via :period prop
// We just emit up — parent updates its own ref → flows back down
const onPeriodClick = val => {
  emit('period-change', val)
}

// ── Custom dates ──────────────────────────────────────────────────────────────
const onDateChange = (which, val) => {
  const from = which === 'from' ? val : props.dateFrom
  const to   = which === 'to'   ? val : props.dateTo
  emit('date-change', { from, to })
}
</script>

<style scoped>
.branch-filter-wrap {
  background: rgba(0,0,0,0.02);
  border: 1px solid rgba(0,0,0,0.07);
  border-radius: 12px;
  padding: 14px 16px;
}
.branch-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.branch-chip {
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}
.branch-chip:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.chip-selected {
  box-shadow: 0 2px 8px rgba(59,130,246,0.25);
}
.gap-2 { gap: 8px;  }
.gap-3 { gap: 12px; }
</style>