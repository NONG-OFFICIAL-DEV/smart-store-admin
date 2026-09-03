<template>
  <v-container fluid class="pa-0">
    <AppToolbar :title="t('stock.management.title')" :subtitle="t('stock.management.subtitle')">
      <template #actions>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openAdd"
        >
          {{ $t('stock.adjust.add_stock') }}
        </v-btn>
      </template>
    </AppToolbar>

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

    <!-- ── Filters — always visible ──────────────────────────────────── -->
    <v-card rounded="lg" border elevation="0" class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="4" v-if="branchStore.branches?.length > 1">
            <v-select
              v-model="filters.branch_id"
              :items="branchStore.branches ?? []"
              item-value="id"
              item-title="name"
              :label="$t('form.branch')"
              variant="outlined"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-select
              v-model="filters.category"
              :items="categoryOptions"
              :label="$t('form.category')"
              variant="outlined"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-select
              v-model="filters.stock_status"
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
    </v-card>

    <!-- ── Table ──────────────────────────────────────────────────────── -->
    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchTableData"
        :filters="appliedFilters"
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
  import AppToolbar from '@/components/common/AppToolbar.vue'
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
  const filters = reactive({
    branch_id: null,
    category: null,
    stock_status: null
  })

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
  // this and refetches (resetting to page 1) whenever it changes. `search`
  // (AppTable's own built-in search box) and `category`/`stock_status` are
  // pre-existing dead filters — the backend's InventoryStockRepository only
  // ever reads `branch_id`; carried over unchanged, not fixed here. `search`
  // itself is deliberately absent from this object — an explicit (even
  // undefined) `search` key here would clobber AppTable's built-in one, since
  // it spreads `filters` after its own `search` key. ───────────────────────
  const appliedFilters = computed(() => ({
    branch_id: filters.branch_id || undefined,
    category: filters.category || undefined,
    stock_status: filters.stock_status || undefined
  }))

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
