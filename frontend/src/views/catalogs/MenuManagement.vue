<template>
  <v-container fluid class="pa-0">
    <div class="d-flex justify-end align-center ga-2 mb-4">
      <v-btn
        :color="showFilters ? 'primary' : 'default'"
        :variant="showFilters ? 'flat' : 'tonal'"
        rounded="lg"
        :prepend-icon="
          showFilters ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
        "
        @click="showFilters = !showFilters"
      >
        {{ $t('btn.filter') }}
        <!-- badge shows how many filters are active -->
        <v-badge
          v-if="activeFilterCount > 0"
          :content="activeFilterCount"
          color="error"
          floating
        />
      </v-btn>
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
        rounded="lg"
        elevation="0"
        @click="openAddDialog"
      >
        {{ $t('menus.dialog.create_menu') }}
      </v-btn>
    </div>

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
    <v-expand-transition>
      <v-card v-if="showFilters" rounded="xl" elevation="0" class="mb-4">
        <v-card-text>
          <v-row dense align="center">
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="search"
                prepend-inner-icon="mdi-magnify"
                :placeholder="$t('menus.list.search_placeholder')"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
                @update:model-value="onSearchChange"
                @keyup.enter="onFilterChange"
              />
            </v-col>
            <v-col cols="12" sm="auto">
              <v-btn-toggle
                v-model="activeFilter"
                color="primary"
                variant="tonal"
                rounded="lg"
                density="compact"
              >
                <v-btn :value="null" size="small" class="text-none px-3">{{ $t('common.all') }}</v-btn>
                <v-btn :value="true" size="small" class="text-none px-3">
                  {{ $t('status.active') }}
                </v-btn>
                <v-btn :value="false" size="small" class="text-none px-3">
                  {{ $t('status.inactive') }}
                </v-btn>
              </v-btn-toggle>
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
            {{ $t('btn.reset') }}
          </v-btn>
          <v-btn
            class="bg-primary"
            rounded="lg"
            prepend-icon="mdi-magnify"
            @click="onFilterChange"
          >
            {{ $t('btn.search') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-expand-transition>

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
                  {{ $t('menus.list.default_badge') }}
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
              {{ $t('roles.more_count', { n: item.branches.length - 2 }) }}
            </v-chip>
          </div>
          <span v-else class="text-caption text-grey">{{ $t('menus.list.not_assigned') }}</span>
        </template>

        <!-- Default -->
        <template #item.is_default="{ item }">
          <v-chip
            size="small"
            :color="item.is_default ? 'primary' : 'grey'"
            :variant="item.is_default ? 'flat' : 'tonal'"
            :prepend-icon="item.is_default ? 'mdi-star' : 'mdi-star-outline'"
          >
            {{ item.is_default ? $t('menus.list.default_badge') : $t('common.no') }}
          </v-chip>
        </template>

        <!-- Active -->
        <template #item.is_active="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="small" />
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
            <v-tooltip :text="$t('menus.list.tooltip.edit')">
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

            <v-tooltip :text="$t('menus.list.tooltip.assign')">
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

            <v-tooltip :text="$t('menus.list.tooltip.delete')">
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
            <p class="text-h6 text-medium-emphasis mb-1">{{ $t('menus.list.empty') }}</p>
            <v-btn
              color="primary"
              variant="tonal"
              prepend-icon="mdi-plus"
              class="mt-2"
              @click="openAddDialog"
            >
              {{ $t('menus.list.create_first') }}
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
  import { useAppUtils, AppStatusChip } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import MenuFormDialog from '@/components/catalogs/MenuFormDialog.vue'
  import BranchMenuFormDialog from '@/components/catalogs/BranchMenuFormDialog.vue'
  import { useDate } from '@/composables/useDate'

  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()
  const { formatShortDate: formatDate } = useDate()

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
  const showFilters = ref(false)

  const options = ref({ page: 1, itemsPerPage: 10 })
  const editItem = ref(null)

  // ── Active filter badge ───────────────────────────────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (search.value?.trim()) count++
    if (activeFilter.value !== null && activeFilter.value !== undefined) count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const stats = computed(() => {
    const all = menuStore.menus ?? []
    return [
      {
        label: t('menus.list.stats.total'),
        icon: 'mdi-book-open-page-variant-outline',
        color: 'primary',
        value: menuStore.pagination?.total ?? all.length
      },
      {
        label: t('status.active'),
        icon: 'mdi-check-circle-outline',
        color: 'success',
        value: all.filter(m => m.is_active).length
      },
      {
        label: t('status.inactive'),
        icon: 'mdi-minus-circle-outline',
        color: 'error',
        value: all.filter(m => !m.is_active).length
      },
      {
        label: t('menus.list.default_badge'),
        icon: 'mdi-star',
        color: 'warning',
        value: all.filter(m => m.is_default).length
      }
    ]
  })

  // ── Headers ───────────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('form.name'), key: 'name', sortable: true },
    { title: t('menus.list.headers.branches'), key: 'branches', sortable: false },
    { title: t('menus.list.default_badge'), key: 'is_default', sortable: false },
    { title: t('form.status'), key: 'is_active', sortable: false },
    { title: t('menus.list.headers.created'), key: 'created_at', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ])

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
      perPage: options.value.itemsPerPage,
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

  // ── Reset to page 1 and reload when filters change ───────────────────────────
  const onFilterChange = () => {
    clearTimeout(searchTimer)
    options.value.page = 1
    loadItems()
  }

  const resetFilters = () => {
    search.value = ''
    activeFilter.value = null
    options.value.page = 1
    loadItems()
  }

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
      title: t('menus.list.confirm_delete.title'),
      message: t('menus.list.confirm_delete.message', { name: menu.name }),
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

  const confirmAssign = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        // Existing assignment → update
        res = await branchMenuStore.update(payload.id, payload)
        notif(t('menus.list.messages.assignment_updated'), {
          type: 'success'
        })
      } else {
        // New assignment → create
        res = await branchMenuStore.create(payload)
        notif(t('menus.list.messages.menu_assigned'), {
          type: 'success'
        })
      }
      notif(t('menus.list.messages.branches_assigned'), { type: 'success' })
      assignDialog.show = false
      await loadItems()
    } catch {
      notif(t('messages.error_occurred'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────

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
