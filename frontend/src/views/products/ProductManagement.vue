<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-tag-outline"
      title="Products"
      subtitle="Manage your product catalog, variants, and availability"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          Add Product
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Filters ── -->
    <v-card rounded="xl" elevation="0" border class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="search"
              placeholder="Search by name, SKU, barcode..."
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
      </v-card-text>
    </v-card>

    <!-- ── Stats row ──────────────────────────────────────────────────────── -->
    <v-row dense class="mb-4">
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

    <!-- ── Table View ─────────────────────────────────────────────────────── -->
    <v-card v-if="viewMode === 'table'" rounded="xl" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="filteredProducts"
        :search="search"
        :loading="loading"
        item-value="id"
        rounded="xl"
        hover
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
        <template #item.base_price="{ item }">
          <span class="font-weight-medium">
            {{ formatPrice(item.base_price) }}
          </span>
          <div v-if="item.cost_price" class="text-caption text-medium-emphasis">
            Cost: {{ formatPrice(item.cost_price) }}
          </div>
        </template>

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
      </v-data-table>
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

  const { confirm, notif } = useAppUtils()
  // ── Store ─────────────────────────────────────────────────────────────────────
  const productStore = useProductStore()
  const categoryStore = useCategoryStore()
  const tenantStore = useTenantStore()
  const { products } = storeToRefs(productStore)
  const { categories } = storeToRefs(categoryStore)
  const { tenants } = storeToRefs(tenantStore)

  // ── UI state ──────────────────────────────────────────────────────────────────
  const loading = ref(false)
  const search = ref('')
  const filterType = ref(null)
  const filterAvailable = ref(null)
  const viewMode = ref('table')
  const dialog = ref(false)
  const editItem = ref(null)
  const deleteDialog = ref(false)
  const deleteTarget = ref(null)
  const deleteLoading = ref(false)
  const snack = ref({ show: false, text: '', color: 'success' })

  // ── Fetch on mount ────────────────────────────────────────────────────────────
  onMounted(async () => {
    await categoryStore.fetchCategories()
    await productStore.fetchProducts()
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

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    { title: 'Product', key: 'name', sortable: true },
    { title: 'Type', key: 'product_type', sortable: true },
    { title: 'Price', key: 'base_price', sortable: true },
    { title: 'Prep Time', key: 'preparation_time', sortable: true },
    { title: 'Available', key: 'is_available', sortable: true },
    { title: 'Featured', key: 'is_featured', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

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
  const filteredProducts = computed(() => {
    return products.value.filter(p => {
      if (filterType.value && p.product_type !== filterType.value) return false
      if (
        filterAvailable.value !== null &&
        filterAvailable.value !== undefined &&
        p.is_available !== filterAvailable.value
      )
        return false
      if (search.value) {
        const q = search.value.toLowerCase()
        return (
          p.name.toLowerCase().includes(q) ||
          (p.sku || '').toLowerCase().includes(q) ||
          (p.barcode || '').toLowerCase().includes(q)
        )
      }
      return true
    })
  })

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

  const showSnack = (text, color = 'success') => {
    snack.value = { show: true, text, color }
  }

  // ── CRUD ──────────────────────────────────────────────────────────────────────
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
        await productStore.deleteProduct(deleteTarget.value.id)

        notif(t('messages.deleted_success'), {
          type: 'success'
        })
        await productStore.fetchProducts()
      }
    })
  }

  const onSaved = async payload => {
    try {
      if (payload.id) {
        await productStore.updateProduct(payload.id, payload)
        notif('Product updated successfully', {
          type: 'success'
        })
      } else {
        await productStore.createProduct(payload)
        notif('Product created successfully', {
          type: 'success'
        })
      }
    } catch (err) {
      notif('Failed to save product', {
        type: 'success'
      })
    }
  }
</script>
