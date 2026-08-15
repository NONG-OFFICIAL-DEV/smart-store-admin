<template>
  <div>
    <custom-title
      icon="mdi-package-variant"
      :title="t('stock_overview.title')"
      :subtitle="t('stock_overview.subtitle')"
    >
      <template #right>
        <div class="d-flex gap-2">
          <!-- Show Stats toggle -->
          <v-btn
            :color="showStats ? 'primary' : 'default'"
            :variant="showStats ? 'flat' : 'tonal'"
            rounded="lg"
            prepend-icon="mdi-chart-bar"
            @click="showStats = !showStats"
          >
            {{ t('btn.stats') }}
          </v-btn>

          <!-- Show Filters toggle -->
          <v-btn
            :color="showFilters ? 'primary' : 'default'"
            :variant="showFilters ? 'flat' : 'tonal'"
            rounded="lg"
            :prepend-icon="
              showFilters ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
            "
            @click="showFilters = !showFilters"
          >
            {{ t('btn.filter') }}
            <v-badge
              v-if="activeFilterCount > 0"
              :content="activeFilterCount"
              color="error"
              floating
            />
          </v-btn>

          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            prepend-icon="mdi-tune"
            @click="openAdjust(null)"
          >
            {{ t('btn.adjust') }}
          </v-btn>
        </div>
      </template>
    </custom-title>

    <!-- ── Stats Panel ─────────────────────────────────────────────────── -->
    <v-expand-transition>
      <v-row v-if="showStats" dense class="mb-5">
        <v-col v-for="s in summaryCards" :key="s.label" cols="6" sm="3">
          <v-card rounded="xl" border elevation="0" class="pa-4">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-caption text-medium-emphasis">
                {{ s.label }}
              </span>
              <v-avatar size="28" :color="s.color" variant="tonal" rounded="lg">
                <v-icon :icon="s.icon" size="14" />
              </v-avatar>
            </div>
            <div class="text-h5 font-weight-black" :class="`text-${s.color}`">
              {{ s.value }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              {{ s.sub }}
            </div>
          </v-card>
        </v-col>
      </v-row>
    </v-expand-transition>

    <!-- ── Filter Panel ────────────────────────────────────────────────── -->
    <v-expand-transition>
      <v-card v-if="showFilters" rounded="lg" border elevation="0" class="mb-4">
        <v-card-text class="pa-4">
          <v-row dense align="center">
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="pendingSearch"
                :placeholder="t('products.filter.placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
                prepend-inner-icon="mdi-magnify"
                @keyup.enter="onFilterChange"
              />
            </v-col>
            <v-col cols="6" sm="3">
              <v-select
                v-model="pendingStockFilter"
                :items="stockFilterOptions"
                item-title="label"
                item-value="value"
                :placeholder="t('products.filter.stock')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
              />
            </v-col>
            <v-col cols="6" sm="3">
              <v-select
                v-model="pendingSortBy"
                :items="sortOptions"
                item-title="label"
                item-value="value"
                variant="outlined"
                rounded="lg"
                hide-details
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="px-4">
          <v-spacer />
          <v-btn
            v-if="hasActiveFilters"
            rounded="lg"
            variant="tonal"
            color="error"
            prepend-icon="mdi-close"
            @click="resetFilters"
          >
            {{ t('btn.reset') }}
          </v-btn>
          <v-btn
            class="bg-primary"
            rounded="lg"
            prepend-icon="mdi-magnify"
            @click="onFilterChange"
          >
            {{ t('btn.search') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-expand-transition>

    <!-- ── TABLE VIEW ──────────────────────────────────────────────────── -->
    <v-card rounded="lg" border elevation="0">
      <v-data-table-server
        :headers="headers"
        :items="martProductStore.products"
        :items-length="totalItems"
        :no-data-text="$t('products.empty')"
        item-value="id"
        rounded="0"
        @update:options="fetchOnOptions"
      >
        <!-- Product -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <v-avatar
              size="40"
              rounded="lg"
              class="border flex-shrink-0 bg-grey-lighten-4"
            >
              <v-img v-if="item.image_url" :src="item.image_url" cover />
              <v-icon
                v-else
                icon="mdi-package-variant"
                size="18"
                color="grey"
              />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">{{ item.name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.sku ?? '—' }}
              </div>
            </div>
          </div>
        </template>

        <template #item.stock_quantity="{ item }">
          <div :class="stockClass(item)">
            <div
              v-for="s in stockByUnits(item)"
              :key="s.label"
              class="text-body-2 font-weight-bold"
            >
              {{ s.qty }}
              <span class="text-caption text-medium-emphasis">
                {{ s.label }}
              </span>
            </div>
          </div>
        </template>

        <!-- Units -->
        <template #item.active_units="{ item }">
          <div class="d-flex gap-1 flex-wrap">
            <v-chip
              v-for="u in item.active_units"
              :key="u.id"
              size="x-small"
              rounded="lg"
              variant="tonal"
              :color="u.is_base_unit ? 'primary' : 'grey'"
            >
              {{ u.unit_label }}
              <template v-if="!u.is_base_unit">
                · ×{{ u.qty_per_base }}
              </template>
            </v-chip>
            <span
              v-if="!item.active_units?.length"
              class="text-caption text-medium-emphasis"
            >
              —
            </span>
          </div>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <v-chip
            size="small"
            rounded="lg"
            variant="tonal"
            :color="statusColor(item)"
            :prepend-icon="statusIcon(item)"
          >
            {{ statusLabel(item) }}
          </v-chip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <v-btn
              size="small"
              icon="mdi-tune"
              variant="text"
              title="Adjust stock"
              @click="openAdjust(item)"
            />
            <v-btn
              size="small"
              icon="mdi-history"
              variant="text"
              title="Stock history"
              @click="
                router.push({
                  name: 'MartStockMovements',
                  query: { product_id: item.id }
                })
              "
            />
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <v-icon
              icon="mdi-package-variant-remove"
              size="48"
              color="grey-lighten-1"
              class="mb-2"
            />
            <div class="text-body-2 text-grey">{{$t('products.empty')}}</div>
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- Adjust dialog -->
    <StockAdjustDialog
      v-model="adjustDialog"
      :preset-product="adjustTarget"
      :branch-list="branchList"
      :loading="adjusting"
      @save="handleAdjust"
    />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import { useMartProductStore } from '@/stores/martProductStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { adjustStockApi } from '@/api/martStockService'
  import StockAdjustDialog from '@/components/mart/StockAdjustDialog.vue'
  import { useI18n } from 'vue-i18n'
  import { useDataTable } from '@/composables/useServerTable'

  const { t } = useI18n()
  const router = useRouter()
  const martProductStore = useMartProductStore()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const { notif } = useAppUtils()

  // ── UI State ──────────────────────────────────────────────────────────────
  const showStats = ref(false)
  const showFilters = ref(false)
  const adjustDialog = ref(false)
  const adjustTarget = ref(null)
  const adjusting = ref(false)

  // ── Pending filter values (not applied yet) ───────────────────────────────
  const pendingSearch = ref('')
  const pendingStockFilter = ref(null)
  const pendingSortBy = ref('name')

  // ── Applied filter values (sent to API) ───────────────────────────────────
  const appliedFilters = ref({
    search: '',
    stock_filter: null,
    sort_by: 'name'
  })

  // ── Active filter count for badge ─────────────────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (appliedFilters.value.search) count++
    if (appliedFilters.value.stock_filter) count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  // ── Apply / Clear (aliased as onFilterChange / resetFilters) ──────────────
  function applyFilters() {
    appliedFilters.value = {
      search: pendingSearch.value,
      stock_filter: pendingStockFilter.value,
      sort_by: pendingSortBy.value
    }
    refresh()
  }

  function clearFilters() {
    pendingSearch.value = ''
    pendingStockFilter.value = null
    pendingSortBy.value = 'name'
    appliedFilters.value = { search: '', stock_filter: null, sort_by: 'name' }
    refresh()
  }

  const onFilterChange = applyFilters
  const resetFilters = clearFilters

  // ── useDataTable ───────────────────────────────────────────────────────────
  const { fetchOnOptions, refresh } = useDataTable(
    martProductStore.fetchMartProducts,
    () => ({
      search: appliedFilters.value.search,
      stock_filter: appliedFilters.value.stock_filter,
      sort_by: appliedFilters.value.sort_by
    })
  )

  // ── Filter options ────────────────────────────────────────────────────────
  const stockFilterOptions = computed(() => [
    { value: 'out', label: t('stock_overview.table.out_stock') },
    { value: 'low', label: t('stock_overview.table.low_stock') },
    { value: 'ok', label: t('stock_overview.table.in_stock') }
  ])

  const sortOptions = [
    { value: 'name', label: 'Name A–Z' },
    { value: 'stock_asc', label: 'Stock: Low first' },
    { value: 'stock_desc', label: 'Stock: High first' }
  ]

  // ── Computed ──────────────────────────────────────────────────────────────
  const totalItems = computed(() => martProductStore.pagination?.total ?? 0)

  const branchList = computed(() => {
    const b = branchStore.branches
    return Array.isArray(b) ? b : (b?.data ?? [])
  })

  // ── Summary cards ─────────────────────────────────────────────────────────
  const summaryCards = computed(() => {
    const all = martProductStore.products
    return [
      {
        label: t('stock_overview.summary.total'),
        icon: 'mdi-package-variant',
        color: 'primary',
        value: all.length,
        sub: 'mart items'
      },
      {
        label: t('stock_overview.summary.in_stock'),
        icon: 'mdi-check-circle',
        color: 'success',
        value: all.filter(p => p.stock_quantity > (p.reorder_level ?? 0))
          .length,
        sub: 'healthy level'
      },
      {
        label: t('stock_overview.summary.low_stock'),
        icon: 'mdi-alert',
        color: 'warning',
        value: all.filter(
          p =>
            p.reorder_level != null &&
            p.stock_quantity > 0 &&
            p.stock_quantity <= p.reorder_level
        ).length,
        sub: 'needs reorder'
      },
      {
        label: t('stock_overview.summary.out_of_stock'),
        icon: 'mdi-alert-circle',
        color: 'error',
        value: all.filter(p => p.stock_quantity <= 0).length,
        sub: 'zero stock'
      }
    ]
  })

  // ── Table headers ─────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('stock_overview.table.product'), key: 'name', sortable: true },
    {
      title: t('stock_overview.table.stock'),
      key: 'stock_quantity',
      sortable: true
    },
    {
      title: t('stock_overview.table.units'),
      key: 'active_units',
      sortable: false
    },
    { title: t('stock_overview.table.status'), key: 'status', sortable: false },
    { title: '', key: 'actions', sortable: false }
  ])

  // ── Helpers ───────────────────────────────────────────────────────────────
  const stockClass = p => {
    if (p.stock_quantity <= 0) return 'text-error'
    if (p.reorder_level && p.stock_quantity <= p.reorder_level)
      return 'text-warning'
    return 'text-success'
  }

  const statusLabel = p => {
    if (p.stock_quantity <= 0) return t('stock_overview.table.out_stock')
    if (p.reorder_level && p.stock_quantity <= p.reorder_level)
      return t('stock_overview.table.low_stock')
    return t('stock_overview.table.in_stock')
  }
  const statusColor = p => {
    if (p.stock_quantity <= 0) return 'error'
    if (p.reorder_level && p.stock_quantity <= p.reorder_level) return 'warning'
    return 'success'
  }
  const statusIcon = p => {
    if (p.stock_quantity <= 0) return 'mdi-alert-circle'
    if (p.reorder_level && p.stock_quantity <= p.reorder_level)
      return 'mdi-alert'
    return 'mdi-check-circle'
  }

  const stockByUnits = product => {
    const units = [...(product.active_units ?? [])].sort(
      (a, b) => b.qty_per_base - a.qty_per_base
    )
    let remaining = product.stock_quantity
    const result = []
    for (const unit of units) {
      if (unit.qty_per_base <= 1) {
        result.push({ label: unit.unit_label, qty: remaining })
      } else {
        const qty = Math.floor(remaining / unit.qty_per_base)
        remaining = remaining % unit.qty_per_base
        if (qty > 0) result.push({ label: unit.unit_label, qty })
      }
    }
    if (!result.length)
      result.push({ label: product.unit ?? 'pcs', qty: product.stock_quantity })
    return result
  }

  // ── Adjust ────────────────────────────────────────────────────────────────
  const openAdjust = product => {
    adjustTarget.value = product
    adjustDialog.value = true
  }

  const handleAdjust = async payload => {
    adjusting.value = true
    try {
      const res = await adjustStockApi({
        branch_id: authStore.branch_id,
        ...payload
      })
      martProductStore.updateStock(
        payload.product_id,
        res.data.data?.qty_after ?? payload.quantity
      )
      notif('Stock adjusted', { type: 'success' })
      adjustDialog.value = false
      refresh()
    } catch (e) {
      notif(e.response?.data?.message ?? 'Failed', { type: 'error' })
    } finally {
      adjusting.value = false
    }
  }

  onMounted(async () => {
    await Promise.all([branchStore.fetchBranches?.()])
  })
</script>

<style scoped>
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }

  .product-card {
    transition: box-shadow 0.15s;
  }
  .product-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08) !important;
  }
  .product-card.card-out {
    border-color: rgb(var(--v-theme-error)) !important;
  }
  .product-card.card-low {
    border-color: rgb(var(--v-theme-warning)) !important;
  }

  .product-img-wrap {
    position: relative;
  }
  .product-img-placeholder {
    height: 120px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .status-badge {
    position: absolute;
    top: 8px;
    right: 8px;
  }

  .stock-display {
    font-size: 28px;
    font-weight: 900;
    line-height: 1;
    margin-bottom: 4px;
  }
  .stock-unit {
    font-size: 13px;
    font-weight: 500;
    color: #94a3b8;
    margin-left: 4px;
  }
</style>
