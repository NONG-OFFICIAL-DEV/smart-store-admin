<template>
  <v-container fluid class="pa-0">
    <custom-title icon="mdi-clock-check-outline">
      Attendance
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-clock-check-outline"
          rounded="lg"
          class="text-none font-weight-bold"
          @click="openCheckIn"
        >
          Check-in / Out
        </v-btn>
      </template>
    </custom-title>

    <v-row class="mb-6">
      <v-col
        v-for="stat in summaryStats"
        :key="stat.title"
        cols="12"
        sm="6"
        md="3"
      >
        <v-card flat rounded="xl" class="border pa-4">
          <div class="d-flex align-center mb-2">
            <v-avatar :color="stat.color + '-lighten-5'" size="40" rounded="lg">
              <v-icon :color="stat.color" :icon="stat.icon" />
            </v-avatar>
            <v-spacer />
            <span class="text-h5 font-weight-black">{{ stat.value }}</span>
          </div>
          <div class="text-caption font-weight-bold text-grey">
            {{ stat.title }}
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-card flat rounded="xl" class="border">
      <v-toolbar color="white" flat class="px-4 border-b">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          label="Search Employee..."
          variant="solo-filled"
          flat
          hide-details
          rounded="lg"
          density="compact"
          max-width="300"
        />
        <v-spacer />
        <v-btn icon="mdi-filter-variant" variant="text" color="grey" />
        <v-btn icon="mdi-download" variant="text" color="grey" />
      </v-toolbar>

      <v-data-table
        :headers="headers"
        :items="attendanceData"
        :search="search"
        hover
        class="attendance-table"
      >
        <template #[`item.employee`]="{ item }">
          <div class="d-flex align-center py-2">
            <v-avatar size="36" color="primary-lighten-4" class="mr-3">
              <span class="text-caption font-weight-bold">
                {{ getInitials(item.employee) }}
              </span>
            </v-avatar>
            <div class="font-weight-bold text-grey-darken-3">
              {{ item.employee }}
            </div>
          </div>
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip
            :color="getStatusColor(item.status)"
            size="small"
            class="text-uppercase font-weight-black"
            label
          >
            {{ item.status }}
          </v-chip>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn
            icon="mdi-eye-outline"
            variant="text"
            color="primary"
            size="small"
          />
          <v-btn
            icon="mdi-pencil-outline"
            variant="text"
            color="grey"
            size="small"
          />
        </template>
      </v-data-table>
    </v-card>

    <CheckInDialog
      v-model="attendanceDialog"
      :employees="staffList"
      @save="handleCheckIn"
    />
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import CheckInDialog from '@/components/staffs/CheckInDialog.vue'
  import { useStaffStore } from '../../stores/employeeStore'

  const staffStore = useStaffStore()

  const search = ref('')
  const attendanceDialog = ref(false)

  const staffList = computed(() => staffStore.employees)

  onMounted(() => {
    staffStore.fetchEmployees()
  })
  const headers = [
    { title: 'Employee', key: 'employee' },
    { title: 'Date', key: 'date' },
    { title: 'Check In', key: 'checkIn' },
    { title: 'Check Out', key: 'checkOut' },
    { title: 'Status', key: 'status' },
    { title: 'Actions', key: 'actions', align: 'end', sortable: false }
  ]

  const attendanceData = ref([
    {
      id: 1,
      employee: 'John Doe',
      date: '2026-02-02',
      checkIn: '08:55 AM',
      checkOut: '05:30 PM',
      status: 'On Time'
    },
    {
      id: 2,
      employee: 'Jane Smith',
      date: '2026-02-02',
      checkIn: '09:15 AM',
      checkOut: '06:00 PM',
      status: 'Late'
    },
    {
      id: 3,
      employee: 'Mike Ross',
      date: '2026-02-02',
      checkIn: '-',
      checkOut: '-',
      status: 'Absent'
    },
    {
      id: 4,
      employee: 'Sarah Connor',
      date: '2026-02-02',
      checkIn: '08:45 AM',
      checkOut: '-',
      status: 'On Time'
    }
  ])

  const summaryStats = computed(() => [
    {
      title: 'Total Employee',
      value: '150',
      icon: 'mdi-account-group',
      color: 'blue'
    },
    { title: 'On Time', value: '124', icon: 'mdi-clock-check', color: 'green' },
    { title: 'Late', value: '12', icon: 'mdi-clock-alert', color: 'orange' },
    { title: 'Absent', value: '14', icon: 'mdi-account-off', color: 'red' }
  ])

  const getStatusColor = status => {
    const colors = {
      'On Time': 'success',
      Late: 'warning',
      Absent: 'error',
      'On Leave': 'info'
    }
    return colors[status] || 'grey'
  }

  const getInitials = name =>
    name
      .split(' ')
      .map(n => n[0])
      .join('')
      
  const openCheckIn = () => (attendanceDialog.value = true)
  const handleCheckIn = data => {
    console.log('Attendance saved:', data)
    attendanceDialog.value = false
  }
</script>

<style scoped>
  .attendance-table :deep(thead th) {
    font-weight: 800 !important;
    color: #757575 !important;
    text-transform: uppercase;
    font-size: 0.75rem;
  }
</style>
