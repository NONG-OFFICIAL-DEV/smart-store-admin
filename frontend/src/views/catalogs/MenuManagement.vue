<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-food"
      title="Menu Management"
      subtitle="Mange your menu"
    >
      <template #right>
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openAddDialog">
          Create Menu Item
        </v-btn>
      </template>
    </custom-title>

    <v-card>
      <v-data-table-server
        :headers="headers"
        :items="menuStore.menus"
        :items-length="menuStore.pagination.total || 0"
        :loading="loading"
        v-model:page="options.page"
        v-model:items-per-page="options.itemsPerPage"
        class="elevation-1"
      >
        <!-- Default -->
        <template #item.is_default="{ item }">
          <v-chip
            size="small"
            :color="item.is_default ? 'primary' : 'grey'"
            variant="flat"
          >
            {{ item.is_default ? 'Default' : 'No' }}
          </v-chip>
        </template>

        <!-- Active -->
        <template #item.is_active="{ item }">
          <v-chip
            size="small"
            :color="item.is_active ? 'success' : 'error'"
            variant="flat"
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-pencil"
            size="small"
            variant="text"
            @click="editMenu(item)"
          />
          <v-btn
            icon="mdi-delete"
            size="small"
            color="error"
            variant="text"
            @click="confirmDelete(item)"
          />
        </template>
      </v-data-table-server>
    </v-card>

    <!-- Dialogs -->
    <MenuFormDialog
      v-model="dialog"
      :edit-mode="isEdit"
      :item="selectedItem"
      :categories="categoryStore.items"
      @save="handleSave"
    />
    <!-- <CategoryDialog
      v-model="categoryDialog"
      :edit-mode="isCategoryEdit"
      :item="selectedCategoryItem"
      @save="handleCategorySave"
    /> -->
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted, watch } from 'vue'
  import { useMenuStore } from '@/stores/menuStore'
  import { useCategoryStore } from '@/stores/categoryStore'
  import MenuFormDialog from '@/components/catalogs/MenuFormDialog.vue'
  import CategoryDialog from '@/components/catalogs/CategoryDialog.vue'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'

  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()

  const menuStore = useMenuStore()
  const categoryStore = useCategoryStore()

  const selectedCategory = ref(null)
  const dialog = ref(false)
  const isEdit = ref(false)
  const selectedItem = ref(null)

  const categoryDialog = ref(false)
  const isCategoryEdit = ref(false)
  const selectedCategoryItem = ref(null)

  // Pagination
  const loading = ref(false)

  const options = ref({
    page: 1,
    itemsPerPage: 10
  })

  const headers = [
    { title: 'Name', key: 'name' },
    { title: 'Default', key: 'is_default' },
    { title: 'Active', key: 'is_active' },
    { title: 'Description', key: 'description' },
    { title: 'Actions', key: 'actions', sortable: false }
  ]

  async function loadItems() {
    loading.value = true

    await menuStore.fetchMenus({
      page: options.value.page,
      per_page: options.value.itemsPerPage
    })

    loading.value = false
  }

  watch(options, loadItems, { deep: true })
  // ✅ FIXED: now using menuStore.menus (array)
  const filteredProducts = computed(() => {
    if (!selectedCategory.value) return menuStore.menus

    return menuStore.menus.filter(
      item => item.menu_category_id === selectedCategory.value
    )
  })

  const pageCount = computed(() =>
    Math.ceil(filteredProducts.value.length / itemsPerPage)
  )

  const paginatedProducts = computed(() =>
    filteredProducts.value.slice(
      (currentPage.value - 1) * itemsPerPage,
      currentPage.value * itemsPerPage
    )
  )

  // Reset page when filter changes
  watch(selectedCategory, () => {
    currentPage.value = 1
  })

  // ───────── MENU ACTIONS ─────────

  const openAddDialog = () => {
    isEdit.value = false
    selectedItem.value = null
    dialog.value = true
  }

  const editMenu = menu => {
    isEdit.value = true
    selectedItem.value = { ...menu }
    dialog.value = true
  }

  const handleSave = async data => {
    try {
      if (isEdit.value) {
        await menuStore.updateMenu(data.id, data)
      } else {
        await menuStore.createMenu(data)
      }

      notif(t('messages.saved_success'), {
        type: 'success',
        color: 'primary'
      })

      dialog.value = false
    } catch (err) {
      notif(t('messages.error_occurred'), { type: 'error' })
    }
  }

  const confirmDelete = menu => {
    confirm({
      title: t('Confirm Delete'),
      message: `Are you sure delete "${menu.name}"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await menuStore.deleteMenu(menu.id)

        notif(t('messages.deleted_success'), {
          type: 'success'
        })
      }
    })
  }

  // ───────── CATEGORY ACTIONS ─────────

  const openCategoryDialog = (item = null) => {
    isCategoryEdit.value = !!item
    selectedCategoryItem.value = item
    categoryDialog.value = true
  }

  const handleCategorySave = async data => {
    try {
      if (isCategoryEdit.value) await categoryStore.updateItem(data.id, data)
      else await categoryStore.createItem(data)

      notif(t('messages.saved_success'), { type: 'success' })

      categoryDialog.value = false
      await categoryStore.fetchCategories()
    } catch {
      notif(t('messages.error_occurred'), { type: 'error' })
    }
  }

  // ───────── INIT ─────────

  onMounted(async () => {
    await loadItems()
    await categoryStore.fetchCategories()
  })
</script>
