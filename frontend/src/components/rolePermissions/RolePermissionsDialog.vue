<template>
  <v-dialog
    :model-value="modelValue"
    max-width="680"
    persistent
    scrollable
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card rounded="xl" elevation="0" border>
      <!-- Header -->
      <v-card-title class="pa-6 pb-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar color="warning" size="40" rounded="lg" class="me-2">
              <v-icon icon="mdi-key-variant" size="20" />
            </v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">Assign Permissions</div>
              <div class="text-caption text-medium-emphasis">
                {{ role?.name }}
              </div>
            </div>
          </div>
          <div class="d-flex align-center gap-2">
            <v-chip color="primary" variant="tonal" size="small">
              {{ selectedIds.length }} selected
            </v-chip>
            <v-btn
              icon="mdi-close"
              size="small"
              variant="text"
              @click="close"
            />
          </div>
        </div>
      </v-card-title>
      <v-divider />

      <!-- Search -->
      <div class="pa-4 pb-0">
        <v-text-field
          v-model="permSearch"
          placeholder="Search permissions..."
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          rounded="lg"
        />
      </div>

      <!-- Grouped permissions -->
      <v-card-text style="max-height: 460px; overflow-y: auto" class="pa-4">
        <div
          v-for="(group, groupName) in filteredGrouped"
          :key="groupName"
          class="mb-5"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-2">
              <v-chip
                :color="groupColor(groupName)"
                variant="flat"
                size="small"
                rounded="lg"
                class="me-2"
              >
                <v-icon icon="mdi-folder" size="13" class="mr-1" />
                {{ groupName }}
              </v-chip>
              <span class="text-caption text-medium-emphasis">
                {{ group.length }} permissions
              </span>
            </div>
            <v-btn
              size="x-small"
              variant="text"
              :color="groupAllSelected(group) ? 'error' : 'primary'"
              @click="toggleGroup(group)"
            >
              {{ groupAllSelected(group) ? 'Deselect all' : 'Select all' }}
            </v-btn>
          </div>

          <v-row dense>
            <v-col v-for="perm in group" :key="perm.id" cols="12" sm="6">
              <v-card
                rounded="lg"
                elevation="0"
                :color="
                  selectedIds.includes(perm.id)
                    ? groupColor(groupName)
                    : undefined
                "
                :variant="selectedIds.includes(perm.id) ? 'tonal' : 'outlined'"
                class="perm-card pa-2"
                @click="togglePerm(perm.id)"
              >
                <div class="d-flex align-center gap-2">
                  <v-checkbox
                    :model-value="selectedIds.includes(perm.id)"
                    :color="groupColor(groupName)"
                    density="compact"
                    hide-details
                    class="me-2"
                  />
                  <div>
                    <div class="text-body-2 font-weight-medium">
                      {{ perm.code }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ perm.description }}
                    </div>
                  </div>
                </div>
              </v-card>
            </v-col>
          </v-row>
        </div>

        <div
          v-if="!Object.keys(filteredGrouped).length"
          class="text-center py-8 text-medium-emphasis"
        >
          <v-icon icon="mdi-key-off" size="40" color="grey-lighten-2" />
          <p class="text-body-2 mt-2">No permissions found</p>
        </div>
      </v-card-text>

      <v-divider />
      <v-card-actions>
        <v-spacer/>
        <v-btn variant="tonal" rounded="lg" @click="close">Cancel</v-btn>
        <v-btn
          color="warning"
          variant="flat"
          rounded="lg"
          prepend-icon="mdi-content-save"
          :loading="loading"
          @click="handleSave"
        >
          Save Permissions
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'

  // ── Props & Emits ─────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    role: { type: Object, default: null }, // the role being edited
    permissions: { type: Array, default: () => [] }, // all available permissions
    assigned: { type: Array, default: () => [] }, // currently assigned permission ids
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  // ── State ─────────────────────────────────────────────────────────────────────
  const permSearch = ref('')
  const selectedIds = ref([])

  // ── Sync assigned ids when dialog opens ──────────────────────────────────────
  watch(
    () => props.assigned,
    val => {
      selectedIds.value = [...val]
    },
    { immediate: true }
  )

  watch(
    () => props.modelValue,
    open => {
      if (open) {
        selectedIds.value = [...props.assigned]
        permSearch.value = ''
      }
    }
  )

  // ── Grouped permissions ───────────────────────────────────────────────────────
  const groupedAll = computed(() => {
    return props.permissions.reduce((acc, p) => {
      if (!acc[p.group]) acc[p.group] = []
      acc[p.group].push(p)
      return acc
    }, {})
  })

  const filteredGrouped = computed(() => {
    if (!permSearch.value) return groupedAll.value
    const q = permSearch.value.toLowerCase()
    const result = {}
    for (const [group, perms] of Object.entries(groupedAll.value)) {
      const filtered = perms.filter(
        p =>
          p.code.toLowerCase().includes(q) ||
          (p.description || '').toLowerCase().includes(q)
      )
      if (filtered.length) result[group] = filtered
    }
    return result
  })

  // ── Color map ─────────────────────────────────────────────────────────────────
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
      colorCache[g] =
        colorList[Object.keys(colorCache).length % colorList.length]
    }
    return colorCache[g]
  }

  // ── Toggle helpers ────────────────────────────────────────────────────────────
  const groupAllSelected = group =>
    group.every(p => selectedIds.value.includes(p.id))

  const toggleGroup = group => {
    if (groupAllSelected(group)) {
      selectedIds.value = selectedIds.value.filter(
        id => !group.find(p => p.id === id)
      )
    } else {
      group.forEach(p => {
        if (!selectedIds.value.includes(p.id)) selectedIds.value.push(p.id)
      })
    }
  }

  const togglePerm = id => {
    const idx = selectedIds.value.indexOf(id)
    if (idx === -1) selectedIds.value.push(id)
    else selectedIds.value.splice(idx, 1)
  }

  // ── Actions ───────────────────────────────────────────────────────────────────
  const handleSave = () => {
    emit('saved', {
      role_id: props.role?.id,
      permission_ids: selectedIds.value
    })
  }

  const close = () => {
    emit('update:modelValue', false)
  }
</script>

<style scoped>
  .perm-card {
    cursor: pointer;
    transition: all 0.15s;
  }
  .perm-card:hover {
    transform: translateY(-1px);
  }
</style>
