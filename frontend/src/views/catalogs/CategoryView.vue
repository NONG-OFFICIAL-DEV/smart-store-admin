<template>
  <v-container fluid class="pa-0">
    <custom-title
      title="Categories"
      subtitle="Manage your product groupings and display order"
      icon="mdi-format-list-bulleted-type"
    >
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          @click="openCreateDialog"
        >
          Add Category
        </v-btn>
      </template>
    </custom-title>

    <!-- Table Card -->
    <v-card rounded="lg" elevation="0" border>
      <!-- Search & Filters -->

      <!-- Data Table -->
      <v-data-table-server
        v-model:items-per-page="filters.per_page"
        v-model:page="filters.page"
        :headers="headers"
        :items="categoryStore.categories"
        :items-length="totalItems"
        :loading="categoryStore.loading"
        loading-text="Loading categories..."
        no-data-text="No categories found"
        item-value="id"
        rounded="0"
        @update:options="onTableOptions"
      >
        <!-- No. Column -->
        <template #item.no="{ index }">
          <span class="text-body-2 text-grey">
            {{ (filters.page - 1) * filters.per_page + index + 1 }}
          </span>
        </template>

        <!-- Name Column -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar :color="avatarColor(item.name)" size="32" rounded="md">
              <span class="text-caption font-weight-bold text-white">
                {{ item.name?.charAt(0)?.toUpperCase() }}
              </span>
            </v-avatar>
            <span class="font-weight-medium text-body-2">{{ item.name }}</span>
          </div>
        </template>

        <!-- Description Column -->
        <template #item.description="{ item }">
          <span
            class="text-body-2 text-grey text-truncate d-inline-block"
            style="max-width: 260px"
          >
            {{ item.description || '—' }}
          </span>
        </template>

        <!-- Status Column -->
        <template #item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'default'"
            variant="tonal"
            size="small"
            rounded="md"
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>

        <!-- Created At Column -->
        <template #item.created_at="{ item }">
          <span class="text-body-2 text-grey">
            {{ formatDate(item.created_at) }}
          </span>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center gap-1">
            <v-btn
              icon="mdi-pencil-outline"
              variant="text"
              size="small"
              color="primary"
              @click="openEditDialog(item)"
            />
            <v-btn
              icon="mdi-trash-can-outline"
              variant="text"
              size="small"
              color="error"
              @click="openDeleteConfirm(item)"
            />
          </div>
        </template>

        <!-- Loading Slot -->
        <template #loading>
          <v-skeleton-loader type="table-row@8" />
        </template>
      </v-data-table-server>
    </v-card>

    <!-- Category Dialog (Create / Edit) -->
    <CategoryDialog
      v-model="dialog.show"
      :category="dialog.category"
      @saved="onSaved"
    />
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useCategoryStore } from '@/stores/categoryStore'
  import CategoryDialog from '@/components/catalogs/CategoryDialog.vue'
  import { useAppUtils } from '@nong-official-dev/core'

  const { confirm, notif } = useAppUtils()

  // ── Store ──────────────────────────────────────────────────────────────────
  const categoryStore = useCategoryStore()

  // ── Table Headers ──────────────────────────────────────────────────────────
  const headers = [
    { title: '#', key: 'no', sortable: false, width: '60px' },
    { title: 'Name', key: 'name', sortable: true },
    { title: 'Description', key: 'description', sortable: false },
    { title: 'Status', key: 'is_active', sortable: true, width: '110px' },
    {
      title: 'Actions',
      key: 'actions',
      sortable: false,
      width: '100px',
      align: 'center'
    }
  ]

  // ── Filters & Pagination ───────────────────────────────────────────────────
  const filters = reactive({
    page: 1,
    per_page: 10,
    search: '',
    sort_by: 'created_at',
    sort_order: 'desc'
  })

  const totalItems = computed(() => categoryStore.pagination?.total ?? 0)

  // ── Search debounce ────────────────────────────────────────────────────────
  let searchTimer = null
  const onSearch = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      filters.page = 1
      fetchData()
    }, 400)
  }

  // ── Table options sync ─────────────────────────────────────────────────────
  const onTableOptions = ({ page, itemsPerPage, sortBy }) => {
    filters.page = page
    filters.per_page = itemsPerPage
    if (sortBy?.length) {
      filters.sort_by = sortBy[0].key
      filters.sort_order = sortBy[0].order
    }
    fetchData()
  }

  // ── Fetch ──────────────────────────────────────────────────────────────────
  const fetchData = async () => {
    await categoryStore.fetchCategories({ ...filters })
  }

  onMounted(fetchData)

  // ── Create / Edit Dialog ───────────────────────────────────────────────────
  const dialog = reactive({
    show: false,
    category: null
  })

  const openCreateDialog = () => {
    dialog.category = null
    dialog.show = true
  }

  const openEditDialog = item => {
    dialog.category = { ...item }
    dialog.show = true
  }

  const onSaved = () => {
    dialog.show = false
    fetchData()
  }

  const openDeleteConfirm = async (item) => {
    confirm({
      title: 'Delete Category',
      message: `Are you sure you want to delete category "${item.name}"?`,
      options: { type: 'warning', width: 550 },
      agree: () => {
        categoryStore.deleteCategory(item.id)
      },
      cancel: () => {}
    })
    fetchData()
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  const formatDate = date => {
    if (!date) return '—'
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  }

  const AVATAR_COLORS = [
    'primary',
    'secondary',
    'success',
    'info',
    'warning',
    'purple',
    'teal',
    'pink'
  ]
  const avatarColor = (name = '') => {
    const index = name.charCodeAt(0) % AVATAR_COLORS.length
    return AVATAR_COLORS[index]
  }
</script>
