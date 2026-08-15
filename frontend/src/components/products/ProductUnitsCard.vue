<template>
  <v-card rounded="xl" border elevation="0" class="mb-4">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between px-5 pt-5 pb-3">
      <div>
        <p class="text-body-1 font-weight-semibold mb-0">
          {{ $t('unit.title') }}
        </p>
        <p class="text-caption text-grey mb-0">{{ $t('unit.manage_hint') }}</p>
      </div>
      <v-btn
        size="small"
        color="primary"
        variant="tonal"
        prepend-icon="mdi-plus"
        rounded="lg"
        @click="openCreate"
      >
        {{ t('unit.add_unit') }}
      </v-btn>
    </div>
    <v-divider />

    <!-- Info banner -->
    <div class="px-5 mt-3 mb-2">
      <v-alert type="info" variant="tonal" density="compact" rounded="lg">
        <template #title>
         {{ t('unit.how_title') }}
        </template>
        {{ t('unit.how_desc') }}
      </v-alert>
    </div>
    <v-divider />

    <!-- Unit rows -->
    <div class="pa-3 d-flex flex-column" style="gap: 8px">
      <!-- Loading skeletons -->
      <template v-if="unitStore.loading">
        <v-skeleton-loader
          v-for="n in 2"
          :key="n"
          type="list-item-two-line"
          rounded="lg"
        />
      </template>

      <!-- Empty state -->
      <div v-else-if="!unitStore.units.length" class="text-center py-10">
        <v-icon
          icon="mdi-package-variant-remove"
          size="52"
          color="grey-lighten-1"
          class="mb-3"
        />
        <div class="text-body-2 font-weight-medium text-grey mb-1">
          {{ t('unit.no_data') }}
        </div>
        <p class="text-caption text-grey mb-4">{{ t('unit.no_data_desc') }}</p>
        <v-btn
          color="primary"
          variant="flat"
          size="small"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ t('unit.add_first_unit') }}
        </v-btn>
      </div>

      <!-- Unit rows -->
      <template v-else>
        <div v-for="item in sortedUnits" :key="item.id" class="unit-row">
          <!-- Row summary -->
          <div class="unit-row-summary" @click="toggleExpand(item.id)">
            <!-- Icon -->
            <div class="unit-icon-wrap">
              <v-icon
                :icon="
                  item.qty_per_base > 1
                    ? 'mdi-package-variant'
                    : 'mdi-cube-outline'
                "
                size="18"
                color="grey"
              />
            </div>

            <!-- Name block -->
            <div class="unit-name-block">
              <div class="d-flex align-center" style="gap: 6px">
                <span class="text-body-2 font-weight-bold">
                  {{ item.unit_label || item.unit_name }}
                </span>
                <v-chip
                  v-if="item.is_base_unit"
                  size="x-small"
                  color="success"
                  variant="tonal"
                  rounded="lg"
                >
                  {{ t('unit.base') }}
                </v-chip>
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.unit_name }}
              </div>
            </div>

            <!-- Metrics -->
            <div class="unit-metrics">
              <div class="metric-block">
                <div class="metric-label">{{ t('unit.headers.qty') }}</div>
                <!-- Qty -->
                <v-chip
                  size="small"
                  variant="tonal"
                  rounded="lg"
                  class="qty-chip"
                >
                  × {{ item.qty_per_base }}
                </v-chip>
              </div>

              <!-- Retail price -->
              <div class="metric-block">
                <div class="metric-label">{{ t('unit.headers.retail') }}</div>
                <div class="text-body-2 font-weight-bold text-primary">
                  {{ format(item.retail_price) }}
                </div>
              </div>

              <!-- Margin -->
              <div class="metric-block">
                <div class="metric-label">{{ t('unit.headers.margin') }}</div>
                <template v-if="item.cost_price">
                  <v-chip
                    :color="
                      marginPct(item.retail_price, item.cost_price) >= 30
                        ? 'success'
                        : marginPct(item.retail_price, item.cost_price) >= 10
                          ? 'warning'
                          : 'error'
                    "
                    size="x-small"
                    variant="tonal"
                  >
                    {{ marginPct(item.retail_price, item.cost_price) }}%
                  </v-chip>
                </template>
                <span v-else class="text-caption text-medium-emphasis">—</span>
              </div>

              <!-- Active toggle -->
              <v-switch
                :model-value="item.is_active"
                color="success"
                density="compact"
                hide-details
                class="unit-switch"
                @click.stop
                @change="toggleActive(item)"
              />
            </div>

            <!-- Actions -->
            <div class="unit-actions" @click.stop>
              <v-btn
                icon="mdi-pencil-outline"
                size="small"
                variant="text"
                @click="openEdit(item)"
              />
              <v-btn
                icon="mdi-delete-outline"
                size="small"
                variant="text"
                color="error"
                @click="confirmDelete(item)"
              />
            </div>

            <!-- Expand chevron -->
            <v-btn
              :icon="
                expandedIds.has(item.id) ? 'mdi-chevron-up' : 'mdi-chevron-down'
              "
              size="small"
              variant="text"
              class="expand-btn"
            />
          </div>

          <!-- Expanded detail panel -->
          <v-expand-transition>
            <div v-if="expandedIds.has(item.id)" class="unit-detail">
              <v-divider />
              <div class="detail-grid pa-3">
                <div class="detail-cell">
                  <div class="detail-label">{{ t('unit.headers.retail') }}</div>
                  <div class="text-body-2 font-weight-bold text-primary">
                    {{ format(item.retail_price) }}
                  </div>
                </div>
                <div class="detail-cell">
                  <div class="detail-label">
                    {{ t('unit.headers.wholesale') }}
                  </div>
                  <div
                    v-if="item.wholesale_price"
                    class="text-body-2 font-weight-bold text-success"
                  >
                    {{ format(item.wholesale_price) }}
                  </div>
                  <span v-else class="text-caption text-medium-emphasis">
                    —
                  </span>
                </div>
                <div class="detail-cell">
                  <div class="detail-label">{{ $t('unit.cost_price') }}</div>
                  <div v-if="item.cost_price" class="text-body-2">
                    {{ format(item.cost_price) }}
                  </div>
                  <span v-else class="text-caption text-medium-emphasis">
                    —
                  </span>
                </div>
                <div class="detail-cell">
                  <div class="detail-label">
                    {{ t('unit.headers.barcode') }}
                  </div>
                  <div
                    v-if="item.barcode"
                    class="d-flex align-center"
                    style="gap: 4px"
                  >
                    <v-icon icon="mdi-barcode" size="14" />
                    <span class="text-caption font-weight-medium">
                      {{ item.barcode }}
                    </span>
                  </div>
                  <span v-else class="text-caption text-medium-emphasis">
                    —
                  </span>
                </div>
              </div>
            </div>
          </v-expand-transition>
        </div>
      </template>
    </div>

    <!-- Dialog -->
    <ProductUnitDialog
      v-model="dialog"
      :unit="selectedUnit"
      :loading="saving"
      @save="handleSave"
    />
  </v-card>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useProductUnitStore } from '@/stores/productUnitStore'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import ProductUnitDialog from '@/components/products/ProductUnitDialog.vue'

  const props = defineProps({
    productId: { type: [String, Number], required: true }
  })

  const { format } = useCurrency()
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const unitStore = useProductUnitStore()

  const dialog = ref(false)
  const selectedUnit = ref(null)
  const saving = ref(false)
  const expandedIds = ref(new Set())

  const sortedUnits = computed(() =>
    [...unitStore.units].sort((a, b) => a.qty_per_base - b.qty_per_base)
  )

  const marginPct = (sell, cost) => (((sell - cost) / sell) * 100).toFixed(1)

  const toggleExpand = id => {
    const s = new Set(expandedIds.value)
    s.has(id) ? s.delete(id) : s.add(id)
    expandedIds.value = s
  }

  const openCreate = () => {
    selectedUnit.value = null
    dialog.value = true
  }
  const openEdit = unit => {
    selectedUnit.value = { ...unit }
    dialog.value = true
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await unitStore.updateUnit(props.productId, payload.id, payload)
        notif('Unit updated', { type: 'success' })
      } else {
        await unitStore.createUnit(props.productId, payload)
        notif('Unit added', { type: 'success' })
      }
      dialog.value = false
      await unitStore.fetchUnits(props.productId)
    } catch (err) {
      notif(err.response?.data?.message ?? 'Failed to save unit', {
        type: 'error'
      })
    } finally {
      saving.value = false
    }
  }

  const toggleActive = async unit => {
    try {
      await unitStore.updateUnit(props.productId, unit.id, {
        is_active: !unit.is_active
      })
      await unitStore.fetchUnits(props.productId)
    } catch {
      notif('Failed to update unit', { type: 'error' })
    }
  }

  const confirmDelete = unit => {
    confirm({
      title: 'Delete Product Unit?',
      message: `Are you sure you want to delete "${unit.unit_name}"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        try {
          await unitStore.deleteUnit(props.productId, unit.id)
          notif(t('messages.deleted_success'), { type: 'success' })
          await unitStore.fetchUnits(props.productId)
        } catch (err) {
          const status = err.response?.status
          const msgKey =
            status === 422
              ? 'unit.errors.cannot_delete_base_unit'
              : 'messages.delete_failed'
          notif(t(msgKey), { type: 'error' })
        }
      }
    })
  }

  onMounted(() => unitStore.fetchUnits(props.productId))
</script>

<style scoped>
  .unit-row {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 12px;
    overflow: hidden;
  }

  .unit-row-summary {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
  }
  .unit-row-summary:hover {
    background: rgba(var(--v-theme-surface-variant), 0.3);
  }

  .unit-icon-wrap {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: rgba(var(--v-border-color), 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .unit-name-block {
    flex: 1;
    min-width: 0;
  }

  .unit-metrics {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
  }

  .metric-block {
    text-align: right;
    min-width: 64px;
  }

  .metric-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: rgba(var(--v-theme-on-surface), 0.45);
    margin-bottom: 1px;
  }

  .qty-chip {
    flex-shrink: 0;
  }

  .unit-switch {
    flex-shrink: 0;
  }

  .unit-actions {
    display: flex;
    gap: 2px;
    flex-shrink: 0;
    border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding-left: 8px;
  }

  .expand-btn {
    flex-shrink: 0;
    color: rgba(var(--v-theme-on-surface), 0.4) !important;
  }

  .unit-detail {
    background: rgba(var(--v-theme-surface-variant), 0.25);
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 8px;
  }

  .detail-cell {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 8px;
    padding: 10px 12px;
  }

  .detail-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: rgba(var(--v-theme-on-surface), 0.45);
    margin-bottom: 4px;
  }

  /* Hide metric labels on very small screens, keep values */
  @media (max-width: 600px) {
    .metric-label {
      display: none;
    }
    .unit-metrics {
      gap: 10px;
    }
  }
</style>
