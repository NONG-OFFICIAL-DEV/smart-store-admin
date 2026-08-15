<template>
  <v-card rounded="xl" border elevation="0" class="pa-4">
    <div class="card-header">
      <div class="title-wrap">
        <v-icon icon="mdi-alert-circle" color="warning" size="18" />
        <span class="text-body-2 font-weight-bold">
          {{ t('inventory_report.overview.needs_attention', 'Needs Attention') }}
        </span>
        <v-chip size="x-small" color="warning" variant="tonal" rounded="pill">
          {{ products.length }}
        </v-chip>
      </div>
      <v-btn
        v-if="products.length"
        size="small"
        variant="tonal"
        color="primary"
        rounded="lg"
        append-icon="mdi-chevron-right"
        @click="emit('view-all')"
      >
        {{ t('btn.view_all', 'View All') }}
      </v-btn>
    </div>

    <div v-if="products.length" class="chips-wrap">
      <v-chip
        v-for="p in products.slice(0, 14)"
        :key="p.id"
        size="small"
        :color="p.stock_status === 'out_of_stock' ? 'error' : 'warning'"
        variant="tonal"
        rounded="lg"
        :prepend-icon="p.stock_status === 'out_of_stock' ? 'mdi-close-circle' : 'mdi-alert'"
      >
        {{ p.name }}
        <span class="ml-1 font-weight-black">{{ p.stock_quantity }}</span>
      </v-chip>
      <v-chip v-if="products.length > 14" size="small" color="grey" variant="tonal" rounded="lg">
        +{{ products.length - 14 }} more
      </v-chip>
    </div>

    <div v-else class="all-good">
      <v-icon icon="mdi-check-circle" size="16" color="success" />
      <span class="text-caption font-weight-medium text-success">
        {{ t('inventory_report.overview.all_stocked', 'All products are well-stocked') }}
      </span>
    </div>
  </v-card>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
  products: { type: Array, default: () => [] }
})

const emit = defineEmits(['view-all'])
</script>

<style scoped>
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}
.chips-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.all-good {
  display: flex;
  align-items: center;
  gap: 4px;
}
</style>