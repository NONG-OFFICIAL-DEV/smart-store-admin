<template>
  <div>
    <v-container fluid class="pa-0">
      <div class="d-flex justify-end align-center ga-2 mb-4">
        <v-btn
          :color="showFilters ? 'primary' : 'default'"
          :variant="showFilters ? 'flat' : 'tonal'"
          rounded="lg"
          :prepend-icon="
            showFilters ? 'mdi-filter-off-outline' : 'mdi-filter-outline'
          "
          @click="showFilters = !showFilters"
        >
          {{ t('btn.filter') }}
          <!-- badge shows how many filters are active -->
          <v-badge
            v-if="activeFilterCount > 0"
            :content="activeFilterCount"
            color="error"
            floating
          />
        </v-btn>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          @click="openCreateGroup"
        >
          {{ t('modifiers.page.add_group') }}
        </v-btn>
      </div>

      <!-- ── Stats ──────────────────────────────────────────────────────────── -->
      <v-row class="mb-4">
        <v-col v-for="(stat, i) in stats" :key="i" cols="12" sm="6" lg="3">
          <v-card
            rounded="xl"
            border
            elevation="0"
            class="pa-4 d-flex align-center gap-4"
          >
            <v-avatar
              :color="stat.color"
              variant="tonal"
              rounded="lg"
              size="48"
            >
              <v-icon :icon="stat.icon" size="24" />
            </v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
              <div class="text-caption text-grey">{{ stat.label }}</div>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- ── Filters ────────────────────────────────────────────────────────── -->
      <v-expand-transition>
        <v-card v-if="showFilters" rounded="xl" elevation="0" class="mb-4">
          <v-card-text>
            <v-row dense align="center">
              <v-col cols="12" sm="6" md="3">
                <v-select
                  v-model="filters.selection_type"
                  :items="[
                    { label: t('modifiers.group.single'), value: 'single' },
                    { label: t('modifiers.group.multiple'), value: 'multiple' }
                  ]"
                  item-title="label"
                  item-value="value"
                  :label="t('modifiers.group.selection_type_label')"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  clearable
                  prepend-inner-icon="mdi-format-list-checks"
                />
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-select
                  v-model="filters.is_required"
                  :items="[
                    { label: t('modifiers.group.required_toggle'), value: true },
                    { label: t('modifiers.page.optional'), value: false }
                  ]"
                  item-title="label"
                  item-value="value"
                  :label="t('modifiers.group.required_toggle')"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  clearable
                  prepend-inner-icon="mdi-alert-circle-outline"
                />
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-text-field
                  v-model="search"
                  prepend-inner-icon="mdi-magnify"
                  :placeholder="t('modifiers.page.search_placeholder')"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  clearable
                  @keyup.enter="onFilterChange"
                />
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="px-4">
            <v-spacer />
            <v-btn
              v-if="hasActiveFilters"
              rounded="lg"
              variant="tonal"
              color="error"
              prepend-icon="mdi-close"
              @click="resetFilters"
            >
              {{ t('btn.reset') }}
            </v-btn>
            <v-btn
              class="bg-primary"
              rounded="lg"
              prepend-icon="mdi-magnify"
              @click="onFilterChange"
            >
              {{ t('btn.search') }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-expand-transition>

      <!-- ── Group Cards ─────────────────────────────────────────────────────── -->
      <div v-if="groupLoading" class="pa-4">
        <v-skeleton-loader
          v-for="n in 4"
          :key="n"
          type="card"
          class="mb-4"
          rounded="xl"
        />
      </div>

      <div v-else-if="filteredGroups.length" class="d-flex flex-column gap-4">
        <v-card
          v-for="group in filteredGroups"
          :key="group.id"
          rounded="xl"
          border
          elevation="0"
        >
          <!-- Group Header -->
          <div class="d-flex align-center justify-space-between px-5 pt-4 pb-3">
            <div class="d-flex align-center gap-3">
              <v-avatar color="primary" variant="tonal" rounded="lg" size="44">
                <v-icon icon="mdi-format-list-checks" size="22" />
              </v-avatar>
              <div>
                <div class="d-flex align-center gap-2 flex-wrap">
                  <span class="text-body-1 font-weight-semibold">
                    {{ group.name }}
                  </span>
                  <v-chip
                    :color="
                      group.selection_type === 'single' ? 'blue' : 'purple'
                    "
                    size="x-small"
                    variant="tonal"
                  >
                    {{ group.selection_type }}
                  </v-chip>
                  <v-chip
                    :color="group.is_required ? 'error' : 'grey'"
                    size="x-small"
                    variant="tonal"
                  >
                    {{ group.is_required ? t('modifiers.group.required_toggle') : t('modifiers.page.optional') }}
                  </v-chip>
                </div>
                <div class="text-caption text-grey mt-0">
                  <span v-if="group.min_selections">
                    {{ t('modifiers.link.min_prefix') }} {{ group.min_selections }}
                  </span>
                  <span v-if="group.max_selections">
                    · {{ t('modifiers.link.max_prefix') }} {{ group.max_selections }}
                  </span>
                  <span v-if="!group.min_selections && !group.max_selections">
                    {{ t('modifiers.page.no_selection_limits') }}
                  </span>
                </div>
              </div>
            </div>

            <div class="d-flex align-center gap-1">
              <v-btn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="mdi-plus"
                rounded="lg"
                @click="openCreateOption(group)"
              >
                {{ t('modifiers.page.add_option') }}
              </v-btn>
              <v-btn
                icon="mdi-pencil-outline"
                size="small"
                variant="text"
                @click="openEditGroup(group)"
              />
              <v-btn
                icon="mdi-delete-outline"
                size="small"
                variant="text"
                color="error"
                @click="confirmDeleteGroup(group)"
              />
            </div>
          </div>

          <v-divider />

          <!-- Options List -->
          <div v-if="optionLoadingFor === group.id" class="pa-4">
            <v-skeleton-loader
              v-for="n in 3"
              :key="n"
              type="list-item"
              class="mb-1"
            />
          </div>

          <v-list v-else-if="groupOptions(group.id).length" class="pa-0">
            <template
              v-for="(option, idx) in groupOptions(group.id)"
              :key="option.id"
            >
              <v-list-item class="px-5 py-2">
                <template #prepend>
                  <v-icon icon="mdi-circle-small" color="grey" />
                </template>
                <v-list-item-title class="text-body-2 font-weight-medium">
                  {{ option.name }}
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">
                  <span
                    :class="
                      option.price_adjustment >= 0
                        ? 'text-success'
                        : 'text-error'
                    "
                  >
                    {{ option.price_adjustment >= 0 ? '+' : '' }}${{
                      Number(option.price_adjustment).toFixed(2)
                    }}
                  </span>
                  <v-chip
                    :color="option.is_available ? 'success' : 'grey'"
                    size="x-small"
                    variant="tonal"
                    class="ml-2"
                  >
                    {{ option.is_available ? t('tables.status.available') : t('modifiers.page.unavailable') }}
                  </v-chip>
                </v-list-item-subtitle>
                <template #append>
                  <div class="d-flex gap-1">
                    <v-btn
                      icon="mdi-pencil-outline"
                      size="x-small"
                      variant="text"
                      @click="openEditOption(option)"
                    />
                    <v-btn
                      icon="mdi-delete-outline"
                      size="x-small"
                      variant="text"
                      color="error"
                      @click="confirmDeleteOption(option)"
                    />
                  </div>
                </template>
              </v-list-item>
              <v-divider v-if="idx < groupOptions(group.id).length - 1" inset />
            </template>
          </v-list>

          <div v-else class="text-center py-6">
            <p class="text-caption text-grey">{{ t('modifiers.page.no_options_yet') }}</p>
            <v-btn
              size="x-small"
              variant="text"
              color="primary"
              @click="openCreateOption(group)"
            >
              {{ t('modifiers.page.add_first_option') }}
            </v-btn>
          </div>
        </v-card>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-16">
        <v-icon
          icon="mdi-format-list-checks"
          size="64"
          color="grey-lighten-2"
          class="mb-4"
        />
        <p class="text-body-1 text-medium-emphasis mb-2">
          {{ t('modifiers.page.no_groups_yet') }}
        </p>
        <p class="text-caption text-grey mb-4">
          {{ t('modifiers.page.no_groups_hint') }}
        </p>
        <v-btn
          color="primary"
          variant="tonal"
          prepend-icon="mdi-plus"
          @click="openCreateGroup"
        >
          {{ t('modifiers.page.create_first_group') }}
        </v-btn>
      </div>
    </v-container>

    <!-- ── Group Dialog ────────────────────────────────────────────────────── -->
    <ModifierGroupDialog
      v-model="groupDialog"
      :group="selectedGroup"
      :loading="groupSaving"
      @saved="handleGroupSaved"
    />

    <!-- ── Option Dialog ───────────────────────────────────────────────────── -->
    <ModifierOptionDialog
      v-model="optionDialog"
      :option="selectedOption"
      :group-id="activeGroupId"
      :loading="optionSaving"
      @saved="handleOptionSaved"
    />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRouter } from 'vue-router'
  import { useModifierGroupStore } from '@/stores/modifierGroupStore'
  import { useModifierOptionStore } from '@/stores/modifierOptionStore'
  import ModifierGroupDialog from '@/components/products/ModifierGroupDialog.vue'
  import ModifierOptionDialog from '@/components/products/ModifierOptionDialog.vue'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const router = useRouter()
  const groupStore = useModifierGroupStore()
  const optionStore = useModifierOptionStore()

  const { modifierGroups, loading: groupLoading } = storeToRefs(groupStore)
  const { modifierOptions } = storeToRefs(optionStore)

  const search = ref('')
  const groupDialog = ref(false)
  const optionDialog = ref(false)
  const groupSaving = ref(false)
  const optionSaving = ref(false)
  const optionLoadingFor = ref(null)
  const selectedGroup = ref(null)
  const selectedOption = ref(null)
  const activeGroupId = ref(null)

  const filters = ref({ selection_type: null, is_required: null })
  const showFilters = ref(false)

  // ── Active filter badge ─────────────────────────────────────────────────────
  const activeFilterCount = computed(() => {
    let count = 0
    if (search.value?.trim()) count++
    if (filters.value.selection_type) count++
    if (filters.value.is_required !== null && filters.value.is_required !== undefined) count++
    return count
  })
  const hasActiveFilters = computed(() => activeFilterCount.value > 0)

  // Filtering here is fully client-side/reactive (see filteredGroups below),
  // so there's no server refetch to trigger — kept for shell consistency.
  const onFilterChange = () => {}

  const resetFilters = () => {
    search.value = ''
    filters.value = { selection_type: null, is_required: null }
  }

  // ── Stats ────────────────────────────────────────────────────────────────────
  const stats = computed(() => {
    const groups = modifierGroups.value ?? []
    return [
      {
        label: t('modifiers.page.stats.total_groups'),
        value: groups.length,
        icon: 'mdi-format-list-checks',
        color: 'primary'
      },
      {
        label: t('modifiers.page.stats.required_groups'),
        value: groups.filter(g => g.is_required).length,
        icon: 'mdi-alert-circle-outline',
        color: 'error'
      },
      {
        label: t('modifiers.page.stats.single_select'),
        value: groups.filter(g => g.selection_type === 'single').length,
        icon: 'mdi-radiobox-marked',
        color: 'blue'
      },
      {
        label: t('modifiers.page.stats.multi_select'),
        value: groups.filter(g => g.selection_type === 'multiple').length,
        icon: 'mdi-checkbox-marked-outline',
        color: 'purple'
      }
    ]
  })

  // ── Filtered Groups ──────────────────────────────────────────────────────────
  const filteredGroups = computed(() => {
    let list = modifierGroups.value ?? []

    if (filters.value.selection_type) {
      list = list.filter(g => g.selection_type === filters.value.selection_type)
    }
    if (
      filters.value.is_required !== null &&
      filters.value.is_required !== undefined
    ) {
      list = list.filter(g => g.is_required === filters.value.is_required)
    }
    if (search.value) {
      const q = search.value.toLowerCase()
      list = list.filter(g => g.name.toLowerCase().includes(q))
    }

    return list
  })

  // Options per group (from flat modifierOptions list)
  const groupOptions = groupId =>
    (modifierOptions.value ?? []).filter(o => o.group_id === groupId)

  // ── Group Actions ────────────────────────────────────────────────────────────
  const openCreateGroup = () => {
    selectedGroup.value = null
    groupDialog.value = true
  }
  const openEditGroup = g => {
    selectedGroup.value = { ...g }
    groupDialog.value = true
  }

  const handleGroupSaved = async (payload, callbacks) => {
    groupSaving.value = true
    try {
      if (payload.id) {
        await groupStore.updateModifierGroup(payload.id, payload)
      } else {
        const newGroup = await groupStore.createModifierGroup(payload)
        await optionStore.fetchModifierOptions(newGroup?.id ?? payload.id)
      }
      notif(payload.id ? t('modifiers.messages.group_updated') : t('modifiers.messages.group_created'), { type: 'success' })
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)

      // ✅ Only show generic error for non-validation errors
      // 422 = validation — the dialog handles those inline via serverErrors
      if (err?.response?.status !== 422) {
        notif(t('modifiers.messages.group_save_failed'), { type: 'error' })
      }
    } finally {
      groupSaving.value = false
    }
  }

  const confirmDeleteGroup = async group => {
    confirm({
      title: t('modifiers.page.delete_group_title'),
      message: t('modifiers.page.delete_group_message'),
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await groupStore.deleteModifierGroup(group.id)
        notif(t('messages.deleted_success'), {
          type: 'success'
        })
        router.push('/products')
      }
    })
  }

  // ── Option Actions ───────────────────────────────────────────────────────────
  const openCreateOption = group => {
    activeGroupId.value = group.id
    selectedOption.value = null
    optionDialog.value = true
  }
  const openEditOption = opt => {
    activeGroupId.value = opt.group_id
    selectedOption.value = { ...opt }
    optionDialog.value = true
  }

  const handleOptionSaved = async (payload, callbacks) => {
    optionSaving.value = true
    try {
      payload.id
        ? await optionStore.updateModifierOption(payload.group_id ?? activeGroupId.value, payload.id, payload)
        : await optionStore.createModifierOption({
            ...payload,
            group_id: activeGroupId.value
          })
      notif(payload.id ? t('modifiers.messages.option_updated') : t('modifiers.messages.option_added'), { type: 'success' })
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)
      notif(t('modifiers.messages.option_save_failed'), { type: 'error' })
    } finally {
      optionSaving.value = false
    }
  }

  const confirmDeleteOption = async option => {
    confirm({
      title: t('modifiers.page.delete_option_title'),
      message: t('modifiers.page.delete_option_message'),
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await optionStore.deleteModifierOption(option.group_id, option.id)
        notif(t('messages.deleted_success'), {
          type: 'success'
        })
        router.push('/products')
      }
    })
  }

  onMounted(async () => {
    await groupStore.fetchModifierGroups()

    // Fetch options for each group in parallel
    await Promise.all(
      (modifierGroups.value ?? []).map(g =>
        optionStore.fetchModifierOptions(g.id)
      )
    )
  })
</script>
