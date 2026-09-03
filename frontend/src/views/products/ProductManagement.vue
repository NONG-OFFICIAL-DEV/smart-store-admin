<template>
  <v-container fluid class="pa-0">
    <div class="d-flex justify-end align-center ga-2 mb-4">
      <v-btn
        :color="showStats ? 'primary' : 'default'"
        :variant="showStats ? 'flat' : 'tonal'"
        rounded="lg"
        prepend-icon="mdi-chart-bar"
        @click="showStats = !showStats"
      >
        {{ t('btn.stats') }}
      </v-btn>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        {{ t('btn.add_product') }}
      </v-btn>
    </div>

    <!-- Stats panel -->
    <v-expand-transition>
      <v-row v-if="showStats" dense class="mb-4">
        <v-col v-for="stat in stats" :key="stat.label" cols="6" sm="3">
          <v-card rounded="xl" elevation="0" border>
            <v-card-text class="pa-4">
              <div class="d-flex align-center justify-space-between">
                <div>
                  <p class="text-caption text-medium-emphasis">
                    {{ stat.label }}
                  </p>
                  <p class="text-h6 font-weight-bold mt-1">{{ stat.value }}</p>
                </div>
                <v-avatar :color="stat.color" size="40" rounded="lg">
                  <v-icon :icon="stat.icon" size="20" />
                </v-avatar>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-expand-transition>

    <!-- ── Filters ────────────────────────────────────────────────────────── -->
    <v-row dense align="center" class="mb-2">
      <v-col cols="12" sm="3">
        <custom-select
          v-model="filterCategories"
          :items="categories"
          item-title="name"
          item-value="id"
          :label="t('categories.title')"
          :multiple="true"
          :chips="true"
          :max-visible-chips="2"
        />
      </v-col>
      <v-col cols="12" sm="3">
        <v-select
          v-model="filterAvailable"
          :items="availabilityOptions"
          :label="t('products.filter.availability')"
          variant="outlined"
          clearable
          rounded="lg"
          hide-details
        />
      </v-col>
    </v-row>

    <!-- ── Table View ─────────────────────────────────────────────────────── -->
    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchProductsForTable"
        :filters="filters"
        :show-search="true"
        :item-label="t('products.title')"
      >
        <!-- Image + Name -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <v-avatar
              size="35"
              rounded="lg"
              color="grey-lighten-2"
              class="me-2"
            >
              <v-img v-if="item.image_url" :src="item.image_url" cover />
              <v-icon v-else icon="mdi-food" size="20" color="grey" />
            </v-avatar>
            <div>
              <div class="font-weight-medium">{{ item.name }}</div>
            </div>
          </div>
        </template>

        <!-- Price -->
        <template #item.base_price="{ item }">
          <span class="text-body-2 font-weight-medium">{{ formatMoney(item.base_price) }}</span>
        </template>

        <!-- Stock -->
        <template #item.stock_quantity="{ item }">
          <span v-if="item.track_stock" class="text-body-2">{{ item.stock_quantity }}</span>
          <span v-else class="text-body-2 text-medium-emphasis">—</span>
        </template>

        <!-- Stock status -->
        <template #item.stock_status="{ item }">
          <AppStatusChip
            v-if="stockStatus(item)"
            :status="stockStatus(item)"
            :map="stockStatusMap"
            size="small"
          />
          <span v-else class="text-body-2 text-medium-emphasis">—</span>
        </template>

        <!-- Available -->
        <template #item.is_available="{ item }">
          <v-switch
            v-model="item.is_available"
            color="success"
            hide-details
            @change="toggleAvailability(item)"
          />
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <v-btn
              icon="mdi-arrow-top-right"
              size="small"
              variant="text"
              color="primary"
              :to="`/product-details/${item.id}`"
            />
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              color="primary"
              @click="openEdit(item)"
            />
            <v-btn
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="doDelete(item)"
            />
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <v-icon
              icon="mdi-package-variant-closed"
              size="48"
              color="grey-lighten-1"
            />
            <p class="text-medium-emphasis mt-3">{{$t('products.empty')}}</p>
            <v-btn
              color="primary"
              variant="tonal"
              class="mt-3"
              @click="openCreate"
            >
               {{ t('btn.add_product') }}
            </v-btn>
          </div>
        </template>
      </AppTable>
    </v-card>
  </v-container>
