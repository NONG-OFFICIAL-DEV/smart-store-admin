<script setup>
  import { ref, computed, watch, onMounted } from 'vue'
  import { useCustomerStore } from '@/stores/customerStore'
  import { useCustomerAddressStore } from '@/stores/customerAddressStore'
  import CustomerDialog from '@/components/customers/CustomerDialog.vue'
  import CustomerAddressDialog from '@/components/customers/CustomerAddressDialog.vue'
  import CustomerDetailPanel from '@/components/customers/CustomerDetailPanel.vue'

  const customerStore = useCustomerStore()
  const customerAddressStore = useCustomerAddressStore()

  // ── State ─────────────────────────────────────────────────────────────────────
  const loading = ref(false)
  const error = ref(null)
  const search = ref('')
  const selectedCustomer = ref(null)
  const detailPanel = ref(false)

  // Dialog state
  const customerDialog = ref(false)
  const editingCustomer = ref(null)

  const addressDialog = ref(false)
  const editingAddress = ref(null)

  // ── Server-side table state ───────────────────────────────────────────────────
  const tableOptions = ref({ page: 1, itemsPerPage: 10, sortBy: [] })

  const loadCustomers = async (opts = {}) => {
    loading.value = true
    error.value = null
    const { page, itemsPerPage, sortBy } = { ...tableOptions.value, ...opts }
    try {
      await customerStore.fetchCustomers({
        page,
        per_page: itemsPerPage,
        search: search.value || undefined,
        sort_by: sortBy[0]?.key,
        sort_dir: sortBy[0]?.order
      })
    } catch (e) {
      error.value = 'Failed to load customers.'
    } finally {
      loading.value = false
    }
  }

  // Debounced search
  let searchTimer = null
  watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      tableOptions.value.page = 1
      loadCustomers({ page: 1 })
    }, 350)
  })

  onMounted(() => loadCustomers())

  const onTableUpdate = opts => {
    tableOptions.value = opts
    loadCustomers(opts)
  }

  // ── Customer actions ──────────────────────────────────────────────────────────
  const openNewCustomer = () => {
    editingCustomer.value = null
    customerDialog.value = true
  }

  const openEditCustomer = c => {
    editingCustomer.value = { ...c }
    customerDialog.value = true
  }

  const handleSaveCustomer = async formData => {
    if (editingCustomer.value) {
      await customerStore.updateCustomer(editingCustomer.value.id, formData)
      if (selectedCustomer.value?.id === editingCustomer.value.id) {
        selectedCustomer.value = { ...selectedCustomer.value, ...formData }
      }
    } else {
      await customerStore.createCustomer(formData)
    }
    customerDialog.value = false
  }

  const handleDeleteCustomer = async id => {
    await customerStore.deleteCustomer(id)
    if (selectedCustomer.value?.id === id) {
      selectedCustomer.value = null
      detailPanel.value = false
    }
  }

  const openDetail = async customer => {
    selectedCustomer.value = customer
    detailPanel.value = true
    // Load addresses for this customer
    await customerAddressStore.fetchCustomerAddresses(customer.id)
  }

  // ── Address actions ───────────────────────────────────────────────────────────
  const openNewAddress = () => {
    editingAddress.value = null
    addressDialog.value = true
  }

  const openEditAddress = addr => {
    editingAddress.value = { ...addr }
    addressDialog.value = true
  }

  const handleSaveAddress = async formData => {
    if (editingAddress.value) {
      await customerAddressStore.updateCustomerAddress(
        editingAddress.value.id,
        formData
      )
    } else {
      await customerAddressStore.createCustomerAddress(
        selectedCustomer.value.id,
        formData
      )
    }
    addressDialog.value = false
  }

  const handleDeleteAddress = async id => {
    await customerAddressStore.deleteCustomerAddress(id)
  }

  // filteredCustomers removed — search is server-side via loadCustomers

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const stats = computed(() => {
    const all = customerStore.customers
    return {
      total: all.length,
      active: all.filter(c => c.is_active).length,
      optedIn: all.filter(c => c.marketing_opt_in).length,
      points: all.reduce((s, c) => s + (c.loyalty_points || 0), 0)
    }
  })

  // ── Table headers ────────────────────────────────────────────────────────────
  const headers = [
    { title: 'Customer', key: 'first_name', sortable: true, minWidth: '200px' },
    { title: 'Contact', key: 'email', sortable: false },
    { title: 'Source', key: 'source', sortable: true },
    { title: 'Orders', key: 'total_orders', sortable: true },
    { title: 'Spent', key: 'total_spent', sortable: true },
    { title: 'Points', key: 'loyalty_points', sortable: true },
    { title: 'Status', key: 'is_active', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const initials = c =>
    `${c.first_name?.[0] ?? ''}${c.last_name?.[0] ?? ''}`.toUpperCase()
  const avatarColor = c => {
    const colors = [
      'brown-darken-2',
      'blue-darken-2',
      'teal-darken-2',
      'purple-darken-2',
      'orange-darken-2',
      'green-darken-2'
    ]
    return colors[(c.first_name?.charCodeAt(0) ?? 0) % colors.length]
  }
  const sourceChipColor = s =>
    ({ walk_in: 'teal', online: 'blue', referral: 'purple', social: 'pink' })[
      s
    ] ?? 'grey'
</script>

<template>
  <v-container fluid class="pa-0">
    <!-- ── Header ──────────────────────────────────────────── -->
    <custom-title icon="mdi-account-group" title="Customers">
      <template #right>
        <v-btn
          color="primary"
          rounded="lg"
          elevation="2"
          prepend-icon="mdi-account-plus"
          @click="openNewCustomer"
        >
          New Customer
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Error ───────────────────────────────────────────── -->
    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
      rounded="xl"
      class="mb-4"
      closable
      @click:close="error = null"
    >
      {{ error }}
    </v-alert>

    <!-- ── Stat Cards ──────────────────────────────────────── -->
    <v-row dense class="mb-5">
      <v-col
        cols="6"
        md="3"
        v-for="card in [
          {
            label: 'Total Customers',
            value: stats.total,
            icon: 'mdi-account-group',
            color: 'brown-darken-2'
          },
          {
            label: 'Active',
            value: stats.active,
            icon: 'mdi-account-check',
            color: 'green-darken-2'
          },
          {
            label: 'Marketing Opt-in',
            value: stats.optedIn,
            icon: 'mdi-email-newsletter',
            color: 'blue-darken-2'
          },
          {
            label: 'Total Points',
            value: stats.points,
            icon: 'mdi-star-circle',
            color: 'amber-darken-2'
          }
        ]"
        :key="card.label"
      >
        <v-card flat rounded="xl" class="pa-4 bg-white">
          <div class="d-flex align-center justify-space-between">
            <div>
              <p class="text-caption text-medium-emphasis mb-1">
                {{ card.label }}
              </p>
              <p
                class="text-h5 font-weight-black"
                :class="`text-${card.color}`"
              >
                <v-skeleton-loader v-if="loading" type="text" width="40" />
                <template v-else>{{ card.value.toLocaleString() }}</template>
              </p>
            </div>
            <v-avatar :color="card.color" size="44">
              <v-icon :icon="card.icon" color="white" size="22" />
            </v-avatar>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Data Table ─────────────────────────────────────── -->
    <v-card flat rounded="xl" class="bg-white">
      <v-data-table-server
        v-model:options="tableOptions"
        :headers="headers"
        :items="customerStore.customers"
        :items-length="customerStore.pagination.total ?? 0"
        :loading="loading"
        :search="search"
        :items-per-page="tableOptions.itemsPerPage"
        :items-per-page-options="[10, 15, 25, 50]"
        hover
        @update:options="onTableUpdate"
        @click:row="(_, { item }) => openDetail(item)"
      >
        <!-- Search slot -->
        <template #top>
          <div class="pa-4 pb-2">
            <v-text-field
              v-model="search"
              placeholder="Search by name, email or phone…"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              rounded="xl"
              clearable
              hide-details
              style="max-width: 420px"
            />
          </div>
        </template>

        <!-- Customer column -->
        <template #item.first_name="{ item }">
          <div class="d-flex align-center gap-3 py-1">
            <v-avatar :color="avatarColor(item)" size="36">
              <v-img v-if="item.avatar_url" :src="item.avatar_url" />
              <span v-else class="text-caption font-weight-black text-white">
                {{ initials(item) }}
              </span>
            </v-avatar>
            <div>
              <p class="text-body-2 font-weight-bold text-brown-darken-4 mb-0">
                {{ item.first_name }} {{ item.last_name }}
              </p>
              <p
                v-if="item.date_of_birth"
                class="text-caption text-medium-emphasis mb-0"
              >
                {{ item.date_of_birth }}
              </p>
            </div>
          </div>
        </template>

        <!-- Contact column -->
        <template #item.email="{ item }">
          <p class="text-body-2 mb-0">{{ item.email ?? '—' }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            {{ item.phone ?? '—' }}
          </p>
        </template>

        <!-- Source column -->
        <template #item.source="{ item }">
          <v-chip
            v-if="item.source"
            :color="sourceChipColor(item.source)"
            size="x-small"
            label
            class="text-capitalize font-weight-bold"
          >
            {{ item.source }}
          </v-chip>
          <span v-else class="text-caption text-medium-emphasis">—</span>
        </template>

        <!-- Spent column -->
        <template #item.total_spent="{ item }">
          <span class="text-body-2 font-weight-medium">
            ${{ Number(item.total_spent ?? 0).toFixed(2) }}
          </span>
        </template>

        <!-- Points column -->
        <template #item.loyalty_points="{ item }">
          <div class="d-flex align-center gap-1">
            <v-icon icon="mdi-star" size="14" color="amber-darken-2" />
            <span class="text-body-2 font-weight-bold text-amber-darken-3">
              {{ item.loyalty_points ?? 0 }}
            </span>
          </div>
        </template>

        <!-- Status column -->
        <template #item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'grey'"
            size="x-small"
            label
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>

        <!-- Actions column -->
        <template #item.actions="{ item }">
          <div @click.stop>
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              color="brown"
              @click="openEditCustomer(item)"
            />
            <v-btn
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="handleDeleteCustomer(item.id)"
            />
          </div>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="pa-12 text-center">
            <v-icon
              icon="mdi-account-off-outline"
              size="48"
              color="brown-lighten-3"
              class="mb-3"
            />
            <p class="text-h6 font-weight-bold text-brown-darken-3">
              No customers found
            </p>
            <p class="text-body-2 text-medium-emphasis mb-4">
              {{
                search
                  ? 'Try a different search term.'
                  : 'Add your first customer to get started.'
              }}
            </p>
            <v-btn
              v-if="!search"
              color="brown-darken-3"
              rounded="xl"
              prepend-icon="mdi-account-plus"
              @click="openNewCustomer"
            >
              New Customer
            </v-btn>
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <!-- ── Dialogs ──────────────────────────────────────────── -->
    <CustomerDialog
      v-model="customerDialog"
      :editing="editingCustomer"
      @save="handleSaveCustomer"
    />

    <!-- ── Detail Drawer ────────────────────────────────────── -->
    <CustomerDetailPanel
      v-model="detailPanel"
      :customer="selectedCustomer"
      :addresses="customerAddressStore.customerAddresses"
      @edit-customer="openEditCustomer"
      @add-address="openNewAddress"
      @edit-address="openEditAddress"
      @delete-address="handleDeleteAddress"
    />

    <CustomerAddressDialog
      v-model="addressDialog"
      :editing="editingAddress"
      @save="handleSaveAddress"
    />
  </v-container>
</template>
