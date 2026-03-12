<template>
  <v-dialog v-model="model" max-width="600" persistent scrollable>
    <v-card rounded="xl" border elevation="0">
      <v-card-title class="d-flex align-center justify-space-between pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar color="success" variant="tonal" size="40" rounded="lg">
            <v-icon icon="mdi-package-down" size="20" />
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold">Receive Items</div>
            <div class="text-caption text-medium-emphasis">{{ purchaseOrder?.po_number }}</div>
          </div>
        </div>
        <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-5">
        <v-alert type="info" variant="tonal" density="compact" rounded="lg" class="mb-4">
          Enter quantity received for each item. Stock will be updated automatically.
        </v-alert>

        <div v-for="item in receiveItems" :key="item.id" class="mb-4">
          <div class="d-flex align-center justify-space-between mb-1">
            <div>
              <div class="text-body-2 font-weight-medium">{{ item.ingredient_name }}</div>
              <div class="text-caption text-medium-emphasis">
                Ordered: {{ item.quantity_ordered }} | Received: {{ item.quantity_received }}
                | Remaining: {{ item.remaining }}
              </div>
            </div>
            <v-chip size="x-small" rounded="lg" variant="tonal"
              :color="item.remaining === 0 ? 'success' : 'warning'">
              {{ item.remaining === 0 ? 'Complete' : 'Pending' }}
            </v-chip>
          </div>
          <v-text-field
            v-model.number="item.qty_to_receive"
            type="number" :label="`Receive qty (max ${item.remaining})`"
            :max="item.remaining" min="0"
            variant="outlined" density="compact" rounded="lg"
            hide-details
            :disabled="item.remaining === 0"
          />
        </div>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-5 gap-3">
        <v-btn variant="tonal" rounded="lg" @click="close">Cancel</v-btn>
        <v-spacer />
        <v-btn color="success" variant="flat" rounded="lg" :loading="loading"
          prepend-icon="mdi-check" @click="save">
          Confirm Receive
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

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
    ingredient_name:  i.ingredient?.name ?? 'Unknown',
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