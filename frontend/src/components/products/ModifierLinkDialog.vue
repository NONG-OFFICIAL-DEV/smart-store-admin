<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="480"
    :fullscreen="smAndDown"
    :location="smAndDown ? undefined : 'top'"
    scrollable
    @update:model-value="close"
  >
    <v-card class="link-dialog d-flex flex-column" :style="paletteStyle">
      <!-- Header -->
      <div class="link-dialog__header pa-4 flex-grow-0">
        <button
          type="button"
          class="link-icon-btn link-dialog__close"
          :aria-label="t('btn.close')"
          @click="close"
        >
          <v-icon icon="mdi-close" size="20" />
        </button>
        <div class="link-title">{{ t('modifiers.link.title') }}</div>
        <div class="link-subtitle">
          {{ t('modifiers.link.subtitle_product', { name: productName }) }}
        </div>
      </div>

      <v-divider />

      <!-- Search + already-added -->
      <div class="pa-4 flex-grow-0">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          :placeholder="t('modifiers.link.search_placeholder')"
          variant="outlined"
          rounded="lg"
          density="comfortable"
          hide-details
          clearable
          :autofocus="!isTouch"
        />

        <div v-if="linkedGroups.length" class="d-flex flex-wrap ga-1 mt-3">
          <v-chip
            v-for="group in linkedGroups"
            :key="group.id"
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="mdi-check"
          >
            {{ group.name }}
          </v-chip>
        </div>
      </div>

      <v-divider />

      <!-- List -->
      <div class="link-dialog__body flex-grow-1 px-4 py-3">
        <div v-if="groupLoading" class="py-4">
          <v-skeleton-loader
            v-for="n in 4"
            :key="n"
            type="list-item-two-line"
            class="mb-2"
          />
        </div>

        <template v-else-if="selectableGroups.length">
          <button
            v-for="group in selectableGroups"
            :key="group.id"
            type="button"
            class="link-row"
            :class="{ 'link-row--selected': isSelected(group.id) }"
            role="checkbox"
            :aria-checked="isSelected(group.id)"
            :disabled="isUnusable(group)"
            @click="toggleGroup(group)"
          >
            <span
              class="link-checkbox"
              :class="{ 'link-checkbox--checked': isSelected(group.id) }"
            >
              <v-icon v-if="isSelected(group.id)" icon="mdi-check" size="14" />
            </span>

            <span class="min-width-0 flex-grow-1">
              <span class="d-flex align-center ga-2 flex-wrap">
                <span class="link-row__name">{{ group.name }}</span>
                <span v-if="group.name_km" class="link-row__name-km">
                  {{ group.name_km }}
                </span>
                <span
                  v-if="group.is_required"
                  class="link-pill link-pill--required"
                >
                  {{ t('modifiers.group.required_toggle') }}
                </span>
                <span
                  v-if="isDuplicateName(group)"
                  class="link-pill link-pill--warning"
                >
                  {{ t('modifiers.link.duplicate_name') }}
                </span>
              </span>
              <span class="link-row__rule">{{ ruleText(group) }}</span>
              <span v-if="choicesPreview(group)" class="link-row__choices">
                {{ choicesPreview(group) }}
              </span>
              <span v-if="isUnusable(group)" class="link-row__reason">
                {{ t('modifiers.link.unusable_reason') }}
              </span>
            </span>

            <span class="link-row__count flex-shrink-0">
              {{
                t('modifiers.link.products_count', group.products_count ?? 0)
              }}
            </span>
          </button>
        </template>

        <!-- Empty states -->
        <div v-else class="text-center py-10">
          <v-icon
            icon="mdi-format-list-checks"
            size="48"
            color="grey-lighten-1"
          />
          <p class="text-medium-emphasis mt-3 mb-1">
            {{
              search
                ? t('modifiers.link.empty_search')
                : t('modifiers.link.empty_all_linked')
            }}
          </p>
          <v-btn
            color="primary"
            variant="tonal"
            rounded="lg"
            class="mt-2 text-none"
            prepend-icon="mdi-plus"
            @click="emitCreateNew"
          >
            {{ t('products.detail.create_new_group') }}
          </v-btn>
        </div>

        <v-btn
          v-if="selectableGroups.length"
          border="dashed thin"
          variant="text"
          rounded="lg"
          color="primary"
          class="text-none mt-4"
          block
          @click="emitCreateNew"
        >
          <v-icon icon="mdi-plus" size="18" class="mr-1" />
          {{ t('products.detail.create_new_group') }}
        </v-btn>
      </div>

      <v-divider />

      <!-- Footer -->
      <div
        class="link-dialog__footer d-flex align-center ga-2 pa-4 flex-grow-0"
      >
        <v-btn
          variant="tonal"
          rounded="lg"
          class="text-none"
          :disabled="loading"
          @click="close"
        >
          {{ t('btn.cancel') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          class="text-none flex-grow-1"
          :loading="loading"
          :disabled="!selected.length"
          @click="submit"
        >
          {{ addButtonLabel }}
        </v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useDisplay, useTheme } from 'vuetify'
  import { useI18n } from 'vue-i18n'
  import { storeToRefs } from 'pinia'
  import { useModifierGroupStore } from '@/stores/modifierGroupStore'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'

  const { smAndDown } = useDisplay()
  const { t } = useI18n()
  const theme = useTheme()
  const { isTouch } = useTouchDevice()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    productId: { type: String, default: null },
    productName: { type: String, default: '' },
    // Full group objects already linked to this product (not just ids) —
    // needed to render the "already added" chips without a second fetch.
    linkedGroupIds: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'linked', 'create-new'])

  const groupStore = useModifierGroupStore()
  const { modifierGroups, loading: groupLoading } = storeToRefs(groupStore)

  const search = ref('')
  const selected = ref([])

  const isLinked = id => (props.linkedGroupIds ?? []).includes(id)
  const linkedGroups = computed(() =>
    (modifierGroups.value ?? []).filter(g => isLinked(g.id))
  )

  // A required group with no available choice (or no choices at all) can't
  // actually be used once linked — same check as the POS blocking logic and
  // the audit command, kept local since this is a product-admin dialog, not
  // a POS one.
  function isUnusable(group) {
    const options = group.options ?? []
    if (!options.length) return true
    if (group.is_required && !options.some(o => o.is_available !== false))
      return true
    return false
  }

  const duplicateNameSet = computed(() => {
    const counts = {}
    for (const g of modifierGroups.value ?? []) {
      const key = g.name?.trim().toLowerCase()
      if (!key) continue
      counts[key] = (counts[key] ?? 0) + 1
    }
    return new Set(Object.keys(counts).filter(k => counts[k] > 1))
  })
  const isDuplicateName = group =>
    duplicateNameSet.value.has(group.name?.trim().toLowerCase())

  const selectableGroups = computed(() => {
    const list = (modifierGroups.value ?? []).filter(g => !isLinked(g.id))
    if (!search.value) return list
    const q = search.value.toLowerCase()
    return list.filter(
      g =>
        g.name?.toLowerCase().includes(q) ||
        g.name_km?.toLowerCase().includes(q) ||
        (g.options ?? []).some(o => o.name?.toLowerCase().includes(q))
    )
  })

  const isSelected = id => selected.value.includes(id)
  function toggleGroup(group) {
    if (isUnusable(group)) return
    const idx = selected.value.indexOf(group.id)
    if (idx === -1) selected.value.push(group.id)
    else selected.value.splice(idx, 1)
  }

  function ruleText(group) {
    if (group.selection_type === 'single')
      return t('modifiers.options_tab.rule_pick_one')
    return group.max_selections
      ? t('modifiers.options_tab.rule_pick_up_to', { n: group.max_selections })
      : t('modifiers.options_tab.rule_pick_several')
  }

  function choicesPreview(group) {
    const names = (group.options ?? [])
      .filter(o => o.is_available !== false)
      .map(o => o.name)
    return names.join(', ')
  }

  const addButtonLabel = computed(() => {
    if (!selected.value.length) return t('modifiers.link.select_groups')
    return t('modifiers.link.add_groups_count', selected.value.length)
  })

  watch(
    () => props.modelValue,
    async open => {
      search.value = ''
      selected.value = []
      if (open && !modifierGroups.value?.length) {
        await groupStore.fetchModifierGroups({ perPage: -1 })
      }
    },
    { immediate: true }
  )

  function close() {
    if (props.loading) return
    emit('update:modelValue', false)
  }

  function emitCreateNew() {
    emit('update:modelValue', false)
    emit('create-new')
  }

  async function submit() {
    if (!selected.value.length) return

    const payload = {
      product_id: props.productId,
      modifier_group_ids: [...selected.value]
    }

    await new Promise((resolve, reject) =>
      emit('linked', payload, { resolve, reject })
    ).then(() => {
      close()
    })
  }

  // Exact light-mode hex values from the design spec; dark equivalents
  // follow this app's existing teal-dark/on-surface-opacity convention
  // (same pattern as PosProductCustomizeDialog.vue's paletteStyle).
  const paletteStyle = computed(() => {
    const dark = theme.global.current.value.dark
    return dark
      ? {
          '--link-border':
            'rgba(var(--v-border-color), var(--v-border-opacity))',
          '--link-text-muted': 'rgba(var(--v-theme-on-surface), 0.6)',
          '--link-neutral-bg': 'rgba(var(--v-theme-on-surface), 0.08)',
          '--link-selected-border': '#26C6DA',
          '--link-selected-bg': 'rgba(38, 198, 218, 0.14)',
          '--link-pill-required-bg': 'rgba(181, 71, 8, 0.25)',
          '--link-pill-required-text': '#FDB022',
          '--link-pill-warning-bg': 'rgba(181, 71, 8, 0.25)',
          '--link-pill-warning-text': '#FDB022'
        }
      : {
          '--link-border': '#E4E7EC',
          '--link-text-muted': '#667085',
          '--link-neutral-bg': '#F2F4F7',
          '--link-selected-border': '#0F6E75',
          '--link-selected-bg': '#E6F4F5',
          '--link-pill-required-bg': '#FEF0C7',
          '--link-pill-required-text': '#B54708',
          '--link-pill-warning-bg': '#FEF0C7',
          '--link-pill-warning-text': '#B54708'
        }
  })
