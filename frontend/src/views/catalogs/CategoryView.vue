<template>
  <v-container fluid class="pa-0">
    <div class="d-flex justify-end mb-4">
      <v-btn
        v-if="canManage"
        color="primary"
        prepend-icon="mdi-plus"
        rounded="lg"
        elevation="0"
        @click="openCreateDialog"
      >
        {{ t('btn.add_category') }}
      </v-btn>
    </div>

    <!-- Table Card -->
    <v-card rounded="lg" elevation="0" border class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchCategories"
        item-label="categories"
      >
        <!-- Name Column -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <v-avatar :color="avatarColor(item.name)" size="32" rounded="md">
              <span class="text-caption font-weight-bold text-white">
                {{ item.name?.charAt(0)?.toUpperCase() }}
              </span>
            </v-avatar>
            <span class="font-weight-medium text-body-2 ms-2">
              {{ item.name }}
            </span>
          </div>
        </template>
        <!-- Status Column -->
        <template #item.is_active="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="small" />
        </template>

        <!-- Type Column — lets a tenant understand why some rows have no
             edit/delete actions, instead of them just silently disappearing. -->
        <template #item.type="{ item }">
          <v-tooltip v-if="item.is_system" :text="t('categories.view_only_hint')" location="top">
            <template #activator="{ props: tp }">
              <v-chip v-bind="tp" size="x-small" variant="tonal" color="primary" prepend-icon="mdi-shield-star-outline">
                {{ t('categories.system_badge') }}
              </v-chip>
            </template>
          </v-tooltip>
          <v-chip v-else size="x-small" variant="tonal">
            {{ t('categories.custom_badge') }}
          </v-chip>
        </template>

        <!-- Business Types Column — admin mode only. -->
        <template v-if="adminMode" #item.business_types="{ item }">
          <div class="d-flex flex-wrap gap-1">
            <v-chip v-for="bt in item.business_types" :key="bt.id" size="x-small" variant="tonal">
              {{ bt.name }}
            </v-chip>
            <span v-if="!item.business_types?.length" class="text-caption text-medium-emphasis">—</span>
          </div>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div v-if="adminMode || !item.is_system" class="d-flex align-center gap-1">
            <v-btn
              icon="mdi-pencil-outline"
              variant="text"
              size="small"
              color="primary"
              @click="openEditDialog(item)"
            />
            <v-btn
              icon="mdi-trash-can-outline"
              variant="text"
              size="small"
              color="error"
              @click="openDeleteConfirm(item)"
            />
          </div>
        </template>
      </AppTable>
    </v-card>

    <!-- Category Dialog (Create / Edit) -->
    <CategoryDialog
      v-if="dialog.show"
      v-model="dialog.show"
      :category="dialog.category"
      :admin-mode="adminMode"
      @saved="onSaved"
    />
  </v-container>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useCategoryStore } from '@/stores/categoryStore'
import CategoryDialog from '@/components/catalogs/CategoryDialog.vue'
import { getAllCategoriesApi } from '@/api/categoryService'
import { useAppUtils, AppTable, AppStatusChip } from '@nong-official-dev/core'
import { usePermission } from '@/composables/usePermission'
import { useI18n } from 'vue-i18n'
import { useAvatar } from '@/composables/useAvatar'

const props = defineProps({
  // Only true on the super-admin "system categories" page (see router) —
  // there, every category is a system one and every row is fully editable.
  // In the default (tenant) context, a tenant manages only their own
  // custom categories; system categories tagged for their business type
  // show up read-only (see TenantScope's 'categories' branch).
  adminMode: { type: Boolean, default: false }
})

const { t } = useI18n()
const { confirm } = useAppUtils()
const { can } = usePermission()
const categoryStore = useCategoryStore()
const { getAvatarColor } = useAvatar()

const canManage = computed(() => props.adminMode || can('categories.manage'))

const tableRef = ref(null)

// ── Headers ────────────────────────────────────────────────────────────────
const headers = computed(() => [
  { title: t('categories.table.name'), key: 'name', sortable: true },
  { title: t('categories.table.status'), key: 'is_active', sortable: true, width: '110px' },
  { title: t('categories.table.type'), key: 'type', sortable: false, width: '110px' },
  ...(props.adminMode
    ? [{ title: t('categories.dialog.business_types'), key: 'business_types', sortable: false }]
    : []),
  ...(canManage.value
    ? [{ title: '', key: 'actions', sortable: false, width: '100px', align: 'center' }]
    : [])
])

async function fetchCategories(params) {
  const { data } = await getAllCategoriesApi(params)
  return { items: data.data, total: data.meta.total }
}

// ── Dialog ─────────────────────────────────────────────────────────────────
const dialog = reactive({ show: false, category: null })

const openCreateDialog = () => {
  dialog.category = null
  dialog.show = true
}

const openEditDialog = item => {
  dialog.category = { ...item }
  dialog.show = true
}

const onSaved = () => {
  dialog.show = false
  tableRef.value?.refresh()
}

const openDeleteConfirm = async item => {
  confirm({
    title: t('categories.delete_title'),
    message: t('categories.delete_message', { name: item.name }),
    options: { type: 'warning', width: 550 },
    agree: async () => {
      await categoryStore.deleteCategory(item.id)
      tableRef.value?.refresh()
    },
    cancel: () => {}
  })
}

// ── Helpers ────────────────────────────────────────────────────────────────
const AVATAR_COLORS = ['primary', 'secondary', 'success', 'info', 'warning', 'purple', 'teal', 'pink']
const avatarColor = (name = '') => getAvatarColor(name, { palette: AVATAR_COLORS })
</script>