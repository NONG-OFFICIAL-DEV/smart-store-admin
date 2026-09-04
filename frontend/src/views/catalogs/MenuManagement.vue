<template>
  <v-container fluid class="pa-0">
    <!-- Add — inline, no dialog: a name field, two switches, and a plus
         button that creates the menu immediately. -->
    <v-card v-if="canManage" rounded="lg" elevation="0" border class="pa-3 mb-4">
      <div class="d-flex align-center flex-wrap ga-3">
        <v-text-field
          v-model="newMenu.name"
          :placeholder="t('menus.form.name_placeholder')"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          maxlength="80"
          class="flex-grow-1"
          style="min-width: 200px"
          @keyup.enter="submitNew"
        />
        <div class="d-flex align-center ga-2">
          <span class="text-body-2">{{ t('status.active') }}</span>
          <v-switch v-model="newMenu.is_active" color="success" hide-details density="compact" inset />
        </div>
        <div class="d-flex align-center ga-2">
          <span class="text-body-2">{{ t('menus.list.default_badge') }}</span>
          <v-switch v-model="newMenu.is_default" color="primary" hide-details density="compact" inset />
        </div>
        <v-btn
          icon="mdi-plus"
          color="primary"
          variant="flat"
          :loading="saving"
          :disabled="!newMenu.name.trim()"
          @click="submitNew"
        />
      </div>
    </v-card>

    <!-- List -->
    <v-card rounded="lg" elevation="0" border>
      <v-list v-if="menuStore.menus.length" lines="two" class="menu-list">
        <template v-for="item in menuStore.menus" :key="item.id">
          <!-- Editing this row — same shape as the add row above. -->
          <v-list-item v-if="editingId === item.id">
            <div class="d-flex align-center flex-wrap ga-3 py-1">
              <v-text-field
                v-model="editForm.name"
                variant="outlined"
                density="compact"
                rounded="lg"
                hide-details
                maxlength="80"
                class="flex-grow-1"
                style="min-width: 180px"
                @keyup.enter="submitEdit(item)"
              />
              <div class="d-flex align-center ga-2">
                <span class="text-body-2">{{ t('status.active') }}</span>
                <v-switch v-model="editForm.is_active" color="success" hide-details density="compact" inset />
              </div>
              <div class="d-flex align-center ga-2">
                <span class="text-body-2">{{ t('menus.list.default_badge') }}</span>
                <v-switch v-model="editForm.is_default" color="primary" hide-details density="compact" inset />
              </div>
              <v-btn icon="mdi-check" color="success" variant="text" :loading="saving" @click="submitEdit(item)" />
              <v-btn icon="mdi-close" variant="text" @click="cancelEdit" />
            </div>
          </v-list-item>

          <!-- Display -->
          <v-list-item v-else>
            <template #prepend>
              <v-avatar color="primary" variant="tonal" size="32" rounded="md">
                <v-icon icon="mdi-book-open-page-variant-outline" size="16" />
              </v-avatar>
            </template>

            <v-list-item-title class="font-weight-medium">{{ item.name }}</v-list-item-title>
            <v-list-item-subtitle>
              <div class="d-flex align-center flex-wrap ga-1 mt-1">
                <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="x-small" />
                <v-chip v-if="item.is_default" size="x-small" color="primary" variant="flat" prepend-icon="mdi-star">
                  {{ t('menus.list.default_badge') }}
                </v-chip>
                <v-chip v-if="item.branches?.length" size="x-small" variant="tonal" prepend-icon="mdi-store-outline">
                  {{ t('menus.list.branches_count', { n: item.branches.length }) }}
                </v-chip>
                <span v-else class="text-caption text-medium-emphasis">{{ t('menus.list.not_assigned') }}</span>
              </div>
            </v-list-item-subtitle>

            <template #append>
              <div class="d-flex align-center ga-1">
                <v-tooltip :text="t('menus.list.tooltip.assign')">
                  <template #activator="{ props: tp }">
                    <v-btn v-bind="tp" icon="mdi-store-plus-outline" size="small" variant="text" color="primary" @click="openAssignBranch" />
                  </template>
                </v-tooltip>
                <v-btn icon="mdi-pencil-outline" size="small" variant="text" color="primary" @click="startEdit(item)" />
                <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="confirmDelete(item)" />
              </div>
            </template>
          </v-list-item>
        </template>
      </v-list>

      <div v-else class="text-center py-10">
        <v-icon icon="mdi-book-open-variant" size="40" color="grey-lighten-1" />
        <p class="text-medium-emphasis mt-2 mb-0">{{ t('menus.list.empty') }}</p>
      </div>
    </v-card>

    <!-- Assign to branch(es) — a real scheduling assignment (branch, time
         window, days), not something that fits the inline name+switch
         shape above, so it stays a dialog. -->
    <BranchMenuFormDialog
      v-model="assignDialog.show"
      :edit-item="editItem"
      :branches="branchStore.branches"
      :menus="menuStore.menus"
      @saved="confirmAssign"
    />
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useMenuStore } from '@/stores/menuStore'
  import { useBranchMenuStore } from '@/stores/branchMenuStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAppUtils, AppStatusChip } from '@nong-official-dev/core'
  import { usePermission } from '@/composables/usePermission'
  import { useI18n } from 'vue-i18n'
  import BranchMenuFormDialog from '@/components/catalogs/BranchMenuFormDialog.vue'

  const { confirm, notif } = useAppUtils()
  const { t } = useI18n()
  const { can } = usePermission()

  const menuStore = useMenuStore()
  const branchStore = useBranchStore()
  const branchMenuStore = useBranchMenuStore()

  const canManage = computed(() => can('menus.manage'))
  const saving = ref(false)

  async function load() {
    await menuStore.fetchMenus({ perPage: -1 })
  }

  onMounted(() => {
    load()
    branchStore.fetchBranches({ perPage: -1 })
  })

  function errorMessage(err) {
    return err?.response?.data?.message ?? err?.response?.data?.errors?.[0] ?? t('common.error')
  }

  // ── Add ────────────────────────────────────────────────────────────────────
  const defaultNew = () => ({ name: '', is_active: true, is_default: false })
  const newMenu = reactive(defaultNew())

  async function submitNew() {
    if (!newMenu.name.trim()) return
    saving.value = true
    try {
      await menuStore.createMenu({
        name: newMenu.name.trim(),
        is_active: newMenu.is_active,
        is_default: newMenu.is_default
      })
      Object.assign(newMenu, defaultNew())
      notif(t('messages.saved_success'), { type: 'success' })
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
      is_default: item.is_default ?? false
    })
  }

  function cancelEdit() {
    editingId.value = null
  }

  async function submitEdit(item) {
    if (!editForm.name.trim()) return
    saving.value = true
    try {
      await menuStore.updateMenu(item.id, {
        name: editForm.name.trim(),
        is_active: editForm.is_active,
        is_default: editForm.is_default
      })
      editingId.value = null
      notif(t('messages.saved_success'), { type: 'success' })
      await load()
    } catch (err) {
      notif(errorMessage(err), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Delete ─────────────────────────────────────────────────────────────────
  function confirmDelete(menu) {
    confirm({
      title: t('menus.list.confirm_delete.title'),
      message: t('menus.list.confirm_delete.message', { name: menu.name }),
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await menuStore.deleteMenu(menu.id)
        notif(t('messages.deleted_success'), { type: 'success' })
        await load()
      }
    })
  }

  // ── Assign branch ─────────────────────────────────────────────────────────
  // A blank create dialog — the user picks both branch and menu (matches
  // the prior behavior; the dialog has no way to pre-fill "this menu" from
  // the row it was opened from).
  const assignDialog = reactive({ show: false })
  const editItem = ref(null)

  function openAssignBranch() {
    editItem.value = null
    assignDialog.show = true
  }

  async function confirmAssign(payload) {
    saving.value = true
    try {
      if (payload.id) {
        await branchMenuStore.update(payload.id, payload)
        notif(t('menus.list.messages.assignment_updated'), { type: 'success' })
      } else {
        await branchMenuStore.create(payload)
        notif(t('menus.list.messages.menu_assigned'), { type: 'success' })
      }
      assignDialog.show = false
      await load()
    } catch (err) {
      notif(errorMessage(err), { type: 'error' })
    } finally {
      saving.value = false
    }
  }
</script>

<style scoped>
  /* Divider between rows but not after the last one — done via CSS rather
     than a v-if inside the keyed v-for (varying a keyed fragment's child
     count based on the array's length, not the item's own key, desyncs
     Vue's patchKeyedChildren reconciliation on unmount). */
  .menu-list .v-list-item + .v-list-item {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
</style>
