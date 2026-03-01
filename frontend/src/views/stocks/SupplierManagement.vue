<template>
  <custom-title icon="mdi-truck-delivery">
    Supplier Management
    <template #right>
      <BaseButtonFilter class="me-4" @click="toggleFilterForm" />
      <BaseButton icon="mdi-plus" @click="openAddDialog">
        Add Supplier
      </BaseButton>
    </template>
  </custom-title>
  <v-card class="mb-4 pa-4 rounded-lg" elevation="0" v-show="showFilterForm">
    <v-row>
      <!-- Search -->
      <v-col cols="12" md="3">
        <v-text-field
          v-model="draftFilters.keyword"
          label="Search (Name, Contact, Phone, Email)"
          prepend-inner-icon="mdi-magnify"
          hide-details
        />
      </v-col>

      <!-- Status -->
      <v-col cols="12" md="3">
        <v-select
          v-model="draftFilters.status"
          :items="statusOptions"
          label="Status"
          hide-details
        />
      </v-col>

      <!-- Buttons -->
      <v-col cols="12" md="3" class="d-flex align-center">
        <v-btn
          class="me-3"
          variant="outlined"
          :disabled="!isFilterActive"
          @click="resetFilter"
        >
          Reset
        </v-btn>
        <v-btn
          color="primary"
          prepend-icon="mdi-filter-outline"
          @click="applyFilter"
        >
          Apply Filter
        </v-btn>
      </v-col>
    </v-row>
  </v-card>

  <v-data-table-server
    :headers="headers"
    :items="supplierStore.suppliers.data"
    :items-length="supplierStore.suppliers.total || 0"
    :loading="supplierStore.loading"
    v-model:items-per-page="tableOptions.itemsPerPage"
    @update:options="loadItems"
  >
    <template #item.actions="{ item }">
      <v-btn
        icon="mdi-pencil"
        color="primary"
        class="me-2"
        variant="text"
        @click="openEditDialog(item)"
      />
      <v-btn
        icon="mdi-delete"
        color="error"
        variant="text"
        @click="handleDelete(item.id)"
      />
    </template>
    <template #item.status="{ item }">
      <v-chip size="small" :color="item.status == '1' ? 'green' : 'red'">
        <v-icon
          :icon="item.status == '1' ? 'mdi-check-circle' : 'mdi-cancel'"
          start
        ></v-icon>
        {{ item.status == '1' ? 'Active' : 'Inactiv' }}
      </v-chip>
    </template>
  </v-data-table-server>

  <!-- Add / Edit Dialog -->
  <SupplierDialog
    v-model="isDialogOpen"
    :supplier="selectedSupplier"
    @save="handleSave"
  />
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useSupplierStore } from '@/stores/supplierStore'
  import SupplierDialog from '@/components/SupplierDialog.vue'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { useI18n } from 'vue-i18n'

  const supplierStore = useSupplierStore()
  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()

  /* =====================
   TABLE STATE
===================== */

  const tableOptions = reactive({
    page: 1,
    itemsPerPage: 10
  })

  /* =====================
   FILTER STATE
===================== */

  // what user types/selects
  const draftFilters = reactive({
    keyword: '',
    status: null
  })

  // what API actually uses
  const appliedFilters = reactive({
    keyword: '',
    status: ''
  })

  const isFilterActive = computed(() => {
    return draftFilters.keyword.trim() !== '' || draftFilters.status !== ''
  })

  /* =====================
   UI STATE
===================== */

  const showFilterForm = ref(false)
  const isDialogOpen = ref(false)
  const selectedSupplier = ref(null)

  /* =====================
   TABLE HEADERS
===================== */

  const headers = [
    { title: 'Name', key: 'name' },
    { title: 'Contact', key: 'contact_name' },
    { title: 'Phone', key: 'phone' },
    { title: 'Email', key: 'email' },
    { title: 'Status', key: 'status' },
    { title: 'Actions', key: 'actions', sortable: false }
  ]

  const statusOptions = [
    { title: 'Active', value: 1 },
    { title: 'Inactive', value: 0 }
  ]

  /* =====================
   FETCH DATA (ONE SOURCE)
===================== */

  const fetchData = () => {
    supplierStore.fetchSuppliers({
      page: tableOptions.page,
      per_page: tableOptions.itemsPerPage,
      keyword: appliedFilters.keyword,
      status: appliedFilters.status
    })
  }

  /* =====================
   EVENTS
===================== */

  const loadItems = ({ page, itemsPerPage }) => {
    tableOptions.page = page
    tableOptions.itemsPerPage = itemsPerPage
    fetchData()
  }

  const applyFilter = () => {
    appliedFilters.keyword = draftFilters.keyword
    appliedFilters.status = draftFilters.status

    tableOptions.page = 1
    fetchData()
  }

  const resetFilter = () => {
    draftFilters.keyword = ''
    draftFilters.status = ''

    appliedFilters.keyword = ''
    appliedFilters.status = ''

    tableOptions.page = 1
    fetchData()
  }

  const toggleFilterForm = () => {
    showFilterForm.value = !showFilterForm.value
  }

  /* =====================
   CRUD
===================== */

  const openAddDialog = () => {
    selectedSupplier.value = null
    isDialogOpen.value = true
  }

  const openEditDialog = supplier => {
    selectedSupplier.value = { ...supplier }
    isDialogOpen.value = true
  }

  const handleSave = async supplier => {
    if (supplier.id) {
      await supplierStore.updateSupplier(supplier)
      notif(t('messages.updated_success'), { type: 'success' })
    } else {
      await supplierStore.addSupplier(supplier)
      notif(t('messages.saved_success'), { type: 'success' })
    }

    isDialogOpen.value = false
    fetchData()
  }

  const handleDelete = id => {
    confirm({
      title: 'Are you sure?',
      message: 'Are you sure you want to delete this supplier?',
      options: { type: 'error',width: 500 },
      agree: async () => {
        await supplierStore.removeSupplier(id)
        notif(t('messages.deleted_success'), { type: 'success' })
        fetchData()
      }
    })
  }

  /* =====================
   INIT
===================== */

  onMounted(() => {
    fetchData()
  })
</script>
