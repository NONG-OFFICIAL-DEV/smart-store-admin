<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useStaffStore } from '@/stores/staffStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useRoleStore } from '@/stores/roleStore'
  import { getAllStaffApi } from '@/api/staffService'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  import StaffDialogForm from '@/components/staff/StaffDialogForm.vue'
  import { useI18n } from 'vue-i18n'
  import { useAvatar, AVATAR_HEX_PALETTE } from '@/composables/useAvatar'
  const { t } = useI18n()
  const { getInitials, getAvatarColor } = useAvatar()
  const staffStore = useStaffStore()
  const branchStore = useBranchStore()
  const roleStore = useRoleStore()
  const { confirm, notif } = useAppUtils()

  const { branches } = storeToRefs(branchStore)
  const { roles } = storeToRefs(roleStore)

  const tableRef = ref(null)
  const branchFilter = ref(null)
  const roleFilter = ref(null)
  const dialog = ref(false)
  const saving = ref(false)
  const selectedItem = ref(null)

  // ── Branch/role options ──────────────────────────────────────────────────────
  const branchOptions = computed(
    () => branches.value?.data ?? branches.value ?? []
  )
  const roleOptions = computed(() => roles.value ?? [])

  // ── Table headers — only employee_code is in StaffRepository::ALLOWED_SORTS;
  // the rest fall back to a silent latest()-sort server-side, so they're not
  // marked sortable to avoid implying an order that isn't happening. ──────────
    const headers = computed(() => [
      { title: t('staff.table.name'), key: 'full_name', sortable: false },
      { title: t('staff.table.code'), key: 'employee_code', sortable: true },
      { title: t('staff.table.branch'), key: 'branch_name', sortable: false },
      { title: t('staff.table.role'), key: 'role_name', sortable: false },
      { title: t('staff.table.compensation'), key: 'salary', sortable: false },
      { title: t('staff.table.status'), key: 'is_active', sortable: false },
      {
        title: t('staff.table.actions'),
        key: 'actions',
        sortable: false,
        width: '10%'
      }
    ])

  // ── Server-driven filters — matches StaffRepository's contract
  // (search/sortBy/sortDesc/page/perPage + branch_id/role_id). ─────────────────
  const filters = computed(() => ({
    branch_id: branchFilter.value || undefined,
    role_id: roleFilter.value || undefined
  }))

  async function fetchStaff(params) {
    const { data } = await getAllStaffApi(params)
    return { items: data.data, total: data.meta.total }
  }

  // ── Actions ────────────────────────────────────────────────────────────────
  const openCreate = () => {
    selectedItem.value = null
    dialog.value = true
  }

  const openEdit = item => {
    selectedItem.value = { ...item }
    dialog.value = true
  }

  const confirmDelete = member => {
    confirm({
      title: 'Deactivate Staff Member',
      message: `Are you sure you want to deactivate "${member.full_name}"? Their history will be kept.`,
      options: { type: 'warning', width: 480 },
      agree: async () => {
        try {
          await staffStore.deleteStaff(member.id)
          notif(`${member.full_name} deactivated`, { type: 'success' })
          tableRef.value?.refresh()
        } catch {
          notif('Failed to deactivate staff', { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await staffStore.updateStaff(payload.id, payload)
        notif('Staff updated successfully', { type: 'success' })
      } else {
        await staffStore.createStaff(payload)
        notif('Staff created successfully', { type: 'success' })
      }
      dialog.value = false
      tableRef.value?.refresh()
    } catch {
      notif('Failed to save staff', { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  const initials = n => getInitials(n)
  const avatarColor = n =>
    getAvatarColor(n, { palette: AVATAR_HEX_PALETTE, fallback: '#808080' })

  onMounted(() => {
    branchStore.fetchBranches({ perPage: 100 })
    roleStore.fetchRoles({ perPage: 100 })
  })
</script>

<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-account-group"
      :title="$t('staff.title')"
      :subtitle="$t('staff.subtitle')"
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

    <!-- ── Filters ────────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-2">
      <!-- Branch -->
      <v-col cols="12" md="3" sm="3">
        <v-select
          v-model="branchFilter"
          :items="branchOptions"
          item-title="name"
          item-value="id"
          :placeholder="$t('staff.filter.all_branches')"
          variant="outlined"
          rounded="lg"
          clearable
        />
      </v-col>

      <!-- Role -->
      <v-col cols="12" md="3" sm="3">
        <v-select
          v-model="roleFilter"
          :items="roleOptions"
          item-title="name"
          item-value="id"
          :placeholder="$t('staff.filter.all_roles')"
          variant="outlined"
          rounded="lg"
          clearable
        />
      </v-col>
    </v-row>
    <!-- ── Table ────────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" border flat class="overflow-hidden pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchStaff"
        :filters="filters"
        item-label="staff"
      >
        <!-- Name + phone -->
        <template #[`item.full_name`]="{ item }">
          <div class="d-flex align-center py-2">
            <v-avatar
              :color="avatarColor(item.full_name)"
              size="32"
              variant="tonal"
              class="mr-3"
              rounded="sm"
            >
              <span class="text-caption font-weight-black">
                {{ initials(item.full_name) }}
              </span>
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-bold">
                {{ item.full_name }}
              </div>
              <div class="text-tiny text-medium-emphasis">{{ item.phone }}</div>
            </div>
          </div>
        </template>

        <!-- Status chip -->
        <template #[`item.is_active`]="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'grey'"
            size="x-small"
            variant="tonal"
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>

        <!-- Compensation -->
        <template #[`item.salary`]="{ item }">
          {{
            item.hourly_rate
              ? `$${item.hourly_rate}/hr`
              : item.salary
                ? `$${item.salary}/mo`
                : '—'
          }}
        </template>

        <!-- Actions -->
        <template #[`item.actions`]="{ item }">
          <v-btn
            icon="mdi-pencil-outline"
            variant="text"
            size="small"
            @click="openEdit(item)"
          />
          <v-btn
            icon="mdi-delete-outline"
            variant="text"
            size="small"
            color="error"
            @click="confirmDelete(item)"
          />
        </template>
      </AppTable>
    </v-card>

    <!-- ── Dialog ───────────────────────────────────────────────────────────── -->
    <StaffDialogForm
      v-model="dialog"
      :item="selectedItem"
      :loading="saving"
      @save="handleSave"
    />
  </v-container>
</template>

<style scoped>
  .text-tiny {
    font-size: 0.65rem !important;
    line-height: 1rem;
    letter-spacing: 0.05em;
  }

  :deep(.v-table__wrapper) {
    scrollbar-width: thin;
  }
</style>
