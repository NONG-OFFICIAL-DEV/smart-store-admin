<template>
  <div v-if="hasAnyControl" class="pos-options-bar d-flex align-center flex-wrap" :class="touch ? 'ga-3' : 'ga-2'">
    <!-- Order type — only rendered at all when there's an actual choice
         (2+ enabled); a single enabled type is just implied, no UI needed. -->
    <v-btn-toggle
      v-if="orderTypeOptions.length > 1"
      :model-value="orderType"
      color="primary"
      variant="tonal"
      :density="touch ? 'default' : 'compact'"
      rounded="lg"
      mandatory
      @update:model-value="$emit('update-order-type', $event)"
    >
      <v-btn
        v-for="ot in orderTypeOptions"
        :key="ot.value"
        :value="ot.value"
        :size="touch ? 'default' : 'small'"
        class="text-none"
        :class="touch ? 'px-5' : 'px-3'"
      >
        {{ ot.label }}
      </v-btn>
    </v-btn-toggle>

    <!-- Table — dine-in only, and only when the tenant actually has tables. -->
    <v-select
      v-if="orderType === 'dine_in' && tables.length"
      :model-value="tableId"
      :items="tables"
      item-title="table_number"
      item-value="id"
      :placeholder="$t('pos.cart.table_label')"
      variant="outlined"
      :density="touch ? 'comfortable' : 'compact'"
      rounded="lg"
      hide-details
      clearable
      :style="{ maxWidth: touch ? '170px' : '140px' }"
      @update:model-value="$emit('update-table-id', $event)"
    />

    <v-spacer class="d-none d-sm-block" />

    <!-- Customer — compact trigger, opens a small searchable menu. Never
         forced: a walk-in sale needs zero clicks here. -->
    <v-menu v-if="showCustomer" :close-on-content-click="false" location="bottom end">
      <template #activator="{ props }">
        <v-chip
          v-bind="props"
          variant="tonal"
          :color="customerId ? 'primary' : undefined"
          rounded="lg"
          :size="touch ? 'default' : 'small'"
          prepend-icon="mdi-account-outline"
          append-icon="mdi-menu-down"
        >
          {{ customerName || $t('pos.cart.customer_label') }}
        </v-chip>
      </template>
      <v-card rounded="lg" width="260" class="pa-2">
        <v-autocomplete
          :model-value="customerId"
          :items="customerOptions"
          item-title="label"
          item-value="id"
          :placeholder="$t('pos.cart.customer_placeholder')"
          variant="outlined"
          :density="touch ? 'comfortable' : 'compact'"
          rounded="lg"
          hide-details
          clearable
          autofocus
          prepend-inner-icon="mdi-magnify"
          @update:search="$emit('search-customer', $event)"
          @update:model-value="onCustomerSelect"
        />
      </v-card>
    </v-menu>

    <!-- Order note — compact trigger, opens a small popover for the text. -->
    <v-menu v-if="showNotes" :close-on-content-click="false" location="bottom end">
      <template #activator="{ props }">
        <v-chip
          v-bind="props"
          variant="tonal"
          :color="note ? 'primary' : undefined"
          rounded="lg"
          :size="touch ? 'default' : 'small'"
          prepend-icon="mdi-note-text-outline"
        >
          {{ note ? $t('pos.cart.note_added') : $t('pos.cart.add_note') }}
        </v-chip>
      </template>
      <v-card rounded="lg" width="280" class="pa-3">
        <v-textarea
          :model-value="note"
          :placeholder="$t('pos.cart.note_placeholder')"
          variant="outlined"
          density="compact"
          rounded="lg"
          rows="2"
          auto-grow
          hide-details
          autofocus
          @update:model-value="$emit('update-note', $event)"
        />
      </v-card>
    </v-menu>
  </div>
</template>

<script setup>
  import { computed } from 'vue'
  import { useDisplay } from 'vuetify'

  const { mdAndDown: touch } = useDisplay()

  const props = defineProps({
    orderTypeOptions: { type: Array, default: () => [] },
    orderType: { type: String, default: 'takeaway' },
    tables: { type: Array, default: () => [] },
    tableId: { type: String, default: null },
    showCustomer: { type: Boolean, default: false },
    customers: { type: Array, default: () => [] },
    customerId: { type: String, default: null },
    customerName: { type: String, default: null },
    showNotes: { type: Boolean, default: false },
    note: { type: String, default: '' }
  })

  const emit = defineEmits([
    'update-order-type',
    'update-table-id',
    'search-customer',
    'update-customer',
    'update-note'
  ])

  const hasAnyControl = computed(
    () =>
      props.orderTypeOptions.length > 1 ||
      (props.orderType === 'dine_in' && props.tables.length > 0) ||
      props.showCustomer ||
      props.showNotes
  )

  const customerOptions = computed(() => {
    const options = props.customers.map(c => ({
      id: c.id,
      label: [c.first_name, c.last_name].filter(Boolean).join(' ')
    }))
    if (props.customerId && !options.some(o => o.id === props.customerId)) {
      options.unshift({ id: props.customerId, label: props.customerName ?? '' })
    }
    return options
  })

  function onCustomerSelect(id) {
    const match = customerOptions.value.find(o => o.id === id)
    emit('update-customer', id, match?.label ?? null)
  }
</script>
