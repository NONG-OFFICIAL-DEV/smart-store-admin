<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-key"
      :title="$t('permissions.title')"
      :subtitle="$t('permissions.subtitle')"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          class="ms-2"
          @click="openCreate"
        >
          {{ $t('permissions.new') }}
        </v-btn>
      </template>
    </custom-title>

    <!-- ── Filters ────────────────────────────────────────────────────────── -->
    <v-row dense align="center" class="mb-2">
      <v-col cols="12" sm="3">
        <v-select
          v-model="filterGroup"
          :items="groupOptions"
          :label="$t('permissions.filter_by_group')"
          variant="outlined"
          clearable
          rounded="lg"
        />
      </v-col>
    </v-row>

    <!-- ── Table View — server-driven, independent of the grouped view's
    fully-loaded list above (permission catalog is small, but the table view
    still paginates/searches/sorts through the real API). ────────────────── -->
    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchPermissionsForTable"
        :filters="tableFilters"
        item-label="permissions"
      >
        <template #[`item.code`]="{ item }">
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

        <template #[`item.group`]="{ item }">
          <div class="d-flex align-center gap-2">
            <v-avatar :color="groupColor(item.group)" size="22" rounded="md">
              <v-icon :icon="groupIcon(item.group)" size="12" color="white" />
            </v-avatar>
            <span class="text-capitalize">{{ item.group }}</span>
          </div>
        </template>

        <template #[`item.description`]="{ item }">
          <span class="text-body-2 text-medium-emphasis">
            {{ item.description || '—' }}
          </span>
        </template>

        <template #[`item.actions`]="{ item }">
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
      </AppTable>
    </v-card>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ── Permission Form Dialog ────────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <AppDialog
      v-model="dialog"
      :max-width="480"
      :title="isEdit ? $t('permissions.edit_title') : $t('permissions.new')"
      :subtitle="
        isEdit
          ? $t('permissions.edit_subtitle')
          : $t('permissions.new_subtitle')
      "
      icon="mdi-key-outline"
      :color="isEdit ? 'primary' : 'success'"
      :loading="saving"
      :submit-text="isEdit ? $t('btn.save_changes') : $t('permissions.create')"
      @close="closeDialog"
      @submit="handleSubmit"
    >
      <v-form ref="formRef">
        <v-row dense>
          <!-- Code -->
          <v-col cols="12">
            <v-text-field
              v-model="form.code"
              :label="$t('permissions.field.code')"
              variant="outlined"
              prepend-inner-icon="mdi-code-tags"
              :rules="[rules.required, rules.maxLen(100), rules.codeFormat]"
              :hint="$t('permissions.field.code_hint')"
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
              :label="$t('permissions.field.group')"
              variant="outlined"
              prepend-inner-icon="mdi-folder-outline"
              :rules="[rules.required, rules.maxLen(60)]"
              :hint="$t('permissions.field.group_hint')"
              persistent-hint
            />
          </v-col>

          <!-- Description -->
          <v-col cols="12">
            <v-textarea
              v-model="form.description"
              :label="$t('form.description')"
              variant="outlined"
              prepend-inner-icon="mdi-text"
              rows="3"
              :hint="$t('permissions.field.description_hint')"
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
                {{ $t('permissions.preview') }}
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
                  {{ form.description || $t('permissions.no_description') }}
                </span>
              </div>
            </v-card>
          </v-col>
        </v-row>
      </v-form>
    </AppDialog>
  </v-container>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { usePermissionStore } from '@/stores/permissionStore'
  import { getAllPermissionsApi } from '@/api/permissionService'
  import { useAppUtils, AppTable } from '@nong-official-dev/core'
  import AppDialog from '@/components/common/AppDialog.vue'
  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()
  const store = usePermissionStore()

  // ── UI state ──────────────────────────────────────────────────────────────────
  const tableRef = ref(null)
  const filterGroup = ref(null)
  const dialog = ref(false)
  const deleteLoading = ref(false)
  const saving = ref(false)
  const formRef = ref(null)
  const editItem = ref(null)

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


  const groupOptions = computed(() => allGroups.value)
  

  // ── Table view — server-driven, independent of the grouped view's fully
  // loaded `permissions` list above. Only `group` is shared as a filter
  // concept; search is AppTable's own built-in box. ───────────────────────
  const tableFilters = computed(() => ({
    group: filterGroup.value || undefined
  }))

  async function fetchPermissionsForTable(params) {
    const { data } = await getAllPermissionsApi(params)
    return { items: data.data, total: data.meta.total }
  }

  // ── Table headers ─────────────────────────────────────────────────────────────
  const headers = [
    { title: t('permissions.table.code'), key: 'code', sortable: true },
    { title: t('permissions.table.group'), key: 'group', sortable: true },
    { title: t('form.description'), key: 'description', sortable: false },
    { title: '', key: 'actions', sortable: false, align: 'end' }
  ]

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || t('validation.required'),
    maxLen: n => v => !v || v.length <= n || t('validation.max_length', { n }),
    codeFormat: v =>
      !v ||
      /^[a-z0-9_]+(\.[a-z0-9_]+)*$/.test(v) ||
      t('permissions.code_format_error')
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
        notif(t('permissions.messages.updated'), {
          type: 'success'
        })
      } else {
        await store.createPermission(form.value)
        notif(t('permissions.messages.created'), {
          type: 'success'
        })
      }
      await store.fetchPermissions()
      tableRef.value?.refresh()
      closeDialog()
    } catch (e) {
      notif(e?.response?.data?.message || t('unit.delete_failed'), {
        type: 'error'
      })
    } finally {
      saving.value = false
    }
  }
  const confirmDelete = async item => {
    deleteLoading.value = true
    try {
      confirm({
        title: t('permissions.delete_title'),
        message: t('permissions.delete_message', { code: item.code }),
        options: { type: 'warning', width: 550 },
        agree: async () => {
          await store.deletePermission(item.id)
          tableRef.value?.refresh()
          notif(t('permissions.messages.deleted'), {
            type: 'success'
          })
        },
        cancel: () => {}
      })
    } catch {
      notif(t('unit.delete_failed'), {
        type: 'error'
      })
    } finally {
      deleteLoading.value = false
    }
  }

  // ── Lifecycle ─────────────────────────────────────────────────────────────────
  onMounted(() => store.fetchPermissions())
</script>
