<template>
  <custom-title icon="mdi-warehouse">
    Current Stock Levels
    <template #right>
      <BaseButtonFilter @click="toggleFilterForm" />
    </template>
  </custom-title>
  <v-card class="mb-4 pa-4 rounded-lg" elevation="0" v-show="showFilterForm">
    <v-row>
      <!-- Product Name / SKU -->
      <v-col cols="12" md="3">
        <v-text-field
          v-model="filters.keyword"
          label="Search (Product / SKU)"
          prepend-inner-icon="mdi-magnify"
          hide-details
        />
      </v-col>

      <!-- Category -->
      <v-col cols="12" md="3">
        <v-select
          v-model="filters.category_id"
          :items="categoryStore.categories.data"
          item-title="name"
          item-value="id"
          label="Category"
          multiple
          hide-details
        >
          <template v-slot:selection="{ item, index }">
            <v-chip v-if="index < 2" :text="item.title" size="x-small" />

            <span
              v-if="index === 2"
              class="text-grey text-caption align-self-center"
            >
              (+{{ filters.category_id.length - 2 }} others)
            </span>
          </template>
        </v-select>
      </v-col>

      <!-- Unit -->
      <v-col cols="12" md="3">
        <v-select
          v-model="filters.unit_id"
          :items="unitStore.units"
          item-title="name"
          item-value="id"
          label="Unit"
          hide-details
        />
      </v-col>

      <!-- Stock Level -->
      <v-col cols="12" md="3">
        <v-select
          v-model="filters.stock_level"
          :items="stockLevelOptions"
          label="Stock Level"
          hide-details
        />
      </v-col>

      <!-- Quantity Range -->
      <v-col cols="12" md="3">
        <v-text-field
          v-model="filters.min_qty"
          type="number"
          label="Min Quantity"
          hide-details
        />
      </v-col>

      <v-col cols="12" md="3">
        <v-text-field
          v-model="filters.max_qty"
          type="number"
          label="Max Quantity"
          hide-details
        />
      </v-col>

      <!-- Buttons -->
      <v-col cols="12" md="3" class="d-flex align-center">
        <v-btn class="me-3" variant="outlined" @click="resetFilter">
          Reset
        </v-btn>
        <v-btn
          color="primary"
          prepend-icon="mdi-filter-outline"
          @click="applyFilter"
        >
          Apply Filter
        </v-btn>
      </v-col>
    </v-row>
  </v-card>

  <v-data-table-server
    v-model:items-per-page="itemsPerPage"
    :items-length="stockStore.stocks.total || 0"
    @update:options="loadItems"
    :headers="headers"
    :items="stockStore.stocks.data"
    class="text-no-wrap elevation-0"
  >
    <template #item.product="{ item }">
      {{ item.product?.name }}
    </template>
    <template #item.updated_at="{ item }">
      {{ formatDate(item.product?.updated_at) }}
    </template>
    <template #item.stock_alert="{ item }">
      <v-chip :color="stockAlertChip(item).color" variant="flat" size="small">
        <v-icon :icon="stockAlertChip(item).icon" start></v-icon>
        {{ stockAlertChip(item).text }}
      </v-chip>
    </template>
    <template #item.no="{ index }">
      {{ index + 1 }}
    </template>
    <template #item.actions="{ item }">
      <v-row dense>
        <!-- Stock Adjustment (Admin, Storekeeper only) -->
        <v-tooltip location="top" v-if="canAdjustStock">
          <template #activator="{ props }">
            <v-btn
              v-bind="props"
              icon
              color="orange"
              variant="text"
              @click="openDialog('adjustment', item)"
            >
              <v-icon>mdi-tune</v-icon>
            </v-btn>
          </template>
          <span>Adjustment</span>
        </v-tooltip>

        <!-- Stock Movements (Admin, Storekeeper, Purchaser) -->
        <v-tooltip location="top" v-if="canViewMovements">
          <template #activator="{ props }">
            <v-btn
              v-bind="props"
              icon
              variant="text"
              @click="openMovementDialog(item)"
            >
              <v-icon>mdi-history</v-icon>
            </v-btn>
          </template>
          <span>Stock Movements</span>
        </v-tooltip>
      </v-row>
    </template>
  </v-data-table-server>

  <MovementDialog v-model="isDialogOpen" :stock="selectedStock" />

  <!-- Stock Action Dialog -->
  <StockActionDialog
    v-model="dialogVisible"
    :actionType="dialogType"
    :stock="selectedStock"
    @submitAction="handleAction"
  />
</template>

