<template>
  <v-container fluid class="pa-0">
    <!-- Title & Buttons -->
    <custom-title
      icon="mdi-timeline-clock-outline"
      :title="$t('audit_log.system_audit_trail')"
      :subtitle="$t('audit_log.subtitle')"
    >
      <template #right>
        <v-btn
          color="green"
          prepend-icon="mdi-download"
          @click="toggleExportForm"
        >
          {{ $t('btn.export') }}
        </v-btn>
      </template>
    </custom-title>

    <!-- EXPORT FORM -->
    <v-card class="mb-4 pa-4" elevation="0" v-show="showExportForm">
      <v-row class="align-center">
        <v-col cols="12" md="4">
          <v-date-input
            v-model="exportDates.startDate"
            :label="$t('audit_log.start_date')"
            :error="!!exportErrors.start"
            :error-messages="exportErrors.start"
            clearable
          />
        </v-col>
        <v-col cols="12" md="4">
          <v-date-input
            v-model="exportDates.endDate"
            :label="$t('audit_log.end_date')"
            :error="!!exportErrors.end"
            :error-messages="exportErrors.end"
            clearable
          />
        </v-col>
        <v-col cols="12" md="4" class="d-flex align-center gap-2 mb-4">
          <v-btn
            color="green"
            prepend-icon="mdi-microsoft-excel"
            :disabled="!isDateRangeValid"
            @click="handleExport"
          >
            {{ $t('audit_log.generate') }}
          </v-btn>
          <v-btn variant="outlined" @click="closeExportForm">
            {{ $t('btn.cancel') }}
          </v-btn>
        </v-col>
      </v-row>
    </v-card>

    <!-- ── Filters ────────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-2">
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="filters.date_from" :label="$t('audit_log.start_date')" />
      </v-col>
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="filters.date_to" :label="$t('audit_log.end_date')" />
      </v-col>
    </v-row>

    <!-- Audit Table -->
    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable :headers="headers" :fetch-fn="fetchLogs" :filters="filters" item-label="entries">
        <template #[`item.user_name`]="{ item }">
          <div class="d-flex flex-column">
            <span class="text-body-2 font-weight-medium">
              {{ item.user_name || '—' }}
            </span>
            <span class="text-caption text-grey">{{ item.user_email }}</span>
          </div>
        </template>

        <template #[`item.action`]="{ item }">
          <v-chip :color="actionColor(item.action)" size="small" variant="tonal" label>
            {{ item.action }}
          </v-chip>
        </template>

        <template #[`item.entity_type`]="{ item }">
          <span v-if="item.entity_type" class="text-body-2">
            {{ item.entity_type }}
            <span v-if="item.entity_id" class="text-caption text-grey ms-1">
              #{{ item.entity_id.slice(0, 8) }}...
            </span>
          </span>
          <span v-else class="text-grey">—</span>
        </template>

        <template #[`item.ip_address`]="{ item }">
          <span class="text-caption font-weight-mono">{{ item.ip_address || '—' }}</span>
        </template>

        <template #[`item.created_at`]="{ item }">
          <span class="text-caption">{{ formatDateTime(item.created_at) }}</span>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn icon="mdi-arrow-right-circle" size="small" variant="text" color="primary" @click="goToDetails(item.id)" />
        </template>
      </AppTable>
    </v-card>
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRouter } from 'vue-router'
  import { useDate } from '@/composables/useDate'
  import { useAuditLogStore } from '@/stores/auditLogStore'
  import auditLogApi from '@/api/auditLog'
  import { AppTable, AppDatePicker } from '@nong-official-dev/core'

  const router = useRouter()
  const { t } = useI18n()
  const { formatDateTime } = useDate()
  const store = useAuditLogStore()

  const showExportForm = ref(false)

  const toggleExportForm = () => {
    showExportForm.value = !showExportForm.value
    clearExportErrors()
  }

  const goToDetails = id => router.push(`/audit-log/${id}`)

  // ── Action color helper ────────────────────────────────────────────────────────
  const actionColor = action => {
    if (action.includes('login')) return 'success'
    if (action.includes('logout')) return 'grey'
    if (action.includes('created')) return 'primary'
    if (action.includes('updated')) return 'warning'
    if (action.includes('deleted')) return 'error'
    return 'secondary'
  }

  // ── Filters — live-bound, AppTable refetches on change; text search is
  // AppTable's own built-in field (matches ActivityLogRepository::$searchable
  // = action/description/user_name/user_email/entity_type) ──────────────────────
  const filters = ref({ date_from: null, date_to: null })

  // Server-driven — matches BaseRepository::paginateServer()'s contract
  // (search/sortBy/sortDesc/page/perPage + date_from/date_to, see
  // ActivityLogRepository/AuditLogController).
  async function fetchLogs(params) {
    const { data } = await auditLogApi.getAll(params)
    return { items: data.data, total: data.meta.total }
  }

  // ── Table headers ──────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('audit_log.user'), key: 'user_name', sortable: false },
    { title: t('audit_log.action'), key: 'action' },
    { title: t('form.description'), key: 'description', sortable: false },
    { title: t('audit_log.entity'), key: 'entity_type', sortable: false },
    { title: t('audit_log.ip_address'), key: 'ip_address', sortable: false },
    { title: t('form.date'), key: 'created_at' },
    { title: '', key: 'actions', sortable: false }
  ])

  // ── Export — NOTE: pre-existing, unrelated to this page's table; the
  // export button was already a no-op before this change (auditLogApi has
  // no working export() and no backend route exists for it) ────────────────────
  const exportDates = reactive({ startDate: null, endDate: null })
  const exportErrors = reactive({ start: '', end: '' })

  const clearExportErrors = () => {
    exportErrors.start = ''
    exportErrors.end = ''
  }
  const closeExportForm = () => {
    showExportForm.value = false
    clearExportErrors()
  }

  const isDateRangeValid = computed(() => {
    clearExportErrors()
    if (!exportDates.startDate || !exportDates.endDate) return false
    if (new Date(exportDates.startDate) > new Date(exportDates.endDate)) {
      exportErrors.end = t('audit_log.end_before_start')
      return false
    }
    return true
  })

  const handleExport = () => {
    if (!isDateRangeValid.value) return
    store.exportCSV({
      date_from: exportDates.startDate,
      date_to: exportDates.endDate
    })
    closeExportForm()
  }
</script>
