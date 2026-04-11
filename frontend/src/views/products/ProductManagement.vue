<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-tag-outline"
      :title="t('products.title')"
      :subtitle="t('products.subtitle')"
    >
      <template #right>
        <div class="d-flex gap-2 align-center">
          <v-btn
            :color="showStats ? 'primary' : 'default'"
            :variant="showStats ? 'flat' : 'tonal'"
            rounded="lg"
            prepend-icon="mdi-chart-bar"
            @click="showStats = !showStats"
            class="me-4"
          >
            Show State
          </v-btn>
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
            <!-- badge shows how many filters are active -->
            <v-badge
              v-if="activeFilterCount > 0"
              :content="activeFilterCount"
              color="error"
              floating
            />
          </v-btn>
          <v-btn
            class="ms-4"
            color="primary"
            variant="flat"
            rounded="lg"
            prepend-icon="mdi-plus"
            @click="openCreate"
          >
            {{ t('btn.add_product') }}
          </v-btn>
        </div>
      </template>
    </custom-title>

    <!-- Collapsible filter panel -->
    <v-expand-transition>
      <v-card v-if="showFilters" rounded="xl" elevation="0" border class="mb-4">
        <v-card-text class="pa-4">
          <v-row dense align="center">
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="search"
                :placeholder="t('products.filter.placeholder')"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="comfortable"
                hide-details
                clearable
                rounded="lg"
              />
            </v-col>
            <v-col cols="6" sm="3">
              <v-select
                v-model="filterType"
                :items="productTypeOptions"
                label="Type"
                variant="outlined"
                density="comfortable"
                hide-details
                clearable
                rounded="lg"
              />
            </v-col>
            <v-col cols="6" sm="3">
              <v-select
                v-model="filterAvailable"
                :items="availabilityOptions"
                label="Availability"
                variant="outlined"
                density="comfortable"
                hide-details
                clearable
                rounded="lg"
              />
            </v-col>
            <v-col cols="12" sm="2" class="d-flex justify-end">
              <v-btn-toggle
                v-model="viewMode"
                density="compact"
                rounded="lg"
                variant="outlined"
              >
                <v-btn value="table" icon="mdi-view-list" size="small" />
                <v-btn value="grid" icon="mdi-view-grid" size="small" />
              </v-btn-toggle>
            </v-col>
          </v-row>

          <v-col cols="12" class="d-flex justify-end gap-2 mt-2">
            <v-btn
              color="primary"
              rounded="lg"
              prepend-icon="mdi-filter"
              class="me-4"
              @click="applyFilters"
            >
              {{ t('btn.apply') }}
            </v-btn>

            <v-btn
              variant="outlined"
              color="error"
              rounded="lg"
              prepend-icon="mdi-close"
              @click="clearFilters"
            >
              {{t('btn.clear')}}
            </v-btn>
          </v-col>
        </v-card-text>
      </v-card>
    </v-expand-transition>
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
    <!-- ── Table View ─────────────────────────────────────────────────────── -->
    <v-card v-if="viewMode === 'table'" rounded="xl" elevation="0" border>
      <v-data-table-server
        :headers="headers"
        :items="products"
        :items-length="pagination.total || 0"
        :items-per-page="pagination.per_page"
        item-value="id"
        rounded="xl"
        hover
        @update:options="fetchOnOptions"
      >
        <!-- Image + Name -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <v-avatar
              size="44"
              rounded="lg"
              color="grey-lighten-2"
              class="me-2"
            >
              <v-img v-if="item.image_url" :src="item.image_url" cover />
              <v-icon v-else icon="mdi-food" size="22" color="grey" />
            </v-avatar>
            <div>
              <div class="font-weight-medium">{{ item.name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.sku || '—' }}
              </div>
            </div>
          </div>
        </template>

        <!-- Type chip -->
        <template #item.product_type="{ item }">
          <v-chip
            :color="typeColor(item.product_type)"
            variant="tonal"
            size="small"
            rounded="lg"
          >
            <v-icon
              :icon="typeIcon(item.product_type)"
              size="14"
              class="mr-1"
            />
            {{ capitalize(item.product_type) }}
          </v-chip>
        </template>

        <!-- Price -->
        <!-- <template #item.base_price="{ item }">
          <span class="font-weight-medium">
            {{ formatPrice(item.base_price) }}
          </span>
          <div v-if="item.cost_price" class="text-caption text-medium-emphasis">
            Cost: {{ formatPrice(item.cost_price) }}
          </div>
        </template> -->

        <!-- Available -->
        <template #item.is_available="{ item }">
          <v-switch
            v-model="item.is_available"
            color="success"
            density="compact"
            hide-details
            @change="toggleAvailability(item)"
          />
        </template>

        <!-- Featured -->
        <template #item.is_featured="{ item }">
          <v-icon
            :icon="item.is_featured ? 'mdi-star' : 'mdi-star-outline'"
            :color="item.is_featured ? 'warning' : 'grey'"
            size="20"
          />
        </template>

        <!-- Prep time -->
        <template #item.preparation_time="{ item }">
          <span v-if="item.preparation_time">
            <v-icon icon="mdi-clock-outline" size="14" class="mr-1" />
            {{ item.preparation_time }} min
          </span>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <v-btn
              icon="mdi-package-variant"
              size="small"
              variant="text"
              color="primary"
              title="Manage Units"
              @click="goToUnits(item)"
            />
            <v-btn
              icon="mdi-eye-outline"
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
            <p class="text-medium-emphasis mt-3">No products found</p>
            <v-btn
              color="primary"
              variant="tonal"
              class="mt-3"
              @click="openCreate"
            >
              Add First Product
            </v-btn>
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- ── Grid View ──────────────────────────────────────────────────────── -->
    <v-row v-else dense>
      <v-col
        v-for="product in filteredProducts"
        :key="product.id"
        cols="12"
        sm="6"
        md="4"
        lg="3"
      >
        <v-card rounded="xl" elevation="0" border hover>
          <!-- Image -->
          <div class="product-image-wrap">
            <v-img
              :src="product.image_url || ''"
              height="160"
              cover
              class="rounded-t-xl"
              gradient="to bottom, transparent 60%, rgba(0,0,0,0.4)"
            >
              <template #placeholder>
                <div
                  class="d-flex align-center justify-center fill-height bg-grey-lighten-3"
                >
                  <v-icon icon="mdi-food" size="48" color="grey" />
                </div>
              </template>
              <!-- Overlay chips -->
              <div class="pa-3 d-flex justify-space-between align-start">
                <v-chip
                  :color="typeColor(product.product_type)"
                  variant="flat"
                  size="x-small"
                  rounded="lg"
                >
                  {{ capitalize(product.product_type) }}
                </v-chip>
                <v-icon
                  v-if="product.is_featured"
                  icon="mdi-star"
                  color="warning"
                  size="18"
                />
              </div>
            </v-img>
          </div>

          <v-card-text class="pa-4">
            <div class="d-flex justify-space-between align-start">
              <div class="flex-1 mr-2">
                <p class="font-weight-semibold text-body-1 text-truncate">
                  {{ product.name }}
                </p>
                <p class="text-caption text-medium-emphasis">
                  {{ product.sku || 'No SKU' }}
                </p>
              </div>
              <div class="text-right">
                <p class="font-weight-bold text-body-1">
                  {{ formatPrice(product.base_price) }}
                </p>
              </div>
            </div>

            <div class="d-flex align-center justify-space-between mt-3">
              <v-chip
                :color="product.is_available ? 'success' : 'error'"
                variant="tonal"
                size="x-small"
                rounded="lg"
              >
                {{ product.is_available ? 'Available' : 'Unavailable' }}
              </v-chip>
              <div class="d-flex gap-1">
                <v-btn
                  icon="mdi-pencil-outline"
                  size="x-small"
                  variant="text"
                  color="primary"
                  @click="openEdit(product)"
                />
                <v-btn
                  icon="mdi-delete-outline"
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="confirmDelete(product)"
                />
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col v-if="!filteredProducts.length" cols="12">
        <div class="text-center py-16">
          <v-icon
            icon="mdi-package-variant-closed"
            size="64"
            color="grey-lighten-1"
          />
          <p class="text-h6 text-medium-emphasis mt-4">No products found</p>
        </div>
      </v-col>
    </v-row>

    <!-- ── Product Form Dialog ────────────────────────────────────────────── -->
    <ProductFormDialog
      v-if="dialog"
      v-model="dialog"
      :edit-item="editItem"
      :categories="categories"
      :tenants="tenants"
      @saved="onSaved"
    />
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import ProductFormDialog from '@/components/products/ProductFormDialog.vue'
  import { storeToRefs } from 'pinia'
  import { useProductStore } from '@/stores/productStore'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useTenantStore } from '@/stores/tenantStore'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { useDataTable } from '@/composables/useServerTable'

  const router = useRouter()
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  // ── Store ─────────────────────────────────────────────────────────────────────
  const productStore = useProductStore()
  const categoryStore = useCategoryStore()
  const tenantStore = useTenantStore()
  const { products, pagination } = storeToRefs(productStore)
  const { categories } = storeToRefs(categoryStore)
  const { tenants } = storeToRefs(tenantStore)

  // ── UI state ──────────────────────────────────────────────────────────────────
  const search = ref('')
  const filterType = ref(null)
  const filterAvailable = ref(null)
  const viewMode = ref('table')
  const dialog = ref(false)
  const editItem = ref(null)
  const showFilters = ref(false)
  const showStats = ref(false)
  // ── Fetch on mount ────────────────────────────────────────────────────────────
  onMounted(async () => {
    await categoryStore.fetchCategories({ per_page: 1000 })
    await tenantStore.fetchTenants()
  })

  // ── Options ───────────────────────────────────────────────────────────────────
  const productTypeOptions = [
    { title: 'Food', value: 'food' },
    { title: 'Beverage', value: 'beverage' },
    { title: 'Retail', value: 'retail' },
    { title: 'Combo', value: 'combo' }
  ]

  const availabilityOptions = [
    { title: 'Available', value: true },
    { title: 'Unavailable', value: false }
  ]

  const appliedFilters = ref({
    search: '',
    product_type: null,
    is_available: null
  })

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('products.table.name'), key: 'name', sortable: true },
    { title: t('products.table.type'), key: 'product_type', sortable: true },
    // { title: t('products.table.price'), key: 'base_price', sortable: true },
    { title: 'Available', key: 'is_available', sortable: true },
    { title: 'Featured', key: 'is_featured', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ])

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const stats = computed(() => [
    {
      label: 'Total Products',
      value: products.value.length,
      icon: 'mdi-package-variant',
      color: 'primary'
    },
    {
      label: 'Available',
      value: products.value.filter(p => p.is_available).length,
      icon: 'mdi-check-circle',
      color: 'success'
    },
    {
      label: 'Featured',
      value: products.value.filter(p => p.is_featured).length,
      icon: 'mdi-star',
      color: 'warning'
    },
    {
      label: 'Unavailable',
      value: products.value.filter(p => !p.is_available).length,
      icon: 'mdi-close-circle',
      color: 'error'
    }
  ])

  // ── Filtered ──────────────────────────────────────────────────────────────────
  const filteredProducts = computed(() => products.value)

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const typeColor = type =>
    ({ food: 'orange', beverage: 'blue', retail: 'purple', combo: 'teal' })[
      type
    ] || 'grey'
  const typeIcon = type =>
    ({
      food: 'mdi-food',
      beverage: 'mdi-cup',
      retail: 'mdi-shopping',
      combo: 'mdi-layers'
    })[type] || 'mdi-package'
  const capitalize = s => (s ? s.charAt(0).toUpperCase() + s.slice(1) : '')
  const formatPrice = v => `$${Number(v).toFixed(2)}`

  // ── Function ──────────────────────────────────────────────────────────────────────
  const { fetchOnOptions, refresh } = useDataTable(
    productStore.fetchProducts, // ✅ your existing store action
    () => ({
      search: appliedFilters.value.search,
      product_type: appliedFilters.value.product_type,
      is_available: appliedFilters.value.is_available
    })
  )
  // ── CRUD ──────────────────────────────────────────────────────────────────────
  // ✅ count active filters for badge
  const activeFilterCount = computed(() => {
    let count = 0
    if (search.value) count++
    if (filterType.value) count++
    if (filterAvailable.value) count++
    return count
  })

  function applyFilters() {
    appliedFilters.value = {
      search: search.value,
      product_type: filterType.value,
      is_available: filterAvailable.value
    }

    refresh() // 🔥 trigger API
  }

  function clearFilters() {
    search.value = ''
    filterType.value = null
    filterAvailable.value = null

    appliedFilters.value = {
      search: '',
      product_type: null,
      is_available: null
    }

    refresh() // 🔥 reload without filters
  }

  const goToUnits = p => {
    router.push({
      name: 'ProductUnits',
      params: { id: p.id }, // ← id not productId
      query: { name: p.name }
    })
  }
  const openCreate = () => {
    editItem.value = null
    dialog.value = true
  }

  const openEdit = item => {
    editItem.value = { ...item }
    dialog.value = true
  }

  const toggleAvailability = async item => {
    try {
      await productStore.updateProduct(item.id, {
        is_available: item.is_available
      })
      notif(`${item.name} ${item.is_available ? 'enabled' : 'disabled'}`, {
        type: 'success'
      })
    } catch (err) {
      // Revert optimistic UI update on failure
      item.is_available = !item.is_available
      notif(`Failed to update ${item.name}`, {
        type: 'success'
      })
    }
  }

  const doDelete = async product => {
    confirm({
      title: 'Delete Product?',
      message: `Are you sure delete "${product.name}"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await productStore.deleteProduct(product.id)
        notif(t('messages.deleted_success'), {
          type: 'success'
        })
        refresh()
      }
    })
  }

  const onSaved = async payload => {
    try {
      if (payload.id || payload.get?.('id')) {
        await productStore.updateProduct(
          payload.get?.('id') ?? payload.id,
          payload,
          payload instanceof FormData
            ? { 'Content-Type': 'multipart/form-data' }
            : { 'Content-Type': 'application/json' }
        )
        notif('Product updated successfully', { type: 'success' })
      } else {
        await productStore.createProduct(payload)
        notif('Product created successfully', { type: 'success' })
      }

      dialog.value = false // ← close after success
    } catch (err) {
      notif('Failed to save product', { type: 'error' }) // ← also fix typo: was 'success'
    }
  }
</script>
