<template>
  <div>
    <v-container fluid class="pa-0">
      <custom-title
        icon="mdi-calendar-check-outline"
        :title="$t('menu.reservations')"
        :subtitle="$t('reservations.subtitle')"
      >
        <template #right>
          <v-btn
            color="primary"
            prepend-icon="mdi-calendar-plus"
            rounded="lg"
            elevation="0"
            class="ms-2"
            @click="openCreate"
          >
            {{ $t('reservations.new') }}
          </v-btn>
        </template>
      </custom-title>

      <!-- Filters -->
      <v-row dense align="center" class="mb-2">
        <v-col cols="12" sm="6" md="2">
          <div class="d-flex ga-2 mb-4">
            <v-btn-toggle
              v-model="dateQuickFilter"
              color="primary"
              variant="outlined"
              rounded="lg"
              divided
            >
              <v-btn value="today" size="small" class="text-none">
                {{ $t('common.today') }}
              </v-btn>
              <v-btn value="all" size="small" class="text-none">
                {{ $t('common.all') }}
              </v-btn>
            </v-btn-toggle>
          </div>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-date-input
            v-model="filterDate"
            :label="$t('form.date')"
            rounded="lg"
            clearable
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.status"
            :items="statusOptions"
            item-title="label"
            item-value="value"
            :label="$t('form.status')"
            variant="outlined"
            rounded="lg"
            clearable
            prepend-inner-icon="mdi-filter-outline"
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.table_id"
            :items="tables"
            item-value="id"
            :label="$t('reservations.field_table')"
            variant="outlined"
            rounded="lg"
            clearable
            prepend-inner-icon="mdi-table-chair"
          >
            <template #item="{ props, item }">
              <v-list-item
                v-bind="props"
                :title="
                  $t('reservations.table_number', {
                    n: item.raw.table_number
                  })
                "
              />
            </template>
            <template #selection="{ item }">
              {{
                $t('reservations.table_number', { n: item.raw?.table_number })
              }}
            </template>
          </v-select>
        </v-col>
      </v-row>

      <!-- Reservations table -->
      <v-card rounded="lg" border elevation="0" class="pa-4">
        <AppTable
          ref="tableRef"
          :headers="headers"
          :fetch-fn="fetchReservationsForTable"
          :filters="tableFilters"
          :show-search="true"
          :item-label="$t('menu.reservations')"
        >
          <!-- Guest -->
          <template #item.customer_name="{ item }">
            <div class="d-flex align-center ga-3">
              <v-avatar color="primary" variant="tonal" size="36" rounded="lg">
                <span class="text-caption font-weight-bold">
                  {{ initials(item.customer_name) }}
                </span>
              </v-avatar>
              <div>
                <div class="d-flex align-center ga-1">
                  <span class="text-body-2 font-weight-medium">{{ item.customer_name }}</span>
                  <v-tooltip v-if="item.notes" :text="item.notes">
                    <template #activator="{ props: tp }">
                      <v-icon v-bind="tp" icon="mdi-note-text-outline" size="14" color="grey" />
                    </template>
                  </v-tooltip>
                </div>
                <div v-if="item.customer_phone" class="text-caption text-grey">
                  {{ item.customer_phone }}
                </div>
              </div>
            </div>
          </template>

          <!-- Date & Time -->
          <template #item.reserved_at="{ item }">
            <div class="text-body-2">{{ formatDateTime(item.reserved_at) }}</div>
            <div v-if="item.duration_minutes" class="text-caption text-grey">
              {{ item.duration_minutes }}m
            </div>
          </template>

          <!-- Party -->
          <template #item.party_size="{ item }">
            <span class="text-body-2">{{ item.party_size }} {{ $t('reservations.guests') }}</span>
          </template>

          <!-- Table -->
          <template #item.table="{ item }">
            <v-chip v-if="item.table" size="small" variant="tonal" color="primary">
              {{ $t('reservations.table_number', { n: item.table.table_number }) }}
            </v-chip>
            <span v-else class="text-grey">—</span>
          </template>

          <!-- Status -->
          <template #item.status="{ item }">
            <AppStatusChip :status="item.status" :map="reservationStatusMap" size="small" />
          </template>

          <!-- Actions — status transitions live in one menu instead of a
               row of buttons, so the row stays compact regardless of how
               many transitions apply to the current status. -->
          <template #item.actions="{ item }">
            <div class="d-flex justify-end ga-1">
              <v-menu v-if="nextActions(item).length">
                <template #activator="{ props: mp }">
                  <v-btn v-bind="mp" icon="mdi-dots-vertical" size="small" variant="text" />
                </template>
                <v-list density="compact">
                  <v-list-item
                    v-for="action in nextActions(item)"
                    :key="action.status"
                    :prepend-icon="action.icon"
                    :base-color="action.color"
                    @click="updateStatus(item, action.status)"
                  >
                    <v-list-item-title>{{ action.label }}</v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-menu>
              <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(item)" />
              <v-btn
                icon="mdi-delete-outline"
                size="small"
                variant="text"
                color="error"
                @click="confirmDelete(item)"
              />
            </div>
          </template>

          <!-- Empty -->
          <template #no-data>
            <div class="text-center py-16">
              <v-icon
                icon="mdi-calendar-blank-outline"
                size="64"
                color="grey-lighten-1"
                class="mb-4"
              />
              <p class="text-h6 text-medium-emphasis mb-1">
                {{ $t('reservations.empty') }}
              </p>
              <p class="text-body-2 text-grey mb-4">
                {{ $t('reservations.empty_period') }}
              </p>
              <v-btn
                color="primary"
                variant="tonal"
                prepend-icon="mdi-calendar-plus"
                @click="openCreate"
              >
                {{ $t('reservations.add') }}
              </v-btn>
            </div>
          </template>
        </AppTable>
      </v-card>
    </v-container>

    <!-- Reservation Form Dialog -->
    <ReservationFormDialog
      v-model="dialog"
      :item="selectedItem"
      :tables="tables"
      :loading="saving"
      @save="handleSave"
    />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRoute } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { useReservationStore } from '@/stores/reservationStore'
  import { useTableStore } from '@/stores/tableStore'
  import ReservationFormDialog from '@/components/reservations/ReservationFormDialog.vue'
  import { AppTable, AppStatusChip, useAppUtils } from '@nong-official-dev/core'
  import { useDate } from '@/composables/useDate'
  import { useAvatar } from '@/composables/useAvatar'
  const { confirm, notif } = useAppUtils()
  const { formatLocalDate, formatShortDateTime: formatDateTime } = useDate()
  const { getInitials } = useAvatar()
  const { t } = useI18n()
  const route = useRoute()
  const reservationStore = useReservationStore()
  const tableStore = useTableStore()

  const { tables } = storeToRefs(tableStore)

  const tableRef = ref(null)
  const saving = ref(false)
  const dialog = ref(false)
  const selectedItem = ref(null)

  const todayStr = formatLocalDate(new Date())

  // ── Filters — deep-watched by AppTable, auto-refetches on change (free-text
  // search is AppTable's own built-in field). ───────────────────────────────────
  const filters = ref({
    date: todayStr,
    status: null,
    table_id: route.query.table_id || null
  })

  const tableFilters = computed(() => ({
    date: filters.value.date || undefined,
    status: filters.value.status || undefined,
    table_id: filters.value.table_id || undefined
  }))

  // ── AppTable's fetch-fn contract: params -> { items, total } ─────────────────
  const fetchReservationsForTable = params =>
    reservationStore.fetchReservations(params)

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    { title: t('reservations.field.guest_name'), key: 'customer_name', sortable: true },
    { title: t('reservations.field.reserved_at'), key: 'reserved_at', sortable: true },
    { title: t('reservations.field.party_size'), key: 'party_size', sortable: true },
    { title: t('reservations.field.table'), key: 'table', sortable: false },
    { title: t('form.status'), key: 'status', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // filters.date stays a "YYYY-MM-DD" string (used for startsWith comparisons
  // below) — this proxy is what the date picker actually binds to.
  const filterDate = computed({
    get: () => (filters.value.date ? new Date(filters.value.date) : null),
    set: v => {
      filters.value.date = v instanceof Date ? formatLocalDate(v) : v
    }
  })

  // "Today" / "All" quick toggle — reads/writes the same filters.date, so
  // picking a specific day via the date input below just leaves neither
  // button highlighted (v-btn-toggle allows no selection to match).
  const dateQuickFilter = computed({
    get: () => {
      if (filters.value.date === todayStr) return 'today'
      if (!filters.value.date) return 'all'
      return null
    },
    set: v => {
      if (v === 'today') filters.value.date = todayStr
      else if (v === 'all') filters.value.date = null
    }
  })

  const statusOptions = [
    { value: 'pending', label: t('status.pending') },
    { value: 'confirmed', label: t('order.status.confirmed') },
    { value: 'seated', label: t('reservations.status.seated') },
    { value: 'completed', label: t('status.completed') },
    { value: 'no_show', label: t('reservations.no_show') },
    { value: 'cancelled', label: t('status.cancelled') }
  ]

  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }
  const openEdit = r => {
    selectedItem.value = { ...r }
    dialog.value = true
  }
  // Which status transitions apply right now — mirrors the old per-status
  // button branches, just rendered as one menu instead of a button row.
  const nextActions = res => {
    switch (res.status) {
      case 'pending':
        return [
          { status: 'confirmed', label: t('btn.confirm'), icon: 'mdi-check', color: 'success' },
          { status: 'cancelled', label: t('btn.cancel'), icon: 'mdi-close', color: 'error' }
        ]
      case 'confirmed':
        return [
          { status: 'seated', label: t('reservations.seat_now'), icon: 'mdi-seat', color: 'primary' },
          { status: 'no_show', label: t('reservations.no_show'), icon: 'mdi-account-off', color: 'warning' }
        ]
      case 'seated':
        return [
          { status: 'completed', label: t('reservations.complete'), icon: 'mdi-check-all', color: 'grey' }
        ]
      default:
        return []
    }
  }

  // Stable backend error_code -> local translation key. The backend's own
  // `message` is English-only (it's a fallback for non-UI API consumers),
  // so any error the UI wants properly translated needs an entry here.
  const ERROR_CODE_KEYS = {
    reservation_table_conflict: 'reservations.messages.table_conflict'
  }
  const resolveErrorMessage = (err, fallbackKey) => {
    const code = err.response?.data?.code
    if (code && ERROR_CODE_KEYS[code]) return t(ERROR_CODE_KEYS[code])
    return err.response?.data?.message ?? t(fallbackKey)
  }

  const updateStatus = async (res, status) => {
    saving.value = true
    try {
      await reservationStore.updateReservation(res.id, { status })
      notif(t('reservations.status_updated', { status }), {
        type: 'success'
      })
      tableRef.value?.refresh()
    } catch (err) {
      notif(resolveErrorMessage(err, 'reservations.messages.save_failed'), {
        type: 'error'
      })
    } finally {
      saving.value = false
    }
  }

  const confirmDelete = res => {
    confirm({
      title: t('reservations.delete_title'),
      message: t('reservations.delete_message', {
        name: res.customer_name
      }),
      options: { type: 'warning', width: 500 },
      agree: async () => {
        // saving belongs here, not around confirm() itself — confirm() just
        // opens the dialog and returns immediately, so wrapping it made
        // `saving` flip back to false before the user had even clicked
        // agree, and meant a real delete failure was never actually caught.
        saving.value = true
        try {
          await reservationStore.deleteReservation(res.id)
          notif(t('reservations.messages.deleted'), {
            type: 'success'
          })
          tableRef.value?.refresh()
        } catch (err) {
          notif(err.response?.data?.message ?? t('reservations.messages.delete_failed'), {
            type: 'error'
          })
        } finally {
          saving.value = false
        }
      },
      cancel: () => {}
    })
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      payload.id
        ? await reservationStore.updateReservation(payload.id, payload)
        : await reservationStore.createReservation(payload)
      dialog.value = false
      notif(
        payload.id
          ? t('reservations.messages.updated')
          : t('reservations.messages.created'),
        {
          type: 'success'
        }
      )
      tableRef.value?.refresh()
    } catch (err) {
      notif(resolveErrorMessage(err, 'reservations.messages.save_failed'), {
        type: 'error'
      })
    } finally {
      saving.value = false
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const initials = name => getInitials(name)

  const reservationStatusMap = {
    pending: { color: 'warning', label: t('reservations.status.pending') },
    confirmed: { color: 'success', label: t('reservations.status.confirmed') },
    seated: { color: 'info', label: t('reservations.status.seated') },
    completed: { color: 'grey', label: t('reservations.status.completed') },
    no_show: { color: 'error', label: t('reservations.status.no_show') },
    cancelled: { color: 'grey', label: t('reservations.status.cancelled') }
  }


  onMounted(() => tableStore.fetchTables())
</script>