</script>

<style scoped>
  .link-dialog {
    border-radius: 18px;
  }
  .link-dialog__header {
    position: relative;
  }
  .link-dialog__body {
    overflow-y: auto;
    min-height: 0;
    -webkit-overflow-scrolling: touch;
  }
  .link-dialog__footer {
    border-top: 1px solid var(--link-border);
  }
  .min-width-0 {
    min-width: 0;
  }

  .link-icon-btn {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: none;
    background: var(--link-neutral-bg);
    color: rgba(var(--v-theme-on-surface), 0.7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  }
  .link-dialog__close {
    position: absolute;
    top: 16px;
    right: 16px;
  }
  .link-title {
    font-size: 18px;
    font-weight: 700;
    padding-right: 48px;
  }
  .link-subtitle {
    font-size: 13px;
    color: var(--link-text-muted);
    margin-top: 2px;
  }

  .link-row {
    width: 100%;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid var(--link-border);
    background: transparent;
    font-family: inherit;
    text-align: start;
    cursor: pointer;
    margin-bottom: 8px;
    transition:
      border-color 0.15s,
      background-color 0.15s;
  }
  .link-row--selected {
    border-width: 2px;
    border-color: var(--link-selected-border);
    background: var(--link-selected-bg);
  }
  .link-row:disabled {
    cursor: not-allowed;
    opacity: 0.55;
  }

  .link-checkbox {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    margin-top: 2px;
    border-radius: 6px;
    border: 2px solid var(--link-border);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
  }
  .link-checkbox--checked {
    border-color: var(--link-selected-border);
    background: var(--link-selected-border);
  }

  .link-row__name {
    font-size: 14px;
    font-weight: 700;
  }
  .link-row__name-km {
    font-size: 12px;
    color: var(--link-text-muted);
  }
  .link-row__rule {
    display: block;
    font-size: 12px;
    color: var(--link-text-muted);
    margin-top: 2px;
  }
  .link-row__choices {
    display: block;
    font-size: 12px;
    color: var(--link-text-muted);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .link-row__reason {
    display: block;
    font-size: 12px;
    color: var(--link-pill-warning-text);
    margin-top: 2px;
  }
  .link-row__count {
    font-size: 12px;
    color: var(--link-text-muted);
    margin-top: 2px;
  }

  .link-pill {
    flex-shrink: 0;
    padding: 1px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.6;
    white-space: nowrap;
  }
  .link-pill--required {
    background: var(--link-pill-required-bg);
    color: var(--link-pill-required-text);
  }
  .link-pill--warning {
    background: var(--link-pill-warning-bg);
    color: var(--link-pill-warning-text);
  }
</style>
