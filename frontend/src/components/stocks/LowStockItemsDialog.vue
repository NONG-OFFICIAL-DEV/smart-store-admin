<template>
  <AppDialog
    v-model="internalModel"
    :max-width="800"
    :title="$t('dashboard.low_stock_dialog.title')"
    icon="mdi-alert-outline"
    color="warning"
    :hide-submit="true"
    :cancel-text="$t('btn.close')"
    @close="close"
  >
    <v-data-table
      :items="lowStockItems"
      :headers="tableHeaders"
      class="pa-2"
    />
  </AppDialog>
</template>

<script setup>
  import { ref, watch, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useDashboardStore } from '@/stores/dashboardStore'
  import AppDialog from '@/components/common/AppDialog.vue'

  const dashboardStore = useDashboardStore()
  const { t } = useI18n()

  // Props
  const props = defineProps({
    modelValue: { type: Boolean, required: true }
  })

  // Emits
  const emit = defineEmits(['update:modelValue'])

  // Internal v-model
  const internalModel = ref(props.modelValue)

  // Sync parent → child
  watch(
    () => props.modelValue,
    val => (internalModel.value = val)
  )

  // Sync child → parent
  watch(internalModel, val => {
    emit('update:modelValue', val)
  })

  // Computed low-stock items
  const lowStockItems = computed(() => {
    return dashboardStore.stats?.lowStockItems || []
  })

  // Fetch stats when opening dialog
  watch(internalModel, async val => {
    if (val) {
      await dashboardStore.fetchStats()
    }
  })

  // Close dialog
  function close() {
    internalModel.value = false
  }

  // Table headers
  const tableHeaders = computed(() => [
    { title: t('stock_overview.table.product'), key: 'name' },
    { title: t('form.stock'), key: 'stock' },
    { title: t('form.category'), key: 'category' }
  ])
</script>
