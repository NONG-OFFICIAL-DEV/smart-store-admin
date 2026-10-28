<template>
  <AppDialog v-model="model" :max-width="480" :persistent="false" :title="dialogTitle">
    <template v-if="order">
      <div class="d-flex align-center flex-wrap ga-2 mb-4">
        <v-chip size="small" variant="tonal" :prepend-icon="sourceMeta.icon">
          {{ sourceMeta.label }}
        </v-chip>
        <v-chip size="small" variant="tonal" color="primary" class="text-capitalize">
          {{ order.order_type?.replace('_', ' ') ?? '—' }}
        </v-chip>
        <v-chip size="small" variant="flat" :color="statusMeta.color">
          {{ statusMeta.label }}
        </v-chip>
        <v-spacer />
        <span class="text-caption text-medium-emphasis d-flex align-center ga-1">
          <v-icon icon="mdi-clock-outline" size="14" />
          {{ formatTime(order.created_at) }} · {{ t('kitchen.elapsed_minutes', { n: elapsedMinutes }) }}
        </span>
      </div>

      <div class="text-caption text-medium-emphasis font-weight-bold mb-3 text-uppercase">
        {{ t('order.items') }}
      </div>
      <div v-for="item in order.items ?? []" :key="item.id" class="d-flex align-center justify-space-between mb-3 ga-2">
        <div>
          <div class="text-body-2 font-weight-medium">
            {{ item.product_name }}
            <span v-if="item.variant_name" class="text-medium-emphasis">· {{ item.variant_name }}</span>
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ formatBase(item.unit_price) }} × {{ item.quantity }}
          </div>
          <div v-if="item.modifiers?.length" class="text-caption text-medium-emphasis">
            {{ item.modifiers.map(m => m.name).join(', ') }}
          </div>
          <div v-if="item.notes" class="text-caption text-info">
            {{ t('order.note_prefix') }} {{ item.notes }}
          </div>
        </div>
        <div class="text-body-2 font-weight-bold flex-shrink-0">
          {{ formatBase(item.total_price) }}
        </div>
      </div>

      <v-divider class="my-3" />

      <div class="d-flex justify-space-between mb-2">
        <span class="text-body-2 text-medium-emphasis">{{ t('order.subtotal') }}</span>
        <span class="text-body-2">{{ formatBase(order.subtotal) }}</span>
      </div>
      <div v-if="Number(order.discount_amount) > 0" class="d-flex justify-space-between mb-2">
        <span class="text-body-2 text-medium-emphasis">{{ t('order.discount') }}</span>
        <span class="text-body-2 text-error">-{{ formatBase(order.discount_amount) }}</span>
      </div>
      <div v-if="Number(order.tax_amount) > 0" class="d-flex justify-space-between mb-2">
        <span class="text-body-2 text-medium-emphasis">{{ t('order.tax') }}</span>
        <span class="text-body-2">{{ formatBase(order.tax_amount) }}</span>
      </div>
      <div v-if="Number(order.service_charge_amount) > 0" class="d-flex justify-space-between mb-2">
        <span class="text-body-2 text-medium-emphasis">{{ t('branches.detail.service_charge') }}</span>
        <span class="text-body-2">{{ formatBase(order.service_charge_amount) }}</span>
      </div>
      <v-divider class="my-2" />
      <div class="d-flex justify-space-between">
        <span class="text-body-1 font-weight-bold">{{ t('order.total') }}</span>
        <span class="text-body-1 font-weight-bold text-primary">{{ formatBase(order.total_amount) }}</span>
      </div>

      <v-alert
        v-if="actionFailed"
        type="error"
        variant="tonal"
        density="compact"
        rounded="lg"
        class="mt-4"
        :text="t('kitchen.action_failed')"
      />
    </template>

    <template #actions>
      <v-btn variant="tonal" rounded="lg" @click="model = false">
        {{ t('btn.close') }}
      </v-btn>
      <v-spacer />
      <v-btn
        v-if="ticket?.status !== 'done'"
        variant="text"
        color="error"
        rounded="lg"
        :loading="acting === 'cancel'"
        :disabled="!!acting"
        @click="runAction('cancel')"
      >
        {{ t('btn.cancel') }}
      </v-btn>
      <v-btn
        v-if="ticket?.status === 'new'"
        color="primary"
        variant="flat"
        rounded="lg"
        :loading="acting === 'start'"
        :disabled="!!acting"
        @click="runAction('start')"
      >
        {{ t('kitchen.start') }}
      </v-btn>
      <v-btn
        v-if="ticket?.status === 'in_progress'"
        color="success"
        variant="flat"
        rounded="lg"
        :loading="acting === 'complete'"
        :disabled="!!acting"
        @click="runAction('complete')"
      >
        {{ t('kitchen.complete') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useDate } from '@/composables/useDate'
  import {
    startKitchenDisplayTicketApi,
    completeKitchenDisplayTicketApi,
    cancelKitchenDisplayTicketApi
  } from '@/api/kitchenDisplayTicketService'

  const { t } = useI18n()
  const { formatBase } = useCurrency()
  const { formatTime } = useDate()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    order: { type: Object, default: null },
    ticket: { type: Object, default: null }
  })
  const emit = defineEmits(['update:modelValue', 'updated'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const dialogTitle = computed(() => {
    if (!props.order) return t('order.title')
    if (!props.order.table?.number) return props.order.order_number
    return `${props.order.order_number} · ${t('kitchen.table_prefix')} ${props.order.table.number}`
  })

  // Local copy so Start/Complete/Cancel reflects instantly from the API
  // response — the carousel's own list resync (triggered via 'updated')
  // happens in the background rather than being waited on here.
  const ticket = ref(props.ticket)
  watch(() => props.ticket, val => (ticket.value = val))

  const acting = ref(null)
  const actionFailed = ref(false)

  const elapsedMinutes = computed(() => {
    if (!ticket.value?.created_at) return 0
    return Math.max(0, Math.floor((Date.now() - new Date(ticket.value.created_at).getTime()) / 60000))
  })

  const statusMeta = computed(() => {
    if (ticket.value?.status === 'in_progress') return { label: t('pos.mobile_orders.status_in_kitchen'), color: 'info' }
    if (ticket.value?.status === 'done') return { label: t('pos.mobile_orders.status_ready'), color: 'success' }
    return { label: t('pos.mobile_orders.status_new'), color: 'warning' }
  })

  const SOURCE_META = {
    pos: { icon: 'mdi-point-of-sale', label: () => t('pos.mobile_orders.source_pos') },
    mobile_app: { icon: 'mdi-cellphone', label: () => t('pos.mobile_orders.source_mobile') },
    kiosk: { icon: 'mdi-tablet', label: () => t('pos.mobile_orders.source_kiosk') },
    web: { icon: 'mdi-web', label: () => t('pos.mobile_orders.source_web') },
    third_party: { icon: 'mdi-truck-delivery-outline', label: () => t('pos.mobile_orders.source_third_party') }
  }
  const sourceMeta = computed(() => {
    const meta = SOURCE_META[props.order?.source] ?? SOURCE_META.mobile_app
    return { icon: meta.icon, label: meta.label() }
  })

  const ACTION_API = {
    start: startKitchenDisplayTicketApi,
    complete: completeKitchenDisplayTicketApi,
    cancel: cancelKitchenDisplayTicketApi
  }

  async function runAction(action) {
    if (!ticket.value) return
    acting.value = action
    actionFailed.value = false
    try {
      const { data } = await ACTION_API[action](ticket.value.id)
      ticket.value = data.data
      emit('updated')
      if (action === 'cancel') model.value = false
    } catch {
      actionFailed.value = true
    } finally {
      acting.value = null
    }
  }
</script>
