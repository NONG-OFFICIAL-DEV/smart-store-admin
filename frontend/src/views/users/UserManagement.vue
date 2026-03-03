<template>
  <v-container fluid class="pa-0">
    <custom-title
      title="Users"
      subtitle="Manage all user accounts across the platform"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          Add User
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

    <!-- ── Filters ────────────────────────────────────────────────────────── -->
    <v-card rounded="xl" elevation="0" border class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="5">
            <v-text-field
              v-model="search"
              placeholder="Search by name, email, phone..."
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
              v-model="filterStatus"
              :items="[
                { title: 'Active', value: true },
                { title: 'Inactive', value: false }
              ]"
              label="Status"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              rounded="lg"
            />
          </v-col>
          <v-col cols="6" sm="3">
            <v-select
              v-model="filterVerified"
              :items="[
                { title: 'Verified', value: true },
                { title: 'Unverified', value: false }
              ]"
              label="Email Verified"
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

    <!-- ── Table ──────────────────────────────────────────────────────────── -->
    <v-card rounded="xl" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="filteredUsers"
        :loading="store.loading"
        item-value="id"
        rounded="xl"
        hover
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
            {{ item.email_verified_at ? 'Verified' : 'Pending' }}
          </v-chip>
        </template>

        <!-- Last login -->
        <template #item.last_login_at="{ item }">
          <span v-if="item.last_login_at" class="text-body-2">
            {{ formatDate(item.last_login_at) }}
          </span>
          <span v-else class="text-medium-emphasis text-caption">Never</span>
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

        <template #item.staff.role.name="{ item }">
          <v-chip
            :color="item.is_super_admin ? 'primary' : 'secondary'"
            :prepend-icon="
              item.is_super_admin ? 'mdi-shield-check' : 'mdi-account-tie'
            "
            size="small"
            variant="flat"
            class="text-uppercase"
          >
            {{ item.is_super_admin ? 'Super Admin' : item.staff?.role?.name }}
          </v-chip>
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
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="confirmDelete(item)"
            />
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <v-icon icon="mdi-account-off" size="48" color="grey-lighten-2" />
            <p class="text-medium-emphasis mt-3">No users found</p>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- ── Dialogs ────────────────────────────────────────────────────────── -->
    <UserFormDialog
      v-model="dialog"
      :edit-item="editItem"
      :loading="saving"
      @saved="handleUserSaved"
    />

    <!-- Delete Confirm -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="xl" elevation="0" border>
        <v-card-text class="pa-6 text-center">
          <v-avatar color="error" size="56" rounded="lg" class="mb-4">
            <v-icon icon="mdi-account-remove" size="28" />
          </v-avatar>
          <h3 class="text-h6 font-weight-bold mb-2">Delete User?</h3>
          <p class="text-body-2 text-medium-emphasis">
            Are you sure you want to delete
            <strong>
              {{ deleteTarget?.first_name }} {{ deleteTarget?.last_name }}
            </strong>
            ? This cannot be undone.
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
  import { useUserStore } from '@/stores/userStore'
  import UserFormDialog from '@/components/users/UserFormDialog.vue'

  const store = useUserStore()

  // ── UI state ──────────────────────────────────────────────────────────────────
  const search = ref('')
  const filterStatus = ref(null)
  const filterVerified = ref(null)
  const dialog = ref(false)
  const deleteDialog = ref(false)
  const deleteTarget = ref(null)
  const deleteLoading = ref(false)
  const saving = ref(false)
  const editItem = ref(null)
  const snack = ref({ show: false, text: '', color: 'success' })

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    { title: 'User', key: 'first_name', sortable: true },
    { title: 'Role', key: 'staff.role.name', sortable: true },
    { title: 'Phone', key: 'phone', sortable: false },
    { title: 'Email Status', key: 'email_verified_at', sortable: false },
    { title: 'Last Login', key: 'last_login_at', sortable: true },
    { title: 'Active', key: 'is_active', sortable: true },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const users = computed(() => store.users?.data || [])

  const stats = computed(() => [
    {
      label: 'Total Users',
      value: users.value.length,
      icon: 'mdi-account-group',
      color: 'primary'
    },
    {
      label: 'Active',
      value: users.value.filter(u => u.is_active).length,
      icon: 'mdi-check-circle',
      color: 'success'
    },
    {
      label: 'Verified',
      value: users.value.filter(u => u.email_verified_at).length,
      icon: 'mdi-email-check',
      color: 'blue'
    },
    {
      label: 'Inactive',
      value: users.value.filter(u => !u.is_active).length,
      icon: 'mdi-account-off',
      color: 'error'
    }
  ])

  // ── Filtered ──────────────────────────────────────────────────────────────────
  const filteredUsers = computed(() => {
    return users.value.filter(u => {
      if (
        filterStatus.value !== null &&
        filterStatus.value !== undefined &&
        u.is_active !== filterStatus.value
      )
        return false
      if (filterVerified.value !== null && filterVerified.value !== undefined) {
        if (filterVerified.value && !u.email_verified_at) return false
        if (!filterVerified.value && u.email_verified_at) return false
      }
      if (search.value) {
        const q = search.value.toLowerCase()
        return (
          `${u.first_name} ${u.last_name}`.toLowerCase().includes(q) ||
          u.email.toLowerCase().includes(q) ||
          (u.phone || '').toLowerCase().includes(q)
        )
      }
      return true
    })
  })

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
  const avatarColor = name =>
    name ? colors[name.charCodeAt(0) % colors.length] : 'grey'
  const initials = u =>
    `${u.first_name?.[0] || ''}${u.last_name?.[0] || ''}`.toUpperCase()
  const formatDate = d =>
    d
      ? new Date(d).toLocaleDateString('en-US', {
          month: 'short',
          day: 'numeric',
          year: 'numeric'
        })
      : '—'
  const showSnack = (text, color = 'success') => {
    snack.value = { show: true, text, color }
  }

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
        showSnack('User updated successfully')
      } else {
        await store.addUser(payload)
        showSnack('User created successfully')
      }
      await store.fetchUsers()
      dialog.value = false
    } catch (e) {
      showSnack(e?.response?.data?.message || 'Operation failed', 'error')
    } finally {
      saving.value = false
    }
  }

  const toggleActive = async item => {
    try {
      await store.updateUser({ id: item.id, is_active: item.is_active })
      showSnack(`User ${item.is_active ? 'activated' : 'deactivated'}`)
    } catch {
      item.is_active = !item.is_active // revert on error
    }
  }

  const confirmDelete = item => {
    deleteTarget.value = item
    deleteDialog.value = true
  }

  const doDelete = async () => {
    deleteLoading.value = true
    try {
      await store.deleteUser(deleteTarget.value.id)
      showSnack('User deleted', 'error')
      deleteDialog.value = false
    } catch (e) {
      showSnack(e?.response?.data?.message || 'Delete failed', 'error')
    } finally {
      deleteLoading.value = false
    }
  }

  // ── Lifecycle ─────────────────────────────────────────────────────────────────
  onMounted(() => store.fetchUsers())
</script>
