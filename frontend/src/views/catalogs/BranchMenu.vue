<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-book-open-variant"
      title="Branch Menu Assignments"
      subtitle=" Assign and manage menus for each branch"
    >
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          @click="openCreateDialog"
        >
          Assign Menu
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Filters ─────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" elevation="0" border class="mb-5">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" md="4">
            <v-select
              v-model="filters.branch_id"
              :items="branchStore.branches.data"
              item-title="name"
              item-value="id"
              label="Filter by Branch"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              prepend-inner-icon="mdi-store"
              @update:modelValue="loadData"
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-select
              v-model="filters.menu_id"
              :items="menus"
              item-title="name"
              item-value="id"
              label="Filter by Menu"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              prepend-inner-icon="mdi-book-open-outline"
              @update:modelValue="loadData"
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-btn
              variant="tonal"
              color="secondary"
              prepend-icon="mdi-refresh"
              rounded="lg"
              @click="resetFilters"
            >
              Reset
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- ── Data Table ───────────────────────────────────────────────────────── -->
    <v-card rounded="lg" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="store.branchMenus"
        :loading="store.loading"
        item-value="id"
        rounded="lg"
        hover
      >
        <!-- Branch column -->
        <template #item.branch="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar color="primary" size="32" rounded="md">
              <v-icon icon="mdi-store" size="16" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ item.branch?.name || '—' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.branch?.city || '' }}
              </div>
            </div>
          </div>
        </template>

        <!-- Menu column -->
        <template #item.menu="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar color="success" size="32" rounded="md">
              <v-icon icon="mdi-book-open-outline" size="16" />
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ item.menu?.name || '—' }}
              </div>
              <v-chip
                v-if="item.menu?.is_default"
                size="x-small"
                color="primary"
                variant="tonal"
              >
                Default
              </v-chip>
            </div>
          </div>
        </template>

        <!-- Time column -->
        <template #item.time="{ item }">
          <div
            v-if="item.available_from || item.available_until"
            class="d-flex align-center gap-1"
          >
            <v-icon
              icon="mdi-clock-outline"
              size="14"
              class="text-medium-emphasis"
            />
            <span class="text-body-2">
              {{ item.available_from || '00:00' }} –
              {{ item.available_until || '23:59' }}
            </span>
          </div>
          <span v-else class="text-body-2 text-medium-emphasis">All Day</span>
        </template>

        <!-- Days column -->
        <template #item.days_of_week="{ item }">
          <div class="d-flex gap-1 flex-wrap">
            <template v-if="item.days_of_week && item.days_of_week.length">
              <v-chip
                v-for="day in item.days_of_week"
                :key="day"
                size="x-small"
                color="info"
                variant="tonal"
              >
                {{ DAY_LABELS[day] }}
              </v-chip>
            </template>
            <span v-else class="text-body-2 text-medium-emphasis">
              Every Day
            </span>
          </div>
        </template>

        <!-- Available now column -->
        <template #item.available_now="{ item }">
          <v-chip
            :color="isAvailableNow(item) ? 'success' : 'default'"
            size="small"
            variant="tonal"
            :prepend-icon="
              isAvailableNow(item)
                ? 'mdi-check-circle'
                : 'mdi-clock-plus-outline'
            "
          >
            {{ isAvailableNow(item) ? 'Available' : 'Unavailable' }}
          </v-chip>
        </template>

        <!-- Sort order column -->
        <template #item.sort_order="{ item }">
          <v-chip size="small" variant="tonal" color="secondary">
            {{ item.sort_order ?? 0 }}
          </v-chip>
        </template>

        <!-- Actions column -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-end">
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              color="primary"
              @click="openEditDialog(item)"
            />
            <v-btn
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="confirmDelete(item)"
            />
          </div>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="text-center py-10">
            <v-icon
              icon="mdi-book-open-variant"
              size="48"
              color="medium-emphasis"
              class="mb-3"
            />
            <p class="text-body-1 text-medium-emphasis">
              No menu assignments found
            </p>
            <v-btn
              color="primary"
              variant="tonal"
              prepend-icon="mdi-plus"
              class="mt-3"
              @click="openCreateDialog"
            >
              Assign First Menu
            </v-btn>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- ── Create / Edit Dialog ─────────────────────────────────────────────── -->
    <BranchMenuFormDialog
      v-model="dialog"
      :edit-item="editItem"
      :branches="branchStore.branches.data"
      :menus="menuStore.menus"
      @saved="onSaved"
    />
  </v-container>
</template>

<script setup>
  import { ref, onMounted } from 'vue'
  import { useBranchMenuStore } from '@/stores/branchMenuStore'
  import BranchMenuFormDialog from '@/components/catalogs/BranchMenuFormDialog.vue'
  import { useBranchStore } from '@/stores/branchStore'
  import { useMenuStore } from '@/stores/menuStore'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  // ── Store ─────────────────────────────────────────────────────────────────────
  const store = useBranchMenuStore()
  const branchStore = useBranchStore()
  const menuStore = useMenuStore()
  // ── Day labels ────────────────────────────────────────────────────────────────
  const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    { title: 'Branch', key: 'branch', sortable: true },
    { title: 'Menu', key: 'menu', sortable: true },
    { title: 'Time Window', key: 'time', sortable: false },
    { title: 'Days', key: 'days_of_week', sortable: false },
    { title: 'Status', key: 'available_now', sortable: false },
    { title: 'Sort Order', key: 'sort_order', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Filters ───────────────────────────────────────────────────────────────────
  const filters = ref({ branch_id: null, menu_id: null })
  const menus = ref([]) // TODO: load from menu store

  // ── Dialog state ──────────────────────────────────────────────────────────────
  const dialog = ref(false)
  const editItem = ref(null)
  const deleteTarget = ref(null)

  // ── Load data ─────────────────────────────────────────────────────────────────
  const loadData = () => {
    store.fetchAll()
    branchStore.fetchBranches()
    menuStore.fetchMenus()
  }

  const resetFilters = () => {
    filters.value = { branch_id: null, menu_id: null }
    loadData()
  }

  // ── Available now check ───────────────────────────────────────────────────────
  const isAvailableNow = item => {
    const now = new Date()
    const today = now.getDay()
    const nowTime = now.toTimeString().slice(0, 5)

    if (item.days_of_week?.length && !item.days_of_week.includes(today))
      return false
    if (item.available_from && nowTime < item.available_from) return false
    if (item.available_until && nowTime > item.available_until) return false
    return true
  }

  // ── Dialog actions ────────────────────────────────────────────────────────────
  const openCreateDialog = () => {
    editItem.value = null
    dialog.value = true
  }

  const openEditDialog = item => {
    editItem.value = { ...item }
    dialog.value = true
  }

  const onSaved = async payload => {
    let res
    if (payload.id) {
      // Existing assignment → update
      res = await store.update(payload.id, payload)
      notif('Assignment updated successfully', {
        type: 'success'
      })
    } else {
      // New assignment → create
      res = await store.create(payload)
      notif('Menu assigned to branch successfully', {
        type: 'success'
      })
    }

    if (res.success) loadData()
    else
      notif('Operation failed', {
        type: 'error'
      })
  }

  const confirmDelete = async menu => {
    confirm({
      title: 'Remove Assignment?',
      message: `Are you sure delete "${menu.menu.name}"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        store.remove(menu.id)

        notif(t('messages.deleted_success'), {
          type: 'success'
        })
      }
    })
  }

  // ── Init ──────────────────────────────────────────────────────────────────────
  onMounted(() => loadData())
</script>
