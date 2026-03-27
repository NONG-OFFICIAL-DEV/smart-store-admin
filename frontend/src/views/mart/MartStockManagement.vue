<template>
  <div>
    <custom-title
      icon="mdi-package-variant"
      :title="t('stock_overview.title')"
      :subtitle="t('stock_overview.subtitle')"
    >
      <template #right>
        <div class="d-flex gap-2">
          <v-btn
            variant="tonal"
            rounded="lg"
            prepend-icon="mdi-refresh"
            :loading="martProductStore.loading"
            @click="refresh()"
          >
            {{ t('btn.refresh') }}
          </v-btn>
          <v-btn
            color="warning"
            variant="tonal"
            rounded="lg"
            prepend-icon="mdi-alert-circle-outline"
          >
            <!-- :to="{ name: 'MartLowStock' }" -->
            Low Stock
            <!-- {{ t('btn.low_stock') }} -->
            <v-badge
              v-if="martProductStore.lowStockCount > 0"
              :content="martProductStore.lowStockCount"
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

    <!-- Summary cards -->
    <v-row dense class="mb-5">
      <v-col v-for="s in summaryCards" :key="s.label" cols="6" sm="3">
        <v-card rounded="xl" border elevation="0" class="pa-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption text-medium-emphasis">{{ s.label }}</span>
            <v-avatar size="28" :color="s.color" variant="tonal" rounded="lg">
              <v-icon :icon="s.icon" size="14" />
            </v-avatar>
          </div>
          <div class="text-h5 font-weight-black" :class="`text-${s.color}`">
            {{ s.value }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">{{ s.sub }}</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filters -->
    <v-card rounded="lg" border elevation="0" class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="search"
              placeholder="Search product name or SKU..."
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              prepend-inner-icon="mdi-magnify"
            />
          </v-col>
          <v-col cols="6" sm="2">
            <v-select
              v-model="stockFilter"
              :items="stockFilterOptions"
              item-title="label"
              item-value="value"
              placeholder="Stock status"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="6" sm="2">
            <v-select
              v-model="sortBy"
              :items="sortOptions"
              item-title="label"
              item-value="value"
              placeholder="Sort by"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
            />
          </v-col>
          <!-- View toggle -->
          <v-col cols="12" sm="4" class="d-flex justify-end">
            <v-btn-toggle
              v-model="viewMode"
              mandatory
              rounded="lg"
              density="compact"
              variant="tonal"
              color="primary"
            >
              <v-btn value="table" icon="mdi-table" size="small" />
              <v-btn value="grid" icon="mdi-view-grid" size="small" />
            </v-btn-toggle>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- ── TABLE VIEW ──────────────────────────────────────────────────── -->
    <v-card v-if="viewMode === 'table'" rounded="lg" border elevation="0">
      <v-data-table-server
        :headers="headers"
        :items="martProductStore.products"
        :items-length="totalItems"
        no-data-text="No categories found"
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

        <!-- Reorder level -->
        <template #item.reorder_level="{ item }">
          <span class="text-body-2">
            {{ item.reorder_level ?? '—' }}
          </span>
        </template>

        <!-- Stock bar -->
        <template #item.stock_bar="{ item }">
          <div style="min-width: 100px">
            <v-progress-linear
              :model-value="stockPercent(item)"
              :color="stockBarColor(item)"
              height="6"
              rounded
              bg-color="grey-lighten-3"
            />
            <div class="text-caption text-medium-emphasis mt-1">
              {{ stockPercent(item) }}% of reorder
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
            <div class="text-body-2 text-grey">No products found</div>
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- ── GRID VIEW ───────────────────────────────────────────────────── -->
    <div v-else>
      <v-row v-if="filtered.length > 0" dense>
        <v-col
          v-for="product in filtered"
          :key="product.id"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <v-card
            rounded="xl"
            border
            elevation="0"
            :class="['product-card', stockCardClass(product)]"
          >
            <!-- Top: image + status -->
            <div class="product-img-wrap">
              <v-img
                v-if="product.image_url"
                :src="product.image_url"
                height="120"
                cover
              />
              <div v-else class="product-img-placeholder">
                <v-icon
                  icon="mdi-package-variant"
                  size="36"
                  color="grey-lighten-2"
                />
              </div>
              <v-chip
                size="x-small"
                rounded="lg"
                variant="flat"
                :color="statusColor(product)"
                class="status-badge"
              >
                {{ statusLabel(product) }}
              </v-chip>
            </div>

            <v-card-text class="pa-4">
              <!-- Name + SKU -->
              <div class="text-body-2 font-weight-bold text-truncate mb-0">
                {{ product.name }}
              </div>
              <div class="text-caption text-medium-emphasis mb-3">
                {{ product.sku ?? 'No SKU' }}
              </div>

              <!-- Big stock number -->
              <div class="stock-display" :class="stockClass(product)">
                <div v-for="s in stockByUnits(product)" :key="s.label">
                  {{ s.qty }}
                  <span class="stock-unit">{{ s.label }}</span>
                </div>
              </div>

              <!-- Progress toward reorder -->
              <div v-if="product.reorder_level" class="mt-2 mb-3">
                <div class="d-flex justify-space-between mb-1">
                  <span class="text-caption text-medium-emphasis">
                    Reorder at {{ product.reorder_level }}
                  </span>
                  <span
                    class="text-caption font-weight-bold"
                    :class="`text-${stockBarColor(product)}`"
                  >
                    {{ stockPercent(product) }}%
                  </span>
                </div>
                <v-progress-linear
                  :model-value="stockPercent(product)"
                  :color="stockBarColor(product)"
                  height="5"
                  rounded
                  bg-color="grey-lighten-3"
                />
              </div>

              <!-- Units -->
              <div class="d-flex gap-1 flex-wrap mb-3">
                <v-chip
                  v-for="u in product.active_units"
                  :key="u.id"
                  size="x-small"
                  rounded="lg"
                  variant="tonal"
                  :color="u.is_base_unit ? 'primary' : 'default'"
                >
                  {{ u.unit_label }}
                </v-chip>
              </div>

              <!-- Action row -->
              <div class="d-flex gap-2">
                <v-btn
                  size="small"
                  variant="tonal"
                  rounded="lg"
                  prepend-icon="mdi-tune"
                  class="flex-grow-1"
                  @click="openAdjust(product)"
                >
                  Adjust
                </v-btn>
                <v-btn
                  size="small"
                  variant="tonal"
                  rounded="lg"
                  icon="mdi-history"
                />
                <!-- @click="router.push({ name: 'MartStockMovements', query: { product_id: product.id } })" -->
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <div v-else class="text-center py-16">
        <v-icon
          icon="mdi-package-variant-remove"
          size="56"
          color="grey-lighten-1"
          class="mb-3"
        />
        <div class="text-body-1 font-weight-medium text-grey">
          No products found
        </div>
      </div>
    </div>
    <!-- Adjust dialog -->
    <StockAdjustDialog
      v-model="adjustDialog"
      :preset-product="adjustTarget"
      :products="productsList"
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
  const { t } = useI18n()
  import { useDataTable } from '@/composables/useServerTable' // ✅ your composable

  const router = useRouter()
  const martProductStore = useMartProductStore()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()

  const { notif } = useAppUtils()

  const search = ref('')
  const stockFilter = ref(null)
  const sortBy = ref('name')
  const viewMode = ref('table')
  const adjustDialog = ref(false)
  const adjustTarget = ref(null)
  const productsList = ref([])
  const adjusting = ref(false)

  // ── Filter options ────────────────────────────────────────────────────────
  const stockFilterOptions = [
    { value: 'out', label: 'Out of stock' },
    { value: 'low', label: 'Low stock' },
    { value: 'ok', label: 'In stock' }
  ]
  const sortOptions = [
    { value: 'name', label: 'Name A–Z' },
    { value: 'stock_asc', label: 'Stock: Low first' },
    { value: 'stock_desc', label: 'Stock: High first' }
  ]

  // ── Filtered + sorted list ────────────────────────────────────────────────
  const totalItems = computed(() => martProductStore.pagination?.total ?? 0)

  const branchList = computed(() => {
    const b = branchStore.branches
    return Array.isArray(b) ? b : (b?.data ?? [])
  })
  const filtered = computed(() => {
    let list = [...martProductStore.products]

    // text search
    if (search.value) {
      const q = search.value.toLowerCase()
      list = list.filter(
        p =>
          p.name.toLowerCase().includes(q) ||
          (p.sku ?? '').toLowerCase().includes(q)
      )
    }

    // stock filter
    if (stockFilter.value === 'out')
      list = list.filter(p => p.stock_quantity <= 0)
    else if (stockFilter.value === 'low')
      list = list.filter(
        p =>
          p.reorder_level != null &&
          p.stock_quantity > 0 &&
          p.stock_quantity <= p.reorder_level
      )
    else if (stockFilter.value === 'ok')
      list = list.filter(
        p => p.reorder_level == null || p.stock_quantity > p.reorder_level
      )

    // sort
    if (sortBy.value === 'name')
      list.sort((a, b) => a.name.localeCompare(b.name))
    else if (sortBy.value === 'stock_asc')
      list.sort((a, b) => a.stock_quantity - b.stock_quantity)
    else if (sortBy.value === 'stock_desc')
      list.sort((a, b) => b.stock_quantity - a.stock_quantity)

    return list
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
      title: t('stock_overview.table.reorder'),
      key: 'reorder_level',
      sortable: true
    },
    {
      title: t('stock_overview.table.stock_bar'),
      key: 'stock_bar',
      sortable: false
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
  const stockPercent = p => {
    if (!p.reorder_level) return 100
    return Math.min(100, Math.round((p.stock_quantity / p.reorder_level) * 100))
  }

  const stockBarColor = p => {
    if (p.stock_quantity <= 0) return 'error'
    if (p.reorder_level && p.stock_quantity <= p.reorder_level) return 'warning'
    return 'success'
  }

  const stockClass = p => {
    if (p.stock_quantity <= 0) return 'text-error'
    if (p.reorder_level && p.stock_quantity <= p.reorder_level)
      return 'text-warning'
    return 'text-success'
  }

  const stockCardClass = p => {
    if (p.stock_quantity <= 0) return 'card-out'
    if (p.reorder_level && p.stock_quantity <= p.reorder_level)
      return 'card-low'
    return ''
  }

  const statusLabel = p => {
    if (p.stock_quantity <= 0) return 'Out of Stock'
    if (p.reorder_level && p.stock_quantity <= p.reorder_level)
      return 'Low Stock'
    return 'In Stock'
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
  // ── useDataTable ───────────────────────────────────────────────────────────
  const { fetchOnOptions, refresh } = useDataTable(
    martProductStore.fetchProducts,
    () => ({
      search: search.value,
      stock_filter: stockFilter.value,
      sort_by: sortBy.value
    })
  )
  // ── Adjust ────────────────────────────────────────────────────────────────
  const openAdjust = product => {
    adjustTarget.value = product
    productsList.value = martProductStore.products
    adjustDialog.value = true
  }

  const handleAdjust = async payload => {
    adjusting.value = true
    try {
      const res = await adjustStockApi({
        branch_id: authStore.branch_id,
        ...payload
      })
      // Update store optimistically
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

  const stockByUnits = product => {
    const units = [...(product.active_units ?? [])].sort(
      (a, b) => b.qty_per_base - a.qty_per_base
    ) // largest unit first

    let remaining = product.stock_quantity
    const result = []

    for (const unit of units) {
      if (unit.qty_per_base <= 1) {
        // base unit — just show remainder
        result.push({ label: unit.unit_label, qty: remaining })
      } else {
        const qty = Math.floor(remaining / unit.qty_per_base)
        remaining = remaining % unit.qty_per_base
        if (qty > 0) result.push({ label: unit.unit_label, qty })
      }
    }

    // if no units defined, fall back
    if (!result.length)
      result.push({ label: product.unit ?? 'pcs', qty: product.stock_quantity })

    return result
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

  /* Product card */
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

  /* Image wrapper */
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

  /* Big stock number in grid */
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
