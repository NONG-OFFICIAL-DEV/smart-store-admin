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
            {{ t('btn.stats') }}
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
            <v-col cols="12" sm="3">
              <v-text-field
                v-model="search"
                :placeholder="t('products.filter.placeholder')"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                rounded="lg"
                @keyup.enter="onFilterChange"
              />
            </v-col>
            <v-col cols="6" sm="4">
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
            <v-col cols="6" sm="3">
              <v-select
                v-model="filterAvailable"
                :items="availabilityOptions"
                :label="t('products.filter.availability')"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                rounded="lg"
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
    <v-card rounded="lg" elevation="0" border>
      <v-data-table-server
        :headers="headers"
        :items="products"
        :items-length="pagination.total || 0"
        :items-per-page="pagination.per_page"
        item-value="id"
        rounded="lg"
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
      </v-data-table-server>
    </v-card>
  </v-container>
</template>
<script setup>
  import { ref, computed, onMounted } from 'vue'
  import CustomSelect from '@/components/customs/CustomSelect.vue'
  import { storeToRefs } from 'pinia'
  import { useProductStore } from '@/stores/productStore'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { useDataTable } from '@/composables/useServerTable'
 
  const router = useRouter()
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()

  // ── Stores ────────────────────────────────────────────────────────────────────
  const productStore = useProductStore()
  const categoryStore = useCategoryStore()
  const { products, pagination } = storeToRefs(productStore)
  const { categories } = storeToRefs(categoryStore)

  // ── UI state ──────────────────────────────────────────────────────────────────
  const search = ref('')
  const filterCategories = ref([]) // ✅ always an array
  const filterAvailable = ref(null)
  const showFilters = ref(false)
  const showStats = ref(false)

  // ── Bootstrap ─────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await categoryStore.fetchCategories({ perPage: 1000 })
  })

  // ── Static options ────────────────────────────────────────────────────────────
  const availabilityOptions = [
    { title: 'Available', value: true },
    { title: 'Unavailable', value: false }
  ]

  // ── Applied filters (only updated on "Apply") ─────────────────────────────────
  const appliedFilters = ref({
    search: '',
    categories: [],
    is_available: null
  })

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('products.table.name'), key: 'name', sortable: true },
    {
      title: t('products.table.category'),
      key: 'category.name',
      sortable: false
    },
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

  // ── Data table ────────────────────────────────────────────────────────────────
  const { fetchOnOptions, refresh } = useDataTable(
    productStore.fetchProducts,
    () => ({
      search: appliedFilters.value.search,
      categories: appliedFilters.value.categories, // ✅ array of UUIDs
      is_available: appliedFilters.value.is_available
    })
  )

  // ── Active filter badge ───────────────────────────────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (search.value?.trim()) count++ // ✅ string check
    if (filterCategories.value?.length > 0) count++ // ✅ array length
    if (filterAvailable.value !== null && filterAvailable.value !== undefined)
      count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  // ── Filter actions (aliased as onFilterChange / resetFilters) ─────────────────
  function applyFilters() {
    appliedFilters.value = {
      search: search.value?.trim() ?? '',
      categories: filterCategories.value ?? [], // ✅ always send array
      is_available: filterAvailable.value
    }
    refresh()
  }

  function clearFilters() {
    search.value = ''
    filterCategories.value = [] // ✅ reset to array, NOT null
    filterAvailable.value = null

    appliedFilters.value = {
      search: '',
      categories: [],
      is_available: null
    }
    refresh()
  }

  const onFilterChange = applyFilters
  const resetFilters = clearFilters

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
      notif(`Failed to update ${item.name}`, { type: 'error' }) // ✅ fixed: was 'success'
    }
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
        refresh()
      }
    })
  }

</script>
