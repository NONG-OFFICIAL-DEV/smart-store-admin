<template>
  <custom-title icon="mdi-cart-arrow-down">
    Purchase Orders
    <template #right>
      <BaseButtonFilter @click="toggleFilter" />
      <BaseButton
        class="ms-4"
        icon="mdi-plus"
        @click="goToCreate"
        v-if="isPurchaser || isManager || isAdmin"
      >
        New Purchase
      </BaseButton>
    </template>
  </custom-title>

  <!-- FILTER FORM -->
  <v-card v-show="showFilter" elevation="0" class="mb-4 pa-4 rounded-lg">
    <v-row>
      <!-- Keyword -->
      <v-col cols="12" md="3">
        <v-text-field
          v-model="filters.keyword"
          label="Search (Invoice / Purchase)"
          hide-details
        />
      </v-col>

      <!-- Status -->
      <v-col cols="12" md="3">
        <v-select
          v-model="filters.status"
          label="Status"
          :items="purchaseStore.statuses"
          item-title="label"
          item-value="code"
        />
      </v-col>

      <!-- Payment Status -->
      <v-col cols="12" md="3">
        <v-select
          v-model="filters.payment_status"
          label="Payment Status"
          :items="paymentOptions"
          hide-details
        />
      </v-col>

      <!-- Supplier -->
      <v-col cols="12" md="3">
        <v-select
          v-model="filters.supplier_id"
          label="Supplier"
          :items="supplierStore.suppliers.data"
          item-title="name"
          item-value="id"
          hide-details
        />
      </v-col>

      <!-- Date From -->
      <v-col cols="12" md="3">
        <v-date-input
          v-model="filters.date_from"
          label="From Date"
          hide-details
        />
      </v-col>

      <!-- Date To -->
      <v-col cols="12" md="3">
        <v-date-input
          v-model="filters.date_to"
          label="To Date"
          hide-details
          :allowed-dates="
            date => !filters.date_from || date >= filters.date_from
          "
        />
      </v-col>

      <!-- Buttons -->
      <v-col cols="12" md="3" class="d-flex align-center">
        <v-btn
          variant="outlined"
          class="me-2"
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

  <!-- TABLE -->
  <v-data-table-server
    v-model:items-per-page="itemsPerPage"
    :items-length="purchaseStore.purchases.total || 0"
    :items="purchaseStore.purchases.data"
    :headers="headers"
    :loading="purchaseStore.loading"
    item-key="id"
    hover
    @update:options="loadItems"
  >
    <template #item.purchase_date="{ item }">
      {{ formatDate(item.purchase_date) }}
    </template>

    <template #item.total_amount="{ item }">
      {{ formatCurrency(item.total_amount) }}
    </template>

    <template #item.status="{ item }">
      <v-chip
        :color="statusColor(item.purchase_status.code)"
        size="small"
        variant="tonal"
        class="cursor-default"
      >
        <v-icon :icon="statusIcon(item.purchase_status.code)" start size="16" />
        <span class="font-weight-medium">
          {{ formatText(item.purchase_status.code) }}
        </span>
      </v-chip>
    </template>

    <template #item.payment_status="{ item }">
      <v-chip
        :color="paymentColor(item.payment_status)"
        size="small"
        variant="tonal"
      >
        <v-icon :icon="paymentIcon(item.payment_status)" start />
        {{ formatText(item.payment_status) }}
      </v-chip>
    </template>

    <template #item.actions="{ item }">
      <v-btn
        v-if="canEditPurchase(item) || (isPurchaser || isManager || isAdmin)"
        icon="mdi-pencil"
        size="small"
        variant="text"
        color="primary"
        @click="goToEdit(item)"
      />
      <v-btn
        icon="mdi-eye"
        size="small"
        variant="text"
        color="info"
        @click="goToDetails(item)"
      />
    </template>
  </v-data-table-server>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import { usePurchaseStore } from '@/stores/purchaseStore'
  import { useSupplierStore } from '@/stores/supplierStore'
  import { useDate } from '@/composables/useDate'
  import { useCurrency } from '@/composables/useCurrency'
  import { usePermission } from '@/composables/usePermission'

  // ------------------------------
  // Composables & Utils
  // ------------------------------
  const { isAdmin, isManager, isPurchaser } = usePermission()
  const router = useRouter()
  const purchaseStore = usePurchaseStore()
  const supplierStore = useSupplierStore()
  const { formatDate } = useDate()
  const { formatCurrency } = useCurrency()

  /* ---------------- STATE ---------------- */
  const showFilter = ref(false)
  const itemsPerPage = ref(10)

  const filters = ref({
    keyword: '',
    status: null,
    payment_status: null,
    supplier_id: null,
    date_from: null,
    date_to: null
  })

  /* ---------------- OPTIONS ---------------- */
  const paymentOptions = ['unpaid', 'partial', 'paid']

  /* ---------------- HEADERS ---------------- */
  const headers = [
    { title: 'PO No', key: 'purchase_number' },
    { title: 'Supplier', key: 'supplier.name' },
    { title: 'Total', key: 'total_amount' },
    { title: 'PO Status', key: 'status' },
    { title: 'Payment Status', key: 'payment_status' },
    { title: 'Date', key: 'purchase_date' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' }
  ]

  /* ---------------- COMPUTED ---------------- */
  const isFilterActive = computed(() => {
    return Object.values(filters.value).some(
      v => v !== null && v !== '' && v !== undefined
    )
  })

  /* ---------------- METHODS ---------------- */
  const toggleFilter = () => {
    showFilter.value = !showFilter.value
  }

  const applyFilter = () => {
    purchaseStore.fetchPurchases({
      page: 1,
      per_page: itemsPerPage.value,
      ...filters.value
    })
  }

  const resetFilter = () => {
    filters.value = {
      keyword: '',
      status: null,
      payment_status: null,
      supplier_id: null,
      date_from: null,
      date_to: null
    }
    purchaseStore.fetchPurchases({
      page: 1,
      per_page: itemsPerPage.value
    })
  }

  const loadItems = ({ page, itemsPerPage }) => {
    purchaseStore.fetchPurchases({
      page,
      per_page: itemsPerPage,
      ...filters.value
    })
  }

  /* ---------------- HELPERS ---------------- */
  // Map status code to chip color
  const statusColor = status =>
    ({
      draft: 'grey',
      request: 'orange',
      approved: 'blue',
      rejected: 'red',
      ordered: 'indigo',
      received: 'green',
      completed: 'purple',
      cancelled: 'red'
    })[status] || 'grey'

  // Map status code to icon
  const statusIcon = status =>
    ({
      draft: 'mdi-file-document-outline',
      request: 'mdi-account-clock', // waiting for approval
      approved: 'mdi-check-bold',
      rejected: 'mdi-cancel',
      ordered: 'mdi-progress-clock',
      received: 'mdi-check-circle',
      completed: 'mdi-flag-checkered',
      cancelled: 'mdi-close-circle'
    })[status] || 'mdi-information'

  const paymentColor = v =>
    ({ paid: 'green', unpaid: 'red', partial: 'orange' })[v] || 'grey'

  const paymentIcon = v =>
    ({
      paid: 'mdi-cash-check',
      unpaid: 'mdi-cash-remove',
      partial: 'mdi-cash-clock'
    })[v] || 'mdi-cash'

  const formatText = v => v.charAt(0).toUpperCase() + v.slice(1)

  /* ---------------- NAVIGATION ---------------- */
  const goToCreate = () => router.push('/purchase/create')
  const goToEdit = p => router.push(`/purchase/${p.id}/edit`)
  const goToDetails = p => router.push(`/purchases/${p.id}/details`)

  // Function to check if a purchase can be edited
  const canEditPurchase = computed(() => {
    return item => {
      if (!item?.purchase_status?.code) return false

      const finalStatuses = ['approved', 'completed', 'cancelled']

      // Nobody can edit final statuses
      if (finalStatuses.includes(item.purchase_status.code)) return false

      if (isPurchaser.value) {
        return item.purchase_status.code === 'draft'
      }

      if (isManager.value) {
        return item.purchase_status.code === 'request'
      }

      // if (isAdmin.value) {
      //   return ['draft', 'request', 'approved', 'ordered'].includes(
      //     item.purchase_status.code
      //   )
      // }

      return false
    }
  })

  /* ---------------- INIT ---------------- */
  onMounted(async () => {
    await supplierStore.fetchSuppliers({ per_page: -1 })
    await purchaseStore.fetchPurchases()
    await purchaseStore.fetchStatuses()
  })
</script>
