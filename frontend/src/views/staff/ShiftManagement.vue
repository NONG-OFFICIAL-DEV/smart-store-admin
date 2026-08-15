<template>
  <div>
    <v-container fluid class="pa-0">
      <custom-title
        icon="mdi-calendar-clock"
        :title="$t('shifts.title')"
        :subtitle="$t('shifts.subtitle')"
      >
        <template #right>
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            rounded="lg"
            elevation="0"
            class="text-none px-6 ms-4"
            @click="openCreate"
          >
            {{ $t('btn.create') }}
          </v-btn>
        </template>
      </custom-title>
      <v-row dense align="center" class="mb-4">
        <v-col cols="12" sm="auto">
          <v-btn-toggle
            v-model="filter"
            color="primary"
            variant="tonal"
            rounded="lg"
            mandatory
            divided
          >
            <v-btn value="all" class="text-none px-4">
              {{ $t('status.all') }}
            </v-btn>
            <v-btn value="active" class="text-none px-4">
              {{ $t('status.active') }}
            </v-btn>
            <v-btn value="inactive" class="text-none px-4">
              {{ $t('status.inactive') }}
            </v-btn>
          </v-btn-toggle>
        </v-col>
      </v-row>
      <!-- ── Table ────────────────────────────────────────────────────────── -->
      <v-card rounded="lg" border elevation="0" class="pa-4">
        <AppTable
          ref="tableRef"
          :headers="headers"
          :fetch-fn="fetchShifts"
          :filters="filters"
          item-label="shifts"
        >
          <!-- Name + type -->
          <template #[`item.name`]="{ item }">
            <div>
              <div class="text-body-2 font-weight-bold">{{ item.name }}</div>
              <v-chip
                size="x-small"
                variant="tonal"
                :color="shiftTypeColor(item.shift_type)"
                class="mt-1 font-weight-bold"
              >
                {{ item.shift_type || $t('shifts.card.custom') }}
              </v-chip>
            </div>
          </template>

          <!-- Start -->
          <template #[`item.start`]="{ item }">
            <div class="text-body-2 font-weight-medium">
              {{ item.start_time }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ item.duration }}
              <span v-if="item.break_minutes">
                · {{ $t('shifts.card.break', { minutes: item.break_minutes }) }}
              </span>
            </div>
          </template>

          <!-- End -->
          <template #[`item.end`]="{ item }">
            <div class="text-body-2 font-weight-medium">
              {{ item.end_time }}
              <v-icon
                v-if="item.is_overnight"
                icon="mdi-weather-night"
                size="14"
                color="indigo"
                class="ml-1"
              />
            </div>
            <div v-if="item.is_overnight" class="text-caption text-indigo">
              {{ $t('shifts.card.overnight') }}
            </div>
          </template>

          <!-- Status -->
          <template #[`item.is_active`]="{ item }">
            <v-chip
              :color="item.is_active ? 'success' : 'grey'"
              size="x-small"
              variant="tonal"
            >
              {{ item.is_active ? $t('status.active') : $t('status.inactive') }}
            </v-chip>
          </template>

          <!-- Actions -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex gap-1 justify-end">
              <v-btn
                icon="mdi-account-plus-outline"
                size="small"
                variant="text"
                color="success"
                :to="{ name: 'ShiftAssignments', query: { shift_id: item.id } }"
              />
              <v-btn
                icon="mdi-pencil-outline"
                size="small"
                variant="text"
                color="primary"
                @click="openEdit(item)"
              />
              <v-btn
                icon="mdi-delete-outline"
                size="small"
                variant="text"
                color="error"
                @click="confirmDelete(item)"
              />
            </div>
          </template>
        </AppTable>
      </v-card>
    </v-container>

    <ShiftFormDialog
      v-model="dialog"
      :item="selectedItem"
      :loading="saving"
      @save="handleSave"
    />
  </div>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useShiftStore } from '@/stores/shiftStore'
  import { getAllShiftsApi } from '@/api/shiftService'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  import ShiftFormDialog from '@/components/staff/ShiftFormDialog.vue'
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()
  const shiftStore = useShiftStore()
  const { confirm, notif } = useAppUtils()

  const tableRef = ref(null)
  const filter = ref('all')
  const dialog = ref(false)
  const saving = ref(false)
  const selectedItem = ref(null)

  // ── Table headers — only `name` is a meaningful sort (ShiftRepository's
  // applySort has no whitelist and falls back to it by default). ────────────
  const headers = computed(() => [
    { title: t('shifts.table.name'), key: 'name', sortable: true },
    { title: t('shifts.table.start'), key: 'start', sortable: false },
    { title: t('shifts.table.end'), key: 'end', sortable: false },
    { title: t('shifts.table.status'), key: 'is_active', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ])

  // ── Server-driven filters — matches ShiftRepository's contract
  // (search/sortBy/sortDesc/page/perPage + is_active). ─────────────────────
  const filters = computed(() => ({
    is_active:
      filter.value === 'active'
        ? true
        : filter.value === 'inactive'
          ? false
          : undefined
  }))

  async function fetchShifts(params) {
    const { data } = await getAllShiftsApi(params)
    return { items: data.data, total: data.meta.total }
  }


  // ── Actions ───────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }
  const openEdit = item => {
    selectedItem.value = { ...item }
    dialog.value = true
  }

  const confirmDelete = item => {
    confirm({
      title: t('shifts.confirm_delete.title'),
      message: t('shifts.confirm_delete.message', { name: item.name }),
      options: { type: 'warning', width: 520 },
      agree: async () => {
        saving.value = true
        try {
          await shiftStore.deleteShift(item.id)
          notif(t('shifts.messages.removed'), { type: 'success' })
          tableRef.value?.refresh()
        } catch {
          notif(t('shifts.messages.remove_failed'), { type: 'error' })
        } finally {
          saving.value = false
        }
      }
    })
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      payload.id
        ? await shiftStore.updateShift(payload.id, payload)
        : await shiftStore.createShift(payload)
      dialog.value = false
      notif(
        payload.id
          ? t('shifts.messages.updated')
          : t('shifts.messages.created'),
        {
          type: 'success'
        }
      )
      tableRef.value?.refresh()
    } catch {
      notif(t('shifts.messages.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const shiftTypeColor = type =>
    ({
      morning: '#f59e0b',
      afternoon: '#3b82f6',
      evening: '#8b5cf6',
      full_day: '#10b981',
      split: '#ef4444'
    })[type] || '#6b7280'
</script>

<style scoped>
  .gap-1 {
    gap: 6px;
  }
</style>
