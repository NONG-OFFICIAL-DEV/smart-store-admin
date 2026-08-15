<template>
  <AppDialog
    v-model="model"
    :max-width="640"
    :persistent="false"
    :title="po?.po_number || ''"
    icon="mdi-clipboard-text-outline"
    color="primary"
    body-class="pa-5"
    :hide-actions="true"
  >
    <template #header-extra>
      <div v-if="po" class="d-flex align-center gap-2 px-5 pb-4" style="margin-top: -8px">
        <v-chip
          size="small"
          rounded="lg"
          variant="tonal"
          :color="statusColor(po.status)"
        >
          {{ $t(`po.status.${po.status}`) }}
        </v-chip>
        <span class="text-caption text-medium-emphasis">
          {{ fmtDate(po.created_at) }}
        </span>
      </div>
    </template>

    <template v-if="po">
        <!-- Meta -->
        <v-row dense class="mb-4">
          <v-col cols="6">
            <div class="text-caption text-medium-emphasis">{{ $t('form.supplier') }}</div>
            <div class="text-body-2 font-weight-bold">
              {{ po.supplier?.name }}
            </div>
          </v-col>
          <v-col cols="6">
            <div class="text-caption text-medium-emphasis">{{ $t('form.branch') }}</div>
            <div class="text-body-2 font-weight-bold">
              {{ po.branch?.name }}
            </div>
          </v-col>
          <v-col cols="6">
            <div class="text-caption text-medium-emphasis">
              {{ $t('form.expected_delivery') }}
            </div>
            <div class="text-body-2">{{ fmtDate(po.expected_delivery) }}</div>
          </v-col>
          <v-col cols="6">
            <div class="text-caption text-medium-emphasis">{{ $t('po.total_amount') }}</div>
            <div class="text-body-2 font-weight-black text-primary">
              {{ fmt(po.total_amount) }}
            </div>
          </v-col>
          <v-col v-if="po.notes" cols="12">
            <div class="text-caption text-medium-emphasis">{{ $t('form.notes') }}</div>
            <div class="text-body-2">{{ po.notes }}</div>
          </v-col>
        </v-row>

        <v-divider class="mb-4" />

        <!-- Items -->
        <div class="text-caption font-weight-bold text-medium-emphasis mb-3">
          {{ $t('po.order_items') }}
        </div>
        <div v-for="item in po.items" :key="item.id" class="detail-item mb-2">
          <div class="d-flex align-center justify-space-between">
            <div class="flex-grow-1">
              <div class="text-body-2 font-weight-bold">
                {{ item.product_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.unit_name }} · {{ fmt(item.unit_cost) }}{{ $t('po.per_unit_suffix') }}
              </div>
            </div>
            <div class="text-right ml-4">
              <div class="text-body-2 font-weight-bold">
                {{ fmt(item.total_cost) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ $t('po.received_progress', { received: item.quantity_received, ordered: item.quantity_ordered }) }}
              </div>
            </div>
          </div>
          <!-- Progress bar -->
          <v-progress-linear
            :model-value="
              (item.quantity_received / item.quantity_ordered) * 100
            "
            color="success"
            height="4"
            rounded
            bg-color="grey-lighten-3"
            class="mt-2"
          />
        </div>
    </template>
  </AppDialog>
</template>

<script setup>
  import { computed } from 'vue'
  import AppDialog from '@/components/common/AppDialog.vue'
  import { useDate } from '@/composables/useDate'

  const { formatShortDate: fmtDate } = useDate()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    po: { type: Object, default: null }
  })
  const emit = defineEmits(['update:modelValue'])
  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const statusColor = s =>
    ({
      draft: 'grey',
      submitted: 'blue',
      confirmed: 'indigo',
      partially_received: 'warning',
      received: 'success',
      cancelled: 'error'
    })[s] ?? 'grey'

  const fmt = v =>
    new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(v ?? 0)
</script>

<style scoped>
  .gap-2 {
    gap: 8px;
  }
  .detail-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
  }
</style>
