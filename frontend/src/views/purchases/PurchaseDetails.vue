<template>
  <custom-title>
    <v-btn
      size="x-small"
      icon="mdi-arrow-left"
      class="white mr-2"
      @click="$router.back()"
      variant="tonal"
    ></v-btn>

    <strong>Purchase Details</strong>
    <template #right>
      <v-btn color="primary" prepend-icon="mdi-printer" @click="printInvoice">
        Print Invoice
      </v-btn>
    </template>
  </custom-title>

  <v-card elevation="0" rounded="lg">
    <v-card-title>Purchase Information</v-card-title>
    <v-card-text>
      <v-row>
        <v-col cols="12" md="3">
          <strong>Invoice No:</strong>
          {{ purchase.invoice_number }}
        </v-col>
        <v-col cols="12" md="3">
          <strong>Po No:</strong>
          {{ purchase.purchase_number }}
        </v-col>
        <v-col cols="12" md="3">
          <strong>Purchase Date:</strong>
          {{ formatDate(purchase.purchase_date) }}
        </v-col>
        <v-col cols="12" md="3">
          <strong>Status:</strong>
          <v-chip
            :color="statusColor(purchase.purchase_status?.code)"
            variant="tonal"
            size="small"
            class="font-weight-medium"
            label
          >
            <span class="text-capitalize">
              {{ purchase.purchase_status?.label }}
            </span>
          </v-chip>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <!-- SUPPLIER INFO -->
  <v-card class="mt-4" elevation="0" rounded="lg">
    <v-card-title>Supplier Information</v-card-title>
    <v-card-text>
      <v-row>
        <v-col cols="12" md="4">
          <strong>Name:</strong>
          {{ purchase.supplier?.name }}
        </v-col>
        <v-col cols="12" md="4">
          <strong>Phone:</strong>
          {{ purchase.supplier?.phone }}
        </v-col>
        <v-col cols="12" md="4">
          <strong>Address:</strong>
          {{ purchase.supplier?.address }}
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <v-card class="mt-4" elevation="0" rounded="lg">
    <v-card-title>Purchased Items</v-card-title>
    <v-card-text class="pa-0">
      <v-data-table
        :headers="itemHeaders"
        :items="purchase.items"
        class="px-2"
        density="compact"
      >
        <template #item.cost_price="{ item }">
          {{ formatCurrency(item.cost_price) }}
        </template>
        <template #item.item_discount="{ item }">
          {{ item.item_discount }} %
        </template>
        <template #item.item_tax="{ item }">{{ item.item_tax }} %</template>
        <template #item.total="{ item }">
          {{ formatCurrency(item.quantity * item.cost_price) }}
        </template>
      </v-data-table>
    </v-card-text>
  </v-card>

  <!-- TOTALS -->
  <v-card class="mt-4" elevation="0" rounded="lg">
    <v-card-text>
      <div class="d-flex justify-end">
        <div style="width: 300px">
          <div class="d-flex justify-space-between mb-2">
            <span>Subtotal:</span>
            <strong>{{ formatCurrency(purchase.subtotal) }}</strong>
          </div>

          <div class="d-flex justify-space-between mb-2">
            <span>Tax:</span>
            <strong>{{ formatCurrency(purchase.tax) }}</strong>
          </div>

          <div class="d-flex justify-space-between mb-2">
            <span>Discount:</span>
            <strong>{{ formatCurrency(purchase.discount) }}</strong>
          </div>

          <v-divider class="my-2"></v-divider>

          <div class="d-flex justify-space-between">
            <span><strong>Grand Total:</strong></span>
            <strong class="text-primary">
              {{ formatCurrency(purchase.total_amount) }}
            </strong>
          </div>
        </div>
      </div>
    </v-card-text>
  </v-card>

  <!-- NOTES -->
  <v-card class="mt-4 mb-6" elevation="0" rounded="lg">
    <v-card-title>Notes</v-card-title>
    <v-card-text>
      <p>{{ purchase.note || 'No notes provided.' }}</p>
    </v-card-text>
  </v-card>
</template>

<script setup>
  import { ref, onMounted } from 'vue'
  import { useRoute } from 'vue-router'
  import { usePurchaseStore } from '@/stores/purchaseStore'
  import { useDate } from '@/composables/useDate'
  import { useCurrency } from '@/composables/useCurrency.js'

  const { formatCurrency } = useCurrency()
  const { formatDate } = useDate()
  const route = useRoute()
  const purchaseStore = usePurchaseStore()

  const purchase = ref({})

  const itemHeaders = [
    { title: 'Item', key: 'product.name' },
    { title: 'Cost Price', key: 'cost_price' },
    { title: 'Qty', key: 'quantity' },
    { title: 'Discount', key: 'item_discount' },
    { title: 'Tax', key: 'item_tax' },
    { title: 'Total', key: 'total' }
  ]

  onMounted(async () => {
    await purchaseStore.fetchPurchaseById(route.params.id)
    purchase.value = purchaseStore.purchase
  })

  const statusColor = status =>
    ({
      draft: 'grey',
      requested: 'orange',
      approved: 'blue',
      rejected: 'red',
      ordered: 'indigo',
      received: 'green',
      completed: 'purple',
      cancelled: 'red'
    })[status] || 'grey'

  function printInvoice() {
    window.print()
  }
</script>
