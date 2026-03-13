<template>
  <v-dialog v-model="model" max-width="600" persistent scrollable>
    <v-card rounded="xl" border elevation="0">
      <v-card-title class="d-flex align-center justify-space-between pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar color="success" variant="tonal" size="40" rounded="lg">
            <v-icon icon="mdi-package-down" size="20" />
          </v-avatar>
          <div>
            <div class="text-body-1 font-weight-bold">Receive Stock</div>
            <div class="text-caption text-medium-emphasis">
              {{ po?.po_number }}
            </div>
          </div>
        </div>
        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          @click="model = false"
        />
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-5">
        <!-- Notes -->
        <v-text-field
          v-model="notes"
          label="Receive Notes (optional)"
          variant="outlined"
          density="compact"
          rounded="lg"
          class="mb-4"
          hide-details
        />

        <!-- Items table -->
        <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
          ITEMS
        </div>
        <div
          v-for="item in receiveItems"
          :key="item.id"
          class="receive-row mb-3"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <div>
              <div class="text-body-2 font-weight-bold">
                {{ item.product_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.unit_name }} · Ordered: {{ item.quantity_ordered }} ·
                Already received: {{ item.quantity_received }}
              </div>
            </div>
            <v-chip
              size="small"
              rounded="lg"
              variant="tonal"
              :color="
                item.quantity_received >= item.quantity_ordered
                  ? 'success'
                  : 'warning'
              "
            >
              {{ remaining(item) }} remaining
            </v-chip>
          </div>

          <!-- Qty input -->
          <div class="d-flex align-center gap-3">
            <v-text-field
              v-model.number="item._receiving"
              type="number"
              :label="`Receiving now (max ${remaining(item)})`"
              :max="remaining(item)"
              min="0"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              style="max-width: 200px"
              :disabled="remaining(item) <= 0"
            />
            <v-btn
              v-if="remaining(item) > 0"
              size="small"
              variant="tonal"
              rounded="lg"
              @click="item._receiving = remaining(item)"
            >
              Receive All
            </v-btn>
            <div
              v-if="remaining(item) <= 0"
              class="text-caption text-success font-weight-bold"
            >
              <v-icon icon="mdi-check-circle" size="14" class="mr-1" />
              Fully received
            </div>
          </div>
        </div>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-5 gap-3">
        <v-btn variant="tonal" rounded="lg" @click="model = false">
          Cancel
        </v-btn>
        <v-spacer />
        <v-btn
          color="success"
          variant="flat"
          rounded="lg"
          :loading="loading"
          :disabled="!hasAnyQty"
          prepend-icon="mdi-package-down"
          @click="submit"
        >
          Receive Stock
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    po: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue', 'receive'])

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const notes = ref('')
  const receiveItems = ref([])

  const remaining = item =>
    Math.max(0, item.quantity_ordered - item.quantity_received)
  const hasAnyQty = computed(() =>
    receiveItems.value.some(i => (i._receiving ?? 0) > 0)
  )

  watch(
    () => props.po,
    val => {
      if (val?.items) {
        receiveItems.value = val.items.map(i => ({
          ...i,
          _receiving: remaining(i)
        }))
      }
    },
    { immediate: true }
  )

  const submit = () => {
    emit('receive', {
      notes: notes.value || undefined,
      items: receiveItems.value
        .filter(i => (i._receiving ?? 0) > 0)
        .map(i => ({ id: i.id, quantity_received: i._receiving }))
    })
  }
</script>

<style scoped>
  .gap-3 {
    gap: 12px;
  }
  .receive-row {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
  }
</style>
