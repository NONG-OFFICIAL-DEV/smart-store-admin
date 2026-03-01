<template>
  <v-dialog v-model="model" max-width="560" persistent>
    <v-card rounded="lg">
      <!-- Header -->
      <v-card-title
        class="d-flex align-center justify-space-between px-6 pt-5 pb-4"
      >
        <div class="d-flex align-center gap-3">
          <v-avatar color="secondary" variant="tonal" size="40" rounded="md">
            <v-icon icon="mdi-link-variant-plus" size="20" />
          </v-avatar>
          <div>
            <p class="text-body-1 font-weight-semibold text-grey-darken-3 mb-0">
              Link Modifier Groups
            </p>
            <p class="text-caption text-grey mb-0">
              Select groups to attach to this product
            </p>
          </div>
        </div>
        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          :disabled="loading"
          @click="close"
        />
      </v-card-title>

      <v-divider />

      <v-card-text class="px-6 py-4">
        <!-- Search -->
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          placeholder="Search modifier groups..."
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          clearable
          class="mb-4"
        />

        <!-- Already linked notice -->
        <div v-if="alreadyLinkedIds.length" class="mb-3">
          <p class="text-caption text-grey mb-2">
            <v-icon icon="mdi-link-variant" size="14" class="mr-1" />
            Already linked ({{ alreadyLinkedIds.length }}) — shown with lock
            icon
          </p>
        </div>

        <!-- Group List -->
        <div v-if="groupLoading" class="py-4">
          <v-skeleton-loader
            v-for="n in 4"
            :key="n"
            type="list-item-two-line"
            class="mb-2"
          />
        </div>

        <div v-else-if="filteredGroups.length" class="group-list">
          <v-card
            v-for="group in filteredGroups"
            :key="group.id"
            :class="[
              'group-item mb-2 cursor-pointer',
              isSelected(group.id) ? 'group-item--selected' : '',
              isAlreadyLinked(group.id) ? 'group-item--linked' : ''
            ]"
            rounded="lg"
            border
            elevation="0"
            @click="toggleGroup(group)"
          >
            <div class="d-flex align-center gap-3 pa-3">
              <!-- Checkbox -->
              <v-checkbox-btn
                :model-value="isSelected(group.id) || isAlreadyLinked(group.id)"
                :disabled="isAlreadyLinked(group.id)"
                :color="isAlreadyLinked(group.id) ? 'grey' : 'primary'"
                hide-details
                density="compact"
                @click.stop="toggleGroup(group)"
              />

              <!-- Icon -->
              <v-avatar
                :color="isSelected(group.id) ? 'primary' : 'grey'"
                variant="tonal"
                rounded="md"
                size="36"
              >
                <v-icon icon="mdi-format-list-checks" size="18" />
              </v-avatar>

              <!-- Info -->
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <span class="text-body-2 font-weight-medium">
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
                    v-if="group.is_required"
                    color="error"
                    size="x-small"
                    variant="tonal"
                  >
                    required
                  </v-chip>
                </div>
                <div class="text-caption text-grey mt-0">
                  {{ group.options?.length ?? 0 }} options
                  <span v-if="group.min_selections">
                    · Min: {{ group.min_selections }}
                  </span>
                  <span v-if="group.max_selections">
                    · Max: {{ group.max_selections }}
                  </span>
                </div>
              </div>

              <!-- Already linked lock -->
              <v-icon
                v-if="isAlreadyLinked(group.id)"
                icon="mdi-lock-outline"
                size="16"
                color="grey"
              />
            </div>
          </v-card>
        </div>

        <!-- Empty -->
        <div v-else class="text-center py-10">
          <v-icon
            icon="mdi-format-list-checks"
            size="48"
            color="grey-lighten-2"
            class="mb-3"
          />
          <p class="text-body-2 text-medium-emphasis">
            {{
              search
                ? 'No groups match your search'
                : 'No modifier groups available'
            }}
          </p>
        </div>
      </v-card-text>

      <!-- Selection summary -->
      <div v-if="newSelections.length" class="px-6 py-2 bg-primary-lighten-5">
        <p class="text-caption text-primary mb-0">
          <v-icon icon="mdi-check-circle-outline" size="14" class="mr-1" />
          {{ newSelections.length }} group{{
            newSelections.length > 1 ? 's' : ''
          }}
          selected to link
        </p>
      </div>

      <v-divider />

      <v-card-actions class="px-6 py-4 gap-3">
        <v-btn
          variant="outlined"
          rounded="lg"
          class="flex-grow-1"
          :disabled="loading"
          @click="close"
        >
          Cancel
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          class="flex-grow-1"
          :loading="loading"
          :disabled="newSelections.length === 0"
          @click="submit"
        >
          Link
          {{
            newSelections.length > 0 ? `(${newSelections.length})` : ''
          }}
          Groups
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useModifierGroupStore } from '@/stores/modifierGroupStore'

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    productId: { type: String, default: null },
    // IDs of groups already linked to this product
    linkedGroupIds: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'linked'])

  const groupStore = useModifierGroupStore()
  const { modifierGroups, loading: groupLoading } = storeToRefs(groupStore)

  const search = ref('')
  // IDs the user just selected in THIS dialog session (not already linked)
  const selected = ref([])

  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const alreadyLinkedIds = computed(() => props.linkedGroupIds ?? [])

  const isAlreadyLinked = id => alreadyLinkedIds.value.includes(id)
  const isSelected = id => selected.value.includes(id)

  // Only the newly selected ones (not already linked)
  const newSelections = computed(() =>
    selected.value.filter(id => !isAlreadyLinked(id))
  )

  // ── Filtered groups ───────────────────────────────────────────────────────────
  const filteredGroups = computed(() => {
    const list = modifierGroups.value ?? []
    if (!search.value) return list
    const q = search.value.toLowerCase()
    return list.filter(g => g.name.toLowerCase().includes(q))
  })

  // ── Toggle selection ──────────────────────────────────────────────────────────
  const toggleGroup = group => {
    if (isAlreadyLinked(group.id)) return // can't unlink from here

    const idx = selected.value.indexOf(group.id)
    if (idx === -1) {
      selected.value.push(group.id)
    } else {
      selected.value.splice(idx, 1)
    }
  }

  // ── Reset on open ─────────────────────────────────────────────────────────────
  watch(
    () => props.modelValue,
    async val => {
      if (val) {
        search.value = ''
        selected.value = []
        if (!modifierGroups.value?.length) {
          await groupStore.fetchModifierGroups()
        }
      }
    }
  )

  // ── Actions ───────────────────────────────────────────────────────────────────
  const close = () => {
    if (props.loading) return
    model.value = false
    selected.value = []
    search.value = ''
  }

  const submit = async () => {
    if (!newSelections.value.length) return

    const payload = {
      product_id: props.productId,
      modifier_group_ids: newSelections.value
    }

    await new Promise((resolve, reject) => {
      emit('linked', payload, { resolve, reject })
    })
      .then(() => {
        close()
      })
      .catch(err => {
        console.error('Failed to link modifier groups', err)
      })
  }
</script>

<style scoped>
  .group-list {
    max-height: 380px;
    overflow-y: auto;
  }

  .group-item {
    transition:
      border-color 0.15s,
      background-color 0.15s;
    cursor: pointer;
  }

  .group-item:hover {
    border-color: rgba(var(--v-theme-primary), 0.4) !important;
    background: rgba(var(--v-theme-primary), 0.03);
  }

  .group-item--selected {
    border-color: rgba(var(--v-theme-primary), 0.6) !important;
    background: rgba(var(--v-theme-primary), 0.06) !important;
  }

  .group-item--linked {
    border-color: rgba(0, 0, 0, 0.08) !important;
    background: rgba(0, 0, 0, 0.02) !important;
    opacity: 0.7;
  }

  .cursor-pointer {
    cursor: pointer;
  }

  .min-width-0 {
    min-width: 0;
  }
</style>
