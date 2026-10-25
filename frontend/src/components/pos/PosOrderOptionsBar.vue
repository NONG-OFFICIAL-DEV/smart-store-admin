<template>
  <div v-if="hasAnyControl" class="pos-options-bar d-flex align-center flex-wrap" :class="touch ? 'ga-3' : 'ga-2'">
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
          :autofocus="!isTouch"
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
          :autofocus="!isTouch"
          enterkeyhint="done"
          @update:model-value="$emit('update-note', $event)"
        />
      </v-card>
    </v-menu>
  </div>
</template>

<script setup>
  import { computed } from 'vue'
  import { useDisplay } from 'vuetify'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'

  const { mdAndDown: touch } = useDisplay()
  const { isTouch } = useTouchDevice()

  const props = defineProps({
    showCustomer: { type: Boolean, default: false },
    customers: { type: Array, default: () => [] },
    customerId: { type: String, default: null },
    customerName: { type: String, default: null },
    showNotes: { type: Boolean, default: false },
    note: { type: String, default: '' }
  })

  const emit = defineEmits([
    'search-customer',
    'update-customer',
    'update-note'
  ])

  const hasAnyControl = computed(() => props.showCustomer || props.showNotes)

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
