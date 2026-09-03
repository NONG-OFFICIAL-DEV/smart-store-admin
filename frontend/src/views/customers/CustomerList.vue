<script setup>
  import { ref, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useCustomerStore } from '@/stores/customerStore'
  import { useCustomerAddressStore } from '@/stores/customerAddressStore'
  import CustomerDialog from '@/components/customers/CustomerDialog.vue'
  import CustomerAddressDialog from '@/components/customers/CustomerAddressDialog.vue'
  import CustomerDetailPanel from '@/components/customers/CustomerDetailPanel.vue'
  import { useAppUtils, AppTable, AppStatusChip } from '@nong-official-dev/core'
  import AppToolbar from '@/components/common/AppToolbar.vue'
  import { useDate } from '@/composables/useDate'
  import { useAvatar } from '@/composables/useAvatar'

  const { formatShortDate: formatDate } = useDate()
  const { getInitials, getAvatarColor } = useAvatar()
  const customerStore = useCustomerStore()
  const customerAddressStore = useCustomerAddressStore()
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  // ── State ─────────────────────────────────────────────────────────────────────
  const tableRef = ref(null)
  const selectedCustomer = ref(null)
  const detailPanel = ref(false)

  // Dialog state
  const customerDialog = ref(false)
  const editingCustomer = ref(null)

  const addressDialog = ref(false)
  const editingAddress = ref(null)

  // ── Server-driven fetch — customerStore.fetchCustomers already sets
  // customers/pagination, so the stat cards below keep reading store state. ───
  async function fetchCustomers(params) {
    await customerStore.fetchCustomers(params)
    return { items: customerStore.customers, total: customerStore.pagination?.total ?? 0 }
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
    tableRef.value?.refresh()
  }

  const handleDeleteCustomer = async item => {
     confirm({
      title: "Delete Customer?",
      message: t('messages.confirm_delete'),
      options: { type: 'warning', width: 400 },
      agree: async () => {
        await customerStore.deleteCustomer(item.id)
        if (selectedCustomer.value?.id === item.id) {
          selectedCustomer.value = null
          detailPanel.value = false
        }
        tableRef.value?.refresh()
        notif(t('messages.deleted_success'), {
          type: 'success'
        })
      },
      cancel: () => {
        // optionally reset product selection to null
        // item.product_id = null
      }
    })
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
    confirm({
      title: t('purchases.form.duplicate_title'),
      message: t('purchases.form.duplicate_message'),
      options: { type: 'warning', width: 500 },
      agree: async () => {
        await customerAddressStore.deleteCustomerAddress(id)
      },
      cancel: () => {
        // optionally reset product selection to null
        // item.product_id = null
      }
    })
  }
  // ── Table headers ────────────────────────────────────────────────────────────
  const headers = computed(() => [
    {
      title: t('customers.table.customer'),
      key: 'first_name',
      sortable: true,
      minWidth: '200px'
    },
    { title: t('customers.table.contact'), key: 'email', sortable: false },
    { title: t('customers.table.source'), key: 'source', sortable: true },
    {
      title: t('customers.table.orders'),
      key: 'total_orders',
      sortable: true
    },
    { title: t('customers.table.spent'), key: 'total_spent', sortable: true },
    {
      title: t('customers.table.points'),
      key: 'loyalty_points',
      sortable: true
    },
    { title: t('form.status'), key: 'is_active', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ])

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const initials = c => getInitials(c, '')
  const avatarColor = c => getAvatarColor(c, { fallback: 'brown-darken-2' })
  const sourceChipColor = s =>
    ({ walk_in: 'teal', online: 'blue', referral: 'purple', social: 'pink' })[
      s
    ] ?? 'grey'
</script>

<template>
  <v-container fluid class="pa-0">
    <AppToolbar :title="t('customers_hub.title')" :subtitle="t('customers_hub.subtitle')">
      <template #actions>
        <v-btn
          color="primary"
          rounded="lg"
          elevation="2"
          prepend-icon="mdi-account-plus"
          @click="openNewCustomer"
        >
          {{ $t('btn.add_customer') }}
        </v-btn>
      </template>
    </AppToolbar>
    <!-- ── Data Table ─────────────────────────────────────── -->
    <v-card flat border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchCustomers"
        item-label="customers"
      >
        <!-- Customer column -->
        <template #[`item.first_name`]="{ item }">
          <div class="d-flex align-center gap-3 py-1" style="cursor: pointer" @click="openDetail(item)">
            <v-avatar :color="avatarColor(item)" size="36" class="me-2">
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
                {{ formatDate(item.date_of_birth) }}
              </p>
            </div>
          </div>
        </template>

        <!-- Contact column -->
        <template #[`item.email`]="{ item }">
          <p class="text-body-2 mb-0">{{ item.email ?? '—' }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            {{ item.phone ?? '—' }}
          </p>
        </template>

        <!-- Source column -->
        <template #[`item.source`]="{ item }">
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
        <template #[`item.total_spent`]="{ item }">
          <span class="text-body-2 font-weight-medium">
            ${{ Number(item.total_spent ?? 0).toFixed(2) }}
          </span>
        </template>

        <!-- Points column -->
        <template #[`item.loyalty_points`]="{ item }">
          <div class="d-flex align-center gap-1">
            <v-icon icon="mdi-star" size="14" color="amber-darken-2" />
            <span class="text-body-2 font-weight-bold text-amber-darken-3">
              {{ item.loyalty_points ?? 0 }}
            </span>
          </div>
        </template>

        <!-- Status column -->
        <template #[`item.is_active`]="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="x-small" />
        </template>

        <!-- Actions column -->
        <template #[`item.actions`]="{ item }">
          <v-btn
            icon="mdi-arrow-right-circle"
            size="small"
            variant="text"
            color="primary"
            @click="openDetail(item)"
          />
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
            @click="handleDeleteCustomer(item)"
          />
        </template>
      </AppTable>
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
