<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-warehouse"
      title="Inventory & Stock Report"
      subtitle="Mange stock report"
    >
      <template #right>
        <v-btn
          class="ms-2"
          variant="outlined"
          color="success"
          prepend-icon="mdi-file-excel"
        >
          Export Excel
        </v-btn>
      </template>
    </custom-title>

    <v-row>
      <v-col v-for="(stat, i) in summaryStats" :key="i" cols="12" sm="6" md="3">
        <v-card border flat>
          <v-card-item :prepend-icon="stat.icon">
            <v-card-subtitle>{{ stat.title }}</v-card-subtitle>
            <v-card-title class="text-h5">{{ stat.value }}</v-card-title>
          </v-card-item>
        </v-card>
      </v-col>

      <v-col cols="12">
        <v-card border flat>
          <v-toolbar color="white" flat>
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search SKU or Product Name..."
              variant="solo-filled"
              flat
              hide-details
              density="comfortable"
              class="mx-4"
            ></v-text-field>
            <v-spacer></v-spacer>
            <v-select
              v-model="filterStatus"
              :items="['All', 'In Stock', 'Low Stock', 'Out of Stock']"
              label="Status"
              variant="outlined"
              density="compact"
              hide-details
              style="max-width: 200px"
              class="mr-4"
            ></v-select>
          </v-toolbar>

          <v-data-table
            :headers="headers"
            :items="filteredItems"
            :search="search"
            hover
          >
            <template #[`item.stock`]="{ item }">
              <v-chip
                :color="getStockColor(item.stock)"
                size="small"
                variant="flat"
                class="font-weight-bold"
              >
                {{ item.stock }}
              </v-chip>
            </template>

            <template #[`item.status`]="{ item }">
              <v-badge
                dot
                inline
                :color="
                  item.stock <= 0
                    ? 'error'
                    : item.stock < 15
                      ? 'warning'
                      : 'success'
                "
              >
                <span class="ml-2 text-caption text-uppercase">
                  {{
                    item.stock <= 0
                      ? 'Out of Stock'
                      : item.stock < 15
                        ? 'Low Stock'
                        : 'In Stock'
                  }}
                </span>
              </v-badge>
            </template>

            <template #[`item.totalValue`]="{ item }">
              ${{ (item.price * item.stock).toLocaleString() }}
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, computed } from 'vue'

  const search = ref('')
  const filterStatus = ref('All')

  // Mock Data
  const stockItems = ref([
    {
      id: 1,
      sku: 'TSH-001',
      name: 'Cotton T-Shirt (M)',
      category: 'Apparel',
      stock: 120,
      price: 25
    },
    {
      id: 2,
      sku: 'HOD-992',
      name: 'Tech Hoodie',
      category: 'Apparel',
      stock: 8,
      price: 55
    },
    {
      id: 3,
      sku: 'CAP-441',
      name: 'Snapback Cap',
      category: 'Accessories',
      stock: 0,
      price: 15
    },
    {
      id: 4,
      sku: 'WTC-102',
      name: 'Smart Watch v2',
      category: 'Electronics',
      stock: 45,
      price: 199
    },
    {
      id: 5,
      sku: 'BTP-550',
      name: 'Bluetooth Speaker',
      category: 'Electronics',
      stock: 12,
      price: 89
    }
  ])

  const headers = [
    { title: 'SKU', key: 'sku', align: 'start' },
    { title: 'Product Name', key: 'name' },
    { title: 'Category', key: 'category' },
    { title: 'Current Stock', key: 'stock', align: 'center' },
    { title: 'Unit Price', key: 'price' },
    { title: 'Stock Value', key: 'totalValue' },
    { title: 'Status', key: 'status', sortable: false }
  ]

  // Computed Stats
  const summaryStats = computed(() => [
    {
      title: 'Total SKUs',
      value: stockItems.value.length,
      icon: 'mdi-package-variant'
    },
    {
      title: 'Low Stock Alerts',
      value: stockItems.value.filter(i => i.stock < 15 && i.stock > 0).length,
      icon: 'mdi-alert-circle-outline'
    },
    {
      title: 'Out of Stock',
      value: stockItems.value.filter(i => i.stock <= 0).length,
      icon: 'mdi-close-circle-outline'
    },
    {
      title: 'Total Inventory Value',
      value: `$${totalInventoryValue.value}`,
      icon: 'mdi-currency-usd'
    }
  ])

  const totalInventoryValue = computed(() => {
    return stockItems.value
      .reduce((acc, item) => acc + item.price * item.stock, 0)
      .toLocaleString()
  })

  const filteredItems = computed(() => {
    if (filterStatus.value === 'All') return stockItems.value
    return stockItems.value.filter(item => {
      const status =
        item.stock <= 0
          ? 'Out of Stock'
          : item.stock < 15
            ? 'Low Stock'
            : 'In Stock'
      return status === filterStatus.value
    })
  })

  const getStockColor = stock => {
    if (stock <= 0) return 'red-darken-1'
    if (stock < 15) return 'orange-darken-1'
    return 'green-darken-1'
  }

  const exportReport = () => {
    alert('Exporting to CSV...')
    // Implementation for CSV export logic would go here
  }
</script>

<style scoped>
  .v-card {
    transition: transform 0.2s ease-in-out;
  }
</style>
