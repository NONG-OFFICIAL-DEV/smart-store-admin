<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-account-group"
      :title="t('users.page.title')"
      :subtitle="t('users.page.subtitle')"
    >
      <template #right>
        <v-btn
          class="ms-2"
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ t('users.page.add_user') }}
        </v-btn>
      </template>
    </custom-title>
    <!-- ── Filters ────────────────────────────────────────────────────────── -->
    <v-row dense align="center" class="mb-2">
      <v-col cols="6" sm="3">
        <v-select
          v-model="filterStatus"
          :items="[
            { title: t('status.active'), value: true },
            { title: t('status.inactive'), value: false }
          ]"
          :label="t('form.status')"
          variant="outlined"
          clearable
          rounded="lg"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filterVerified"
          :items="[
            { title: t('users.page.verified'), value: true },
            { title: t('users.page.unverified'), value: false }
          ]"
          :label="t('users.page.email_verified')"
          variant="outlined"
          clearable
          rounded="lg"
        />
      </v-col>
    </v-row>

    <!-- ── Table ──────────────────────────────────────────────────────────── -->
    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchFn"
        :filters="filters"
        :show-search="true"
        :item-label="t('users.page.title')"
      >
        <!-- Avatar + Name -->
        <template #item.first_name="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <v-avatar
              size="38"
              rounded="lg"
              class="me-2"
              :color="avatarColor(item.first_name)"
            >
              <v-img v-if="item.avatar_url" :src="item.avatar_url" cover />
              <span v-else class="text-caption font-weight-bold text-white">
                {{ initials(item) }}
              </span>
            </v-avatar>
            <div>
              <div class="font-weight-medium">
                {{ item.first_name }} {{ item.last_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.email }}
              </div>
            </div>
          </div>
        </template>

        <!-- Phone -->
        <template #item.phone="{ item }">
          <span v-if="item.phone">{{ item.phone }}</span>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <!-- Email verified -->
        <template #item.email_verified_at="{ item }">
          <v-chip
            :color="item.email_verified_at ? 'success' : 'warning'"
            variant="tonal"
            size="small"
            rounded="lg"
          >
            <v-icon
              :icon="
                item.email_verified_at
                  ? 'mdi-check-circle'
                  : 'mdi-clock-outline'
              "
              size="13"
              class="mr-1"
            />
            {{
              item.email_verified_at
                ? t('users.page.verified')
                : t('status.pending')
            }}
          </v-chip>
        </template>

        <!-- Last login -->
        <template #item.last_login_at="{ item }">
          <span v-if="item.last_login_at" class="text-body-2">
            {{ formatDateText(item.last_login_at) }}
          </span>
          <span v-else class="text-medium-emphasis text-caption">
            {{ t('stock.management.never') }}
          </span>
        </template>

        <!-- Active toggle -->
        <template #item.is_active="{ item }">
          <v-switch
            v-model="item.is_active"
            color="success"
            density="compact"
            hide-details
            inset
            @change="toggleActive(item)"
          />
        </template>

        <template #item.resolved_type="{ item }">
          <div class="d-flex flex-column gap-1">
            <!-- Has a role — show chip normally -->
            <template v-if="item.resolved_type?.type !== 'unassigned'">
              <v-chip
                :color="typeColor(item.resolved_type?.type)"
                :prepend-icon="typeIcon(item.resolved_type?.type)"
                size="small"
                variant="tonal"
              >
                {{ item.resolved_type?.label }}
              </v-chip>
              <span
                v-if="item.resolved_type?.tenant"
                class="text-caption text-grey"
              >
                {{ item.resolved_type.tenant }}
              </span>
            </template>

            <!-- Unassigned — no action here; assigning a role means making
                 them staff of a specific tenant, which only makes sense
                 from that tenant's own Staff Management page (it needs a
                 tenant/branch context this global directory doesn't have). -->
            <template v-else>
              <v-chip
                size="small"
                variant="tonal"
                color="grey"
                prepend-icon="mdi-account-off-outline"
              >
                {{ t('users.page.unassigned') }}
              </v-chip>
            </template>
          </div>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <v-btn
              icon="mdi-pencil-outline"
              size="small"
              variant="text"
              color="primary"
              @click="openEdit(item)"
            />
            <v-btn
              icon="mdi-lock-reset"
              size="small"
              variant="text"
              color="warning"
              @click="confirmResetPassword(item)"
            />
            <v-btn
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="confirmDeleteUser(item)"
            />
          </div>
        </template>
      </AppTable>
    </v-card>

    <!-- ── Dialogs ────────────────────────────────────────────────────────── -->
    <UserFormDialog
      v-model="dialog"
      :edit-item="editItem"
      :loading="saving"
      @saved="handleUserSaved"
    />

    <TemporaryPasswordDialog
      v-model="tempPasswordDialog"
      :password="temporaryPassword"
    />
  </v-container>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useUserStore } from '@/stores/userStore'
  import UserFormDialog from '@/components/users/UserFormDialog.vue'
  import TemporaryPasswordDialog from '@/components/common/TemporaryPasswordDialog.vue'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  import { useAvatar } from '@/composables/useAvatar'
  const { confirm, notif } = useAppUtils()
  const { getInitials, getAvatarColor } = useAvatar()
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const store = useUserStore()
  import { formatDateText } from '@nong-official-dev/core'
  // ── UI state ──────────────────────────────────────────────────────────────────
  const tableRef = ref(null)
  const filterStatus = ref(null)
  const filterVerified = ref(null)
  const dialog = ref(false)
  const saving = ref(false)
  const editItem = ref(null)
  const tempPasswordDialog = ref(false)
  const temporaryPassword = ref('')
  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = computed(() => [
    { title: t('users.page.table.user'), key: 'first_name', sortable: true },
    // resolved_type is a computed accessor (owner/staff/super-admin derived
    // across relations), not a real column — sorting by it server-side
    // would throw ("column resolved_type does not exist").
    {
      title: t('users.page.table.role'),
      key: 'resolved_type',
      sortable: false
    },
    { title: t('form.phone'), key: 'phone', sortable: false },
    {
      title: t('users.page.table.email_status'),
      key: 'email_verified_at',
      sortable: false
    },
    {
      title: t('users.page.table.last_login'),
      key: 'last_login_at',
      sortable: true
    },
    { title: t('status.active'), key: 'is_active', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ])

  // ── Server-driven filters — status/verified apply immediately; search is
  // AppTable's own built-in box. AppTable deep-watches `filters` and
  // refetches (resetting to page 1) whenever this object changes. ──────────
  const filters = computed(() => ({
    is_active: filterStatus.value ?? undefined,
    verified: filterVerified.value ?? undefined
  }))

  const fetchFn = params => store.fetchForTable(params)

  const typeColor = type =>
    ({
      super_admin: 'error',
      owner: 'warning',
      staff: 'success',
      unassigned: 'grey'
    })[type] || 'grey'

  const typeIcon = type =>
    ({
      super_admin: 'mdi-shield-crown',
      owner: 'mdi-domain',
      staff: 'mdi-account-tie',
      unassigned: 'mdi-account-off-outline'
    })[type] || 'mdi-account'
  // ── Helpers ───────────────────────────────────────────────────────────────────
  const colors = [
    'primary',
    'success',
    'warning',
    'error',
    'secondary',
    'teal',
    'purple',
    'orange'
  ]
  const avatarColor = name => getAvatarColor(name, { palette: colors })
  const initials = u => getInitials(u, '')

  // ── CRUD ──────────────────────────────────────────────────────────────────────
  const openCreate = () => {
    editItem.value = null
    dialog.value = true
  }
  const openEdit = item => {
    editItem.value = item
    dialog.value = true
  }

  const handleUserSaved = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await store.updateUser(payload)
        notif(t('users.page.messages.updated'), {
          type: 'success'
        })
      } else {
        await store.addUser(payload)
        notif(t('users.page.messages.created'), {
          type: 'success'
        })
      }
      tableRef.value?.refresh()
      dialog.value = false
    } catch (e) {
      notif(e?.response?.data?.message || t('branch_menu.operation_failed'), {
        type: 'error'
      })
    } finally {
      saving.value = false
    }
  }

  const toggleActive = async item => {
    try {
      await store.updateUser({ id: item.id, is_active: item.is_active })
      notif(
        item.is_active
          ? t('users.page.messages.activated')
          : t('users.page.messages.deactivated'),
        {
          type: 'success'
        }
      )
    } catch {
      item.is_active = !item.is_active // revert on error
    }
  }

  const confirmResetPassword = data => {
    confirm({
      title: t('users.page.reset_password_title'),
      message: t('users.page.reset_password_message', {
        name: `${data?.first_name} ${data?.last_name}`
      }),
      options: { type: 'warning', width: 500 },
      agree: async () => {
        try {
          temporaryPassword.value = await store.resetPassword(data.id)
          tempPasswordDialog.value = true
        } catch {
          notif(t('users.page.messages.reset_password_failed'), {
            type: 'error'
          })
        }
      },
      cancel: () => {}
    })
  }

  const confirmDeleteUser = data => {
    confirm({
      title: t('users.page.delete_title'),
      message: t('users.page.delete_message', {
        name: `${data?.first_name} ${data?.last_name}`
      }),
      options: { type: 'warning', width: 500 },
      agree: async () => {
        try {
          await store.deleteUser(data.id)
          tableRef.value?.refresh()
          notif(t('users.page.messages.deleted'), {
            type: 'success'
          })
        } catch {
          notif(t('users.page.messages.delete_failed'), {
            type: 'error'
          })
        }
      },
      cancel: () => {}
    })
  }
</script>
