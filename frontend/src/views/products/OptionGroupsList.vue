<template>
  <div>
    <div class="d-flex align-center justify-end mb-4">
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        class="text-none"
        prepend-icon="mdi-plus"
        @click="openCreate"
      >
        {{ t('modifiers.options_tab.add_group') }}
      </v-btn>
    </div>

    <v-card rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchGroupsForTable"
        :show-search="true"
        :item-label="t('modifiers.options_tab.tab_label')"
      >
        <template #item.name="{ item }">
          <div>
            <div class="font-weight-medium">{{ item.name }}</div>
            <div v-if="item.name_km" class="text-caption text-medium-emphasis">
              {{ item.name_km }}
            </div>
          </div>
        </template>

        <template #item.rule="{ item }">
          <div class="d-flex flex-wrap ga-1">
            <v-chip size="x-small" variant="tonal" color="primary">{{ ruleText(item) }}</v-chip>
            <v-chip v-if="item.is_required" size="x-small" variant="tonal" color="error">
              {{ t('modifiers.group.required_toggle') }}
            </v-chip>
          </div>
        </template>

        <template #item.options="{ item }">
          <div class="d-flex flex-wrap ga-1" style="max-width: 320px">
            <template v-if="item.options?.length">
              <v-chip
                v-for="opt in item.options"
                :key="opt.id"
                size="x-small"
                variant="tonal"
                :class="opt.is_available === false ? 'opacity-60' : ''"
              >
                {{ opt.name }}
              </v-chip>
            </template>
            <span v-else class="text-caption text-medium-emphasis">
              {{ t('modifiers.options_tab.no_choices') }}
            </span>
          </div>
        </template>

        <template #item.products_count="{ item }">
          <span class="text-body-2 text-medium-emphasis">{{ usedByText(item) }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-end gap-1">
            <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(item)" />
            <v-btn
              icon="mdi-delete-outline"
              size="small"
              variant="text"
              color="error"
              @click="confirmDeleteGroup(item)"
            />
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10">
            <v-icon icon="mdi-format-list-checks" size="48" color="grey-lighten-1" />
            <p class="text-medium-emphasis mt-3">{{ t('modifiers.options_tab.empty') }}</p>
            <p class="text-caption text-medium-emphasis">{{ t('modifiers.options_tab.empty_hint') }}</p>
            <v-btn color="primary" variant="tonal" class="mt-3 text-none" prepend-icon="mdi-plus" @click="openCreate">
              {{ t('modifiers.options_tab.add_group') }}
            </v-btn>
          </div>
        </template>
      </AppTable>
    </v-card>

    <!-- Rendered at this component's root, not nested in the table, so
         the drawer overlay always covers the full screen. -->
    <OptionGroupDrawer
      v-model="groupDialog"
      :group="selectedGroup"
      :loading="groupSaving"
      @saved="handleGroupSaved"
    />
  </div>
</template>

<script setup>
  import { ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppTable, useAppUtils } from '@nong-official-dev/core'
  import { useModifierGroupStore } from '@/stores/modifierGroupStore'
  import { getAllModifierGroupsApi } from '@/api/modifierGroupService'
  import OptionGroupDrawer from '@/components/products/OptionGroupDrawer.vue'

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const groupStore = useModifierGroupStore()

  const tableRef = ref(null)
  const groupDialog = ref(false)
  const groupSaving = ref(false)
  const selectedGroup = ref(null)

  const headers = [
    { title: t('modifiers.options_tab.header_name'), key: 'name', sortable: true },
    { title: t('modifiers.options_tab.header_rule'), key: 'rule', sortable: false },
    { title: t('modifiers.options_tab.header_choices'), key: 'options', sortable: false },
    { title: t('modifiers.options_tab.header_used_by'), key: 'products_count', sortable: false },
    { title: t('modifiers.options_tab.header_actions'), key: 'actions', sortable: false, align: 'end' }
  ]

  // Deliberately bypasses groupStore.fetchModifierGroups() — that action
  // writes its result into the shared groupStore.modifierGroups array,
  // which ModifierLinkDialog.vue (a separate page's "link existing group"
  // picker) expects to hold whatever list ITS OWN fetch populated. Routing
  // AppTable's page-sized requests through the same store action would
  // stomp that unrelated consumer's state.
  const fetchGroupsForTable = async params => {
    const { data } = await getAllModifierGroupsApi(params)
    return { items: data.data, total: data.meta.total }
  }

  function ruleText(group) {
    if (group.selection_type === 'single') return t('modifiers.options_tab.rule_pick_one')
    return group.max_selections
      ? t('modifiers.options_tab.rule_pick_up_to', { n: group.max_selections })
      : t('modifiers.options_tab.rule_pick_several')
  }

  function usedByText(group) {
    return t('modifiers.options_tab.used_by', group.products_count ?? 0)
  }

  const openCreate = () => {
    selectedGroup.value = null
    groupDialog.value = true
  }
  const openEdit = group => {
    selectedGroup.value = { ...group }
    groupDialog.value = true
  }

  const handleGroupSaved = async (payload, callbacks) => {
    groupSaving.value = true
    try {
      if (payload.id) {
        await groupStore.updateModifierGroup(payload.id, payload)
      } else {
        await groupStore.createModifierGroup(payload)
      }
      tableRef.value?.refresh()
      notif(
        payload.id ? t('modifiers.messages.group_updated') : t('modifiers.messages.group_created'),
        { type: 'success' }
      )
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)
      if (err?.response?.status !== 422) {
        notif(t('modifiers.messages.group_save_failed'), { type: 'error' })
      }
    } finally {
      groupSaving.value = false
    }
  }

  const confirmDeleteGroup = group => {
    const count = group.products_count ?? 0
    confirm({
      title: t('modifiers.options_tab.delete_title'),
      message:
        count > 0
          ? t(
              count === 1
                ? 'modifiers.options_tab.delete_message_used_one'
                : 'modifiers.options_tab.delete_message_used_other',
              { n: count, name: group.name }
            )
          : t('modifiers.options_tab.delete_message_unused', { name: group.name }),
      options: { type: 'warning', color: 'warning', width: 450 },
      agree: async () => {
        await groupStore.deleteModifierGroup(group.id)
        tableRef.value?.refresh()
        notif(t('messages.deleted_success'), { type: 'success' })
      }
    })
  }
</script>
