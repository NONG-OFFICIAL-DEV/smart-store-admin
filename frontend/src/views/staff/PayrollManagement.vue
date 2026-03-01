<template>
  <v-container fluid class="pa-0">
    <custom-title icon="mdi-bank-transfer">
      Payroll & Salaries
      <template #right>
        <div class="d-flex gap-2">
          <v-btn
            variant="outlined"
            prepend-icon="mdi-printer"
            rounded="lg"
            class="mr-2 text-none"
          >
            Print Slips
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-cash-check"
            rounded="lg"
            class="text-none"
            @click="processPayroll"
          >
            Process All
          </v-btn>
        </div>
      </template>
    </custom-title>

    <v-container fluid class="pa-0">
      <v-row class="mb-4">
        <v-col cols="12" sm="6" md="3">
          <v-card rounded="lg" flat border class="pa-3">
            <div class="text-caption text-grey font-weight-bold">
              TOTAL PAYOUT (THIS MONTH)
            </div>
            <div class="text-h6 font-weight-black text-primary">$12,450.00</div>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-card rounded="lg" flat border class="pa-3">
            <div class="text-caption text-grey font-weight-bold">
              PENDING PAYMENTS
            </div>
            <div class="text-h6 font-weight-black text-warning">05 Staff</div>
          </v-card>
        </v-col>
      </v-row>

      <v-row class="mb-4" dense>
        <v-col cols="12" md="4">
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            label="Search staff..."
            variant="solo"
            flat
            density="compact"
            rounded="lg"
            hide-details
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            v-model="selectedMonth"
            :items="['January', 'February', 'March', 'April', 'May']"
            variant="solo"
            flat
            density="compact"
            rounded="lg"
            hide-details
          ></v-select>
        </v-col>
      </v-row>

      <v-card rounded="lg">
        <v-data-table :headers="headers" :items="payrollData" :search="search">
          <template v-slot:item.staff="{ item }">
            <div class="d-flex align-center py-2">
              <v-avatar size="32" class="mr-3 border">
                <v-img :src="item.avatar"></v-img>
              </v-avatar>
              <div>
                <div class="font-weight-bold">{{ item.name }}</div>
                <div class="text-grey text-caption">
                  {{ item.role }}
                </div>
              </div>
            </div>
          </template>

          <template v-slot:item.status="{ item }">
            <v-chip
              :color="item.status === 'Paid' ? 'success' : 'warning'"
              size="small"
              variant="tonal"
            >
              {{ item.status }}
            </v-chip>
          </template>

          <template v-slot:item.base="{ item }">
            <span>${{ item.base }}</span>
          </template>

          <template v-slot:item.total="{ item }">
            <span class="font-weight-black text-primary">
              ${{ item.total }}
            </span>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-eye-outline"
              variant="text"
              size="x-small"
              color="grey-darken-1"
              @click="viewSlip(item)"
            ></v-btn>
            <v-btn
              icon="mdi-pencil-outline"
              variant="text"
              size="x-small"
              color="primary"
              @click="editSalary(item)"
            ></v-btn>
          </template>
        </v-data-table>
      </v-card>
    </v-container>
  </v-container>
</template>

<script setup>
  import { ref } from 'vue'

  const search = ref('')
  const selectedMonth = ref('May')

  const headers = [
    { title: 'STAFF MEMBER', key: 'staff', align: 'start', sortable: true },
    { title: 'HOURS', key: 'hours', align: 'start' },
    { title: 'BASE SALARY', key: 'base', align: 'start' },
    { title: 'BONUS/TIPS', key: 'bonus', align: 'start' },
    { title: 'TOTAL', key: 'total', align: 'start' },
    { title: 'STATUS', key: 'status', align: 'start' },
    { title: '', key: 'actions', align: 'start', sortable: false }
  ]

  const payrollData = ref([
    {
      id: 1,
      name: 'Sopheap Meas',
      role: 'Chef',
      hours: 160,
      base: '1200',
      bonus: '150',
      total: '1350',
      status: 'Paid',
      avatar: 'https://i.pravatar.cc/150?u=1'
    },
    {
      id: 2,
      name: 'Borith Keo',
      role: 'Server',
      hours: 145,
      base: '600',
      bonus: '450',
      total: '1050',
      status: 'Pending',
      avatar: 'https://i.pravatar.cc/150?u=2'
    },
    {
      id: 3,
      name: 'Dara Sam',
      role: 'Manager',
      hours: 160,
      base: '1800',
      bonus: '0',
      total: '1800',
      status: 'Paid',
      avatar: 'https://i.pravatar.cc/150?u=3'
    },
    {
      id: 4,
      name: 'Chanthou Ly',
      role: 'Cashier',
      hours: 155,
      base: '750',
      bonus: '120',
      total: '870',
      status: 'Pending',
      avatar: 'https://i.pravatar.cc/150?u=4'
    }
  ])
</script>

<style scoped></style>
