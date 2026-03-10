<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-warehouse"
      title="Inventory Stock"
      subtitle="Track stock levels across all branches"
    >
      <template #right>
        <div class="d-flex gap-2">
          <v-btn
            variant="tonal"
            rounded="lg"
            :prepend-icon="
              showFilter ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
            "
            @click="showFilter = !showFilter"
          >
            Filter
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            prepend-icon="mdi-plus"
            @click="openAdd"
          >
            Add Stock
          </v-btn>
        </div>
      </template>
    </custom-title>

    <!-- ── Low Stock Alert ────────────────────────────────────────────── -->
    <v-alert
      v-if="store.lowStockItems.length"
      type="warning"
      variant="tonal"
      rounded="xl"
      density="compact"
      class="mb-4"
      :text="`${store.lowStockItems.length} item(s) are at or below reorder point`"
      prepend-icon="mdi-alert-outline"
    />

    <!-- ── Stats ──────────────────────────────────────────────────────── -->
    <v-row dense class="mb-4">
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
          <div class="text-h5 font-weight-black text-primary">
            {{ store.stocks.total ?? 0 }}
          </div>
          <div class="text-caption text-grey mt-1">Total Items</div>
        </v-card>
      </v-col>
      <v-col cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border class="pa-4 text-center">
          <div class="text-h5 font-weight-black text-warning">
            {{ store.lowStockItems.length }}
          </div>
          <div class="text-caption text-grey mt-1">Low Stock</div>
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
          <div class="text-caption text-grey mt-1">In Stock</div>
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
          <div class="text-caption text-grey mt-1">Out of Stock</div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Filter ─────────────────────────────────────────────────────── -->
    <v-expand-transition>
      <v-card
        v-show="showFilter"
        rounded="xl"
        elevation="0"
        border
        class="mb-4 pa-4"
      >
        <v-row dense align="center">
          <v-col cols="12" sm="3">
            <v-text-field
              v-model="draft.keyword"
              label="Search ingredient..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="12" sm="3">
            <v-select
              v-model="draft.branch_id"
              :items="branchStore.branches?.data ?? []"
              item-value="id"
              item-title="name"
              label="Branch"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="12" sm="2">
            <v-select
              v-model="draft.category"
              :items="categoryOptions"
              label="Category"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="12" sm="2">
            <v-select
              v-model="draft.stock_status"
              :items="stockStatusOptions"
              label="Stock Status"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="12" sm="2" class="d-flex gap-2">
            <v-btn
              variant="tonal"
              rounded="lg"
              :disabled="!filterActive"
              @click="resetFilter"
            >
              Reset
            </v-btn>
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              @click="applyFilter"
            >
              Apply
            </v-btn>
          </v-col>
        </v-row>
      </v-card>
    </v-expand-transition>

    <!-- ── Table ──────────────────────────────────────────────────────── -->
    <v-card rounded="xl" elevation="0" border>
      <v-data-table-server
        :headers="headers"
        :items="store.stocks.data ?? []"
        :items-length="store.stocks.total ?? 0"
        :loading="store.loading"
        v-model:items-per-page="opts.itemsPerPage"
        @update:options="loadItems"
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
              item.last_counted_at ? formatDate(item.last_counted_at) : 'Never'
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
      </v-data-table-server>
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
  import { useInventoryStockStore } from '@/stores/inventoryStockStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import InventoryStockDialog from '@/components/inventory/InventoryStockDialog.vue'

  const store = useInventoryStockStore()
  const branchStore = useBranchStore()
  const { confirm, notif } = useAppUtils()

  // ── Table ──────────────────────────────────────────────────────────────────────
  const opts = reactive({ page: 1, itemsPerPage: 15 })

  const headers = [
    { title: 'Ingredient', key: 'ingredient', sortable: false },
    { title: 'Branch', key: 'branch', sortable: false },
    { title: 'On Hand', key: 'quantity_on_hand' },
    { title: 'Reserved', key: 'quantity_reserved' },
    { title: 'Reorder Point', key: 'reorder_point', sortable: false },
    { title: 'Last Counted', key: 'last_counted_at' },
    { title: 'Status', key: 'status', sortable: false },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Filter ─────────────────────────────────────────────────────────────────────
  const showFilter = ref(false)
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
  const filterActive = computed(
    () =>
      draft.keyword.trim() !== '' ||
      draft.branch_id ||
      draft.category ||
      draft.stock_status
  )

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
    { title: 'In Stock', value: 'in_stock' },
    { title: 'Low Stock', value: 'low_stock' },
    { title: 'Out of Stock', value: 'out_of_stock' }
  ]

  const applyFilter = () => {
    Object.assign(applied, { ...draft })
    opts.page = 1
    fetchData()
  }

  const resetFilter = () => {
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
    opts.page = 1
    fetchData()
  }

  // ── Fetch ──────────────────────────────────────────────────────────────────────
  const fetchData = () =>
    store.fetchStocks({
      page: opts.page,
      per_page: opts.itemsPerPage,
      keyword: applied.keyword || undefined,
      branch_id: applied.branch_id || undefined,
      category: applied.category || undefined,
      stock_status: applied.stock_status || undefined
    })

  const loadItems = ({ page, itemsPerPage }) => {
    opts.page = page
    opts.itemsPerPage = itemsPerPage
    fetchData()
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
    if (qty <= 0) return 'Out of Stock'
    if (isLowStock(item)) return 'Low Stock'
    return 'In Stock'
  }

  const stockStatusChipColor = item => {
    const qty = parseFloat(item.quantity_on_hand)
    if (qty <= 0) return 'error'
    if (isLowStock(item)) return 'warning'
    return 'success'
  }

  const formatDate = dt =>
    new Date(dt).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    })

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
        notif('Stock updated', { type: 'success' })
      } else {
        await store.addStock(data)
        notif('Stock added', { type: 'success' })
      }
      dialog.value = false
      fetchData()
    } catch {
      notif('Something went wrong', { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const handleDelete = id => {
    confirm({
      title: 'Delete Stock Record',
      message: 'This will remove this stock entry permanently.',
      options: { type: 'error' },
      agree: async () => {
        await store.removeStock(id)
        notif('Deleted', { type: 'success' })
        fetchData()
      }
    })
  }

  onMounted(() => {
    branchStore.fetchBranches()
    fetchData()
  })
</script>

<style scoped>
  .gap-2 {
    gap: 8px;
  }
</style>
