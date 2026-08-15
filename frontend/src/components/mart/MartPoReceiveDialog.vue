<template>
  <AppDialog
    v-model="model"
    :max-width="600"
    :title="$t('po.receive')"
    :subtitle="po?.po_number"
    icon="mdi-package-down"
    color="success"
    :loading="loading"
    :disable-submit="!hasAnyQty"
    :submit-text="$t('po.receive')"
    submit-icon="mdi-package-down"
    body-class="pa-5"
    @submit="submit"
  >
        <!-- Notes -->
        <v-text-field
          v-model="notes"
          :label="$t('po.receive_notes_label')"
          variant="outlined"
          density="compact"
          rounded="lg"
          class="mb-4"
          hide-details
        />

        <!-- Items table -->
        <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
          {{ $t('order.items') }}
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
                {{ item.unit_name }} · {{ $t('po.ordered_colon') }} {{ item.quantity_ordered }} ·
                {{ $t('po.already_received_colon') }} {{ item.quantity_received }}
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
              {{ $t('po.remaining_count', { n: remaining(item) }) }}
            </v-chip>
          </div>

          <!-- Qty input -->
          <div class="d-flex align-center gap-3">
            <v-text-field
              v-model.number="item._receiving"
              type="number"
              :label="$t('po.receiving_now_label', { max: remaining(item) })"
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
              {{ $t('po.receive_all') }}
            </v-btn>
            <div
              v-if="remaining(item) <= 0"
              class="text-caption text-success font-weight-bold"
            >
              <v-icon icon="mdi-check-circle" size="14" class="mr-1" />
              {{ $t('po.fully_received') }}
            </div>
          </div>
        </div>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import AppDialog from '@/components/common/AppDialog.vue'

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
