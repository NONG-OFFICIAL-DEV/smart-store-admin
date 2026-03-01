<template>
  <custom-title
    title="Modifier Groups"
    icon="mdi-layers-outline"
    subtitle="Add-ons · Extras · Customizations"
  >
    <template #right>
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
        rounded="lg"
        @click="openCreateGroup"
      >
        Add Group
      </v-btn>
    </template>
  </custom-title>

  <v-container fluid class="pa-0">
    <!-- ── Stats ──────────────────────────────────────────────────────────── -->
    <v-row class="mb-4">
      <v-col v-for="(stat, i) in stats" :key="i" cols="12" sm="6" lg="3">
        <v-card
          rounded="xl"
          border
          elevation="0"
          class="pa-4 d-flex align-center gap-4"
        >
          <v-avatar :color="stat.color" variant="tonal" rounded="lg" size="48">
            <v-icon :icon="stat.icon" size="24" />
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            <div class="text-caption text-grey">{{ stat.label }}</div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Search ─────────────────────────────────────────────────────────── -->
    <v-row align="center" class="mb-4 px-1">
      <v-col cols="12" sm="6" md="3">
        <v-select
          v-model="filters.selection_type"
          :items="[
            { label: 'Single', value: 'single' },
            { label: 'Multiple', value: 'multiple' }
          ]"
          item-title="label"
          item-value="value"
          label="Selection Type"
          variant="outlined"
          density="compact"
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
            { label: 'Required', value: true },
            { label: 'Optional', value: false }
          ]"
          item-title="label"
          item-value="value"
          label="Required"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          clearable
          prepend-inner-icon="mdi-alert-circle-outline"
        />
      </v-col>
      <v-spacer />
      <v-col cols="12" sm="auto">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          placeholder="Search groups..."
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          max-width="300"
          clearable
        />
      </v-col>
    </v-row>

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
                  :color="group.selection_type === 'single' ? 'blue' : 'purple'"
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
                  {{ group.is_required ? 'Required' : 'Optional' }}
                </v-chip>
              </div>
              <div class="text-caption text-grey mt-0">
                <span v-if="group.min_selections">
                  Min: {{ group.min_selections }}
                </span>
                <span v-if="group.max_selections">
                  · Max: {{ group.max_selections }}
                </span>
                <span v-if="!group.min_selections && !group.max_selections">
                  No selection limits
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
              Add Option
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
                    option.price_adjustment >= 0 ? 'text-success' : 'text-error'
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
                  {{ option.is_available ? 'Available' : 'Unavailable' }}
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
          <p class="text-caption text-grey">No options yet</p>
          <v-btn
            size="x-small"
            variant="text"
            color="primary"
            @click="openCreateOption(group)"
          >
            + Add First Option
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
        No modifier groups yet
      </p>
      <p class="text-caption text-grey mb-4">
        Create groups like "Spice Level", "Add-ons", "Sauces"
      </p>
      <v-btn
        color="primary"
        variant="tonal"
        prepend-icon="mdi-plus"
        @click="openCreateGroup"
      >
        Create First Group
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
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useModifierGroupStore } from '@/stores/modifierGroupStore'
  import { useModifierOptionStore } from '@/stores/modifierOptionStore'
  import ModifierGroupDialog from '@/components/products/ModifierGroupDialog.vue'
  import ModifierOptionDialog from '@/components/products/ModifierOptionDialog.vue'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'

  const { confirm, notif } = useAppUtils()
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


  // ── Stats ────────────────────────────────────────────────────────────────────
  const stats = computed(() => {
    const groups = modifierGroups.value ?? []
    const options = modifierOptions.value ?? []
    return [
      {
        label: 'Total Groups',
        value: groups.length,
        icon: 'mdi-format-list-checks',
        color: 'primary'
      },
      {
        label: 'Required Groups',
        value: groups.filter(g => g.is_required).length,
        icon: 'mdi-alert-circle-outline',
        color: 'error'
      },
      {
        label: 'Single Select',
        value: groups.filter(g => g.selection_type === 'single').length,
        icon: 'mdi-radiobox-marked',
        color: 'blue'
      },
      {
        label: 'Multi Select',
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
        // Pre-load empty options for the new group so groupOptions() works immediately
        await optionStore.fetchModifierOptions(newGroup?.id ?? payload.id)
      }
      showSnack(payload.id ? 'Group updated' : 'Group created')
      notif(payload.id ? 'Group updated' : 'Group created', {
        type: 'success'
      })
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)
      notif('Failed to save group', {
        type: 'error'
      })
    } finally {
      groupSaving.value = false
    }
  }

  const confirmDeleteGroup = async group => {
    confirm({
      title: 'Variant Group?',
      message: `Are you sure delete this group"?`,
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
        ? await optionStore.updateModifierOption(payload.id, payload)
        : await optionStore.createModifierOption({
            ...payload,
            group_id: activeGroupId.value
          })
      showSnack(payload.id ? 'Option updated' : 'Option added')
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)
      showSnack('Failed to save option', 'error')
    } finally {
      optionSaving.value = false
    }
  }

  const confirmDeleteOption = async option => {
    confirm({
      title: 'Variant deleted?',
      message: `Are you sure delete this product"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await optionStore.deleteModifierOption(option.id)
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
