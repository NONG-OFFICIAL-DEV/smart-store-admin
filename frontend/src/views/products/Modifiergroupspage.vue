<template>
  <v-container fluid class="pa-0">
    <custom-title
      title="Modifier Groups"
      subtitle=" Manage add-ons and customizations customers can choose from"
    >
      <template #right>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-plus"
          @click="openCreateGroup"
        >
          New Group
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
                <v-icon :icon="stat.icon" size="20" />
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Search ─────────────────────────────────────────────────────────── -->
    <v-card rounded="xl" elevation="0" border class="mb-4">
      <v-card-text class="pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="5">
            <v-text-field
              v-model="search"
              placeholder="Search modifier groups..."
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
              v-model="filterType"
              :items="[
                { title: 'Single', value: 'single' },
                { title: 'Multiple', value: 'multiple' }
              ]"
              label="Selection Type"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              rounded="lg"
            />
          </v-col>
          <v-col cols="6" sm="3">
            <v-select
              v-model="filterRequired"
              :items="[
                { title: 'Required', value: true },
                { title: 'Optional', value: false }
              ]"
              label="Required"
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

    <!-- ── Groups List ────────────────────────────────────────────────────── -->
    <v-row dense>
      <v-col v-for="group in filteredGroups" :key="group.id" cols="12" md="6">
        <v-card rounded="xl" elevation="0" border>
          <!-- Group Header -->
          <v-card-title class="pa-5 pb-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-3">
                <v-avatar
                  :color="
                    group.selection_type === 'single' ? 'primary' : 'secondary'
                  "
                  size="38"
                  rounded="lg"
                >
                  <v-icon
                    :icon="
                      group.selection_type === 'single'
                        ? 'mdi-radiobox-marked'
                        : 'mdi-checkbox-marked'
                    "
                    size="18"
                  />
                </v-avatar>
                <div>
                  <div class="text-subtitle-1 font-weight-bold">
                    {{ group.name }}
                  </div>
                  <div class="d-flex align-center gap-2 mt-1">
                    <v-chip
                      :color="
                        group.selection_type === 'single'
                          ? 'primary'
                          : 'secondary'
                      "
                      variant="tonal"
                      size="x-small"
                      rounded="lg"
                    >
                      {{ group.selection_type }}
                    </v-chip>
                    <v-chip
                      v-if="group.is_required"
                      color="error"
                      variant="tonal"
                      size="x-small"
                      rounded="lg"
                    >
                      Required
                    </v-chip>
                    <span class="text-caption text-medium-emphasis">
                      Min: {{ group.min_selections }}
                      <span v-if="group.max_selections">
                        · Max: {{ group.max_selections }}
                      </span>
                    </span>
                  </div>
                </div>
              </div>
              <div class="d-flex gap-1">
                <v-btn
                  icon="mdi-pencil-outline"
                  size="small"
                  variant="text"
                  color="primary"
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
          </v-card-title>

          <v-divider />

          <!-- Options List -->
          <v-list density="compact" class="pa-2">
            <v-list-item
              v-for="opt in group.options"
              :key="opt.id"
              rounded="lg"
            >
              <template #prepend>
                <v-icon
                  :icon="
                    group.selection_type === 'single'
                      ? 'mdi-radiobox-blank'
                      : 'mdi-checkbox-blank-outline'
                  "
                  size="16"
                  color="grey"
                  class="mr-2"
                />
              </template>
              <v-list-item-title class="text-body-2">
                {{ opt.name }}
              </v-list-item-title>
              <template #append>
                <div class="d-flex align-center gap-3">
                  <v-chip
                    v-if="!opt.is_available"
                    size="x-small"
                    color="error"
                    variant="tonal"
                  >
                    unavailable
                  </v-chip>
                  <span
                    class="text-body-2 font-weight-medium"
                    :class="
                      opt.price_adjustment > 0
                        ? 'text-success'
                        : opt.price_adjustment < 0
                          ? 'text-error'
                          : 'text-medium-emphasis'
                    "
                  >
                    {{ formatAdj(opt.price_adjustment) }}
                  </span>
                  <v-btn
                    icon="mdi-pencil-outline"
                    size="x-small"
                    variant="text"
                    color="primary"
                    @click="openEditOption(group, opt)"
                  />
                  <v-btn
                    icon="mdi-delete-outline"
                    size="x-small"
                    variant="text"
                    color="error"
                    @click="confirmDeleteOption(group.id, opt)"
                  />
                </div>
              </template>
            </v-list-item>

            <div
              v-if="!group.options?.length"
              class="text-center py-4 text-medium-emphasis text-caption"
            >
              No options yet
            </div>
          </v-list>

          <!-- Add option btn -->
          <div class="pa-3 pt-1">
            <v-btn
              variant="tonal"
              color="secondary"
              size="small"
              rounded="lg"
              block
              prepend-icon="mdi-plus"
              @click="openCreateOption(group)"
            >
              Add Option
            </v-btn>
          </div>
        </v-card>
      </v-col>

      <v-col v-if="!filteredGroups.length" cols="12">
        <div class="text-center py-16">
          <v-icon icon="mdi-tune-variant" size="64" color="grey-lighten-2" />
          <p class="text-h6 text-medium-emphasis mt-4">
            No modifier groups found
          </p>
          <v-btn
            color="primary"
            variant="tonal"
            class="mt-3"
            @click="openCreateGroup"
          >
            Create First Group
          </v-btn>
        </div>
      </v-col>
    </v-row>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ── Modifier Group Form Dialog ────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <v-dialog v-model="groupDialog" max-width="500" persistent>
      <v-card rounded="xl" elevation="0" border>
        <v-card-title class="pa-6 pb-4">
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-3">
              <v-avatar
                :color="isEditGroup ? 'primary' : 'success'"
                size="36"
                rounded="lg"
              >
                <v-icon
                  :icon="isEditGroup ? 'mdi-pencil' : 'mdi-plus'"
                  size="18"
                />
              </v-avatar>
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  {{
                    isEditGroup ? 'Edit Modifier Group' : 'New Modifier Group'
                  }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Define selection rules
                </div>
              </div>
            </div>
            <v-btn
              icon="mdi-close"
              size="small"
              variant="text"
              @click="groupDialog = false"
            />
          </div>
        </v-card-title>
        <v-divider />

        <v-card-text class="pa-6">
          <v-form ref="groupFormRef" @submit.prevent="handleGroupSubmit">
            <v-row dense>
              <!-- Name -->
              <v-col cols="12">
                <v-text-field
                  v-model="groupForm.name"
                  label="Group Name"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-tune"
                  :rules="[rules.required, rules.maxLen(100)]"
                  counter="100"
                  hint="e.g. Crust Type, Extra Toppings, Drink Size"
                  persistent-hint
                />
              </v-col>

              <!-- Selection Type -->
              <v-col cols="12">
                <div class="text-caption text-medium-emphasis mb-2">
                  Selection Type
                </div>
                <v-btn-toggle
                  v-model="groupForm.selection_type"
                  mandatory
                  rounded="lg"
                  variant="outlined"
                  color="primary"
                  class="w-100"
                >
                  <v-btn
                    value="single"
                    class="flex-1"
                    prepend-icon="mdi-radiobox-marked"
                  >
                    Single — pick one
                  </v-btn>
                  <v-btn
                    value="multiple"
                    class="flex-1"
                    prepend-icon="mdi-checkbox-marked"
                  >
                    Multiple — pick many
                  </v-btn>
                </v-btn-toggle>
              </v-col>

              <!-- Min / Max -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="groupForm.min_selections"
                  label="Min Selections"
                  variant="outlined"
                  density="comfortable"
                  type="number"
                  min="0"
                  prepend-inner-icon="mdi-arrow-collapse-down"
                  hint="0 = optional"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="groupForm.max_selections"
                  label="Max Selections"
                  variant="outlined"
                  density="comfortable"
                  type="number"
                  min="1"
                  prepend-inner-icon="mdi-arrow-collapse-up"
                  hint="Leave empty for unlimited"
                  persistent-hint
                  clearable
                />
              </v-col>

              <!-- Required -->
              <v-col cols="12">
                <v-switch
                  v-model="groupForm.is_required"
                  color="error"
                  inset
                  density="compact"
                  hide-details
                  label="Customer must make a selection (Required)"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider />
        <v-card-actions class="pa-6 pt-4 gap-3">
          <v-btn
            block
            variant="tonal"
            rounded="lg"
            size="large"
            @click="groupDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            block
            :color="isEditGroup ? 'primary' : 'success'"
            variant="flat"
            rounded="lg"
            size="large"
            @click="handleGroupSubmit"
          >
            {{ isEditGroup ? 'Save Changes' : 'Create Group' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- ── Modifier Option Form Dialog ───────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <v-dialog v-model="optionDialog" max-width="440" persistent>
      <v-card rounded="xl" elevation="0" border>
        <v-card-title class="pa-6 pb-4">
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-3">
              <v-avatar
                :color="isEditOption ? 'primary' : 'success'"
                size="36"
                rounded="lg"
              >
                <v-icon
                  :icon="isEditOption ? 'mdi-pencil' : 'mdi-plus'"
                  size="18"
                />
              </v-avatar>
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  {{ isEditOption ? 'Edit Option' : 'Add Option' }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ activeGroupName }}
                </div>
              </div>
            </div>
            <v-btn
              icon="mdi-close"
              size="small"
              variant="text"
              @click="optionDialog = false"
            />
          </div>
        </v-card-title>
        <v-divider />

        <v-card-text class="pa-6">
          <v-form ref="optionFormRef" @submit.prevent="handleOptionSubmit">
            <v-row dense>
              <!-- Name -->
              <v-col cols="12">
                <v-text-field
                  v-model="optionForm.name"
                  label="Option Name"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-format-list-bulleted"
                  :rules="[rules.required, rules.maxLen(100)]"
                  counter="100"
                  hint="e.g. Mushrooms, Thin Crust, Extra Shot"
                  persistent-hint
                />
              </v-col>

              <!-- Price Adjustment -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="optionForm.price_adjustment"
                  label="Price Adjustment"
                  variant="outlined"
                  density="comfortable"
                  type="number"
                  step="0.01"
                  prepend-inner-icon="mdi-currency-usd"
                  hint="0 = no extra charge"
                  persistent-hint
                />
              </v-col>

              <!-- Sort Order -->
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="optionForm.sort_order"
                  label="Sort Order"
                  variant="outlined"
                  density="comfortable"
                  type="number"
                  min="0"
                  prepend-inner-icon="mdi-sort"
                  hint="Display order"
                  persistent-hint
                />
              </v-col>

              <!-- Available -->
              <v-col cols="12">
                <v-switch
                  v-model="optionForm.is_available"
                  color="success"
                  inset
                  density="compact"
                  hide-details
                  label="Option is available for ordering"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider />
        <v-card-actions class="pa-6 pt-4 gap-3">
          <v-btn
            block
            variant="tonal"
            rounded="lg"
            size="large"
            @click="optionDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            block
            :color="isEditOption ? 'primary' : 'success'"
            variant="flat"
            rounded="lg"
            size="large"
            @click="handleOptionSubmit"
          >
            {{ isEditOption ? 'Save' : 'Add Option' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ── Delete Confirms ────────────────────────────────────────────────── -->
    <v-dialog v-model="deleteGroupDialog" max-width="380">
      <v-card rounded="xl" elevation="0" border>
        <v-card-text class="pa-6 text-center">
          <v-avatar color="error" size="52" rounded="lg" class="mb-3">
            <v-icon icon="mdi-delete-outline" size="26" />
          </v-avatar>
          <h3 class="text-subtitle-1 font-weight-bold mb-1">Delete Group?</h3>
          <p class="text-body-2 text-medium-emphasis">
            Deleting
            <strong>{{ deleteGroupTarget?.name }}</strong>
            will also remove all its options.
          </p>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0 gap-3">
          <v-btn
            block
            variant="tonal"
            rounded="lg"
            @click="deleteGroupDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            block
            color="error"
            variant="flat"
            rounded="lg"
            @click="doDeleteGroup"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="deleteOptionDialog" max-width="360">
      <v-card rounded="xl" elevation="0" border>
        <v-card-text class="pa-6 text-center">
          <v-avatar color="error" size="52" rounded="lg" class="mb-3">
            <v-icon icon="mdi-delete-outline" size="26" />
          </v-avatar>
          <h3 class="text-subtitle-1 font-weight-bold mb-1">Delete Option?</h3>
          <p class="text-body-2 text-medium-emphasis">
            Delete
            <strong>{{ deleteOptionTarget?.name }}</strong>
            ?
          </p>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0 gap-3">
          <v-btn
            block
            variant="tonal"
            rounded="lg"
            @click="deleteOptionDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            block
            color="error"
            variant="flat"
            rounded="lg"
            @click="doDeleteOption"
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
  import { ref, computed } from 'vue'

  // ── Mock data ─────────────────────────────────────────────────────────────────
  const groups = ref([
    {
      id: 'g1',
      name: 'Crust Type',
      selection_type: 'single',
      min_selections: 1,
      max_selections: 1,
      is_required: true,
      options: [
        {
          id: 'o1',
          name: 'Thin',
          price_adjustment: 0,
          is_available: true,
          sort_order: 0
        },
        {
          id: 'o2',
          name: 'Thick',
          price_adjustment: 1.0,
          is_available: true,
          sort_order: 1
        },
        {
          id: 'o3',
          name: 'Gluten-Free',
          price_adjustment: 2.0,
          is_available: false,
          sort_order: 2
        }
      ]
    },
    {
      id: 'g2',
      name: 'Extra Toppings',
      selection_type: 'multiple',
      min_selections: 0,
      max_selections: null,
      is_required: false,
      options: [
        {
          id: 'o4',
          name: 'Mushrooms',
          price_adjustment: 1.0,
          is_available: true,
          sort_order: 0
        },
        {
          id: 'o5',
          name: 'Jalapeños',
          price_adjustment: 0.75,
          is_available: true,
          sort_order: 1
        },
        {
          id: 'o6',
          name: 'Olives',
          price_adjustment: 0.5,
          is_available: true,
          sort_order: 2
        }
      ]
    }
  ])

  // ── UI state ──────────────────────────────────────────────────────────────────
  const search = ref('')
  const filterType = ref(null)
  const filterRequired = ref(null)
  const snack = ref({ show: false, text: '', color: 'success' })

  // Group dialog
  const groupDialog = ref(false)
  const groupFormRef = ref(null)
  const editGroupItem = ref(null)
  const isEditGroup = computed(() => !!editGroupItem.value)

  const defaultGroupForm = () => ({
    id: null,
    name: '',
    selection_type: 'single',
    min_selections: 0,
    max_selections: null,
    is_required: false
  })
  const groupForm = ref(defaultGroupForm())

  // Option dialog
  const optionDialog = ref(false)
  const optionFormRef = ref(null)
  const editOptionItem = ref(null)
  const activeGroupId = ref(null)
  const activeGroupName = ref('')
  const isEditOption = computed(() => !!editOptionItem.value)

  const defaultOptionForm = () => ({
    id: null,
    name: '',
    price_adjustment: 0.0,
    is_available: true,
    sort_order: 0
  })
  const optionForm = ref(defaultOptionForm())

  // Delete
  const deleteGroupDialog = ref(false)
  const deleteGroupTarget = ref(null)
  const deleteOptionDialog = ref(false)
  const deleteOptionTarget = ref(null)
  const deleteOptionGroupId = ref(null)

  // ── Computed ──────────────────────────────────────────────────────────────────
  const stats = computed(() => [
    {
      label: 'Total Groups',
      value: groups.value.length,
      icon: 'mdi-tune',
      color: 'primary'
    },
    {
      label: 'Required',
      value: groups.value.filter(g => g.is_required).length,
      icon: 'mdi-alert-circle',
      color: 'error'
    },
    {
      label: 'Single Select',
      value: groups.value.filter(g => g.selection_type === 'single').length,
      icon: 'mdi-radiobox-marked',
      color: 'blue'
    },
    {
      label: 'Multi Select',
      value: groups.value.filter(g => g.selection_type === 'multiple').length,
      icon: 'mdi-checkbox-marked',
      color: 'secondary'
    }
  ])

  const filteredGroups = computed(() => {
    return groups.value.filter(g => {
      if (filterType.value && g.selection_type !== filterType.value)
        return false
      if (
        filterRequired.value !== null &&
        filterRequired.value !== undefined &&
        g.is_required !== filterRequired.value
      )
        return false
      if (
        search.value &&
        !g.name.toLowerCase().includes(search.value.toLowerCase())
      )
        return false
      return true
    })
  })

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || 'Required',
    maxLen: n => v => !v || v.length <= n || `Max ${n} chars`
  }

  const formatAdj = v => {
    if (!v || v === 0) return '+$0.00'
    return v > 0 ? `+$${Number(v).toFixed(2)}` : `-$${Math.abs(v).toFixed(2)}`
  }

  const showSnack = (text, color = 'success') => {
    snack.value = { show: true, text, color }
  }

  // ── Group CRUD ────────────────────────────────────────────────────────────────
  const openCreateGroup = () => {
    editGroupItem.value = null
    groupForm.value = defaultGroupForm()
    groupDialog.value = true
  }

  const openEditGroup = item => {
    editGroupItem.value = item
    groupForm.value = { ...item }
    groupDialog.value = true
  }

  const handleGroupSubmit = async () => {
    const { valid } = await groupFormRef.value.validate()
    if (!valid) return

    const payload = {
      id: groupForm.value.id,
      name: groupForm.value.name,
      selection_type: groupForm.value.selection_type,
      min_selections: groupForm.value.min_selections ?? 0,
      max_selections: groupForm.value.max_selections || null,
      is_required: groupForm.value.is_required
    }

    if (isEditGroup.value) {
      const idx = groups.value.findIndex(g => g.id === payload.id)
      if (idx !== -1) groups.value[idx] = { ...groups.value[idx], ...payload }
      showSnack('Group updated')
    } else {
      groups.value.unshift({
        ...payload,
        id: Date.now().toString(),
        options: []
      })
      showSnack('Group created')
    }

    groupDialog.value = false
  }

  const confirmDeleteGroup = item => {
    deleteGroupTarget.value = item
    deleteGroupDialog.value = true
  }

  const doDeleteGroup = () => {
    groups.value = groups.value.filter(g => g.id !== deleteGroupTarget.value.id)
    deleteGroupDialog.value = false
    showSnack('Group deleted', 'error')
  }

  // ── Option CRUD ───────────────────────────────────────────────────────────────
  const openCreateOption = group => {
    editOptionItem.value = null
    activeGroupId.value = group.id
    activeGroupName.value = group.name
    optionForm.value = defaultOptionForm()
    optionDialog.value = true
  }

  const openEditOption = (group, opt) => {
    editOptionItem.value = opt
    activeGroupId.value = group.id
    activeGroupName.value = group.name
    optionForm.value = { ...opt }
    optionDialog.value = true
  }

  const handleOptionSubmit = async () => {
    const { valid } = await optionFormRef.value.validate()
    if (!valid) return

    const payload = {
      id: optionForm.value.id,
      group_id: activeGroupId.value,
      name: optionForm.value.name,
      price_adjustment: Number(optionForm.value.price_adjustment),
      is_available: optionForm.value.is_available,
      sort_order: optionForm.value.sort_order ?? 0
    }

    const group = groups.value.find(g => g.id === activeGroupId.value)
    if (!group) return

    if (isEditOption.value) {
      const idx = group.options.findIndex(o => o.id === payload.id)
      if (idx !== -1) group.options[idx] = { ...group.options[idx], ...payload }
      showSnack('Option updated')
    } else {
      group.options.push({ ...payload, id: Date.now().toString() })
      showSnack('Option added')
    }

    optionDialog.value = false
  }

  const confirmDeleteOption = (groupId, opt) => {
    deleteOptionTarget.value = opt
    deleteOptionGroupId.value = groupId
    deleteOptionDialog.value = true
  }

  const doDeleteOption = () => {
    const group = groups.value.find(g => g.id === deleteOptionGroupId.value)
    if (group)
      group.options = group.options.filter(
        o => o.id !== deleteOptionTarget.value.id
      )
    deleteOptionDialog.value = false
    showSnack('Option deleted', 'error')
  }
</script>
