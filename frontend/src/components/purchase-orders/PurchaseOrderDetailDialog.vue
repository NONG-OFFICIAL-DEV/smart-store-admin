<template>
  <AppDialog
    v-model="model"
    :max-width="640"
    :title="purchaseOrder?.po_number || ''"
  >
    <template v-if="purchaseOrder">
      <div class="d-flex align-center gap-2 mb-4">
        <v-chip
          size="small"
          rounded="lg"
          variant="tonal"
          :color="statusColor(purchaseOrder.status)"
        >
          {{ $t(`po.status.${purchaseOrder.status}`) }}
        </v-chip>
        <span class="text-caption text-medium-emphasis">
          {{ fmtDate(purchaseOrder.created_at) }}
        </span>
      </div>

      <!-- Meta -->
      <v-row dense class="mb-4">
        <v-col cols="6">
          <div class="text-caption text-medium-emphasis">{{ $t('form.supplier') }}</div>
          <div class="text-body-2 font-weight-bold">
            {{ purchaseOrder.supplier?.name }}
          </div>
        </v-col>
        <v-col cols="6">
          <div class="text-caption text-medium-emphasis">{{ $t('form.branch') }}</div>
          <div class="text-body-2 font-weight-bold">
            {{ purchaseOrder.branch?.name }}
          </div>
        </v-col>
        <v-col cols="6">
          <div class="text-caption text-medium-emphasis">
            {{ $t('form.expected_delivery') }}
          </div>
          <div class="text-body-2">{{ fmtDate(purchaseOrder.expected_delivery) }}</div>
        </v-col>
        <v-col cols="6">
          <div class="text-caption text-medium-emphasis">{{ $t('po.total_amount') }}</div>
          <div class="text-body-2 font-weight-black text-primary">
            {{ fmt(purchaseOrder.total_amount) }}
          </div>
        </v-col>
        <v-col v-if="purchaseOrder.notes" cols="12">
          <div class="text-caption text-medium-emphasis">{{ $t('form.notes') }}</div>
          <div class="text-body-2">{{ purchaseOrder.notes }}</div>
        </v-col>
      </v-row>

      <v-divider class="mb-4" />

      <!-- Items -->
      <div class="text-caption font-weight-bold text-medium-emphasis mb-3">
        {{ $t('po.order_items') }}
      </div>
      <div v-for="item in purchaseOrder.items" :key="item.id" class="detail-item mb-2">
        <div class="d-flex align-center justify-space-between">
          <div class="flex-grow-1">
            <div class="text-body-2 font-weight-bold">
              {{ item.ingredient?.name ?? $t('common.unknown') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ fmt(item.unit_price) }}{{ $t('po.per_unit_suffix') }}
            </div>
          </div>
          <div class="text-right ml-4">
            <div class="text-body-2 font-weight-bold">
              {{ fmt(item.quantity_ordered * item.unit_price) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ $t('po.received_progress', { received: item.quantity_received ?? 0, ordered: item.quantity_ordered }) }}
            </div>
          </div>
        </div>
        <!-- Progress bar -->
        <v-progress-linear
          :model-value="((item.quantity_received ?? 0) / item.quantity_ordered) * 100"
          color="success"
          height="4"
          rounded
          bg-color="grey-lighten-3"
          class="mt-2"
        />
      </div>
    </template>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.close') }}
      </v-btn>
      <v-spacer />
      <v-btn
        v-if="canReceive"
        color="success"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-package-down"
        @click="$emit('receive', purchaseOrder)"
      >
        {{ $t('po.receive') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { computed } from 'vue'
  import { AppDialog } from '@nong-official-dev/core'
  import { useDate } from '@/composables/useDate'
  import { useCurrency } from '@/composables/useCurrency_v2.js'

  const { formatShortDate: fmtDate } = useDate()
  const { format: fmt } = useCurrency()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    purchaseOrder: { type: Object, default: null }
  })
  const emit = defineEmits(['update:modelValue', 'receive'])
  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const canReceive = computed(() =>
    ['confirmed', 'partially_received'].includes(props.purchaseOrder?.status)
  )

  const statusColor = s =>
    ({
      draft: 'grey',
      submitted: 'info',
      confirmed: 'primary',
      partially_received: 'warning',
      received: 'success',
      cancelled: 'error'
    })[s] ?? 'grey'

  const close = () => {
    model.value = false
  }
</script>

<style scoped>
  .detail-item {
    padding-bottom: 8px;
  }
</style>
