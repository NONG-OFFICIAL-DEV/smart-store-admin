<template>
  <v-container fluid class="pa-0">
    <!-- Title & Buttons -->
    <custom-title icon="mdi-timeline-clock-outline">
      Audit Log
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

        <v-col cols="12" md="3" class="d-flex align-center">
          <v-btn class="me-3" variant="outlined" @click="resetFilter">
            Reset
          </v-btn>
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

        <v-col cols="12" md="4" class="d-flex align-center mb-4">
          <v-btn
            color="green"
            prepend-icon="mdi-microsoft-excel"
            :disabled="!isDateRangeValid"
            @click="handleExport"
          >
            Generate
          </v-btn>

          <v-btn class="ms-3" variant="outlined" @click="closeExportForm">
            Cancel
          </v-btn>
        </v-col>
      </v-row>
    </v-card>

    <!-- Audit Table -->
    <v-data-table
      :headers="headers"
      :items="store.logs.data"
      class="elevation-0"
    >
      <template #item.actions="{ item }">
        <v-btn
          icon="mdi-arrow-right-circle"
          size="small"
          variant="text"
          color="primary"
          @click="goToDetails(item.id)"
        ></v-btn>
      </template>
      <template #item.created_at="{ item }">
        <td>{{ formatDateTime(item.created_at) }}</td>
      </template>
    </v-data-table>
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useAuditLogStore } from '@/stores/auditLogStore'
  import { useDate } from '@/composables/useDate'
  import { useRouter } from 'vue-router'
  const router = useRouter()

  const { formatDateTime } = useDate()
  const store = useAuditLogStore()

  /* ----------------------------- UI STATE ----------------------------- */
  const showFilterForm = ref(false)
  const showExportForm = ref(false)

  const goToDetails = id => {
    router.push(`/audit-log/${id}`)
  }

  const toggleFilterForm = () => {
    showFilterForm.value = !showFilterForm.value
    showExportForm.value = false
  }

  const toggleExportForm = () => {
    showExportForm.value = !showExportForm.value
    showFilterForm.value = false
    clearExportErrors()
  }

  /* ----------------------------- FILTER FORM ----------------------------- */
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

    // reload default list
    store.getAllAuditLogs()
  }

  /* ----------------------------- EXPORT FORM ----------------------------- */
  const exportDates = reactive({
    startDate: null,
    endDate: null
  })

  const exportErrors = reactive({
    start: '',
    end: ''
  })

  const clearExportErrors = () => {
    exportErrors.start = ''
    exportErrors.end = ''
  }

  const closeExportForm = () => {
    showExportForm.value = false
    clearExportErrors()
  }

  /* ----------------------------- EXPORT VALIDATION ----------------------------- */
  const isDateRangeValid = computed(() => {
    clearExportErrors()

    if (!exportDates.startDate || !exportDates.endDate) return false

    if (new Date(exportDates.startDate) > new Date(exportDates.endDate)) {
      exportErrors.end = 'End Date cannot be earlier than Start Date'
      return false
    }

    return true
  })

  /* ----------------------------- EXPORT HANDLER ----------------------------- */
  const handleExport = () => {
    if (!isDateRangeValid.value) return

    store.exportCSV({
      date_from: exportDates.startDate,
      date_to: exportDates.endDate
    })

    closeExportForm()
  }

  /* ----------------------------- TABLE HEADERS ----------------------------- */
  const headers = ref([
    { title: 'ID', key: 'id' },
    { title: 'Username', key: 'user.name' },
    { title: 'Action', key: 'action_type' },
    { title: 'Description', key: 'description' },
    { title: 'Ip address', key: 'ip_address' },
    { title: 'Date', key: 'created_at' },
    { title: '', key: 'actions', sortable: false }
  ])

  /* ----------------------------- INITIAL LOAD ----------------------------- */
  onMounted(() => {
    store.getAllAuditLogs()
  })
</script>
