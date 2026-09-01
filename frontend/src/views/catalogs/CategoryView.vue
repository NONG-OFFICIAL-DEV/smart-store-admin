<template>
  <v-container fluid class="pa-0">
    <custom-title
      :title="t('categories.title')"
      :subtitle="t('categories.subtitle')"
      icon="mdi-format-list-bulleted-type"
    >
      <template #right>
        <v-btn
          v-if="isSuperAdmin()"
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          class="ms-2"
          @click="openCreateDialog"
        >
          {{ t('btn.add_category') }}
        </v-btn>
      </template>
    </custom-title>

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

        <!-- Description Column -->
        <template #item.description="{ item }">
          <span
            class="text-body-2 text-grey text-truncate d-inline-block"
            style="max-width: 260px"
          >
            {{ item.description || '—' }}
          </span>
        </template>
        <template #item.icon="{ item }">
          <v-icon>{{ item.icon }}</v-icon>
        </template>

        <!-- Status Column -->
        <template #item.is_active="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="small" />
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center gap-1">
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

const { t } = useI18n()
const { confirm } = useAppUtils()
const { isSuperAdmin } = usePermission()
const categoryStore = useCategoryStore()
const { getAvatarColor } = useAvatar()

const tableRef = ref(null)

// ── Headers ────────────────────────────────────────────────────────────────
const headers = computed(() =>[
  { title: t('categories.table.name'), key: 'name', sortable: true },
  { title: t('categories.table.icon'), key: 'icon', sortable: true },
  { title: t('categories.table.description'), key: 'description', sortable: false },
  { title: t('categories.table.status'), key: 'is_active', sortable: true, width: '110px' },
  ...(isSuperAdmin()
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