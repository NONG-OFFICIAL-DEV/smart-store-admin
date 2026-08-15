<template>
  <AppDialog
    v-model="model"
    :max-width="600"
    :title="$t('po.receive_items')"
    :subtitle="purchaseOrder?.po_number"
    icon="mdi-package-down"
    color="success"
    :loading="loading"
    :submit-text="$t('po.confirm_receive')"
    submit-icon="mdi-check"
    @close="close"
    @submit="save"
  >
    <v-alert type="info" variant="tonal" density="compact" rounded="lg" class="mb-4">
      {{ $t('po.receive_instructions') }}
    </v-alert>

    <div v-for="item in receiveItems" :key="item.id" class="mb-4">
      <div class="d-flex align-center justify-space-between mb-1">
        <div>
          <div class="text-body-2 font-weight-medium">{{ item.ingredient_name }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ $t('po.quantity_ordered') }}: {{ item.quantity_ordered }} | {{ $t('po.quantity_received') }}: {{ item.quantity_received }}
            | {{ $t('po.remaining') }}: {{ item.remaining }}
          </div>
        </div>
        <v-chip size="x-small" rounded="lg" variant="tonal"
          :color="item.remaining === 0 ? 'success' : 'warning'">
          {{ item.remaining === 0 ? $t('status.completed') : $t('status.pending') }}
        </v-chip>
      </div>
      <v-text-field
        v-model.number="item.qty_to_receive"
        type="number" :label="$t('po.receive_qty_max', { max: item.remaining })"
        :max="item.remaining" min="0"
        variant="outlined" density="compact" rounded="lg"
        hide-details
        :disabled="item.remaining === 0"
      />
    </div>
  </AppDialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'

const { t } = useI18n()

const props = defineProps({
  modelValue:    { type: Boolean, default: false },
  purchaseOrder: { type: Object,  default: null  },
  loading:       { type: Boolean, default: false },
})
const emit    = defineEmits(['update:modelValue', 'save'])
const model   = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })

const receiveItems = ref([])

watch(() => props.purchaseOrder, val => {
  if (!val) return
  receiveItems.value = (val.items ?? []).map(i => ({
    id:               i.id,
    ingredient_name:  i.ingredient?.name ?? t('common.unknown'),
    quantity_ordered: parseFloat(i.quantity_ordered),
    quantity_received:parseFloat(i.quantity_received ?? 0),
    remaining:        parseFloat(i.quantity_ordered) - parseFloat(i.quantity_received ?? 0),
    qty_to_receive:   parseFloat(i.quantity_ordered) - parseFloat(i.quantity_received ?? 0),
  }))
}, { immediate: true })

const save = () => {
  const payload = {
    items: receiveItems.value
      .filter(i => i.qty_to_receive > 0)
      .map(i => ({ id: i.id, quantity_received: i.qty_to_receive }))
  }
  if (!payload.items.length) return
  emit('save', payload)
}

const close = () => { model.value = false }
</script>