<template>
  <v-container fluid class="pa-0">
    <!-- Add — inline, no dialog: a name field, two switches, and a plus
         button that creates the category immediately. -->
    <v-card v-if="canManage" rounded="lg" elevation="0" border class="pa-3 mb-4">
      <div class="d-flex align-center flex-wrap ga-3">
        <v-text-field
          v-model="newCategory.name"
          :placeholder="t('categories.dialog.name_placeholder')"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          maxlength="100"
          class="flex-grow-1"
          style="min-width: 200px"
          @keyup.enter="submitNew"
        />
        <div class="d-flex align-center ga-2">
          <span class="text-body-2">{{ t('categories.dialog.active_status') }}</span>
          <v-switch v-model="newCategory.is_active" color="success" hide-details density="compact" />
        </div>
        <div class="d-flex align-center ga-2">
          <span class="text-body-2">{{ t('categories.dialog.lid_exchange') }}</span>
          <v-switch v-model="newCategory.is_lid_exchange" color="warning" hide-details density="compact" />
        </div>
        <v-select
          v-if="adminMode"
          v-model="newCategory.business_type_ids"
          :items="businessTypeStore.businessTypes"
          item-title="name"
          item-value="id"
          :placeholder="t('categories.dialog.business_types_placeholder')"
          variant="outlined"
          density="compact"
          rounded="lg"
          multiple
          chips
          closable-chips
          hide-details
          class="flex-grow-1"
          style="min-width: 220px"
        />
        <v-btn
          icon="mdi-plus"
          color="primary"
          variant="flat"
          :loading="saving"
          :disabled="!newCategory.name.trim()"
          @click="submitNew"
        />
      </div>
    </v-card>

    <!-- List -->
    <!-- <v-card rounded="lg" elevation="0" border> -->
      <v-list v-if="items.length" lines="two" desnsity="compact">
        <template v-for="(item, idx) in items" :key="item.id">
          <!-- Editing this row — same shape as the add row above. -->
          <v-list-item v-if="editingId === item.id">
            <div class="d-flex align-center flex-wrap ga-3 py-1">
              <v-text-field
                v-model="editForm.name"
                variant="outlined"
                density="compact"
                rounded="lg"
                hide-details
                maxlength="100"
                class="flex-grow-1"
                style="min-width: 180px"
                @keyup.enter="submitEdit(item)"
              />
              <div class="d-flex align-center ga-2">
                <span class="text-body-2">{{ t('categories.dialog.active_status') }}</span>
                <v-switch v-model="editForm.is_active" color="success" hide-details density="compact" />
              </div>
              <div class="d-flex align-center ga-2">
                <span class="text-body-2">{{ t('categories.dialog.lid_exchange') }}</span>
                <v-switch v-model="editForm.is_lid_exchange" color="warning" hide-details density="compact" />
              </div>
              <v-select
                v-if="adminMode"
                v-model="editForm.business_type_ids"
                :items="businessTypeStore.businessTypes"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                rounded="lg"
                multiple
                chips
                closable-chips
                hide-details
                class="flex-grow-1"
                style="min-width: 220px"
              />
              <v-btn icon="mdi-check" color="success" variant="text" :loading="saving" @click="submitEdit(item)" />
              <v-btn icon="mdi-close" variant="text" @click="cancelEdit" />
            </div>
          </v-list-item>

          <!-- Display -->
          <v-list-item v-else>
            <v-list-item-title class="font-weight-medium">{{ item.name }}</v-list-item-title>
            <v-list-item-subtitle>
              <div class="d-flex align-center flex-wrap ga-1 mt-1">
                <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="x-small" />
                <v-tooltip v-if="item.is_system" :text="t('categories.view_only_hint')" location="top">
                  <template #activator="{ props: tp }">
                    <v-chip v-bind="tp" size="x-small" variant="tonal" color="primary" prepend-icon="mdi-shield-star-outline">
                      {{ t('categories.system_badge') }}
                    </v-chip>
                  </template>
                </v-tooltip>
                <v-chip v-else size="x-small" variant="tonal">{{ t('categories.custom_badge') }}</v-chip>
                <v-chip v-if="item.is_lid_exchange" size="x-small" variant="tonal" color="warning">
                  {{ t('categories.dialog.lid_exchange') }}
                </v-chip>
                <template v-if="adminMode">
                  <v-chip v-for="bt in item.business_types" :key="bt.id" size="x-small" variant="tonal">
                    {{ bt.name }}
                  </v-chip>
                </template>
              </div>
            </v-list-item-subtitle>

            <template #append>
              <div v-if="adminMode || !item.is_system" class="d-flex align-center ga-1">
                <v-btn icon="mdi-pencil-outline" size="small" variant="text" color="primary" @click="startEdit(item)" />
                <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="openDeleteConfirm(item)" />
              </div>
            </template>
          </v-list-item>
          <v-divider v-if="idx < items.length - 1" />
        </template>
      </v-list>

      <div v-else class="text-center py-10">
        <v-icon icon="mdi-shape-outline" size="40" color="grey-lighten-1" />
        <p class="text-medium-emphasis mt-2 mb-0">{{ t('categories.empty') }}</p>
      </div>
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useCategoryStore } from '@/stores/categoryStore'
  import { useBusinessTypeStore } from '@/stores/businessTypeStore'
  import { useAppUtils, AppStatusChip } from '@nong-official-dev/core'
  import { usePermission } from '@/composables/usePermission'
  import { useI18n } from 'vue-i18n'

  const props = defineProps({
    // Only true on the super-admin "system categories" page (see router) —
    // there, every category is a system one and every row is fully editable.
    // In the default (tenant) context, a tenant manages only their own
    // custom categories; system categories tagged for their business type
    // show up read-only (see TenantScope's 'categories' branch).
    adminMode: { type: Boolean, default: false }
  })

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const { can } = usePermission()
  const categoryStore = useCategoryStore()
  const businessTypeStore = useBusinessTypeStore()

  const canManage = computed(() => props.adminMode || can('categories.manage'))
  const items = computed(() => categoryStore.categories)
  const saving = ref(false)

  async function load() {
    await categoryStore.fetchCategories({ perPage: 200 })
  }

  onMounted(() => {
    load()
    if (props.adminMode && !businessTypeStore.businessTypes.length) {
      businessTypeStore.fetchBusinessTypes()
    }
  })

  function errorMessage(err) {
    return err?.response?.data?.message ?? err?.response?.data?.errors?.[0] ?? t('common.error')
  }

  // ── Add ────────────────────────────────────────────────────────────────────
  const defaultNew = () => ({ name: '', is_active: true, is_lid_exchange: false, business_type_ids: [] })
  const newCategory = reactive(defaultNew())

  async function submitNew() {
    if (!newCategory.name.trim()) return
    saving.value = true
    try {
      await categoryStore.createCategory({
        name: newCategory.name.trim(),
        is_active: newCategory.is_active,
        is_lid_exchange: newCategory.is_lid_exchange,
        is_system: props.adminMode,
        business_type_ids: props.adminMode ? newCategory.business_type_ids : undefined
      })
      Object.assign(newCategory, defaultNew())
      notif(t('categories.messages.saved'))
      await load()
    } catch (err) {
      notif(errorMessage(err), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Edit ───────────────────────────────────────────────────────────────────
  const editingId = ref(null)
  const editForm = reactive(defaultNew())

  function startEdit(item) {
    editingId.value = item.id
    Object.assign(editForm, {
      name: item.name ?? '',
      is_active: item.is_active ?? true,
      is_lid_exchange: item.is_lid_exchange ?? false,
      business_type_ids: (item.business_types ?? []).map(b => b.id)
    })
  }

  function cancelEdit() {
    editingId.value = null
  }

  async function submitEdit(item) {
    if (!editForm.name.trim()) return
    saving.value = true
    try {
      await categoryStore.updateCategory(item.id, {
        name: editForm.name.trim(),
        is_active: editForm.is_active,
        is_lid_exchange: editForm.is_lid_exchange,
        business_type_ids: props.adminMode ? editForm.business_type_ids : undefined
      })
      editingId.value = null
      notif(t('categories.messages.saved'))
      await load()
    } catch (err) {
      notif(errorMessage(err), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Delete ─────────────────────────────────────────────────────────────────
  function openDeleteConfirm(item) {
    confirm({
      title: t('categories.delete_title'),
      message: t('categories.delete_message', { name: item.name }),
      options: { type: 'warning', width: 550 },
      agree: async () => {
        await categoryStore.deleteCategory(item.id)
        notif(t('categories.messages.deleted'))
      },
      cancel: () => {}
    })
  }
</script>
