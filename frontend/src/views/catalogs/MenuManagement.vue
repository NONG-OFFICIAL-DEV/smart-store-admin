<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-food"
      title="Menu Management"
      subtitle="Manage your menus"
    >
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          @click="openAddDialog"
        >
          Create Menu
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Stats ──────────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-5">
      <v-col v-for="stat in stats" :key="stat.label" cols="6" sm="3">
        <v-card
          rounded="xl"
          border
          elevation="0"
          class="pa-4 d-flex align-center gap-3"
        >
          <v-avatar :color="stat.color" variant="tonal" rounded="lg" size="44">
            <v-icon :icon="stat.icon" size="20" />
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            <div class="text-caption text-grey">{{ stat.label }}</div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Filters ─────────────────────────────────────────────────────────────── -->
    <v-row dense align="center" class="mb-4">
      <v-col cols="12" sm="4">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          placeholder="Search menus..."
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          clearable
          @update:model-value="onSearchChange"
        />
      </v-col>
      <v-col cols="12" sm="auto">
        <v-btn-toggle
          v-model="activeFilter"
          color="primary"
          variant="tonal"
          rounded="lg"
        >
          <v-btn :value="null" size="small" class="text-none px-3">All</v-btn>
          <v-btn :value="true" size="small" class="text-none px-3">
            Active
          </v-btn>
          <v-btn :value="false" size="small" class="text-none px-3">
            Inactive
          </v-btn>
        </v-btn-toggle>
      </v-col>
    </v-row>

    <!-- ── Table ───────────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" elevation="0" border>
      <v-data-table-server
        :headers="headers"
        :items="filteredMenus"
        :items-length="menuStore.pagination.total || 0"
        v-model:page="options.page"
        v-model:items-per-page="options.itemsPerPage"
        hover
      >
        <!-- Name + Description -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <v-avatar color="primary" variant="tonal" rounded="lg" size="38">
              <v-icon icon="mdi-book-open-page-variant-outline" size="18" />
            </v-avatar>
            <div>
              <div
                class="text-body-2 font-weight-bold d-flex align-center gap-2"
              >
                {{ item.name }}
                <v-chip
                  v-if="item.is_default"
                  size="x-small"
                  color="primary"
                  variant="flat"
                  prepend-icon="mdi-star"
                >
                  Default
                </v-chip>
              </div>
              <div
                class="text-caption text-grey text-truncate"
                style="max-width: 260px"
              >
                {{ item.description || '—' }}
              </div>
            </div>
          </div>
        </template>

        <!-- Branches assigned -->
        <template #item.branches="{ item }">
          <div v-if="item.branches?.length" class="d-flex flex-wrap gap-1">
            <v-chip
              v-for="b in item.branches.slice(0, 2)"
              :key="b.id"
              size="x-small"
              variant="tonal"
              color="primary"
              prepend-icon="mdi-store-outline"
            >
              {{ b.name }}
            </v-chip>
            <v-chip
              v-if="item.branches.length > 2"
              size="x-small"
              variant="tonal"
              color="grey"
            >
              +{{ item.branches.length - 2 }} more
            </v-chip>
          </div>
          <span v-else class="text-caption text-grey">Not assigned</span>
        </template>

        <!-- Default -->
        <template #item.is_default="{ item }">
          <v-chip
            size="small"
            :color="item.is_default ? 'primary' : 'grey'"
            :variant="item.is_default ? 'flat' : 'tonal'"
            :prepend-icon="item.is_default ? 'mdi-star' : 'mdi-star-outline'"
          >
            {{ item.is_default ? 'Default' : 'No' }}
          </v-chip>
        </template>

        <!-- Active -->
        <template #item.is_active="{ item }">
          <v-chip
            size="small"
            :color="item.is_active ? 'success' : 'error'"
            variant="tonal"
            :prepend-icon="
              item.is_active
                ? 'mdi-check-circle-outline'
                : 'mdi-minus-circle-outline'
            "
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>

        <!-- Created at -->
        <template #item.created_at="{ item }">
          <span class="text-caption text-grey">
            {{ formatDate(item.created_at) }}
          </span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-end">
            <v-tooltip text="Edit menu">
              <template #activator="{ props: tt }">
                <v-btn
                  v-bind="tt"
                  icon="mdi-pencil-outline"
                  size="small"
                  variant="text"
                  @click="editMenu(item)"
                />
              </template>
            </v-tooltip>

            <v-tooltip text="Assign to branch">
              <template #activator="{ props: tt }">
                <v-btn
                  v-bind="tt"
                  icon="mdi-store-plus-outline"
                  size="small"
                  variant="text"
                  color="primary"
                  @click="openAssignBranch(item)"
                />
              </template>
            </v-tooltip>

            <v-tooltip text="Delete menu">
              <template #activator="{ props: tt }">
                <v-btn
                  v-bind="tt"
                  icon="mdi-delete-outline"
                  size="small"
                  color="error"
                  variant="text"
                  @click="confirmDelete(item)"
                />
              </template>
            </v-tooltip>
          </div>
        </template>

        <!-- Empty -->
        <template #no-data>
          <div class="text-center py-12">
            <v-icon
              icon="mdi-food-off"
              size="56"
              color="grey-lighten-1"
              class="mb-3"
            />
            <p class="text-h6 text-medium-emphasis mb-1">No menus found</p>
            <v-btn
              color="primary"
              variant="tonal"
              prepend-icon="mdi-plus"
              class="mt-2"
              @click="openAddDialog"
            >
              Create First Menu
            </v-btn>
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- ── Menu Form Dialog ────────────────────────────────────────────────────── -->
    <MenuFormDialog
      v-model="dialog"
      :edit-mode="isEdit"
      :item="selectedItem"
      :categories="categoryStore.items"
      @save="handleSave"
    />

    <!-- ── Assign Branch Dialog ────────────────────────────────────────────────── -->
    <!-- <v-dialog v-model="assignDialog.show" max-width="480" persistent>
      <v-card rounded="xl" elevation="0" border>
        <v-card-title class="pa-6 pb-4">
          <div class="d-flex align-center gap-3">
            <v-avatar color="primary" size="44" rounded="lg">
              <v-icon icon="mdi-store-plus-outline" size="22" />
            </v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">Assign to Branch</div>
              <div class="text-caption text-medium-emphasis">
                {{ assignDialog.menu?.name }}
              </div>
            </div>
          </div>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-select
            v-model="assignDialog.branch_ids"
            :items="branchStore.branches?.data || branchStore.branches || []"
            item-value="id"
            item-title="name"
            label="Select Branches"
            variant="outlined"
            density="comfortable"
            rounded="lg"
            multiple
            chips
            closable-chips
            prepend-inner-icon="mdi-store-outline"
          />
          <v-row dense class="mt-3">
            <v-col cols="6">
              <v-text-field
                v-model="assignDialog.available_from"
                type="time"
                label="Available From"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-clock-start"
              />
            </v-col>
            <v-col cols="6">
              <v-text-field
                v-model="assignDialog.available_until"
                type="time"
                label="Available Until"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-clock-end"
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-6 pt-4 gap-3">
          <v-btn
            block
            variant="tonal"
            rounded="lg"
            @click="assignDialog.show = false"
          >
            Cancel
          </v-btn>
          <v-btn
            block
            color="primary"
            variant="flat"
            rounded="lg"
            :loading="saving"
            @click="confirmAssign"
          >
            Assign
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog> -->
    <!-- assigment dialog -->
    <BranchMenuFormDialog
      v-model="assignDialog.show"
      :edit-item="editItem"
      :branches="branchStore.branches.data"
      :menus="menuStore.menus"
      @saved="confirmAssign"
    />
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { useMenuStore } from '@/stores/menuStore'
  import { useBranchMenuStore } from '@/stores/branchMenuStore'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import MenuFormDialog from '@/components/catalogs/MenuFormDialog.vue'
  import BranchMenuFormDialog from '@/components/catalogs/BranchMenuFormDialog.vue'

  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()

  const menuStore = useMenuStore()
  const categoryStore = useCategoryStore()
  const branchStore = useBranchStore()
  const branchMenuStore = useBranchMenuStore()

  // ── Dialog state ──────────────────────────────────────────────────────────────
  const dialog = ref(false)
  const isEdit = ref(false)
  const selectedItem = ref(null)
  const saving = ref(false)

  const assignDialog = reactive({
    show: false,
    menu: null,
    branch_ids: [],
    available_from: null,
    available_until: null
  })

  // ── Pagination + filters ──────────────────────────────────────────────────────
  const loading = ref(false)
  const search = ref('')
  const activeFilter = ref(null)

  const options = ref({ page: 1, itemsPerPage: 10 })
  const editItem = ref(null)

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const stats = computed(() => {
    const all = menuStore.menus ?? []
    return [
      {
        label: 'Total Menus',
        icon: 'mdi-book-open-page-variant-outline',
        color: 'primary',
        value: menuStore.pagination?.total ?? all.length
      },
      {
        label: 'Active',
        icon: 'mdi-check-circle-outline',
        color: 'success',
        value: all.filter(m => m.is_active).length
      },
      {
        label: 'Inactive',
        icon: 'mdi-minus-circle-outline',
        color: 'error',
        value: all.filter(m => !m.is_active).length
      },
      {
        label: 'Default',
        icon: 'mdi-star',
        color: 'warning',
        value: all.filter(m => m.is_default).length
      }
    ]
  })

  // ── Headers ───────────────────────────────────────────────────────────────────
  const headers = [
    { title: 'Name', key: 'name', sortable: true },
    { title: 'Branches', key: 'branches', sortable: false },
    { title: 'Default', key: 'is_default', sortable: false },
    { title: 'Status', key: 'is_active', sortable: false },
    { title: 'Created', key: 'created_at', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Filtered menus (client-side active filter) ────────────────────────────────
  const filteredMenus = computed(() => {
    let list = menuStore.menus ?? []
    if (activeFilter.value !== null && activeFilter.value !== undefined) {
      list = list.filter(m => m.is_active === activeFilter.value)
    }
    return list
  })

  // ── Load from server ──────────────────────────────────────────────────────────
  const loadItems = async () => {
    loading.value = true
    await menuStore.fetchMenus({
      page: options.value.page,
      per_page: options.value.itemsPerPage,
      search: search.value || undefined
    })
    loading.value = false
  }

  // Debounced search
  let searchTimer = null
  const onSearchChange = () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      options.value.page = 1
      loadItems()
    }, 400)
  }

  watch(options, loadItems, { deep: true })

  // ── CRUD Actions ──────────────────────────────────────────────────────────────
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
    saving.value = true
    try {
      if (isEdit.value) {
        await menuStore.updateMenu(data.id, data)
      } else {
        await menuStore.createMenu(data)
      }
      notif(t('messages.saved_success'), { type: 'success', color: 'primary' })
      dialog.value = false
      await loadItems()
    } catch {
      notif(t('messages.error_occurred'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const confirmDelete = menu => {
    confirm({
      title: t('Confirm Delete'),
      message: `Are you sure you want to delete "${menu.name}"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await menuStore.deleteMenu(menu.id)
        notif(t('messages.deleted_success'), { type: 'success' })
        await loadItems()
      }
    })
  }

  // ── Assign branch ─────────────────────────────────────────────────────────────
  const openAssignBranch = menu => {
    editItem.value = null
    assignDialog.menu = menu
    assignDialog.branch_ids = menu.branches?.map(b => b.id) ?? []
    assignDialog.available_from = null
    assignDialog.available_until = null
    assignDialog.show = true
  }

  const confirmAssign = async (payload) => {
    saving.value = true
    try {
      if (payload.id) {
        // Existing assignment → update
        res = await branchMenuStore.update(payload.id, payload)
        notif('Assignment updated successfully', {
          type: 'success'
        })
      } else {
        // New assignment → create
        res = await branchMenuStore.create(payload)
        notif('Menu assigned to branch successfully', {
          type: 'success'
        })
      }
      notif('Branches assigned successfully', { type: 'success' })
      assignDialog.show = false
      await loadItems()
    } catch {
      notif(t('messages.error_occurred'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const formatDate = d =>
    d
      ? new Date(d).toLocaleDateString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric'
        })
      : '—'

  // ── Init ──────────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await Promise.all([
      loadItems(),
      categoryStore.fetchCategories(),
      branchStore.fetchBranches()
    ])
  })
</script>

<style scoped>
  .gap-1 {
    gap: 4px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
