<template>
  <v-container fluid class="pa-0">
    <div class="d-flex justify-end align-center ga-2 mb-4">
      <v-btn
        :color="showFilters ? 'primary' : 'default'"
        :variant="showFilters ? 'flat' : 'tonal'"
        rounded="lg"
        :prepend-icon="
          showFilters ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
        "
        @click="showFilters = !showFilters"
      >
        {{ $t('btn.filter') }}
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
        prepend-icon="mdi-plus"
        @click="openAdd"
      >
        {{ $t('stock.adjust.add_stock') }}
      </v-btn>
    </div>

    <!-- ── Low Stock Alert ────────────────────────────────────────────── -->
    <v-alert
      v-if="store.lowStockItems.length"
      type="warning"
      variant="tonal"
      rounded="xl"
      density="compact"
      class="mb-4"
      :text="$t('stock.management.low_stock_alert', { n: store.lowStockItems.length })"
      prepend-icon="mdi-alert-outline"
    />

    <!-- ── Stats ──────────────────────────────────────────────────────── -->
    <v-row dense class="mb-4">
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
          <div class="text-h5 font-weight-black text-primary">
            {{ store.stocks.total ?? 0 }}
          </div>
          <div class="text-caption text-grey mt-1">{{ $t('stock.management.stats.total_items') }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
          <div class="text-h5 font-weight-black text-warning">
            {{ store.lowStockItems.length }}
          </div>
          <div class="text-caption text-grey mt-1">{{ $t('inventory.low_stock') }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
          <div class="text-h5 font-weight-black text-success">
            {{
              store.stocks.data?.filter(s => parseFloat(s.quantity_on_hand) > 0)
                .length ?? 0
            }}
          </div>
          <div class="text-caption text-grey mt-1">{{ $t('inventory.in_stock') }}</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
          <div class="text-h5 font-weight-black text-error">
            {{
              store.stocks.data?.filter(
                s => parseFloat(s.quantity_on_hand) <= 0
              ).length ?? 0
            }}
          </div>
          <div class="text-caption text-grey mt-1">{{ $t('inventory.out_of_stock') }}</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Filter ─────────────────────────────────────────────────────── -->
    <v-expand-transition>
      <v-card v-if="showFilters" rounded="xl" elevation="0" class="mb-4">
        <v-card-text>
          <v-row dense align="center">
            <v-col cols="12" sm="3">
              <v-text-field
                v-model="draft.keyword"
                :label="$t('stock.management.search_ingredient')"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
                @keyup.enter="onFilterChange"
              />
            </v-col>
            <v-col cols="12" sm="3">
              <v-select
                v-model="draft.branch_id"
                :items="branchStore.branches?.data ?? []"
                item-value="id"
                item-title="name"
                :label="$t('form.branch')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
              />
            </v-col>
            <v-col cols="12" sm="3">
              <v-select
                v-model="draft.category"
                :items="categoryOptions"
                :label="$t('form.category')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
              />
            </v-col>
            <v-col cols="12" sm="3">
              <v-select
                v-model="draft.stock_status"
                :items="stockStatusOptions"
                :label="$t('products.filter.stock')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
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
            {{ $t('btn.reset') }}
          </v-btn>
          <v-btn
            class="bg-primary"
            rounded="lg"
            prepend-icon="mdi-magnify"
            @click="onFilterChange"
          >
            {{ $t('btn.search') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-expand-transition>

    <!-- ── Table ──────────────────────────────────────────────────────── -->
    <v-card rounded="xl" elevation="0" border>
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchTableData"
        :filters="appliedFilters"
        :show-search="false"
        item-label="stock"
      >
        <!-- Ingredient -->
        <template #item.ingredient="{ item }">
          <div>
            <div class="text-body-2 font-weight-medium">
              {{ item.ingredient?.name ?? '—' }}
            </div>
            <div class="text-caption text-grey">
              {{ item.ingredient?.category ?? '' }}
            </div>
          </div>
        </template>

        <!-- Branch -->
        <template #item.branch="{ item }">
          <span class="text-body-2">{{ item.branch?.name ?? '—' }}</span>
        </template>

        <!-- Quantity on hand -->
        <template #item.quantity_on_hand="{ item }">
          <div class="d-flex align-center gap-2">
            <span
              class="text-body-2 font-weight-bold"
              :class="stockColor(item)"
            >
              {{ item.quantity_on_hand }}
            </span>
            <span class="text-caption text-grey">
              {{ item.ingredient?.unit }}
            </span>
            <!-- Low stock badge -->
            <v-icon
              v-if="isLowStock(item)"
              icon="mdi-alert-circle"
              color="warning"
              size="16"
            />
          </div>
        </template>

        <!-- Reserved -->
        <template #item.quantity_reserved="{ item }">
          <span class="text-body-2">
            {{ item.quantity_reserved }}
            <span class="text-caption text-grey">
              {{ item.ingredient?.unit }}
            </span>
          </span>
        </template>

        <!-- Reorder point -->
        <template #item.reorder_point="{ item }">
          <span class="text-caption text-grey">
            {{ item.ingredient?.reorder_point ?? '—' }}
          </span>
        </template>

        <!-- Last counted -->
        <template #item.last_counted_at="{ item }">
          <span class="text-caption text-grey">
            {{
              item.last_counted_at ? formatDate(item.last_counted_at) : $t('stock.management.never')
            }}
          </span>
        </template>

        <!-- Status chip -->
        <template #item.status="{ item }">
          <v-chip
            :color="stockStatusChipColor(item)"
            size="x-small"
            variant="tonal"
            label
          >
            {{ stockStatusLabel(item) }}
          </v-chip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-pencil-outline"
            size="small"
            variant="text"
            color="primary"
            class="mr-1"
            @click="openEdit(item)"
          />
          <v-btn
            icon="mdi-delete-outline"
            size="small"
            variant="text"
            color="error"
            @click="handleDelete(item.id)"
          />
        </template>
      </AppTable>
    </v-card>

    <!-- ── Dialog ─────────────────────────────────────────────────────── -->
    <InventoryStockDialog
      v-model="dialog"
      :stock="selected"
      :loading="saving"
      @save="handleSave"
    />
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useInventoryStockStore } from '@/stores/inventoryStockStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { AppTable } from '@nong-official-dev/core'
  import InventoryStockDialog from '@/components/inventory/InventoryStockDialog.vue'
  import { useDate } from '@/composables/useDate'

  const { t } = useI18n()
  const store = useInventoryStockStore()
  const branchStore = useBranchStore()
  const { confirm, notif } = useAppUtils()
  const { formatShortDate: formatDate } = useDate()

  // ── Table ──────────────────────────────────────────────────────────────────────
  const tableRef = ref(null)

  const headers = [
    { title: t('stock.management.table.ingredient'), key: 'ingredient', sortable: false },
    { title: t('form.branch'), key: 'branch', sortable: false },
    { title: t('stock.management.table.on_hand'), key: 'quantity_on_hand' },
    { title: t('stock.management.table.reserved'), key: 'quantity_reserved' },
    { title: t('stock.management.table.reorder_point'), key: 'reorder_point', sortable: false },
    { title: t('stock.management.table.last_counted'), key: 'last_counted_at' },
    { title: t('form.status'), key: 'status', sortable: false },
    { title: t('table.actions'), key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Filter ─────────────────────────────────────────────────────────────────────
  const showFilters = ref(false)
  const draft = reactive({
    keyword: '',
    branch_id: null,
    category: null,
    stock_status: null
  })
  const applied = reactive({
    keyword: '',
    branch_id: null,
    category: null,
    stock_status: null
  })

  // ── Active filter badge ───────────────────────────────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (draft.keyword.trim() !== '') count++
    if (draft.branch_id) count++
    if (draft.category) count++
    if (draft.stock_status) count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  const categoryOptions = [
    'dairy',
    'produce',
    'packaging',
    'meat',
    'seafood',
    'dry goods',
    'beverages',
    'other'
  ]
  const stockStatusOptions = [
    { title: t('inventory.in_stock'), value: 'in_stock' },
    { title: t('inventory.low_stock'), value: 'low_stock' },
    { title: t('inventory.out_of_stock'), value: 'out_of_stock' }
  ]

  // ── Filters passed straight through to fetchTableData — AppTable deep-watches
  // this and refetches (resetting to page 1) whenever it changes. `keyword`,
  // `category` and `stock_status` are pre-existing dead filters — the backend's
  // InventoryStockRepository only ever reads `branch_id`; carried over unchanged,
  // not fixed here. ──────────────────────────────────────────────────────────
  const appliedFilters = computed(() => ({
    keyword: applied.keyword || undefined,
    branch_id: applied.branch_id || undefined,
    category: applied.category || undefined,
    stock_status: applied.stock_status || undefined
  }))

  // ── Apply/reset just update `applied` — no manual refetch needed. ────────────
  const onFilterChange = () => {
    Object.assign(applied, { ...draft })
  }

  const resetFilters = () => {
    Object.assign(draft, {
      keyword: '',
      branch_id: null,
      category: null,
      stock_status: null
    })
    Object.assign(applied, {
      keyword: '',
      branch_id: null,
      category: null,
      stock_status: null
    })
  }

  // ── Fetch ──────────────────────────────────────────────────────────────────────
  async function fetchTableData(params) {
    await store.fetchStocks(params)
    return { items: store.stocks.data ?? [], total: store.stocks.total ?? 0 }
  }

  // ── Helpers ────────────────────────────────────────────────────────────────────
  const isLowStock = item =>
    item.ingredient?.reorder_point !== null &&
    parseFloat(item.quantity_on_hand) <=
      parseFloat(item.ingredient?.reorder_point ?? 0) &&
    parseFloat(item.quantity_on_hand) > 0

  const stockColor = item => {
    const qty = parseFloat(item.quantity_on_hand)
    if (qty <= 0) return 'text-error'
    if (isLowStock(item)) return 'text-warning'
    return 'text-success'
  }

  const stockStatusLabel = item => {
    const qty = parseFloat(item.quantity_on_hand)
    if (qty <= 0) return t('stock_overview.table.out_stock')
    if (isLowStock(item)) return t('stock_overview.table.low_stock')
    return t('stock_overview.table.in_stock')
  }

  const stockStatusChipColor = item => {
    const qty = parseFloat(item.quantity_on_hand)
    if (qty <= 0) return 'error'
    if (isLowStock(item)) return 'warning'
    return 'success'
  }


  // ── CRUD ───────────────────────────────────────────────────────────────────────
  const dialog = ref(false)
  const selected = ref(null)
  const saving = ref(false)

  const openAdd = () => {
    selected.value = null
    dialog.value = true
  }
  const openEdit = item => {
    selected.value = { ...item }
    dialog.value = true
  }

  const handleSave = async data => {
    saving.value = true
    try {
      if (data.id) {
        await store.updateStock(data.id, data)
        notif(t('stock.management.messages.updated'), { type: 'success' })
      } else {
        await store.addStock(data)
        notif(t('stock.management.messages.added'), { type: 'success' })
      }
      dialog.value = false
      tableRef.value?.refresh()
    } catch {
      notif(t('messages.error_occurred'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const handleDelete = id => {
    confirm({
      title: t('stock.management.confirm_delete.title'),
      message: t('stock.management.confirm_delete.message'),
      options: { type: 'error' },
      agree: async () => {
        await store.removeStock(id)
        notif(t('messages.deleted_success'), { type: 'success' })
        tableRef.value?.refresh()
      }
    })
  }

  onMounted(() => {
    branchStore.fetchBranches()
  })
</script>

<style scoped>
  .gap-2 {
    gap: 8px;
  }
</style>
