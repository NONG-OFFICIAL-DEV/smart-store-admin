<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-shield-crown"
      title="Roles"
      subtitle="Define roles and assign permissions to control access"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          New Role
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Search ─────────────────────────────────────────────────────────── -->
    <v-card rounded="xl" elevation="0" border class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="5">
            <v-text-field
              v-model="search"
              placeholder="Search roles..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              rounded="lg"
            />
          </v-col>
          <v-col cols="6" sm="3">
            <v-select
              v-model="filterSystem"
              :items="[
                { title: 'System Roles', value: true },
                { title: 'Custom Roles', value: false }
              ]"
              label="Type"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              rounded="lg"
            />
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- ── Roles Grid ─────────────────────────────────────────────────────── -->
    <v-row dense>
      <v-col
        v-for="role in filteredRoles"
        :key="role.id"
        cols="12"
        md="6"
        lg="4"
      >
        <v-card rounded="xl" elevation="0" border hover>
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-3">
                <v-avatar
                  :color="role.is_system ? 'warning' : 'primary'"
                  size="40"
                  rounded="lg"
                  class="me-2"
                >
                  <v-icon
                    :icon="
                      role.is_system ? 'mdi-shield-crown' : 'mdi-shield-account'
                    "
                    size="20"
                    color="white"
                  />
                </v-avatar>
                <div>
                  <div class="text-subtitle-1 font-weight-bold">
                    {{ role.name }}
                  </div>
                  <div class="d-flex align-center gap-2 mt-1">
                    <v-chip
                      v-if="role.is_system"
                      color="warning"
                      variant="tonal"
                      size="x-small"
                      rounded="lg"
                    >
                      System
                    </v-chip>
                    <span class="text-caption text-medium-emphasis">
                      {{ role.permissions?.length || 0 }} permissions
                    </span>
                  </div>
                </div>
              </div>
              <div class="d-flex gap-1">
                <v-btn
                  icon="mdi-key-variant"
                  size="small"
                  variant="text"
                  color="warning"
                  @click="openPermissions(role)"
                />
                <v-btn
                  icon="mdi-pencil-outline"
                  size="small"
                  variant="text"
                  color="primary"
                  :disabled="role.is_system"
                  @click="openEdit(role)"
                />
                <v-btn
                  icon="mdi-delete-outline"
                  size="small"
                  variant="text"
                  color="error"
                  :disabled="role.is_system"
                  @click="confirmDelete(role)"
                />
              </div>
            </div>
          </v-card-title>

          <v-divider />

          <v-card-text class="pa-5">
            <p class="text-body-2 text-medium-emphasis mb-3">
              {{ role.description || 'No description provided.' }}
            </p>
            <div class="d-flex gap-1 flex-wrap">
              <v-chip
                v-for="perm in (role.permissions || []).slice(0, 4)"
                :key="perm.id"
                size="x-small"
                :color="groupColor(perm.group)"
                variant="tonal"
                rounded="lg"
              >
                {{ perm.code }}
              </v-chip>
              <v-chip
                v-if="(role.permissions?.length || 0) > 4"
                size="x-small"
                color="grey"
                variant="tonal"
                rounded="lg"
              >
                +{{ role.permissions.length - 4 }} more
              </v-chip>
              <span
                v-if="!role.permissions?.length"
                class="text-caption text-medium-emphasis"
              >
                No permissions assigned
              </span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col v-if="!filteredRoles.length" cols="12">
        <div class="text-center py-16">
          <v-icon icon="mdi-shield-off" size="64" color="grey-lighten-2" />
          <p class="text-h6 text-medium-emphasis mt-4">No roles found</p>
        </div>
      </v-col>
    </v-row>

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
  import { ref, computed, onMounted } from 'vue'
  import { useRoleStore } from '@/stores/roleStore'
  import { usePermissionStore } from '@/stores/permissionStore'
  import RoleFormDialog from '@/components/rolePermissions/RoleFormDialog.vue'
  import RolePermissionsDialog from '@/components/rolePermissions/RolePermissionsDialog.vue'
  import { useAppUtils } from '@nong-official-dev/core'
  const { confirm, notif } = useAppUtils()

  const roleStore = useRoleStore()
  const permStore = usePermissionStore()

  // ── UI state ──────────────────────────────────────────────────────────────────
  const search = ref('')
  const filterSystem = ref(null)
  const dialog = ref(false)
  const permDialog = ref(false)
  const deleteLoading = ref(false)
  const saving = ref(false)
  const permSaving = ref(false)
  const editItem = ref(null)
  const activeRole = ref(null)
  const assignedPermIds = ref([])

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const roles = computed(() => roleStore.roles || [])

  // ── Filtered roles ────────────────────────────────────────────────────────────
  const filteredRoles = computed(() => {
    return roles.value.filter(r => {
      if (
        filterSystem.value !== null &&
        filterSystem.value !== undefined &&
        r.is_system !== filterSystem.value
      )
        return false
      if (
        search.value &&
        !r.name.toLowerCase().includes(search.value.toLowerCase())
      )
        return false
      return true
    })
  })

  // ── Group color (for permission chip previews on cards) ───────────────────────
  const colorList = [
    'primary',
    'success',
    'warning',
    'error',
    'secondary',
    'teal',
    'purple',
    'orange',
    'pink',
    'indigo'
  ]
  const colorCache = {}
  const groupColor = g => {
    if (!colorCache[g])
      colorCache[g] =
        colorList[Object.keys(colorCache).length % colorList.length]
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
        notif(`'Role updated`, {
          type: 'success'
        })
      } else {
        await roleStore.createRole(payload)
        notif(`Role created`, {
          type: 'success'
        })
      }
      await roleStore.fetchRoles()
      dialog.value = false
    } catch (e) {
      notif(e?.response?.data?.message || 'Delete failed', {
        type: 'error'
      })
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
      await roleStore.fetchRoles()
      notif(`Permissions saved`, {
        type: 'success'
      })
      permDialog.value = false
    } catch (e) {
      notif(e?.response?.data?.message || 'Delete failed', {
        type: 'error'
      })
    } finally {
      permSaving.value = false
    }
  }

  const confirmDelete = async data => {
    deleteLoading.value = true
    try {
      confirm({
        title: 'Delete Role?',
        message: `Delete
            <strong>${data?.name}.</strong>?
            <br/> Users with this role will lose their access.`,
        options: { type: 'warning', width: 550 },
        agree: async () => {
          await roleStore.deleteRole(data.id)
          notif(`Role deleted`, {
            type: 'success'
          })
        },
        cancel: () => {}
      })
    } catch (e) {
      notif(e?.response?.data?.message || 'Delete failed', {
        type: 'error'
      })
    } finally {
      deleteLoading.value = false
    }
  }

  // ── Lifecycle ─────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await Promise.all([roleStore.fetchRoles(), permStore.fetchPermissions()])
  })
</script>
