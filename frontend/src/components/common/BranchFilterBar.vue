<template>
  <v-card rounded="lg" elevation="0" class="mb-4">
    <v-card-text>
      <v-row>
        <!-- Branch select -->
        <v-col cols="12" sm="4">
          <v-select
            :model-value="selectedId"
            :items="branches"
            item-title="name"
            item-value="id"
            variant="outlined"
            rounded="lg"
            hide-details
            @update:model-value="onSelect"
            chips
          >
            <!-- Dropdown item -->
            <template #item="{ item, props: itemProps }">
              <v-list-item v-bind="itemProps" :title="undefined">
                <template #prepend>
                  <v-avatar
                    size="32"
                    rounded="lg"
                    color="primary"
                    variant="tonal"
                  >
                    <v-icon size="16">mdi-store-outline</v-icon>
                  </v-avatar>
                </template>
                <v-list-item-title class="text-body-2 font-weight-medium">
                  {{ item.raw.name }}
                </v-list-item-title>
                <v-list-item-subtitle class="d-flex align-center gap-1 mt-1">
                  <v-chip
                    v-if="item.raw.branch_type?.name"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    rounded="lg"
                  >
                    {{ item.raw.branch_type.name }}
                  </v-chip>
                </v-list-item-subtitle>
              </v-list-item>
            </template>
          </v-select>
        </v-col>

        <!-- Period toggle -->
        <v-col cols="12" sm="8">
          <div class="overflow-x-auto">
            <v-btn-toggle
              :model-value="period"
              mandatory
              rounded="lg"
              density="comfortable"
              color="primary"
              variant="outlined"
              @update:model-value="onPeriodClick"
            >
              <v-btn value="today" size="small">{{ t('common.today') }}</v-btn>
              <v-btn value="yesterday" size="small" class="d-none d-sm-flex">
                {{ t('common.yesterday') }}
              </v-btn>
              <v-btn value="week" size="small">
                {{ t('common.this_week') }}
              </v-btn>
              <v-btn value="month" size="small">
                {{ t('common.this_month') }}
              </v-btn>
              <v-btn value="last_month" size="small" class="d-none d-sm-flex">
                {{ t('common.last_month') }}
              </v-btn>
              <v-btn value="custom" size="small">
                <v-icon size="14">mdi-calendar-range</v-icon>
                <span class="d-none d-md-inline ml-1">
                  {{ t('common.custom_range') }}
                </span>
              </v-btn>
            </v-btn-toggle>
          </div>

          <!-- Custom date pickers -->
          <div v-if="period === 'custom'" class="d-flex flex-wrap gap-2 mt-2">
            <v-date-input
              :model-value="dateFromValue"
              :label="t('common.from')"
              prepend-icon=""
              prepend-inner-icon="mdi-calendar"
              variant="outlined"
              rounded="lg"
              hide-details
              :style="{ width: $vuetify.display.xs ? '100%' : '160px' }"
              @update:model-value="val => onDateChange('from', val)"
            />
            <v-date-input
              :model-value="dateToValue"
              :label="t('common.to')"
              prepend-icon=""
              prepend-inner-icon="mdi-calendar"
              variant="outlined"
              rounded="lg"
              hide-details
              :style="{ width: $vuetify.display.xs ? '100%' : '160px' }"
              @update:model-value="val => onDateChange('to', val)"
            />
          </div>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()

  const props = defineProps({
    branches: { type: Array, default: () => [] },
    modelValue: { type: [String, Number], default: null }, // single id now
    period: { type: String, default: 'month' },
    dateFrom: { type: String, default: null },
    dateTo: { type: String, default: null }
  })

  const emit = defineEmits([
    'update:modelValue',
    'period-change',
    'date-change'
  ])

  // Single selected branch id
  const selectedId = ref(props.modelValue)

  // Auto-select first branch when branches load
  watch(
    () => props.branches,
    val => {
      if (val.length && !selectedId.value) {
        selectedId.value = val[0].id
        emit('update:modelValue', selectedId.value)
      }
    },
    { immediate: true }
  )

  const onSelect = id => {
    selectedId.value = id
    emit('update:modelValue', id)
    emit('period-change', props.period)
  }

  // Period
  const onPeriodClick = val => {
    emit('period-change', val)
  }

  // Dates
  const dateFromValue = computed(() =>
    props.dateFrom ? new Date(props.dateFrom) : null
  )
  const dateToValue = computed(() =>
    props.dateTo ? new Date(props.dateTo) : null
  )

  const onDateChange = (which, val) => {
    if (!val) return
    const formattedDate = val.toISOString().split('T')[0]
    const from = which === 'from' ? formattedDate : props.dateFrom
    const to = which === 'to' ? formattedDate : props.dateTo
    emit('date-change', { from, to })
  }
</script>
