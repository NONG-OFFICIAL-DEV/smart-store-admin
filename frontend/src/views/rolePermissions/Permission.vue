<template>
  <v-container fluid class="pa-0">
    <custom-title
      title="Permissions"
      subtitle="          System-wide permission codes grouped by module
"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          New Permission
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
              placeholder="Search by code or description..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              rounded="lg"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-select
              v-model="filterGroup"
              :items="groupOptions"
              label="Filter by Group"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              rounded="lg"
            />
          </v-col>
          <v-col cols="12" sm="3">
            <v-btn-toggle
              v-model="viewMode"
              mandatory
              density="compact"
              rounded="lg"
              variant="outlined"
              color="primary"
              class="w-100"
            >
              <v-btn value="grouped" class="flex-1" size="small">
                <v-icon icon="mdi-folder-multiple" size="16" class="mr-1" />
                Grouped
              </v-btn>
              <v-btn value="table" class="flex-1" size="small">
                <v-icon icon="mdi-view-list" size="16" class="mr-1" />
                Table
              </v-btn>
            </v-btn-toggle>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- ── Grouped View ───────────────────────────────────────────────────── -->
    <template v-if="viewMode === 'grouped'">
      <v-row dense>
        <v-col
          v-for="(perms, groupName) in filteredGrouped"
          :key="groupName"
          cols="12"
          md="6"
        >
          <v-card rounded="xl" elevation="0" border class="mb-1">
            <!-- Group header -->
            <v-card-title class="pa-5 pb-3">
              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-3">
                  <v-avatar
                    :color="groupColor(groupName)"
                    size="38"
                    rounded="lg"
                  >
                    <v-icon
                      :icon="groupIcon(groupName)"
                      size="18"
                      color="white"
                    />
                  </v-avatar>
                  <div>
                    <div
                      class="text-subtitle-1 font-weight-bold text-capitalize"
                    >
                      {{ groupName }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ perms.length }} permissions
                    </div>
                  </div>
                </div>
                <v-btn
                  size="x-small"
                  variant="tonal"
                  :color="groupColor(groupName)"
                  rounded="lg"
                  prepend-icon="mdi-plus"
                  @click="openCreate(groupName)"
                >
                  Add
                </v-btn>
              </div>
            </v-card-title>
            <v-divider />

            <!-- Permission rows -->
            <v-list density="compact" class="pa-2">
              <v-list-item
                v-for="perm in perms"
                :key="perm.id"
                rounded="lg"
                class="mb-1"
              >
                <template #prepend>
                  <v-chip
                    :color="groupColor(groupName)"
                    variant="tonal"
                    size="x-small"
                    rounded="lg"
                    class="mr-2 font-weight-bold"
                    style="font-family: monospace"
                  >
                    {{ perm.code }}
                  </v-chip>
                </template>
                <v-list-item-title class="text-body-2">
                  {{ perm.description || '—' }}
                </v-list-item-title>
                <template #append>
                  <div class="d-flex gap-1">
                    <v-btn
                      icon="mdi-pencil-outline"
                      size="x-small"
                      variant="text"
                      color="primary"
                      @click="openEdit(perm)"
                    />
                    <v-btn
                      icon="mdi-delete-outline"
                      size="x-small"
                      variant="text"
                      color="error"
                      @click="confirmDelete(perm)"
                    />
                  </div>
                </template>
              </v-list-item>
            </v-list>
          </v-card>
        </v-col>
      </v-row>

      <div
        v-if="!Object.keys(filteredGrouped).length"
        class="text-center py-16"
      >
        <v-icon icon="mdi-key-off" size="64" color="grey-lighten-2" />
        <p class="text-h6 text-medium-emphasis mt-4">No permissions found</p>
      </div>
    </template>

    <!-- ── Table View ─────────────────────────────────────────────────────── -->
    <v-card v-else rounded="xl" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="filteredList"
        item-value="id"
        rounded="xl"
        hover
      >
        <template #item.code="{ item }">
          <v-chip
            :color="groupColor(item.group)"
            variant="tonal"
            size="small"
            rounded="lg"
            style="font-family: monospace; font-weight: 600"
          >
            {{ item.code }}
          </v-chip>
        </template>

        <template #item.group="{ item }">
          <div class="d-flex align-center gap-2">
            <v-avatar :color="groupColor(item.group)" size="22" rounded="md">
              <v-icon :icon="groupIcon(item.group)" size="12" color="white" />
            </v-avatar>
            <span class="text-capitalize">{{ item.group }}</span>
          </div>
        </template>

        <template #item.description="{ item }">
          <span class="text-body-2 text-medium-emphasis">
            {{ item.description || '—' }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-end">
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
      </v-data-table>
    </v-card>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ── Permission Form Dialog ────────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <v-dialog v-model="dialog" max-width="480" persistent>
      <v-card rounded="xl" elevation="0" border>
        <v-card-title class="d-flex align-center justify-space-between">
          <div>
            {{ isEdit ? 'Edit Permission' : 'New Permission' }}
          </div>
          <v-btn
            icon="mdi-close"
            size="small"
            variant="text"
            @click="closeDialog"
          />
        </v-card-title>
        <v-divider />

        <v-card-text class="pa-6">
          <v-form ref="formRef">
            <v-row dense>
              <!-- Code -->
              <v-col cols="12">
                <v-text-field
                  v-model="form.code"
                  label="Permission Code"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-code-tags"
                  :rules="[rules.required, rules.maxLen(100), rules.codeFormat]"
                  hint="e.g. products.create, orders.view — use dot notation"
                  persistent-hint
                  style="font-family: monospace"
                  :disabled="isEdit"
                />
              </v-col>

              <!-- Group -->
              <v-col cols="12">
                <v-combobox
                  v-model="form.group"
                  :items="groupOptions"
                  label="Group / Module"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-folder-outline"
                  :rules="[rules.required, rules.maxLen(60)]"
                  hint="Existing group or type a new one"
                  persistent-hint
                />
              </v-col>

              <!-- Description -->
              <v-col cols="12">
                <v-textarea
                  v-model="form.description"
                  label="Description"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-text"
                  rows="3"
                  hint="What does this permission allow?"
                  persistent-hint
                  clearable
                />
              </v-col>

              <!-- Code preview -->
              <v-col v-if="form.code" cols="12">
                <v-card
                  rounded="lg"
                  color="grey-lighten-4"
                  elevation="0"
                  class="pa-3"
                >
                  <div class="text-caption text-medium-emphasis mb-1">
                    Preview
                  </div>
                  <div class="d-flex align-center gap-2">
                    <v-chip
                      :color="groupColor(form.group)"
                      variant="tonal"
                      size="small"
                      rounded="lg"
                      style="font-family: monospace; font-weight: 700"
                    >
                      {{ form.code }}
                    </v-chip>
                    <v-icon icon="mdi-arrow-right" size="14" color="grey" />
                    <span class="text-body-2 text-medium-emphasis">
                      {{ form.description || 'No description' }}
                    </span>
                  </div>
                </v-card>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider />
        <v-card-actions class="pa-6 pt-4 gap-3">
          <v-btn variant="tonal" rounded="lg" @click="closeDialog">
            Cancel
          </v-btn>
          <v-btn
            :color="isEdit ? 'primary' : 'success'"
            variant="flat"
            rounded="lg"
            @click="handleSubmit"
          >
            {{ isEdit ? 'Save Changes' : 'Create Permission' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete confirm -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="xl" elevation="0" border>
        <v-card-text class="pa-6 text-center">
          <v-avatar color="error" size="56" rounded="lg" class="mb-4">
            <v-icon icon="mdi-key-remove" size="28" />
          </v-avatar>
          <h3 class="text-h6 font-weight-bold mb-2">Delete Permission?</h3>
          <p class="text-body-2 text-medium-emphasis">
            Delete
            <strong>{{ deleteTarget?.code }}</strong>
            ? Roles using this permission will lose this access.
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
  import { usePermissionStore } from '@/stores/permissionStore'

  const store = usePermissionStore()

  // ── UI state ──────────────────────────────────────────────────────────────────
  const search = ref('')
  const filterGroup = ref(null)
  const viewMode = ref('grouped')
  const dialog = ref(false)
  const deleteDialog = ref(false)
  const deleteTarget = ref(null)
  const deleteLoading = ref(false)
  const saving = ref(false)
  const formRef = ref(null)
  const editItem = ref(null)
  const snack = ref({ show: false, text: '', color: 'success' })

  // ── Default form ──────────────────────────────────────────────────────────────
  const defaultForm = (group = '') => ({
    id: null,
    code: '',
    group,
    description: ''
  })
  const form = ref(defaultForm())
  const isEdit = computed(() => !!editItem.value)

  // ── Data ──────────────────────────────────────────────────────────────────────
  const permissions = computed(() => store.permissions || [])

  // ── Stats ─────────────────────────────────────────────────────────────────────
  const allGroups = computed(() => [
    ...new Set(permissions.value.map(p => p.group))
  ])

  const stats = computed(() => [
    {
      label: 'Total Permissions',
      value: permissions.value.length,
      icon: 'mdi-key-variant',
      color: 'primary'
    },
    {
      label: 'Groups / Modules',
      value: allGroups.value.length,
      icon: 'mdi-folder-multiple',
      color: 'success'
    },
    {
      label: 'Avg per Group',
      value: allGroups.value.length
        ? Math.round(permissions.value.length / allGroups.value.length)
        : 0,
      icon: 'mdi-chart-bar',
      color: 'warning'
    },
    { label: 'Unassigned', value: '—', icon: 'mdi-key-off', color: 'secondary' }
  ])

  const groupOptions = computed(() => allGroups.value)

  // ── Grouped ───────────────────────────────────────────────────────────────────
  const groupedAll = computed(() => {
    return permissions.value.reduce((acc, p) => {
      if (!acc[p.group]) acc[p.group] = []
      acc[p.group].push(p)
      return acc
    }, {})
  })

  const filteredGrouped = computed(() => {
    const result = {}
    for (const [group, perms] of Object.entries(groupedAll.value)) {
      if (filterGroup.value && group !== filterGroup.value) continue
      const filtered = perms.filter(p => {
        if (!search.value) return true
        const q = search.value.toLowerCase()
        return (
          p.code.toLowerCase().includes(q) ||
          (p.description || '').toLowerCase().includes(q)
        )
      })
      if (filtered.length) result[group] = filtered
    }
    return result
  })

  const filteredList = computed(() => {
    return permissions.value.filter(p => {
      if (filterGroup.value && p.group !== filterGroup.value) return false
      if (search.value) {
        const q = search.value.toLowerCase()
        return (
          p.code.toLowerCase().includes(q) ||
          (p.description || '').toLowerCase().includes(q)
        )
      }
      return true
    })
  })

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    { title: 'Code', key: 'code', sortable: true },
    { title: 'Group', key: 'group', sortable: true },
    { title: 'Description', key: 'description', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || 'Required',
    maxLen: n => v => !v || v.length <= n || `Max ${n} chars`,
    codeFormat: v =>
      !v ||
      /^[a-z0-9_]+(\.[a-z0-9_]+)*$/.test(v) ||
      'Use lowercase dot.notation (e.g. orders.view)'
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
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
    if (!colorCache[g]) {
      const keys = Object.keys(colorCache)
      colorCache[g] = colorList[keys.length % colorList.length]
    }
    return colorCache[g]
  }

  const iconMap = {
    products: 'mdi-package-variant',
    orders: 'mdi-receipt',
    users: 'mdi-account-group',
    branches: 'mdi-store',
    menus: 'mdi-book-open',
    roles: 'mdi-shield-account',
    reports: 'mdi-chart-bar',
    settings: 'mdi-cog',
    payments: 'mdi-credit-card',
    inventory: 'mdi-warehouse'
  }
  const groupIcon = g => iconMap[g?.toLowerCase()] || 'mdi-folder'

  const showSnack = (text, color = 'success') => {
    snack.value = { show: true, text, color }
  }

  // ── CRUD ──────────────────────────────────────────────────────────────────────
  const openCreate = (group = '') => {
    editItem.value = null
    form.value = defaultForm(typeof group === 'string' ? group : '')
    dialog.value = true
  }

  const openEdit = item => {
    editItem.value = item
    form.value = {
      id: item.id,
      code: item.code,
      group: item.group,
      description: item.description || ''
    }
    dialog.value = true
  }

  const closeDialog = () => {
    formRef.value?.reset()
    form.value = defaultForm()
    dialog.value = false
  }

  const handleSubmit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    saving.value = true
    try {
      if (isEdit.value) {
        await store.updatePermission(form.value.id, form.value)
        showSnack('Permission updated')
      } else {
        await store.createPermission(form.value)
        showSnack('Permission created')
      }
      await store.fetchPermissions()
      closeDialog()
    } catch (e) {
      showSnack(e?.response?.data?.message || 'Failed', 'error')
    } finally {
      saving.value = false
    }
  }

  const confirmDelete = item => {
    deleteTarget.value = item
    deleteDialog.value = true
  }

  const doDelete = async () => {
    deleteLoading.value = true
    try {
      await store.deletePermission(deleteTarget.value.id)
      showSnack('Permission deleted', 'error')
      deleteDialog.value = false
    } catch (e) {
      showSnack(e?.response?.data?.message || 'Delete failed', 'error')
    } finally {
      deleteLoading.value = false
    }
  }

  // ── Lifecycle ─────────────────────────────────────────────────────────────────
  onMounted(() => store.fetchPermissions())
</script>
