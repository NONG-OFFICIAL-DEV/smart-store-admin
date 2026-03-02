<template>
  <custom-title icon="mdi-calendar-check-outline" title="Reservations" subtitle="Bookings · Seating · Walk-ins">
    <template #right>
      <v-btn
        color="primary"
        prepend-icon="mdi-calendar-plus"
        rounded="lg"
        @click="openCreate"
      >
        New Reservation
      </v-btn>
    </template>
  </custom-title>

  <v-container fluid class="pa-0">
    <!-- Stats -->
    <v-row class="mb-5">
      <v-col v-for="(stat, i) in stats" :key="i" cols="6" sm="3">
        <v-card
          rounded="xl"
          border
          elevation="0"
          class="pa-4 d-flex align-center gap-3"
        >
          <v-avatar :color="stat.color" variant="tonal" rounded="lg" size="44">
            <v-icon :icon="stat.icon" size="20" />
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            <div class="text-caption text-grey">{{ stat.label }}</div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filters -->
    <v-card rounded="xl" border elevation="0" class="mb-5">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="6" md="3">
            <v-text-field
              v-model="filters.date"
              type="date"
              label="Date"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              prepend-inner-icon="mdi-calendar"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              item-title="label"
              item-value="value"
              label="Status"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              prepend-inner-icon="mdi-filter-outline"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-select
              v-model="filters.table_id"
              :items="tables"
              item-value="id"
              label="Table"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              prepend-inner-icon="mdi-table-chair"
            >
              <template #item="{ props, item }">
                <v-list-item
                  v-bind="props"
                  :title="`Table ${item.raw.table_number}`"
                />
              </template>
              <template #selection="{ item }">
                Table {{ item.raw?.table_number }}
              </template>
            </v-select>
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-text-field
              v-model="filters.search"
              prepend-inner-icon="mdi-magnify"
              placeholder="Name or phone..."
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              bg-color="white"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Status tabs -->
    <v-tabs
      v-model="activeTab"
      color="primary"
      density="compact"
      class="bg-grey-lighten-4 rounded-lg pa-1 mb-5"
      hide-slider
    >
      <v-tab
        v-for="s in statusTabs"
        :key="s.value"
        :value="s.value"
        rounded="md"
        class="text-none"
      >
        <v-icon :icon="s.icon" size="16" class="mr-2" />
        {{ s.label }}
        <v-chip
          v-if="tabCount(s.value)"
          size="x-small"
          :color="s.color"
          class="ml-2"
        >
          {{ tabCount(s.value) }}
        </v-chip>
      </v-tab>
    </v-tabs>

    <!-- Reservations list -->
    <v-row v-if="!loading" dense>
      <v-col
        v-for="res in reservations"
        :key="res.id"
        cols="12"
        md="6"
        lg="4"
      >
        <v-card rounded="xl" border elevation="0" class="res-card">
          <!-- Status bar top -->
          <div
            class="res-status-bar"
            :style="{ background: statusColor(res.status) }"
          />

          <v-card-text class="pa-4">
            <!-- Header row -->
            <div class="d-flex align-start justify-space-between mb-3">
              <div class="d-flex align-center gap-3">
                <v-avatar
                  color="primary"
                  variant="tonal"
                  size="40"
                  rounded="lg"
                >
                  <span class="text-body-2 font-weight-bold">
                    {{ initials(res.customer_name) }}
                  </span>
                </v-avatar>
                <div>
                  <div class="text-body-1 font-weight-bold">
                    {{ res.customer_name }}
                  </div>
                  <div v-if="res.customer_phone" class="text-caption text-grey">
                    <v-icon icon="mdi-phone-outline" size="12" class="mr-1" />
                    {{ res.customer_phone }}
                  </div>
                </div>
              </div>
              <v-chip
                :color="statusColor(res.status)"
                size="small"
                variant="tonal"
              >
                {{ res.status }}
              </v-chip>
            </div>

            <!-- Details -->
            <div class="d-flex gap-3 mb-3 flex-wrap">
              <div class="d-flex align-center gap-1 text-caption">
                <v-icon icon="mdi-clock-outline" size="14" color="grey" />
                <span>{{ formatDateTime(res.reserved_at) }}</span>
              </div>
              <div class="d-flex align-center gap-1 text-caption">
                <v-icon
                  icon="mdi-account-group-outline"
                  size="14"
                  color="grey"
                />
                <span>{{ res.party_size }} guests</span>
              </div>
              <div
                v-if="res.table"
                class="d-flex align-center gap-1 text-caption"
              >
                <v-icon icon="mdi-table-chair" size="14" color="grey" />
                <span>Table {{ res.table?.table_number }}</span>
              </div>
              <div
                v-if="res.duration_minutes"
                class="d-flex align-center gap-1 text-caption"
              >
                <v-icon icon="mdi-timer-outline" size="14" color="grey" />
                <span>{{ res.duration_minutes }}m</span>
              </div>
            </div>

            <div
              v-if="res.notes"
              class="text-caption text-grey bg-grey-lighten-5 rounded-lg pa-2 mb-3"
            >
              <v-icon icon="mdi-note-text-outline" size="12" class="mr-1" />
              {{ res.notes }}
            </div>

            <!-- Action buttons -->
            <div class="d-flex gap-2 flex-wrap">
              <!-- Status transitions -->
              <template v-if="res.status === 'pending'">
                <v-btn
                  size="x-small"
                  color="success"
                  variant="tonal"
                  rounded="lg"
                  class="text-none"
                  prepend-icon="mdi-check"
                  @click="updateStatus(res, 'confirmed')"
                >
                  Confirm
                </v-btn>
                <v-btn
                  size="x-small"
                  color="error"
                  variant="tonal"
                  rounded="lg"
                  class="text-none"
                  prepend-icon="mdi-close"
                  @click="updateStatus(res, 'cancelled')"
                >
                  Cancel
                </v-btn>
              </template>
              <template v-else-if="res.status === 'confirmed'">
                <v-btn
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  rounded="lg"
                  class="text-none"
                  prepend-icon="mdi-seat"
                  @click="updateStatus(res, 'seated')"
                >
                  Seat Now
                </v-btn>
                <v-btn
                  size="x-small"
                  color="warning"
                  variant="tonal"
                  rounded="lg"
                  class="text-none"
                  prepend-icon="mdi-account-off"
                  @click="updateStatus(res, 'no_show')"
                >
                  No Show
                </v-btn>
              </template>
              <template v-else-if="res.status === 'seated'">
                <v-btn
                  size="x-small"
                  color="grey"
                  variant="tonal"
                  rounded="lg"
                  class="text-none"
                  prepend-icon="mdi-check-all"
                  @click="updateStatus(res, 'completed')"
                >
                  Complete
                </v-btn>
              </template>

              <v-spacer />
              <v-btn
                icon="mdi-pencil-outline"
                size="x-small"
                variant="text"
                @click="openEdit(res)"
              />
              <v-btn
                icon="mdi-delete-outline"
                size="x-small"
                variant="text"
                color="error"
                @click="confirmDelete(res)"
              />
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Empty -->
      <v-col v-if="!reservations.length" cols="12">
        <div class="text-center py-16">
          <v-icon
            icon="mdi-calendar-blank-outline"
            size="64"
            color="grey-lighten-1"
            class="mb-4"
          />
          <p class="text-h6 text-medium-emphasis mb-1">No reservations found</p>
          <p class="text-body-2 text-grey mb-4">
            {{
              filters.search
                ? 'Try a different search'
                : 'No reservations for this period'
            }}
          </p>
          <v-btn
            color="primary"
            variant="tonal"
            prepend-icon="mdi-calendar-plus"
            @click="openCreate"
          >
            Add Reservation
          </v-btn>
        </div>
      </v-col>
    </v-row>

    <v-row v-else dense>
      <v-col v-for="n in 6" :key="n" cols="12" md="6" lg="4">
        <v-skeleton-loader type="card" rounded="xl" border />
      </v-col>
    </v-row>
  </v-container>

  <!-- Reservation Form Dialog -->
  <ReservationFormDialog
    v-model="dialog"
    :item="selectedItem"
    :tables="tables"
    :loading="saving"
    @save="handleSave"
  />

  <!-- Delete Confirm -->
  <v-dialog v-model="deleteDialog" max-width="400" persistent>
    <v-card rounded="xl" elevation="0" border>
      <v-card-text class="pa-6 text-center">
        <v-avatar color="error" size="56" rounded="lg" class="mb-4">
          <v-icon icon="mdi-delete-outline" size="28" />
        </v-avatar>
        <h3 class="text-h6 font-weight-bold mb-2">Delete Reservation?</h3>
        <p class="text-body-2 text-medium-emphasis">
          Reservation for
          <strong>{{ deleteTarget?.customer_name }}</strong>
          will be removed.
        </p>
      </v-card-text>
      <v-card-actions class="px-6 pb-6 pt-0 gap-3">
        <v-btn block variant="tonal" rounded="lg" @click="deleteDialog = false">
          Cancel
        </v-btn>
        <v-btn
          block
          color="error"
          variant="flat"
          rounded="lg"
          :loading="saving"
          @click="handleDelete"
        >
          Delete
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    location="bottom right"
    rounded="lg"
    :timeout="3000"
  >
    {{ snackbar.message }}
  </v-snackbar>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRoute } from 'vue-router'
  import { useReservationStore } from '@/stores/reservationStore'
  import { useTableStore } from '@/stores/tableStore'
  import ReservationFormDialog from '@/components/reservations/ReservationFormDialog.vue'

  const route = useRoute()
  const reservationStore = useReservationStore()
  const tableStore = useTableStore()

  const { reservations, loading } = storeToRefs(reservationStore)
  const { tables } = storeToRefs(tableStore)

  const saving = ref(false)
  const dialog = ref(false)
  const deleteDialog = ref(false)
  const selectedItem = ref(null)
  const deleteTarget = ref(null)
  const activeTab = ref('all')
  const snackbar = ref({ show: false, message: '', color: 'success' })

  const filters = ref({
    date: new Date().toISOString().split('T')[0],
    status: null,
    table_id: route.query.table_id || null,
    search: ''
  })

  const showSnack = (m, c = 'success') => {
    snackbar.value = { show: true, message: m, color: c }
  }

  const statusTabs = [
    {
      value: 'all',
      label: 'All',
      icon: 'mdi-calendar-month',
      color: 'primary'
    },
    {
      value: 'pending',
      label: 'Pending',
      icon: 'mdi-clock-outline',
      color: 'warning'
    },
    {
      value: 'confirmed',
      label: 'Confirmed',
      icon: 'mdi-check-circle',
      color: 'success'
    },
    { value: 'seated', label: 'Seated', icon: 'mdi-seat', color: 'blue' },
    { value: 'completed', label: 'Done', icon: 'mdi-check-all', color: 'grey' },
    {
      value: 'no_show',
      label: 'No Show',
      icon: 'mdi-account-off',
      color: 'error'
    }
  ]

  const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'seated', label: 'Seated' },
    { value: 'completed', label: 'Completed' },
    { value: 'no_show', label: 'No Show' },
    { value: 'cancelled', label: 'Cancelled' }
  ]

  const stats = computed(() => {
    const list = reservations.value
    const today = filters.value.date
    return [
      {
        label: 'Today',
        value: list.filter(r => r.reserved_at?.startsWith(today)).length,
        icon: 'mdi-calendar-today',
        color: 'primary'
      },
      {
        label: 'Pending',
        value: list.filter(r => r.status === 'pending').length,
        icon: 'mdi-clock-outline',
        color: 'warning'
      },
      {
        label: 'Seated',
        value: list.filter(r => r.status === 'seated').length,
        icon: 'mdi-seat',
        color: 'blue'
      },
      {
        label: 'Completed',
        value: list.filter(r => r.status === 'completed').length,
        icon: 'mdi-check-all',
        color: 'success'
      }
    ]
  })

  const tabCount = status => {
    if (status === 'all') return 0
    return reservations.value.filter(r => r.status === status).length
  }

  const filteredReservations = computed(() => {
    let list = reservations.value

    if (activeTab.value !== 'all')
      list = list.filter(r => r.status === activeTab.value)
    if (filters.value.status)
      list = list.filter(r => r.status === filters.value.status)
    if (filters.value.table_id)
      list = list.filter(r => r.table_id === filters.value.table_id)
    if (filters.value.date)
      list = list.filter(r => r.reserved_at?.startsWith(filters.value.date))
    if (filters.value.search) {
      const q = filters.value.search.toLowerCase()
      list = list.filter(
        r =>
          r.customer_name?.toLowerCase().includes(q) ||
          r.customer_phone?.includes(q)
      )
    }
    return list
  })

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }
  const openEdit = r => {
    selectedItem.value = { ...r }
    dialog.value = true
  }
  const confirmDelete = r => {
    deleteTarget.value = r
    deleteDialog.value = true
  }

  const updateStatus = async (res, status) => {
    saving.value = true
    try {
      await reservationStore.updateReservation(res.id, { status })
      showSnack(`Reservation ${status}`)
    } catch {
      showSnack('Failed to update', 'error')
    } finally {
      saving.value = false
    }
  }

  const handleDelete = async () => {
    saving.value = true
    try {
      await reservationStore.deleteReservation(deleteTarget.value.id)
      deleteDialog.value = false
      showSnack('Reservation deleted')
    } catch {
      showSnack('Failed to delete', 'error')
    } finally {
      saving.value = false
    }
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      payload.id
        ? await reservationStore.updateReservation(payload.id, payload)
        : await reservationStore.createReservation(payload)
      dialog.value = false
      showSnack(payload.id ? 'Reservation updated' : 'Reservation created')
    } catch {
      showSnack('Failed to save', 'error')
    } finally {
      saving.value = false
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const initials = name =>
    name
      ? name
          .split(' ')
          .map(x => x[0])
          .join('')
          .toUpperCase()
          .slice(0, 2)
      : '?'

  const statusColor = s =>
    ({
      pending: '#ff9800',
      confirmed: '#4caf50',
      seated: '#2196f3',
      completed: '#9e9e9e',
      no_show: '#f44336',
      cancelled: '#9e9e9e'
    })[s] || '#9e9e9e'

  const formatDateTime = dt =>
    dt
      ? new Date(dt).toLocaleString([], {
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        })
      : '—'

  onMounted(async () => {
    await Promise.all([
      reservationStore.fetchReservations?.(),
      tableStore.fetchTables()
    ])
  })
</script>

<style scoped>
  .res-card {
    transition: all 0.2s ease;
    overflow: hidden;
  }
  .res-card:hover {
    transform: translateY(-2px);
  }
  .res-status-bar {
    height: 4px;
    width: 100%;
  }
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
