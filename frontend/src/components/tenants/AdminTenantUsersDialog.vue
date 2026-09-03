<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('admin_tenant_users.dialog_title', { name: tenant?.name })"
    max-width="800"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-table v-else density="comfortable">
      <thead>
        <tr>
          <th>{{ t('admin_tenant_users.fields.name') }}</th>
          <th>{{ t('admin_tenant_users.fields.email') }}</th>
          <th>{{ t('admin_tenant_users.role') }}</th>
          <th>{{ t('form.status') }}</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in users" :key="row.user_id">
          <td>{{ row.full_name }}</td>
          <td>{{ row.email }}</td>
          <td>{{ row.role_name || '—' }}</td>
          <td>
            <v-chip size="x-small" :color="row.is_active ? 'success' : 'default'" variant="tonal">
              {{ row.is_active ? t('status.active') : t('tenant_details.status.suspended') }}
            </v-chip>
          </td>
          <td class="text-end">
            <v-menu>
              <template #activator="{ props: menuProps }">
                <v-btn icon="mdi-dots-vertical" size="small" variant="text" :loading="rowLoading === row.user_id" v-bind="menuProps" />
              </template>
              <v-list density="compact" min-width="200">
                <v-list-item
                  :title="t('admin_tenant_users.actions.reset_password')"
                  prepend-icon="mdi-email-lock-outline"
                  @click="sendPasswordReset(row)"
                />
                <v-list-item
                  v-if="row.type === 'staff' && row.is_active"
                  class="text-warning"
                  :title="t('admin_tenant_users.actions.deactivate')"
                  prepend-icon="mdi-account-cancel-outline"
                  @click="askDeactivate(row)"
                />
                <v-list-item
                  v-else-if="row.type === 'staff'"
                  class="text-success"
                  :title="t('admin_tenant_users.actions.reactivate')"
                  prepend-icon="mdi-account-check-outline"
                  @click="askReactivate(row)"
                />
              </v-list>
            </v-menu>
          </td>
        </tr>
      </tbody>
    </v-table>

    <template #actions="{ loading }">
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('btn.close') }}</v-btn>
    </template>
  </AppDialog>

  <TemporaryPasswordDialog v-model="passwordDialog" :password="temporaryPassword" />
</template>

<script setup>
  import { ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils, AppDialog } from '@nong-official-dev/core'
  import TemporaryPasswordDialog from '@/components/common/TemporaryPasswordDialog.vue'
  import {
    getAdminTenantUsersApi,
    deactivateAdminTenantUserApi,
    reactivateAdminTenantUserApi,
    resetAdminTenantUserPasswordApi
  } from '@/api/tenantService'

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    tenant: { type: Object, default: null }
  })

  const emit = defineEmits(['update:modelValue'])

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()

  const loading = ref(false)
  const users = ref([])
  const rowLoading = ref(null)
  const passwordDialog = ref(false)
  const temporaryPassword = ref('')

  async function fetchUsers() {
    if (!props.tenant) return
    loading.value = true
    try {
      const { data } = await getAdminTenantUsersApi(props.tenant.id)
      users.value = data.data
    } finally {
      loading.value = false
    }
  }

  watch(
    () => props.modelValue,
    open => {
      if (open) fetchUsers()
    }
  )

  function askDeactivate(row) {
    confirm({
      title: t('admin_tenant_users.confirm_deactivate_title'),
      message: t('admin_tenant_users.confirm_message', { name: row.full_name }),
      options: { type: 'warning', width: 400 },
      agree: async () => {
        rowLoading.value = row.user_id
        try {
          await deactivateAdminTenantUserApi(props.tenant.id, row.user_id)
          notif(t('admin_tenant_users.messages.deactivated_success'), { type: 'success' })
          await fetchUsers()
        } catch {
          notif(t('admin_tenant_users.messages.action_error'), { type: 'error' })
        } finally {
          rowLoading.value = null
        }
      },
      cancel: () => {}
    })
  }

  function askReactivate(row) {
    confirm({
      title: t('admin_tenant_users.confirm_reactivate_title'),
      message: t('admin_tenant_users.confirm_message', { name: row.full_name }),
      options: { type: 'warning', width: 400 },
      agree: async () => {
        rowLoading.value = row.user_id
        try {
          await reactivateAdminTenantUserApi(props.tenant.id, row.user_id)
          notif(t('admin_tenant_users.messages.reactivated_success'), { type: 'success' })
          await fetchUsers()
        } catch {
          notif(t('admin_tenant_users.messages.action_error'), { type: 'error' })
        } finally {
          rowLoading.value = null
        }
      },
      cancel: () => {}
    })
  }

  async function sendPasswordReset(row) {
    rowLoading.value = row.user_id
    try {
      const { data } = await resetAdminTenantUserPasswordApi(props.tenant.id, row.user_id)
      temporaryPassword.value = data.data.temporary_password
      passwordDialog.value = true
    } catch {
      notif(t('admin_tenant_users.messages.action_error'), { type: 'error' })
    } finally {
      rowLoading.value = null
    }
  }
</script>
