<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('pos.receipt.title')"
    :subtitle="receipt?.order_number"
    icon="mdi-receipt-text-outline"
    color="success"
    :max-width="420"
    :persistent="false"
    hide-submit
    :cancel-text="$t('btn.close')"
    @update:model-value="$emit('update:modelValue', $event)"
    @close="$emit('close')"
  >
    <template #actions>
      <v-spacer />
      <v-btn variant="tonal" rounded="lg" prepend-icon="mdi-printer-outline" @click="print">
        {{ $t('pos.receipt.print') }}
      </v-btn>
      <v-btn color="success" variant="flat" rounded="lg" @click="$emit('close')">
        {{ $t('pos.receipt.new_sale') }}
      </v-btn>
    </template>

    <div v-if="receipt" class="pos-receipt-printable">
      <div class="text-center mb-3">
        <div class="text-body-1 font-weight-bold">{{ receipt.branch_name }}</div>
        <div v-if="receipt.branch_address" class="text-caption text-medium-emphasis">
          {{ receipt.branch_address }}
        </div>
        <div v-if="receipt.branch_phone" class="text-caption text-medium-emphasis">
          {{ receipt.branch_phone }}
        </div>
      </div>

      <v-divider class="mb-2" />

      <div class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.order_number') }}</span>
        <span class="font-weight-medium">{{ receipt.order_number }}</span>
      </div>
      <div v-if="receipt.queue_number_display" class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.queue_number') }}</span>
        <span class="font-weight-medium">{{ receipt.queue_number_display }}</span>
      </div>
      <div class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.cashier') }}</span>
        <span class="font-weight-medium">{{ receipt.cashier }}</span>
      </div>
      <div class="d-flex justify-space-between text-caption mb-2">
        <span>{{ $t('pos.receipt.date') }}</span>
        <span class="font-weight-medium">{{ receipt.date }}</span>
      </div>

      <v-divider class="mb-2" />

      <div v-for="(item, idx) in receipt.items" :key="idx" class="mb-2">
        <div class="d-flex justify-space-between text-body-2">
          <span>{{ item.qty }} × {{ item.name }}</span>
          <span class="font-weight-medium">{{ formatMoney(item.total_price) }}</span>
        </div>
        <div v-if="item.unit" class="text-caption text-medium-emphasis">{{ item.unit }}</div>
        <div v-if="item.note" class="text-caption text-medium-emphasis">{{ item.note }}</div>
        <div
          v-for="(c, cIdx) in item.customizations"
          :key="cIdx"
          class="text-caption text-medium-emphasis"
        >
          {{ c.label ? `${c.label}: ${c.value}` : c.value }}
        </div>
      </div>

      <v-divider class="mb-2" />

      <div class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.subtotal') }}</span>
        <span>{{ formatMoney(receipt.subtotal) }}</span>
      </div>
      <div v-if="receipt.discount" class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.discount') }}</span>
        <span>-{{ formatMoney(receipt.discount) }}</span>
      </div>
      <div v-if="receipt.tax" class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.tax') }}</span>
        <span>{{ formatMoney(receipt.tax) }}</span>
      </div>
      <div v-if="receipt.service_charge" class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.service_charge') }}</span>
        <span>{{ formatMoney(receipt.service_charge) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-1 font-weight-bold mt-2">
        <span>{{ $t('pos.receipt.total') }}</span>
        <span>{{ formatMoney(receipt.total) }}</span>
      </div>

      <v-divider class="my-2" />

      <div class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.payment_method') }}</span>
        <span class="text-uppercase">{{ receipt.payment_method }}</span>
      </div>
      <div v-if="receipt.payment_method === 'cash'" class="d-flex justify-space-between text-caption mb-1">
        <span>{{ $t('pos.receipt.cash_tendered') }}</span>
        <span>{{ formatMoney(receipt.cash_tendered) }}</span>
      </div>
      <div v-if="receipt.payment_method === 'cash'" class="d-flex justify-space-between text-caption">
        <span>{{ $t('pos.receipt.change_given') }}</span>
        <span>{{ formatMoney(receipt.change_given) }}</span>
      </div>
    </div>
  </AppDialog>
</template>

<script setup>
  import AppDialog from '@/components/common/AppDialog.vue'

  defineProps({
    modelValue: { type: Boolean, default: false },
    receipt: { type: Object, default: null }
  })

  defineEmits(['update:modelValue', 'close'])

  function print() {
    window.print()
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value ?? 0)
  }
</script>

<style>
  @media print {
    body * {
      visibility: hidden;
    }
    .pos-receipt-printable,
    .pos-receipt-printable * {
      visibility: visible;
    }
    .pos-receipt-printable {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      padding: 16px;
    }
  }
</style>
