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
        {{ t('btn.filter') }}
        <v-badge
          v-if="activeFilterCount > 0"
          :content="activeFilterCount"
          color="error"
          floating
        />
      </v-btn>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-plus"
        @click="openAdd"
      >
        {{ t('btn.add_supplier') }}
      </v-btn>
    </div>

    <!-- Filter panel -->
    <v-expand-transition>
      <v-card v-if="showFilters" rounded="xl" elevation="0" class="mb-4">
        <v-card-text>
          <v-row dense align="center">
            <v-col cols="12" sm="4">
              <v-text-field
                v-model="draft.keyword"
                label="Search name, contact, phone, email"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
                @keyup.enter="onFilterChange"
              />
            </v-col>
            <v-col cols="12" sm="3">
              <v-select
                v-model="draft.is_active"
                :items="statusOptions"
                label="Status"
                variant="outlined"
                rounded="lg"
                hide-details
                clearable
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

    <!-- Table -->
    <v-card rounded="lg" elevation="0" border>
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchTableData"
        :filters="appliedFilters"
        :show-search="false"
        item-label="suppliers"
      >
        <!-- Status chip -->
        <template #item.is_active="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="x-small" />
        </template>

        <!-- Payment terms -->
        <template #item.payment_terms="{ item }">
          <span class="text-caption text-grey">
            {{ item.payment_terms || '—' }}
          </span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-pencil-outline"
            size="small"
            variant="text"
            color="primary"
            class="mr-1"
            @click="openEdit(item)"
          />
          <v-btn
            icon="mdi-delete-outline"
            size="small"
            variant="text"
            color="error"
            @click="handleDelete(item.id)"
          />
        </template>
      </AppTable>
    </v-card>

    <!-- Dialog -->
    <SupplierDialog
      v-model="dialog"
      :supplier="selected"
      :loading="saving"
      @save="handleSave"
    />
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed } from 'vue'
  import { useSupplierStore } from '@/stores/supplierStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { AppStatusChip, AppTable } from '@nong-official-dev/core'
  import SupplierDialog from '@/components/SupplierDialog.vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const store = useSupplierStore()
  const { confirm, notif } = useAppUtils()

  // ── Table ──────────────────────────────────────────────────────────────────────
  const tableRef = ref(null)

  const headers = computed(() => [
    { title: t('suppliers.table.name'), key: 'name' },
    { title: t('suppliers.table.contact_person'), key: 'contact_person' },
    { title: t('suppliers.table.phone'), key: 'phone' },
    { title: t('suppliers.table.email'), key: 'email' },
    { title: t('suppliers.table.payment_terms'), key: 'payment_terms' },
    { title: t('suppliers.table.status'), key: 'is_active' },
    {
      title: t('suppliers.table.actions'),
      key: 'actions',
      sortable: false,
      align: 'end'
    }
  ])

  // ── Filter ─────────────────────────────────────────────────────────────────────
  const showFilters = ref(false)
  const draft = reactive({ keyword: '', is_active: null })
  const applied = reactive({ keyword: '', is_active: null })

  // ── Active filter badge ───────────────────────────────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (draft.keyword.trim() !== '') count++
    if (draft.is_active !== null) count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  const statusOptions = [
    { title: 'Active', value: true },
    { title: 'Inactive', value: false }
  ]

  // ── Filters passed straight through to fetchTableData — AppTable deep-watches
  // this and refetches (resetting to page 1) whenever it changes. ───────────────
  const appliedFilters = computed(() => ({
    search: applied.keyword || undefined,
    is_active: applied.is_active
  }))

  // ── Apply/reset just update `applied` — no manual refetch needed. ────────────
  const onFilterChange = () => {
    Object.assign(applied, { ...draft })
  }

  const resetFilters = () => {
    Object.assign(draft, { keyword: '', is_active: null })
    Object.assign(applied, { keyword: '', is_active: null })
  }

  // ── Fetch ──────────────────────────────────────────────────────────────────────
  async function fetchTableData(params) {
    await store.fetchSuppliers(params)
    return { items: store.suppliers.data ?? [], total: store.suppliers.total ?? 0 }
  }

  // ── Dialog ─────────────────────────────────────────────────────────────────────
  const dialog = ref(false)
  const selected = ref(null)
  const saving = ref(false)

  const openAdd = () => {
    selected.value = null
    dialog.value = true
  }
  const openEdit = item => {
    selected.value = { ...item }
    dialog.value = true
  }

  const handleSave = async data => {
    saving.value = true
    try {
      if (data.id) {
        await store.updateSupplier(data)
        notif('Supplier updated', { type: 'success' })
      } else {
        await store.addSupplier(data)
        notif('Supplier added', { type: 'success' })
      }
      dialog.value = false
      tableRef.value?.refresh()
    } catch {
      notif('Something went wrong', { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const handleDelete = id => {
    confirm({
      title: 'Delete Supplier',
      message: 'This action cannot be undone.',
      options: { type: 'error' },
      agree: async () => {
        await store.removeSupplier(id)
        notif('Supplier deleted', { type: 'success' })
        tableRef.value?.refresh()
      }
    })
  }
</script>

<style scoped>
  .gap-2 {
    gap: 8px;
  }
</style>
