<template>
  <v-container fluid class="pa-0">
    <custom-title icon="mdi-cash">
      Financial Control
      <template #right>
        <v-btn
          color="grey-darken-4"
          prepend-icon="mdi-export-variant"
          variant="outlined"
          rounded="lg"
          class="mr-3 text-none"
        >
          Export
        </v-btn>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          elevation="4"
          rounded="lg"
          class="px-6 text-none"
          @click="openDialog"
        >
          New Entry
        </v-btn>
      </template>
    </custom-title>
    <div>
      <v-row class="mb-6">
        <v-col v-for="stat in summaryStats" :key="stat.title" cols="12" md="4">
          <v-card rounded="xl" border="sm" flat class="pa-5">
            <div class="d-flex justify-space-between align-start">
              <div>
                <div class="text-subtitle-2 text-grey-darken-1 mb-1">
                  {{ stat.title }}
                </div>
                <div class="text-h4 font-weight-black">${{ stat.value }}</div>
                <div
                  :class="stat.trendColor"
                  class="text-caption font-weight-bold mt-1"
                >
                  <v-icon size="16">{{ stat.trendIcon }}</v-icon>
                  {{ stat.trendText }}
                </div>
              </div>
              <v-avatar :color="stat.iconBg" rounded="lg">
                <v-icon :color="stat.iconColor">{{ stat.icon }}</v-icon>
              </v-avatar>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-row class="mb-4" align="center">
        <v-col cols="12" md="4">
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            label="Search expenses..."
            variant="solo"
            flat
            bg-color="white"
            rounded="lg"
            hide-details
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="8" class="d-flex justify-md-end gap-2">
          <v-btn-toggle
            v-model="filterCategory"
            mandatory
            selected-class="bg-primary text-white"
            rounded="lg"
            variant="outlined"
            density="comfortable"
          >
            <v-btn value="all" class="text-none">All</v-btn>
            <v-btn value="food" class="text-none">Food</v-btn>
            <v-btn value="utilities" class="text-none">Bills</v-btn>
            <v-btn value="salary" class="text-none">Staff</v-btn>
          </v-btn-toggle>
        </v-col>
      </v-row>

      <v-card rounded="xl" flat border="sm">
        <v-table class="modern-table">
          <thead>
            <tr>
              <th class="text-grey-darken-1">Transaction</th>
              <th class="text-grey-darken-1">Category</th>
              <th class="text-grey-darken-1 text-right">Amount</th>
              <th class="text-grey-darken-1 text-center">Status</th>
              <th class="text-grey-darken-1 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in expenses" :key="item.id">
              <td>
                <div class="d-flex align-center py-3">
                  <v-avatar size="40" color="grey-lighten-4" class="mr-3">
                    <v-icon size="20" color="grey-darken-2">
                      {{ getCategoryIcon(item.category) }}
                    </v-icon>
                  </v-avatar>
                  <div>
                    <div class="font-weight-bold text-grey-darken-4">
                      {{ item.title }}
                    </div>
                    <div class="text-caption text-grey">
                      {{ item.date }} • {{ item.ref }}
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <v-chip
                  :color="getCategoryColor(item.category)"
                  variant="tonal"
                  size="x-small"
                  class="font-weight-bold"
                >
                  {{ item.category }}
                </v-chip>
              </td>
              <td class="text-right font-weight-black text-grey-darken-4">
                ${{ item.amount }}
              </td>
              <td class="text-center">
                <v-icon v-if="item.receipt" color="success">
                  mdi-file-check-outline
                </v-icon>
                <v-icon v-else color="grey-lighten-2">
                  mdi-file-remove-outline
                </v-icon>
              </td>
              <td class="text-right">
                <v-btn
                  icon="mdi-dots-vertical"
                  variant="text"
                  color="grey"
                  size="small"
                ></v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </div>
  </v-container>
</template>

<script setup>
  import { ref } from 'vue'

  const search = ref('')
  const filterCategory = ref('all')

  const summaryStats = ref([
    {
      title: 'Total Today',
      value: '284.50',
      icon: 'mdi-cash',
      iconBg: 'primary-lighten-5',
      iconColor: 'primary',
      trendIcon: 'mdi-trending-up',
      trendText: '12% vs yesterday',
      trendColor: 'text-error'
    },
    {
      title: 'Monthly Burn',
      value: '4,120.00',
      icon: 'mdi-trending-down',
      iconBg: 'red-lighten-5',
      iconColor: 'error',
      trendIcon: 'mdi-trending-down',
      trendText: '5% vs last month',
      trendColor: 'text-success'
    },
    {
      title: 'Pending Invoices',
      value: '12',
      icon: 'mdi-clock-outline',
      iconBg: 'amber-lighten-5',
      iconColor: 'amber-darken-3',
      trendIcon: 'mdi-alert-circle-outline',
      trendText: 'Action required',
      trendColor: 'text-amber-darken-4'
    }
  ])

  const expenses = ref([
    {
      id: 1,
      date: 'May 22, 2024',
      ref: 'INV-8821',
      title: 'Whole Foods Market',
      category: 'Food',
      amount: '120.40',
      receipt: true
    },
    {
      id: 2,
      date: 'May 21, 2024',
      ref: 'BILL-002',
      title: 'City Electric Co.',
      category: 'Utilities',
      amount: '840.00',
      receipt: false
    },
    {
      id: 3,
      date: 'May 20, 2024',
      ref: 'PAY-991',
      title: 'John Doe (Overtime)',
      category: 'Salary',
      amount: '45.00',
      receipt: true
    }
  ])

  const getCategoryColor = cat => {
    const map = { Food: 'orange', Utilities: 'blue', Salary: 'purple' }
    return map[cat] || 'grey'
  }

  const getCategoryIcon = cat => {
    const map = {
      Food: 'mdi-food-apple',
      Utilities: 'mdi-lightning-bolt',
      Salary: 'mdi-account-cash'
    }
    return map[cat] || 'mdi-tag'
  }
</script>

<style scoped>
  .modern-table thead th {
    height: 50px !important;
    font-size: 0.7rem !important;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 800 !important;
    border-bottom: 1px solid #f0f0f0 !important;
  }

  .modern-table tbody tr:hover {
    background-color: #f8fafc !important;
    cursor: pointer;
  }

  .modern-table td {
    border-bottom: 1px solid #f8fafc !important;
    height: 70px !important;
  }

  .gap-2 {
    gap: 8px;
  }
</style>
