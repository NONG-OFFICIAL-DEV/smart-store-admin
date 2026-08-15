<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-shield-crown"
      :title="$t('roles.title')"
      :subtitle="$t('roles.subtitle')"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          {{ $t('roles.new') }}
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Filters ────────────────────────────────────────────────────────────── -->
    <v-row class="mb-2" dense>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.is_system"
          :items="[
            { title: $t('roles.filter.system'), value: true },
            { title: $t('roles.filter.custom'), value: false }
          ]"
          :label="$t('form.type')"
          clearable
        />
      </v-col>
    </v-row>

    <!-- ── Roles Table ───────────────────────────────────────────────────────── -->
    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable ref="tableRef" :headers="headers" :fetch-fn="fetchRoles" :filters="filters" item-label="roles">
        <template #[`item.name`]="{ item }">
          <div class="d-flex align-center gap-3">
            <v-avatar :color="item.is_system ? 'warning' : 'primary'" size="32" rounded="lg" class="me-2">
              <v-icon :icon="item.is_system ? 'mdi-shield-crown' : 'mdi-shield-account'" size="16" color="white" />
            </v-avatar>
            <div>
              <div class="font-weight-medium">{{ item.name }}</div>
              <v-chip v-if="item.is_system" color="warning" variant="tonal" size="x-small" rounded="lg">
                {{ $t('roles.system_badge') }}
              </v-chip>
            </div>
          </div>
        </template>

        <template #[`item.description`]="{ item }">
          <span class="text-body-2 text-medium-emphasis">
            {{ item.description || $t('roles.no_description') }}
          </span>
        </template>

        <template #[`item.permissions`]="{ item }">
          <div class="d-flex gap-1 flex-wrap">
            <v-chip
              v-for="perm in (item.permissions || []).slice(0, 2)"
              :key="perm.id"
              size="x-small"
              :color="groupColor(perm.group)"
              variant="tonal"
              rounded="lg"
            >
              {{ perm.code }}
            </v-chip>
            <v-chip v-if="(item.permissions?.length || 0) > 2" size="x-small" color="grey" variant="tonal" rounded="lg">
              {{ $t('roles.more_count', { n: item.permissions.length - 2 }) }}
            </v-chip>
            <span v-if="!item.permissions?.length" class="text-caption text-medium-emphasis">
              {{ $t('roles.no_permissions_assigned') }}
            </span>
          </div>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn icon="mdi-key-variant" size="small" variant="text" color="warning" @click="openPermissions(item)" />
          <v-btn
            icon="mdi-pencil-outline"
            size="small"
            variant="text"
            color="primary"
            :disabled="item.is_system"
            @click="openEdit(item)"
          />
          <v-btn
            icon="mdi-delete-outline"
            size="small"
            variant="text"
            color="error"
            :disabled="item.is_system"
            @click="confirmDelete(item)"
          />
        </template>
      </AppTable>
    </v-card>

    <!-- ── Dialogs ────────────────────────────────────────────────────────── -->
    <RoleFormDialog
      v-model="dialog"
      :edit-item="editItem"
      :loading="saving"
      @saved="handleRoleSaved"
    />

    <RolePermissionsDialog
      v-model="permDialog"
      :role="activeRole"
      :permissions="permStore.permissions || []"
      :assigned="assignedPermIds"
      :loading="permSaving"
      @saved="handlePermissionsSaved"
    />
  </v-container>
</template>

<script setup>
  import { computed, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { usePermissionStore } from '@/stores/permissionStore'
  import { useRoleStore } from '@/stores/roleStore'
  import { getAllRolesApi } from '@/api/roleService'
  import RoleFormDialog from '@/components/rolePermissions/RoleFormDialog.vue'
  import RolePermissionsDialog from '@/components/rolePermissions/RolePermissionsDialog.vue'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()

  const roleStore = useRoleStore()
  const permStore = usePermissionStore()

  const tableRef = ref(null)
  const dialog = ref(false)
  const permDialog = ref(false)
  const saving = ref(false)
  const permSaving = ref(false)
  const editItem = ref(null)
  const activeRole = ref(null)
  const assignedPermIds = ref([])

  const filters = ref({ is_system: null })

  const headers = computed(() => [
    { title: t('form.name'), key: 'name' },
    { title: t('form.description'), key: 'description', sortable: false },
    { title: t('permissions.title'), key: 'permissions', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ])

  // Server-driven — matches BaseRepository::paginateServer()'s contract
  // (search/sortBy/sortDesc/page/perPage + is_system, see RoleRepository).
  async function fetchRoles(params) {
    const { data } = await getAllRolesApi(params)
    return { items: data.data, total: data.meta.total }
  }

  // ── Group color (for permission chip previews) ─────────────────────────────
  const colorList = [
    'primary', 'success', 'warning', 'error', 'secondary',
    'teal', 'purple', 'orange', 'pink', 'indigo'
  ]
  const colorCache = {}
  const groupColor = g => {
    if (!colorCache[g]) colorCache[g] = colorList[Object.keys(colorCache).length % colorList.length]
    return colorCache[g]
  }

  // ── Role CRUD ─────────────────────────────────────────────────────────────────
  const openCreate = () => {
    editItem.value = null
    dialog.value = true
  }
  const openEdit = item => {
    editItem.value = item
    dialog.value = true
  }

  const handleRoleSaved = async payload => {
    saving.value = true
    try {
      if (payload.id) {
        await roleStore.updateRole(payload.id, payload)
        notif(t('roles.messages.updated'), { type: 'success' })
      } else {
        await roleStore.createRole(payload)
        notif(t('roles.messages.created'), { type: 'success' })
      }
      dialog.value = false
      tableRef.value?.refresh()
    } catch (e) {
      notif(e?.response?.data?.message || t('unit.delete_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Permission assignment ─────────────────────────────────────────────────────
  const openPermissions = async role => {
    activeRole.value = role
    await roleStore.fetchRoleById(role.id)
    assignedPermIds.value = (roleStore.role?.permissions || []).map(p => p.id)
    permDialog.value = true
  }

  const handlePermissionsSaved = async ({ role_id, permission_ids }) => {
    permSaving.value = true
    try {
      await roleStore.updateRole(role_id, { permission_ids })
      notif(t('roles.messages.permissions_saved'), { type: 'success' })
      permDialog.value = false
      tableRef.value?.refresh()
    } catch (e) {
      notif(e?.response?.data?.message || t('unit.delete_failed'), { type: 'error' })
    } finally {
      permSaving.value = false
    }
  }

  const confirmDelete = data => {
    confirm({
      title: t('roles.delete_title'),
      message: t('roles.delete_message', { name: data?.name }),
      options: { type: 'warning', width: 550 },
      agree: async () => {
        try {
          await roleStore.deleteRole(data.id)
          notif(t('roles.messages.deleted'), { type: 'success' })
          tableRef.value?.refresh()
        } catch (e) {
          notif(e?.response?.data?.message || t('unit.delete_failed'), { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }

  // ── Lifecycle ─────────────────────────────────────────────────────────────────
  permStore.fetchPermissions({ perPage: 100 })
</script>
