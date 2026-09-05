<template>
  <v-container fluid class="pa-0">
    <AppToolbar :title="t('suppliers.title')" :subtitle="t('suppliers.subtitle')">
      <template #actions>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openAdd"
        >
          {{ t('btn.add_supplier') }}
        </v-btn>
      </template>
    </AppToolbar>

    <!-- Table -->
    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchTableData"
        item-label="suppliers"
      >
        <!-- Status chip -->
        <template #item.is_active="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="x-small" />
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-pencil-outline"
            size="small"
            variant="text"
            color="primary"
            class="mr-1"
            @click="openEdit(item)"
          />
          <v-btn
            icon="mdi-delete-outline"
            size="small"
            variant="text"
            color="error"
            @click="handleDelete(item.id)"
          />
        </template>
      </AppTable>
    </v-card>

    <!-- Dialog -->
    <SupplierDialog
      v-model="dialog"
      :supplier="selected"
      :loading="saving"
      @save="handleSave"
    />
  </v-container>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useSupplierStore } from '@/stores/supplierStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { AppStatusChip, AppTable } from '@nong-official-dev/core'
  import AppToolbar from '@/components/common/AppToolbar.vue'
  import SupplierDialog from '@/components/SupplierDialog.vue'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const store = useSupplierStore()
  const { confirm, notif } = useAppUtils()

  // ── Table ──────────────────────────────────────────────────────────────────────
  const tableRef = ref(null)

  const headers = computed(() => [
    { title: t('suppliers.table.name'), key: 'name' },
    { title: t('suppliers.table.contact_person'), key: 'contact_person' },
    { title: t('suppliers.table.phone'), key: 'phone' },
    { title: t('suppliers.table.email'), key: 'email' },
    { title: t('suppliers.table.status'), key: 'is_active' },
    {
      title: t('suppliers.table.actions'),
      key: 'actions',
      sortable: false,
      align: 'end'
    }
  ])

  // ── Fetch — `search` comes from AppTable's own built-in search box. ─────────────
  async function fetchTableData(params) {
    await store.fetchSuppliers(params)
    return { items: store.suppliers.data ?? [], total: store.suppliers.total ?? 0 }
  }

  // ── Dialog ─────────────────────────────────────────────────────────────────────
  const dialog = ref(false)
  const selected = ref(null)
  const saving = ref(false)

  const openAdd = () => {
    selected.value = null
    dialog.value = true
  }
  const openEdit = item => {
    selected.value = { ...item }
    dialog.value = true
  }

  const handleSave = async data => {
    saving.value = true
    try {
      if (data.id) {
        await store.updateSupplier(data)
        notif('Supplier updated', { type: 'success' })
      } else {
        await store.addSupplier(data)
        notif('Supplier added', { type: 'success' })
      }
      dialog.value = false
      tableRef.value?.refresh()
    } catch {
      notif('Something went wrong', { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const handleDelete = id => {
    confirm({
      title: 'Delete Supplier',
      message: 'This action cannot be undone.',
      options: { type: 'error' },
      agree: async () => {
        await store.removeSupplier(id)
        notif('Supplier deleted', { type: 'success' })
        tableRef.value?.refresh()
      }
    })
  }
</script>

<style scoped>
  .gap-2 {
    gap: 8px;
  }
</style>