</template>
<script setup>
  import { ref, computed, onMounted } from 'vue'
  import CustomSelect from '@/components/customs/CustomSelect.vue'
  import { storeToRefs } from 'pinia'
  import { useProductStore } from '@/stores/productStore'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { AppTable, AppStatusChip, useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'

  const router = useRouter()
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()

  // ── Stores ────────────────────────────────────────────────────────────────────
  const productStore = useProductStore()
  const categoryStore = useCategoryStore()
  const { products, pagination } = storeToRefs(productStore)
  const { categories } = storeToRefs(categoryStore)

  const tableRef = ref(null)
  const showStats = ref(false)

  // ── Filters — deep-watched by AppTable, auto-refetches on change (free-text
  // search is AppTable's own built-in field). ───────────────────────────────────
  const filterCategories = ref([])
  const filterAvailable = ref(null)

  const filters = computed(() => ({
    categories: filterCategories.value?.length ? filterCategories.value : undefined,
    is_available:
      filterAvailable.value !== null ? filterAvailable.value : undefined
  }))

  // ── AppTable's fetch-fn contract: params -> { items, total } ─────────────────
  const fetchProductsForTable = params => productStore.fetchProducts(params)

  // ── Bootstrap ─────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await categoryStore.fetchCategories({ perPage: 1000 })
  })

  // ── Static options ────────────────────────────────────────────────────────────
  const availabilityOptions = [
    { title: 'Available', value: true },
    { title: 'Unavailable', value: false }
  ]

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('products.table.name'), key: 'name', sortable: true },
    {
      title: t('products.table.category'),
      key: 'category.name',
      sortable: false
    },
    // `base_price` is the one extra column in ProductRepository::SORTABLE
    // besides name/created_at/is_available — safe to mark sortable.
    { title: t('products.table.price'), key: 'base_price', sortable: true },
    { title: t('products.table.stock'), key: 'stock_quantity', sortable: false },
    { title: t('products.table.status'), key: 'stock_status', sortable: false },
    {
      title: t('products.table.available'),
      key: 'is_available',
      sortable: false
    },
    {
      title: t('products.table.actions'),
      key: 'actions',
      sortable: false,
      align: 'start'
    }
  ])

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const stats = computed(() => [
    {
      label: t('products.stats.total'),
      value: pagination.value.total || 0,
      icon: 'mdi-package-variant',
      color: 'primary'
    },
    {
      label: t('products.stats.available'),
      value: products.value.filter(p => p.is_available).length,
      icon: 'mdi-check-circle',
      color: 'success'
    },
    {
      label: t('products.stats.featured'),
      value: products.value.filter(p => p.is_featured).length,
      icon: 'mdi-star',
      color: 'warning'
    },
    {
      label: t('products.stats.unavailable'),
      value: products.value.filter(p => !p.is_available).length,
      icon: 'mdi-close-circle',
      color: 'error'
    }
  ])

  // ── Navigation ────────────────────────────────────────────────────────────────
  const openCreate = () => router.push('/products/create')
  const openEdit = item => router.push(`/products/${item.id}/edit`)

  // ── Toggle availability ───────────────────────────────────────────────────────
  const toggleAvailability = async item => {
    try {
      await productStore.updateProduct(item.id, {
        is_available: item.is_available
      })
      notif(`${item.name} ${item.is_available ? 'enabled' : 'disabled'}`, {
        type: 'success'
      })
    } catch {
      item.is_available = !item.is_available // revert optimistic update
      notif(`Failed to update ${item.name}`, { type: 'error' })
    }
  }

  // ── Price/stock display ──────────────────────────────────────────────────────
  const formatMoney = value =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value ?? 0)

  // Untracked items (e.g. made-to-order food) have no meaningful stock state.
  const stockStatus = item => {
    if (!item.track_stock) return null
    if (item.stock_quantity <= 0) return 'out_of_stock'
    if (item.reorder_level && item.stock_quantity <= item.reorder_level) return 'low_stock'
    return 'in_stock'
  }
  const stockStatusMap = {
    out_of_stock: { color: 'error', label: t('products.stock_status.out_of_stock') },
    low_stock: { color: 'warning', label: t('products.stock_status.low_stock') },
    in_stock: { color: 'success', label: t('products.stock_status.in_stock') }
  }

  // ── Delete ────────────────────────────────────────────────────────────────────
  const doDelete = async product => {
    confirm({
      title: 'Delete Product?',
      message: `Are you sure you want to delete "${product.name}"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await productStore.deleteProduct(product.id)
        notif(t('messages.deleted_success'), { type: 'success' })
        tableRef.value?.refresh()
      }
    })
  }
</script>
