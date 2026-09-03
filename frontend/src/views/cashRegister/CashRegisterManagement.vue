<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-cash-register"
      :title="t('cash_register_admin.title')"
      :subtitle="t('cash_register_admin.subtitle')"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-lock-open-variant-outline"
          @click="openOpenDialog"
        >
          {{ t('cash_register.open_register') }}
        </v-btn>
      </template>
    </custom-title>

    <v-row dense align="center" class="mb-2">
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.branch_id"
          :items="branchStore.branches"
          item-title="name"
          item-value="id"
          :label="t('pos.branch_label')"
          variant="outlined"
          rounded="lg"
          clearable
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.is_open"
          :items="statusOptions"
          item-title="label"
          item-value="value"
          :label="t('cash_register_admin.filters.status')"
          variant="outlined"
          rounded="lg"
          clearable
        />
      </v-col>
    </v-row>

    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchDrawersForTable"
        :filters="filters"
        :show-search="true"
        :item-label="t('cash_register_admin.title')"
      >
        <template #item.branch="{ item }">
          <span class="text-body-2">{{ branchName(item.branch_id) }}</span>
        </template>

        <template #item.staff="{ item }">
          <span class="text-body-2">{{ staffName(item.staff_id) }}</span>
        </template>

        <template #item.opening_float="{ item }">
          <span class="text-body-2">{{ formatMoney(item.opening_float) }}</span>
        </template>

        <template #item.status="{ item }">
          <AppStatusChip :status="item.closed_at ? 'closed' : 'open'" :map="statusMap" size="small" />
        </template>

        <template #item.opened_at="{ item }">
          <span class="text-body-2">{{ formatDateTime(item.opened_at) }}</span>
        </template>

        <template #item.variance="{ item }">
          <span
            v-if="item.closed_at"
            class="text-body-2 font-weight-bold"
            :class="Number(item.variance) < 0 ? 'text-error' : 'text-success'"
          >
            {{ formatMoney(item.variance) }}
          </span>
          <span v-else class="text-body-2 text-medium-emphasis">—</span>
        </template>

        <template #item.actions="{ item }">
          <v-btn
            v-if="!item.closed_at"
            size="small"
            color="error"
            variant="tonal"
            @click="openCloseDialog(item)"
          >
            {{ t('cash_register.close_register') }}
          </v-btn>
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <v-icon icon="mdi-cash-register" size="48" color="grey-lighten-1" class="mb-2" />
            <p class="text-body-2 text-medium-emphasis">{{ t('cash_register_admin.empty') }}</p>
          </div>
        </template>
      </AppTable>
    </v-card>

    <!-- Open Register -->
    <AppDialog
      v-model="openDialog"
      :title="t('cash_register.open_register')"
      :max-width="380"
      :loading="submitting"
    >
      <v-select
        v-model="openForm.branch_id"
        :items="branchStore.branches"
        item-title="name"
        item-value="id"
        :label="t('pos.branch_label')"
        variant="outlined"
        rounded="lg"
        @update:model-value="loadStaffForBranch"
      />
      <v-select
        v-model="openForm.staff_id"
        :items="staffOptions"
        item-title="full_name"
        item-value="id"
        :label="t('cash_register_admin.staff_label')"
        variant="outlined"
        rounded="lg"
        :disabled="!openForm.branch_id"
      />
      <v-text-field
        v-model.number="openForm.opening_float"
        type="number"
        min="0"
        step="0.01"
        :label="t('cash_register.opening_float')"
        variant="outlined"
        rounded="lg"
      />

      <template #actions="{ loading }">
        <span v-if="openError" class="text-caption text-error mr-auto">
          <v-icon icon="mdi-alert-circle-outline" size="14" class="mr-1" />{{ openError }}
        </span>
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="openDialog = false">
          {{ t('btn.cancel') }}
        </v-btn>
        <v-btn
          color="success"
          variant="flat"
          rounded="lg"
          :loading="loading"
          :disabled="!openForm.branch_id || !openForm.staff_id"
          @click="submitOpen"
        >
          {{ t('cash_register.open_register') }}
        </v-btn>
      </template>
    </AppDialog>

    <!-- Close Register -->
    <AppDialog
      v-model="closeDialog"
      :title="t('cash_register.close_register')"
      :max-width="380"
      :loading="submitting"
    >
      <v-text-field
        v-model.number="closeForm.actual_cash"
        type="number"
        min="0"
        step="0.01"
        :label="t('cash_register.actual_cash')"
        variant="outlined"
        rounded="lg"
      />
      <v-textarea
        v-model="closeForm.notes"
        :label="t('cash_register.notes_optional')"
        variant="outlined"
        rounded="lg"
        rows="2"
      />

      <template #actions="{ loading }">
        <span v-if="closeError" class="text-caption text-error mr-auto">
          <v-icon icon="mdi-alert-circle-outline" size="14" class="mr-1" />{{ closeError }}
        </span>
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="closeDialog = false">
          {{ t('btn.cancel') }}
        </v-btn>
        <v-btn
          color="error"
          variant="flat"
          rounded="lg"
          :loading="loading"
          :disabled="closeForm.actual_cash === null || closeForm.actual_cash === ''"
          @click="submitClose"
        >
          {{ t('cash_register.confirm_close') }}
        </v-btn>
      </template>
    </AppDialog>
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppTable, AppStatusChip, useAppUtils, AppDialog } from '@nong-official-dev/core'
  import { useBranchStore } from '@/stores/branchStore'
  import { useDate } from '@/composables/useDate'
  import {
    getAllCashDrawersApi,
    openCashDrawerApi,
    closeCashDrawerApi
  } from '@/api/cashDrawerService'
  import { getAllStaffApi, getStaffByIdApi } from '@/api/staffService'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const branchStore = useBranchStore()
  const { formatDateTime } = useDate()

  const tableRef = ref(null)
  const staffCache = ref({})
  const staffOptions = ref([])

  const filters = reactive({ branch_id: null, is_open: null })

  const statusOptions = computed(() => [
    { value: true, label: t('cash_register_admin.status.open') },
    { value: false, label: t('cash_register_admin.status.closed') }
  ])

  const statusMap = {
    open: { color: 'success', label: t('cash_register_admin.status.open') },
    closed: { color: 'grey', label: t('cash_register_admin.status.closed') }
  }

  const headers = [
    { title: t('cash_register_admin.columns.branch'), key: 'branch', sortable: false },
    { title: t('cash_register_admin.columns.staff'), key: 'staff', sortable: false },
    { title: t('cash_register.opening_float'), key: 'opening_float', sortable: false },
    { title: t('cash_register_admin.columns.status'), key: 'status', sortable: false },
    { title: t('cash_register.opened_at'), key: 'opened_at', sortable: false },
    { title: t('cash_register_admin.columns.variance'), key: 'variance', sortable: false, align: 'end' },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  async function fetchDrawersForTable(params) {
    const { data } = await getAllCashDrawersApi({
      page: params.page,
      perPage: params.perPage,
      search: params.search,
      branch_id: params.branch_id,
      is_open: params.is_open
    })
    await hydrateStaffCache(data.data)
    return { items: data.data, total: data.meta.total }
  }

  async function hydrateStaffCache(drawers) {
    const missing = [...new Set(drawers.map(d => d.staff_id))].filter(id => id && !staffCache.value[id])
    await Promise.all(
      missing.map(async id => {
        try {
          const { data } = await getStaffByIdApi(id)
          staffCache.value = { ...staffCache.value, [id]: data.data.full_name }
        } catch {
          // non-fatal — row falls back to a truncated id
        }
      })
    )
  }

  function branchName(id) {
    return branchStore.branches.find(b => b.id === id)?.name ?? '—'
  }

  function staffName(id) {
    return staffCache.value[id] ?? `#${id?.slice(0, 8)}`
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value ?? 0)
  }

  // ── Open register ──────────────────────────────────────────────────────
  const openDialog = ref(false)
  const openForm = reactive({ branch_id: null, staff_id: null, opening_float: 0 })
  const openError = ref('')
  const submitting = ref(false)

  function openOpenDialog() {
    openForm.branch_id = null
    openForm.staff_id = null
    openForm.opening_float = 0
    openError.value = ''
    staffOptions.value = []
    openDialog.value = true
  }

  async function loadStaffForBranch(branchId) {
    openForm.staff_id = null
    if (!branchId) return
    const { data } = await getAllStaffApi({ branch_id: branchId, perPage: 100 })
    staffOptions.value = data.data
  }

  async function submitOpen() {
    submitting.value = true
    openError.value = ''
    try {
      await openCashDrawerApi({
        branch_id: openForm.branch_id,
        staff_id: openForm.staff_id,
        opening_float: openForm.opening_float || 0
      })
      openDialog.value = false
      notif(t('cash_register.opened_success'), { type: 'success' })
      tableRef.value?.refresh?.()
    } catch (err) {
      openError.value = err.response?.data?.message ?? t('cash_register.action_failed')
    } finally {
      submitting.value = false
    }
  }

  // ── Close register ─────────────────────────────────────────────────────
  const closeDialog = ref(false)
  const closeTarget = ref(null)
  const closeForm = reactive({ actual_cash: null, notes: '' })
  const closeError = ref('')

  function openCloseDialog(item) {
    closeTarget.value = item
    closeForm.actual_cash = null
    closeForm.notes = ''
    closeError.value = ''
    closeDialog.value = true
  }

  async function submitClose() {
    submitting.value = true
    closeError.value = ''
    try {
      await closeCashDrawerApi(closeTarget.value.id, {
        actual_cash: closeForm.actual_cash,
        notes: closeForm.notes || undefined
      })
      closeDialog.value = false
      notif(t('cash_register.closed_success'), { type: 'success' })
      tableRef.value?.refresh?.()
    } catch (err) {
      closeError.value = err.response?.data?.message ?? t('cash_register.action_failed')
    } finally {
      submitting.value = false
    }
  }
</script>
