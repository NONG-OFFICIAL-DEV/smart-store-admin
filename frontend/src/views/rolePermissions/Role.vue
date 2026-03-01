<template>
  <v-container fluid class="pa-0">
    <custom-title
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

    <!-- ── Stats ──────────────────────────────────────────────────────────── -->
    <v-row dense class="mb-4">
      <v-col v-for="stat in stats" :key="stat.label" cols="6" sm="3">
        <v-card rounded="xl" elevation="0" border>
          <v-card-text class="pa-4">
            <div class="d-flex align-center justify-space-between">
              <div>
                <p class="text-caption text-medium-emphasis">
                  {{ stat.label }}
                </p>
                <p class="text-h6 font-weight-bold mt-1">{{ stat.value }}</p>
              </div>
              <v-avatar :color="stat.color" size="40" rounded="lg">
                <v-icon :icon="stat.icon" size="20" color="white" />
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

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

    <!-- ── Delete Confirm ─────────────────────────────────────────────────── -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="xl" elevation="0" border>
        <v-card-text class="pa-6 text-center">
          <v-avatar color="error" size="56" rounded="lg" class="mb-4">
            <v-icon icon="mdi-shield-remove" size="28" />
          </v-avatar>
          <h3 class="text-h6 font-weight-bold mb-2">Delete Role?</h3>
          <p class="text-body-2 text-medium-emphasis">
            Delete
            <strong>{{ deleteTarget?.name }}</strong>
            ? Users with this role will lose their access.
          </p>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0 gap-3">
          <v-btn
            block
            variant="tonal"
            rounded="lg"
            @click="deleteDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            block
            color="error"
            variant="flat"
            rounded="lg"
            :loading="deleteLoading"
            @click="doDelete"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar -->
    <v-snackbar
      v-model="snack.show"
      :color="snack.color"
      rounded="lg"
      location="bottom right"
    >
      {{ snack.text }}
      <template #actions>
        <v-btn
          icon="mdi-close"
          size="small"
          variant="text"
          @click="snack.show = false"
        />
      </template>
    </v-snackbar>
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useRoleStore } from '@/stores/roleStore'
  import { usePermissionStore } from '@/stores/permissionStore'
  import RoleFormDialog from '@/components/rolePermissions/RoleFormDialog.vue'
  import RolePermissionsDialog from '@/components/rolePermissions/RolePermissionsDialog.vue'

  const roleStore = useRoleStore()
  const permStore = usePermissionStore()

  // ── UI state ──────────────────────────────────────────────────────────────────
  const search = ref('')
  const filterSystem = ref(null)
  const dialog = ref(false)
  const permDialog = ref(false)
  const deleteDialog = ref(false)
  const deleteTarget = ref(null)
  const deleteLoading = ref(false)
  const saving = ref(false)
  const permSaving = ref(false)
  const editItem = ref(null)
  const activeRole = ref(null)
  const assignedPermIds = ref([])
  const snack = ref({ show: false, text: '', color: 'success' })

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const roles = computed(() => roleStore.roles || [])

  const stats = computed(() => [
    {
      label: 'Total Roles',
      value: roles.value.length,
      icon: 'mdi-shield-account',
      color: 'primary'
    },
    {
      label: 'System Roles',
      value: roles.value.filter(r => r.is_system).length,
      icon: 'mdi-shield-crown',
      color: 'warning'
    },
    {
      label: 'Custom Roles',
      value: roles.value.filter(r => !r.is_system).length,
      icon: 'mdi-shield-edit',
      color: 'success'
    },
    {
      label: 'Permissions',
      value: permStore.permissions?.length || 0,
      icon: 'mdi-key-variant',
      color: 'secondary'
    }
  ])

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

  const showSnack = (text, color = 'success') => {
    snack.value = { show: true, text, color }
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
        showSnack('Role updated')
      } else {
        await roleStore.createRole(payload)
        showSnack('Role created')
      }
      await roleStore.fetchRoles()
      dialog.value = false
    } catch (e) {
      showSnack(e?.response?.data?.message || 'Failed', 'error')
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
      showSnack('Permissions saved')
      permDialog.value = false
    } catch (e) {
      showSnack(e?.response?.data?.message || 'Failed', 'error')
    } finally {
      permSaving.value = false
    }
  }

  // ── Delete ────────────────────────────────────────────────────────────────────
  const confirmDelete = item => {
    deleteTarget.value = item
    deleteDialog.value = true
  }

  const doDelete = async () => {
    deleteLoading.value = true
    try {
      await roleStore.deleteRole(deleteTarget.value.id)
      showSnack('Role deleted', 'error')
      deleteDialog.value = false
    } catch (e) {
      showSnack(e?.response?.data?.message || 'Delete failed', 'error')
    } finally {
      deleteLoading.value = false
    }
  }

  // ── Lifecycle ─────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await Promise.all([roleStore.fetchRoles(), permStore.fetchPermissions()])
  })
</script>
