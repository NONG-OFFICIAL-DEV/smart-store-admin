<template>
  <v-container fluid class="pa-0">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-5">
      <div>
        <h2 class="text-h5 font-weight-bold">Supplier Management</h2>
        <div class="text-caption text-grey">
          Manage all suppliers for your business
        </div>
      </div>
      <div class="d-flex gap-2">
        <v-btn
          variant="tonal"
          rounded="lg"
          :prepend-icon="
            showFilter ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
          "
          @click="showFilter = !showFilter"
        >
          Filter
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openAdd"
        >
          Add Supplier
        </v-btn>
      </div>
    </div>

    <!-- Filter panel -->
    <v-expand-transition>
      <v-card
        v-show="showFilter"
        rounded="xl"
        elevation="0"
        border
        class="mb-4 pa-4"
      >
        <v-row dense align="center">
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="draft.keyword"
              label="Search name, contact, phone, email"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="12" sm="3">
            <v-select
              v-model="draft.is_active"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
            />
          </v-col>
          <v-col cols="12" sm="3" class="d-flex gap-2">
            <v-btn
              variant="tonal"
              rounded="lg"
              :disabled="!filterActive"
              @click="resetFilter"
            >
              Reset
            </v-btn>
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              prepend-icon="mdi-filter"
              @click="applyFilter"
            >
              Apply
            </v-btn>
          </v-col>
        </v-row>
      </v-card>
    </v-expand-transition>

    <!-- Table -->
    <v-card rounded="xl" elevation="0" border>
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

  const store = useSupplierStore()
  const { confirm, notif } = useAppUtils()

  // ── Table ──────────────────────────────────────────────────────────────────────
  const opts = reactive({ page: 1, itemsPerPage: 15 })

  const headers = [
    { title: 'Supplier', key: 'name' },
    { title: 'Contact', key: 'contact_person' },
    { title: 'Phone', key: 'phone' },
    { title: 'Email', key: 'email' },
    { title: 'Payment Terms', key: 'payment_terms' },
    { title: 'Status', key: 'is_active' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Filter ─────────────────────────────────────────────────────────────────────
  const showFilter = ref(false)
  const draft = reactive({ keyword: '', is_active: null })
  const applied = reactive({ keyword: '', is_active: null })
  const filterActive = computed(
    () => draft.keyword.trim() !== '' || draft.is_active !== null
  )

  const statusOptions = [
    { title: 'Active', value: true },
    { title: 'Inactive', value: false }
  ]

  const applyFilter = () => {
    Object.assign(applied, { ...draft })
    opts.page = 1
    fetchData()
  }

  const resetFilter = () => {
    Object.assign(draft, { keyword: '', is_active: null })
    Object.assign(applied, { keyword: '', is_active: null })
    opts.page = 1
    fetchData()
  }

  // ── Fetch ──────────────────────────────────────────────────────────────────────
  const fetchData = () =>
    store.fetchSuppliers({
      page: opts.page,
      per_page: opts.itemsPerPage,
      keyword: applied.keyword,
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
