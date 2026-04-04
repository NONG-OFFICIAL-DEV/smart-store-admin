<template>
  <v-container fluid class="pa-0">
    <!-- Title & Buttons -->
    <custom-title
      icon="mdi-timeline-clock-outline"
      title="System Audit Trail"
      subtitle="Complete history of security and data events"
    >
      <template #right>
        <BaseButtonFilter class="me-4" @click="toggleFilterForm" />
        <v-btn
          color="green"
          prepend-icon="mdi-download"
          @click="toggleExportForm"
        >
          Export
        </v-btn>
      </template>
    </custom-title>

    <!-- FILTER FORM -->
    <v-card class="mb-4 pa-4 rounded-lg" elevation="0" v-show="showFilterForm">
      <v-row>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="formFilters.keyword"
            label="Search keyword"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-date-input
            v-model="formFilters.startDate"
            label="Start Date"
            clearable
            hide-details="auto"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-date-input
            v-model="formFilters.endDate"
            label="End Date"
            clearable
            hide-details="auto"
          />
        </v-col>
        <v-col cols="12" md="3" class="d-flex align-center gap-2">
          <v-btn variant="outlined" @click="resetFilter">Reset</v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-filter-outline"
            @click="applyFilter"
          >
            Apply Filter
          </v-btn>
        </v-col>
      </v-row>
    </v-card>

    <!-- EXPORT FORM -->
    <v-card class="mb-4 pa-4" elevation="0" v-show="showExportForm">
      <v-row class="align-center">
        <v-col cols="12" md="4">
          <v-date-input
            v-model="exportDates.startDate"
            label="Start Date"
            :error="!!exportErrors.start"
            :error-messages="exportErrors.start"
            clearable
          />
        </v-col>
        <v-col cols="12" md="4">
          <v-date-input
            v-model="exportDates.endDate"
            label="End Date"
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
            Generate
          </v-btn>
          <v-btn variant="outlined" @click="closeExportForm">Cancel</v-btn>
        </v-col>
      </v-row>
    </v-card>

    <!-- Audit Table -->
    <v-data-table-server
      :headers="headers"
      :items="logs ?? []"
      :items-length="pagination.total || 0"
      :items-per-page="pagination.per_page"
      class="elevation-0 rounded-lg"
      hover
      @update:options="fetchOnOptions"
    >
      <!-- User column -->
      <template #item.user_name="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-2 font-weight-medium">
            {{
              item.user_name ||
              item.user?.first_name + ' ' + item.user?.last_name ||
              '—'
            }}
          </span>
          <span class="text-caption text-grey">{{ item.user_email }}</span>
        </div>
      </template>

      <!-- Action column -->
      <template #item.action="{ item }">
        <v-chip
          :color="actionColor(item.action)"
          size="small"
          variant="tonal"
          label
        >
          {{ item.action }}
        </v-chip>
      </template>

      <!-- Entity column -->
      <template #item.entity_type="{ item }">
        <span v-if="item.entity_type" class="text-body-2">
          {{ item.entity_type }}
          <span v-if="item.entity_id" class="text-caption text-grey ms-1">
            #{{ item.entity_id.slice(0, 8) }}...
          </span>
        </span>
        <span v-else class="text-grey">—</span>
      </template>

      <!-- IP Address -->
      <template #item.ip_address="{ item }">
        <span class="text-caption font-weight-mono">
          {{ item.ip_address || '—' }}
        </span>
      </template>

      <!-- Date -->
      <template #item.created_at="{ item }">
        <span class="text-caption">{{ formatDateTime(item.created_at) }}</span>
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <v-btn
          icon="mdi-arrow-right-circle"
          size="small"
          variant="text"
          color="primary"
          @click="goToDetails(item.id)"
        />
      </template>
    </v-data-table-server>
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useAuditLogStore } from '@/stores/auditLogStore'
  import { useDate } from '@/composables/useDate'
  import { useRouter } from 'vue-router'
  import { storeToRefs } from 'pinia'
  import { useDataTable } from '@/composables/useServerTable'

  const router = useRouter()
  const { formatDateTime } = useDate()
  const store = useAuditLogStore()
  const { logs, pagination } = storeToRefs(store)

  // ── UI state ───────────────────────────────────────────────────────────────────
  const showFilterForm = ref(false)
  const showExportForm = ref(false)

  const toggleFilterForm = () => {
    showFilterForm.value = !showFilterForm.value
    showExportForm.value = false
  }

  const toggleExportForm = () => {
    showExportForm.value = !showExportForm.value
    showFilterForm.value = false
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

  // ── Filter ─────────────────────────────────────────────────────────────────────
  const formFilters = reactive({
    keyword: null,
    startDate: null,
    endDate: null
  })

  const applyFilter = () => {
    store.getAllAuditLogs({
      keyword: formFilters.keyword,
      date_from: formFilters.startDate,
      date_to: formFilters.endDate
    })
  }

  const resetFilter = () => {
    formFilters.keyword = null
    formFilters.startDate = null
    formFilters.endDate = null
    store.getAllAuditLogs()
  }

  // ── Export ─────────────────────────────────────────────────────────────────────
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
      exportErrors.end = 'End Date cannot be earlier than Start Date'
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

  // ── Table headers ──────────────────────────────────────────────────────────────
  const headers = ref([
    { title: 'User', key: 'user_name', sortable: false },
    { title: 'Action', key: 'action' },
    { title: 'Description', key: 'description', sortable: false },
    { title: 'Entity', key: 'entity_type', sortable: false },
    { title: 'IP Address', key: 'ip_address', sortable: false },
    { title: 'Date', key: 'created_at' },
    { title: '', key: 'actions', sortable: false }
  ])

  // ── Init ───────────────────────────────────────────────────────────────────────
  const { fetchOnOptions, refresh } = useDataTable(
    store.getAllAuditLogs, // ✅ your existing store action
    () => ({
      // ✅ reactive filters
      keyword: formFilters.keyword,
      date_from: formFilters.startDate,
      date_to: formFilters.endDate
    })
  )

  // onMounted(() => store.getAllAuditLogs())
</script>
