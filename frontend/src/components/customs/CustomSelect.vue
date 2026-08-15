<template>
  <v-select
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :items="normalizedItems"
    :item-title="resolvedItemTitle"
    :item-value="resolvedItemValue"
    :label="resolvedLabel"
    :variant="variant"
    :density="density"
    :hide-details="hideDetails"
    :clearable="clearable"
    :rounded="rounded"
    :disabled="disabled"
    :loading="loading"
    :placeholder="placeholder"
    :rules="rules"
    :multiple="multiple"
    :max-width="maxWidth"
    :min-width="minWidth"
      >
    <template v-if="multiple" v-slot:selection="{ item, index }">
      <!-- Visible chips -->
      <v-chip
        v-if="index < maxVisibleChips"
        :color="chipColor"
        :size="chipSize"
        :closable="closableChips"
        @click:close.stop="removeItem(item)"
        class="custom-chip"
      >
        {{ getItemTitle(item) }}
      </v-chip>

      <!-- Overflow counter — shown ONCE at the cutoff index -->
      <v-chip
        v-else-if="index === maxVisibleChips"
        :size="chipSize"
        variant="outlined"
        class="custom-chip overflow-chip"
      >
        {{ $t('common.and_more', { n: (modelValue?.length || 0) - maxVisibleChips }) }}
      </v-chip>

      <!-- index > maxVisibleChips → render nothing -->
    </template>

    <!-- No data -->
    <template v-slot:no-data>
      <slot name="no-data">
        <v-list-item>
          <v-list-item-title class="text-grey">
            {{ resolvedNoDataText }}
          </v-list-item-title>
        </v-list-item>
      </slot>
    </template>
  </v-select>
</template>
<script setup>
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()

  const props = defineProps({
    // Core v-model
    modelValue: {
      type: [Array, String, Number, Object, null],
      default: null
    },

    // Items — supports: strings, numbers, objects
    items: {
      type: Array,
      required: true,
      default: () => []
    },

    // Object key mapping (like native v-select)
    itemTitle: {
      type: [String, Function],
      default: null // auto-detected
    },
    itemValue: {
      type: [String, Function],
      default: null // auto-detected
    },

    // Labels & text
    label: {
      type: String,
      default: undefined
    },
    placeholder: {
      type: String,
      default: undefined
    },
    noDataText: {
      type: String,
      default: undefined
    },

    // Vuetify style props
    variant: {
      type: String,
      default: 'outlined'
    },
    density: {
      type: String,
      default: 'compact'
    },
    rounded: {
      type: [String, Boolean],
      default: 'lg'
    },
    hideDetails: {
      type: [Boolean, String],
      default: true
    },
    clearable: {
      type: Boolean,
      default: true
    },
    disabled: {
      type: Boolean,
      default: false
    },
    loading: {
      type: Boolean,
      default: false
    },

    // Multi-select
    multiple: {
      type: Boolean,
      default: true
    },
    chips: {
      type: Boolean,
      default: true
    },
    closableChips: {
      type: Boolean,
      default: true
    },
    maxVisibleChips: {
      type: Number,
      default: 2
    },
    chipColor: {
      type: String,
      default: 'primary'
    },
    chipSize: {
      type: String,
      default: 'small'
    },

    // Sizing
    maxWidth: {
      type: [String, Number],
      default: undefined
    },
    minWidth: {
      type: [String, Number],
      default: undefined
    },

    // Validation
    rules: {
      type: Array,
      default: () => []
    }
  })

  const emit = defineEmits(['update:modelValue'])

  // Prop defaults can't call `t()` directly — defineProps() default factories
  // are hoisted out of setup(), so they can't close over the useI18n() `t`.
  const resolvedLabel = computed(() => props.label ?? t('common.select'))
  const resolvedNoDataText = computed(() => props.noDataText ?? t('table.no_data'))

  // ── Auto-detect item structure ──────────────────────────────────────────────

  const isObjectItems = computed(
    () =>
      props.items.length > 0 &&
      typeof props.items[0] === 'object' &&
      props.items[0] !== null
  )

  const resolvedItemTitle = computed(() => {
    if (props.itemTitle) return props.itemTitle
    if (isObjectItems.value) {
      const first = props.items[0]
      // Common title keys in priority order
      for (const key of ['name', 'label', 'title', 'text', 'display']) {
        if (key in first) return key
      }
      return Object.keys(first)[0] // fallback to first key
    }
    return 'title' // Vuetify default for primitives
  })

  const resolvedItemValue = computed(() => {
    if (props.itemValue) return props.itemValue
    if (isObjectItems.value) {
      const first = props.items[0]
      for (const key of ['id', 'value', 'key', 'code']) {
        if (key in first) return key
      }
      return Object.keys(first)[1] ?? Object.keys(first)[0]
    }
    return 'value' // Vuetify default for primitives
  })

  // Normalize items to always be safe (filter out null/undefined)
  const normalizedItems = computed(() =>
    (props.items || []).filter(item => item !== null && item !== undefined)
  )

  // ── Helpers ─────────────────────────────────────────────────────────────────

  function getItemTitle(item) {
    // item here is the slot's item object (already resolved by Vuetify)
    if (item?.title !== undefined) return item.title
    if (item?.raw !== undefined) {
      const raw = item.raw
      if (typeof raw === 'object' && raw !== null) {
        return raw[resolvedItemTitle.value] ?? String(raw)
      }
      return String(raw)
    }
    return String(item ?? '')
  }

  function removeItem(item) {
    if (!Array.isArray(props.modelValue)) return
    const valueKey = resolvedItemValue.value
    const rawValue = item?.raw !== undefined ? item.raw : item
    const itemVal =
      typeof rawValue === 'object' && rawValue !== null
        ? rawValue[valueKey]
        : rawValue

    const updated = props.modelValue.filter(v => {
      const vVal = typeof v === 'object' && v !== null ? v[valueKey] : v
      return vVal !== itemVal
    })
    emit('update:modelValue', updated)
  }
</script>

<style scoped>
  .overflow-chip {
    font-weight: 600;
  }
  .selection-text {
    white-space: nowrap;
  }
</style>
