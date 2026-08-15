<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-truck-outline"
      :title="$t('suppliers.title')"
      :subtitle="$t('suppliers.subtitle')"
    >
      <template #right>
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
          class="ms-2"
          prepend-icon="mdi-plus"
          @click="openAdd"
        >
          {{ t('btn.add_supplier') }}
        </v-btn>
      </template>
    </custom-title>

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
      <v-data-table-server
        :headers="headers"
        :items="store.suppliers.data ?? []"
        :items-length="store.suppliers.total ?? 0"
        :loading="store.loading"
        v-model:items-per-page="opts.itemsPerPage"
        @update:options="loadItems"
      >
        <!-- Status chip -->
        <template #item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'error'"
            size="x-small"
            variant="tonal"
            label
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
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
      </v-data-table-server>
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
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useSupplierStore } from '@/stores/supplierStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import SupplierDialog from '@/components/SupplierDialog.vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const store = useSupplierStore()
  const { confirm, notif } = useAppUtils()

  // ── Table ──────────────────────────────────────────────────────────────────────
  const opts = reactive({ page: 1, itemsPerPage: 15 })

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

  // ── Reset to page 1 and reload when filters change ───────────────────────────
  const onFilterChange = () => {
    Object.assign(applied, { ...draft })
    opts.page = 1
    fetchData()
  }

  const resetFilters = () => {
    Object.assign(draft, { keyword: '', is_active: null })
    Object.assign(applied, { keyword: '', is_active: null })
    opts.page = 1
    fetchData()
  }

  // ── Fetch ──────────────────────────────────────────────────────────────────────
  const fetchData = () =>
    store.fetchSuppliers({
      page: opts.page,
      perPage: opts.itemsPerPage,
      search: applied.keyword || undefined,
      is_active: applied.is_active
    })

  const loadItems = ({ page, itemsPerPage }) => {
    opts.page = page
    opts.itemsPerPage = itemsPerPage
    fetchData()
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
      fetchData()
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
        fetchData()
      }
    })
  }

  onMounted(fetchData)
</script>

<style scoped>
  .gap-2 {
    gap: 8px;
  }
</style>