<script setup>
  import { ref, onMounted, computed } from 'vue'
  import { useStockStore } from '@/stores/stockStore'
  import MovementDialog from '@/components/MovementDialog.vue'
  import StockActionDialog from '@/components/stocks/StockActionDialog.vue'
  import { useStockMovementStore } from '@/stores/stockMovementStore'
  import { useDate } from '@/composables/useDate'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useUnitStore } from '@/stores/unitStore'
  import { useAuthStore } from '@/stores/auth'

  const authStore = useAuthStore()
  // import { useAppUtils } from '@/composables/useAppUtils'

  const { formatDate } = useDate()
  // const { confirm, notif } = useAppUtils()
  const stockMovementStore = useStockMovementStore()
  const stockStore = useStockStore()
  const categoryStore = useCategoryStore()
  const unitStore = useUnitStore()

  const headers = [
    { title: 'No', key: 'no' },
    { title: 'Product', key: 'product' },
    { title: 'SKU', key: 'product.sku' },
    { title: 'Category', key: 'product.category.name' },
    { title: 'Quantity', key: 'quantity' },
    { title: 'Unit', key: 'product.unit.abbreviation' },
    // { title: 'Stock Alert', key: 'product.low_stock_threshold' },
    { title: 'Status', key: 'stock_alert' },
    { title: '', key: 'actions' }
  ]
  const itemsPerPage = ref(10)
  const form = ref({
    quantity: 0,
    note: ''
  })
  const filters = ref({
    keyword: '',
    category_id: null,
    unit_id: null,
    stock_level: null,
    min_qty: null,
    max_qty: null
  })
  const stockLevelOptions = [
    { title: 'In Stock', value: 'in_stock' },
    { title: 'Low Stock', value: 'low_stock' },
    { title: 'Out of Stock', value: 'out_of_stock' }
  ]

  const isDialogOpen = ref(false)
  const selectedStock = ref(null)
  const dialogVisible = ref(false)
  const dialogType = ref('') // return | adjustment | loss
  const showFilterForm = ref(false)

  const canAdjustStock = computed(() => [1, 2].includes(authStore.me?.role_id))

  const canViewMovements = computed(() =>
    [1, 2, 3].includes(authStore.me?.role_id)
  )

  onMounted(() => {
    unitStore.fetchUnits()
    categoryStore.fetchCategories({
      per_page: -1
    })
    stockStore.fetchStocks(filters.value)
  })
  const applyFilter = () => {
    stockStore.fetchStocks({
      keyword: filters.value.keyword,
      category_id: filters.value.category_id?.join(',') ?? '',
      unit_id: filters.value.unit_id,
      stock_level: filters.value.stock_level,
      min_qty: filters.value.min_qty,
      max_qty: filters.value.max_qty
    })
  }
  const loadItems = ({ page, itemsPerPage }) => {
    stockStore.fetchStocks({
      page,
      per_page: itemsPerPage
    })
  }
  const resetFilter = () => {
    filters.value = {
      keyword: '',
      category_id: null,
      unit_id: null,
      stock_level: null,
      min_qty: null,
      max_qty: null
    }
    stockStore.fetchStocks()
  }
  const toggleFilterForm = () => {
    showFilterForm.value = !showFilterForm.value
  }
  function openDialog(type, stock) {
    dialogType.value = type
    selectedStock.value = stock
    form.value = { quantity: 0, note: '' }
    dialogVisible.value = true
  }
  const openMovementDialog = item => {
    selectedStock.value = item
    isDialogOpen.value = true
  }
  const stockAlertChip = item => {
    const threshold = item.product?.low_stock_threshold ?? 0
    const qty = item.quantity

    if (qty <= 0) {
      return { text: 'Out of Stock', color: 'red', icon: 'mdi-close-circle' }
    }

    if (qty <= threshold) {
      return { text: 'Low Stock', color: 'warning', icon: 'mdi-alert' }
    }

    return { text: 'In Stock', color: 'green', icon: 'mdi-check-circle' }
  }

  async function handleAction(payload) {
    const { stockId, ...data } = payload

    switch (dialogType.value) {
      case 'return':
        await stockMovementStore.returnStock({
          product_id: stockId,
          ...data
        })
        break
      case 'adjustment':
        await stockMovementStore.adjustStock({
          product_id: stockId,
          ...data
        })
        break
      case 'loss':
        await stockMovementStore.reportLoss({
          product_id: stockId,
          ...data
        })
        break
    }

    // Refresh stock
    await stockStore.fetchStocks()
  }
</script>
