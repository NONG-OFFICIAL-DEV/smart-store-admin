<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="460"
    :fullscreen="smAndDown"
    :location="smAndDown ? undefined : 'top'"
    scrollable
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card
      v-if="product"
      class="pos-customize d-flex flex-column"
      :style="[{ maxHeight: `${availableHeight - 16}px` }, paletteStyle]"
    >
      <!-- Header — thumbnail + name/price + close button, fixed, never
           scrolls. A small 72px thumbnail, not a big hero photo. -->
      <div class="pos-customize__header pa-4 flex-grow-0">
        <button
          type="button"
          class="pos-icon-btn pos-customize__close"
          :aria-label="$t('btn.close')"
          @click="$emit('update:modelValue', false)"
        >
          <v-icon icon="mdi-close" size="20" />
        </button>
        <div class="d-flex ga-3">
          <div class="pos-thumb flex-shrink-0">
            <v-img
              v-if="product.image_url"
              :src="product.image_url"
              cover
              class="pos-thumb__img"
            />
            <div v-else class="pos-thumb__placeholder">
              <v-icon icon="mdi-image-off-outline" size="28" color="grey" />
            </div>
          </div>
          <div class="min-width-0 pt-1">
            <div class="pos-name">{{ product.name }}</div>
            <div v-if="product.name_km" class="pos-name-km">
              {{ product.name_km }}
            </div>
            <div class="pos-price mt-1">{{ priceDisplay }}</div>
          </div>
        </div>
      </div>

      <v-divider />

      <!-- Body — scrolls. Order: Size first, then option groups,
           required groups before optional ones (link order preserved
           within each bucket). -->
      <div class="pos-customize__body flex-grow-1 px-4 py-3">
        <div v-if="sizeOptions.length > 1" class="mb-4">
          <div class="pos-group-name mb-2">{{ $t('pos.customize.size') }}</div>
          <div
            class="pos-choices"
            role="radiogroup"
            :aria-label="$t('pos.customize.size')"
          >
            <button
              v-for="opt in sizeOptions"
              :key="opt.variant_id"
              type="button"
              class="pos-choice"
              :class="{ 'pos-choice--selected': variantId === opt.variant_id }"
              role="radio"
              :aria-checked="variantId === opt.variant_id"
              @click="variantId = opt.variant_id"
            >
              <span class="pos-choice__label">
                {{ opt.label }}
                <span class="pos-choice__price">
                  {{ formatMoney(opt.price) }}
                </span>
              </span>
            </button>
          </div>
        </div>

        <div v-for="group in orderedGroups" :key="group.id" class="mb-4">
          <div class="d-flex align-start justify-space-between ga-2 mb-2">
            <div class="min-width-0">
              <div class="d-flex align-center ga-2 flex-wrap">
                <span class="pos-group-name">{{ group.name }}</span>
                <span v-if="group.name_km" class="pos-group-name-km">
                  {{ group.name_km }}
                </span>
              </div>
              <div class="pos-group-rule">{{ ruleText(group) }}</div>
            </div>
            <span class="pos-pill" :class="`pos-pill--${groupStatus(group)}`">
              {{
                groupStatus(group) === 'required'
                  ? $t('pos.customize.required')
                  : groupStatus(group) === 'done'
                    ? $t('pos.customize.done')
                    : $t('pos.customize.optional')
              }}
            </span>
          </div>

          <!-- Pick one = radio behavior, Pick several = checkbox behavior —
               same tile markup for both; a "+"/"✓" text mark (not an icon)
               distinguishes multi-select, single-select has no mark at
               all. -->
          <div
            class="pos-choices"
            :role="group.selection_type === 'multiple' ? 'group' : 'radiogroup'"
          >
            <button
              v-for="opt in group.options"
              :key="opt.id"
              type="button"
              class="pos-choice"
              :class="{
                'pos-choice--selected': isChoiceSelected(group, opt.id),
                'pos-choice--disabled': isChoiceDisabled(group, opt)
              }"
              :disabled="isChoiceDisabled(group, opt)"
              :role="group.selection_type === 'multiple' ? 'checkbox' : 'radio'"
              :aria-checked="isChoiceSelected(group, opt.id)"
              @click="onChoiceClick(group, opt)"
            >
              <span
                v-if="group.selection_type === 'multiple'"
                class="pos-choice__mark"
              >
                {{ isChoiceSelected(group, opt.id) ? '✓' : '+' }}
              </span>
              <span class="pos-choice__label">
                <template v-if="opt.is_available === false">
                  <span class="text-decoration-line-through">
                    {{ opt.name }}
                  </span>
                  <span class="pos-choice__sold-out">
                    {{ $t('pos.customize.sold_out') }}
                  </span>
                </template>
                <template v-else>
                  {{ opt.name }}
                  <span v-if="opt.price_adjustment" class="pos-choice__price">
                    +{{ formatMoney(opt.price_adjustment) }}
                  </span>
                </template>
              </span>
            </button>
          </div>

          <!-- Custom number — an alternative to the preset tiles above,
               e.g. Sugar. Typing a value matching a preset's own label
               (value + suffix) selects that preset; clearing restores the
               group's default. -->
          <div
            v-if="group.allow_custom_value"
            class="pos-custom-row d-flex align-center ga-2 mt-2"
          >
            <span class="pos-custom-row__label">
              {{ $t('pos.customize.or_type_own') }}
            </span>

            <v-menu
              v-if="isTouch"
              v-model="numpadOpen[group.id]"
              :close-on-content-click="false"
              location="bottom"
            >
              <template #activator="{ props: menuProps }">
                <input
                  v-bind="menuProps"
                  :value="customValues[group.id] || ''"
                  class="pos-custom-input"
                  :class="{
                    'pos-custom-input--selected': isCustomActive(group)
                  }"
                  type="text"
                  inputmode="none"
                  readonly
                  :aria-label="$t('pos.customize.or_type_own')"
                />
              </template>
              <v-card rounded="lg" width="280" class="pa-3">
                <NumPad
                  :model-value="
                    customValues[group.id]
                      ? Number(customValues[group.id])
                      : null
                  "
                  :suffix="group.custom_suffix || '%'"
                  @update:model-value="val => onCustomValueChange(group, val)"
                />
              </v-card>
            </v-menu>
            <input
              v-else
              :value="customValues[group.id] || ''"
              class="pos-custom-input"
              :class="{ 'pos-custom-input--selected': isCustomActive(group) }"
              type="number"
              inputmode="numeric"
              min="0"
              :aria-label="$t('pos.customize.or_type_own')"
              @input="onCustomValueChange(group, $event.target.value)"
            />

            <span class="pos-custom-row__suffix">
              {{ group.custom_suffix || '%' }}
            </span>
          </div>
        </div>
        <v-btn
          v-if="!noteOpen"
          @click="noteOpen = true"
          border="dashed thin md"
          variant="text"
          class="text-none pos-note-btn"
          rounded="lg"
        >
          + {{ $t('pos.customize.note_action') }}
        </v-btn>
        <v-textarea
          v-else
          v-model="note"
          :placeholder="$t('pos.customize.note_placeholder')"
          variant="outlined"
          density="compact"
          rounded="lg"
          rows="2"
          auto-grow
          hide-details
          enterkeyhint="done"
          :autofocus="!isTouch"
          @focus="keepVisible($event.target)"
          class="mb-1"
        />
      </div>

      <v-divider />

      <!-- Footer — one row, fixed, never scrolls: the stepper box, then
           the Add-to-order button filling the rest with its own internal
           label-left / total-right spacing. No separate hint text below —
           the blocked reason is the button's own label. -->
      <div class="pos-customize__footer d-flex align-center pt-3 px-4 pb-4">
        <div class="pos-stepper flex-shrink-0">
          <button
            type="button"
            class="pos-stepper__btn"
            :disabled="quantity <= 1"
            @click="quantity = Math.max(1, quantity - 1)"
          >
            <v-icon icon="mdi-minus" size="20" />
          </button>
          <span class="pos-stepper__value">{{ quantity }}</span>
          <button type="button" class="pos-stepper__btn" @click="quantity++">
            <v-icon icon="mdi-plus" size="20" />
          </button>
        </div>

        <button
          type="button"
          class="pos-add-btn"
          :class="{ 'pos-add-btn--blocked': !canAdd }"
          :disabled="!canAdd"
          @click="onAdd"
        >
          <span>
            {{
              canAdd
                ? $t('pos.customize.add_to_order')
                : $t('pos.customize.choose_group', {
                    name: firstUnsatisfiedRequiredGroup?.name?.toLowerCase()
                  })
            }}
          </span>
          <span>{{ formatMoney(totalPrice) }}</span>
        </button>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { useDisplay, useTheme } from 'vuetify'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'
  import { useKeyboardSafeArea } from '@/composables/useKeyboardSafeArea.js'
  import NumPad from '@/components/shared/NumPad.vue'

  const { smAndDown } = useDisplay()
  const { t } = useI18n()
  const theme = useTheme()
  const { format: formatMoney } = useCurrency()
  const { isTouch } = useTouchDevice()
  const { availableHeight, keepVisible } = useKeyboardSafeArea()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    product: { type: Object, default: null }
  })

  const emit = defineEmits(['update:modelValue', 'add'])

  const variantId = ref(null)
  const selections = ref({})
  const customValues = reactive({})
  const numpadOpen = reactive({})
  const quantity = ref(1)
  const note = ref('')
  const noteOpen = ref(false)

  const sizeOptions = computed(() => props.product?.options ?? [])

  // Hidden entirely, not just disabled — a group with zero available
  // choices has nothing useful to show. A required group with zero
  // available choices is caught earlier, in PosProductGrid, which blocks
  // the card itself before this dialog can even open.
  const visibleGroups = computed(() =>
    (props.product?.modifier_groups ?? []).filter(g =>
      (g.options ?? []).some(o => o.is_available !== false)
    )
  )

  // Required groups first, link order preserved within each bucket —
  // Array.sort is stable per spec, safe to rely on here.
  const orderedGroups = computed(() =>
    [...visibleGroups.value].sort((a, b) => {
      if (a.is_required === b.is_required) return 0
      return a.is_required ? -1 : 1
    })
  )

  // "From {price}" when the product has more than one size (cheapest
  // first), the exact price otherwise — never $0.00 for a sized product.
  const priceDisplay = computed(() => {
    if (!props.product) return ''
    if (sizeOptions.value.length > 1) {
      const min = Math.min(...sizeOptions.value.map(o => o.price))
      return t('pos.customize.from_price', { price: formatMoney(min) })
    }
    return formatMoney(props.product.base_price)
  })

  function resetState() {
    const opts = sizeOptions.value
    variantId.value = opts.length
      ? (opts.find(o => o.is_default)?.variant_id ?? opts[0].variant_id)
      : null

    const nextSelections = {}
    Object.keys(customValues).forEach(k => delete customValues[k])
    Object.keys(numpadOpen).forEach(k => delete numpadOpen[k])

    for (const group of visibleGroups.value) {
      const availableOptions = group.options.filter(
        o => o.is_available !== false
      )
      if (group.selection_type === 'multiple') {
        // Preselect every default choice, capped at max_selections.
        const defaults = availableOptions
          .filter(o => o.is_default)
          .map(o => o.id)
        nextSelections[group.id] = group.max_selections
          ? defaults.slice(0, group.max_selections)
          : defaults
      } else {
        const defaultOption = availableOptions.find(o => o.is_default)
        const firstAvailable = availableOptions[0]
        nextSelections[group.id] = group.is_required
          ? (defaultOption?.id ?? firstAvailable?.id ?? null)
          : (defaultOption?.id ?? null)
      }
    }
    selections.value = nextSelections

    quantity.value = 1
    note.value = ''
    noteOpen.value = false
  }

  watch(
    () => props.modelValue,
    open => {
      if (open) resetState()
    }
  )

  function onSingleSelect(group, optionId) {
    selections.value[group.id] = optionId
    // Picking a preset tile directly overrides whatever custom value was
    // typed — the two are alternatives for the same group, not additive.
    if (optionId) customValues[group.id] = ''
  }

  function isMultiSelected(group, optionId) {
    return (selections.value[group.id] ?? []).includes(optionId)
  }

  function isGroupFull(group) {
    if (!group.max_selections) return false
    return (selections.value[group.id] ?? []).length >= group.max_selections
  }

  function toggleMultiOption(group, optionId) {
    const current = selections.value[group.id] ?? []
    if (current.includes(optionId)) {
      selections.value[group.id] = current.filter(id => id !== optionId)
    } else {
      if (isGroupFull(group)) return
      selections.value[group.id] = [...current, optionId]
    }
  }

  // Shared by both selection types so pick-one and pick-several render from
  // the exact same tile markup — only the mark and the click behavior
  // differ (radio-exclusive vs checkbox-independent).
  function isChoiceSelected(group, optionId) {
    return group.selection_type === 'multiple'
      ? isMultiSelected(group, optionId)
      : selections.value[group.id] === optionId
  }

  function isChoiceDisabled(group, opt) {
    if (opt.is_available === false) return true
    if (group.selection_type === 'multiple') {
      return !isMultiSelected(group, opt.id) && isGroupFull(group)
    }
    return false
  }

  function onChoiceClick(group, opt) {
    if (isChoiceDisabled(group, opt)) return
    if (group.selection_type === 'multiple') {
      toggleMultiOption(group, opt.id)
    } else {
      onSingleSelect(group, opt.id)
    }
  }

  // "Pick 1" / "Pick up to N · K selected" / "Pick several · K selected" —
  // always shown, so the two selection types never look the same.
  function ruleText(group) {
    if (group.selection_type !== 'multiple') return t('pos.customize.pick_one')
    const count = (selections.value[group.id] ?? []).length
    return group.max_selections
      ? t('pos.customize.pick_up_to_selected', {
          n: group.max_selections,
          count
        })
      : t('pos.customize.pick_several_selected', { count })
  }

  // Shared by the status pill and the "first unsatisfied required group"
  // check that drives canAdd/the button label.
  function isGroupSatisfied(group) {
    const sel = selections.value[group.id]
    if (group.selection_type === 'multiple') {
      return (sel?.length ?? 0) >= (group.min_selections || 1)
    }
    return !!sel || !!customValues[group.id]
  }

  function groupStatus(group) {
    if (!group.is_required) return 'optional'
    return isGroupSatisfied(group) ? 'done' : 'required'
  }

  // ── Custom number input ──────────────────────────────────────────────────
  // Typing a value that reconstructs an existing preset's label (value +
  // suffix, e.g. "50" + "%" = "50%") selects that preset outright — the
  // raw typed digits are left as-is (harmless; onAdd() only submits them
  // when no concrete option ended up selected, see below). Clearing the
  // field restores the group's own default.
  function onCustomValueChange(group, rawValue) {
    const value =
      rawValue === null || rawValue === undefined ? '' : String(rawValue).trim()
    customValues[group.id] = value

    if (value === '') {
      const availableOptions = group.options.filter(
        o => o.is_available !== false
      )
      selections.value[group.id] =
        availableOptions.find(o => o.is_default)?.id ?? null
      return
    }

    const suffix = group.custom_suffix || '%'
    const matched = group.options.find(o => o.name === `${value}${suffix}`)
    selections.value[group.id] = matched ? matched.id : null
  }

  // The input gets the "selected" visual treatment only while it's the
  // active choice for its group (a typed value with no matching/selected
  // option) — once a preset tile is chosen directly, or a match is found,
  // the tile itself carries the selected styling instead.
  function isCustomActive(group) {
    return !!customValues[group.id] && !selections.value[group.id]
  }

  const selectedVariantOption = computed(
    () => sizeOptions.value.find(o => o.variant_id === variantId.value) ?? null
  )

  const modifiersTotal = computed(() => {
    let total = 0
    for (const group of visibleGroups.value) {
      const sel = selections.value[group.id]
      const ids =
        group.selection_type === 'multiple' ? (sel ?? []) : sel ? [sel] : []
      for (const id of ids) {
        const opt = group.options.find(o => o.id === id)
        if (opt) total += Number(opt.price_adjustment ?? 0)
      }
    }
    return total
  })

  const unitPrice = computed(() => {
    const base = Number(props.product?.base_price ?? 0)
    const variantPrice = selectedVariantOption.value
      ? Number(selectedVariantOption.value.price)
      : base
    return variantPrice + modifiersTotal.value
  })

  const totalPrice = computed(() => unitPrice.value * quantity.value)

  // The first required-but-unsatisfied group, in display order — drives
  // both canAdd and the Add-button's own "Choose {name}" label, so a
  // blocked button is never silent about why.
  const firstUnsatisfiedRequiredGroup = computed(() => {
    for (const group of orderedGroups.value) {
      if (group.is_required && !isGroupSatisfied(group)) return group
    }
    return null
  })

  const canAdd = computed(() => !firstUnsatisfiedRequiredGroup.value)

  function onAdd() {
    const customizations = []
    const modifierOptionIds = []
    const customModifierValues = []

    for (const group of visibleGroups.value) {
      const sel = selections.value[group.id]
      const ids =
        group.selection_type === 'multiple' ? (sel ?? []) : sel ? [sel] : []
      for (const id of ids) {
        const opt = group.options.find(o => o.id === id)
        if (!opt) continue
        customizations.push({
          label: group.name,
          value: opt.name,
          price: Number(opt.price_adjustment ?? 0)
        })
        modifierOptionIds.push(opt.id)
      }

      // Only submitted when no concrete option ended up selected for this
      // group — a matched preset already covered it via `sel` above, and
      // submitting both would double-count.
      const customValue = customValues[group.id]
      if (customValue && !sel) {
        customizations.push({
          label: group.name,
          value: `${customValue}${group.custom_suffix || '%'}`,
          price: 0
        })
        customModifierValues.push({
          group_id: group.id,
          value: String(customValue)
        })
      }
    }

    emit('add', {
      product: props.product,
      variant_id: selectedVariantOption.value?.variant_id ?? null,
      variant_name: selectedVariantOption.value?.label ?? null,
      unit_price: unitPrice.value,
      quantity: quantity.value,
      notes: note.value || '',
      customizations,
      modifier_option_ids: modifierOptionIds,
      custom_modifier_values: customModifierValues
    })
    emit('update:modelValue', false)
  }

  // ── Light/dark palette ────────────────────────────────────────────────────
  // The design spec gives exact hex values for light mode. There's no
  // existing convention in this codebase for a component-level dark-mode
  // CSS override (everywhere else just uses Vuetify's auto-adapting
  // --v-theme-* tokens), so these are bound as CSS custom properties driven
  // by the app's real theme state (Settings > the actual light/dark/auto
  // toggle, via useTheme()) rather than guessing a selector. Where the spec
  // didn't give an exact color (e.g. "teal bg"), the dark side reuses this
  // app's own theme tokens instead of inventing a value.
  const paletteStyle = computed(() => {
    const dark = theme.global.current.value.dark
    return dark
      ? {
          '--pos-pill-required-bg': 'rgba(181, 71, 8, 0.25)',
          '--pos-pill-required-text': '#FDB022',
          '--pos-pill-done-bg': 'rgba(6, 118, 71, 0.25)',
          '--pos-pill-done-text': '#32D583',
          '--pos-pill-optional-bg': 'rgba(var(--v-theme-on-surface), 0.08)',
          '--pos-pill-optional-text': 'rgba(var(--v-theme-on-surface), 0.6)',
          '--pos-choice-border':
            'rgba(var(--v-border-color), var(--v-border-opacity))',
          '--pos-choice-text': 'rgba(var(--v-theme-on-surface), 0.92)',
          '--pos-choice-selected-border': '#26C6DA',
          '--pos-choice-selected-bg': 'rgba(38, 198, 218, 0.16)',
          '--pos-choice-selected-text': '#26C6DA',
          '--pos-choice-disabled-bg': 'rgba(var(--v-theme-on-surface), 0.04)',
          '--pos-choice-disabled-border':
            'rgba(var(--v-theme-on-surface), 0.08)',
          '--pos-choice-disabled-text': 'rgba(var(--v-theme-on-surface), 0.38)',
          '--pos-neutral-bg': 'rgba(var(--v-theme-on-surface), 0.08)',
          '--pos-blocked-bg': 'rgba(var(--v-theme-on-surface), 0.12)',
          '--pos-blocked-text': 'rgba(var(--v-theme-on-surface), 0.38)'
        }
      : {
          '--pos-pill-required-bg': '#FEF0C7',
          '--pos-pill-required-text': '#B54708',
          '--pos-pill-done-bg': '#ECFDF3',
          '--pos-pill-done-text': '#067647',
          '--pos-pill-optional-bg': '#F2F4F7',
          '--pos-pill-optional-text': '#667085',
          '--pos-choice-border': '#E4E7EC',
          '--pos-choice-text': '#344054',
          '--pos-choice-selected-border': '#0F6E75',
          '--pos-choice-selected-bg': '#E6F4F5',
          '--pos-choice-selected-text': '#0F6E75',
          '--pos-choice-disabled-bg': '#F9FAFB',
          '--pos-choice-disabled-border': '#F2F4F7',
          '--pos-choice-disabled-text': '#98A2B3',
          '--pos-neutral-bg': '#F2F4F7',
          '--pos-blocked-bg': '#E4E7EC',
          '--pos-blocked-text': '#667085'
        }
  })
</script>

<style scoped>
  .pos-customize {
    border-radius: 18px;
  }
  .pos-customize__header {
    position: relative;
    flex-shrink: 0;
  }
  .pos-customize__body {
    overflow-y: auto;
    min-height: 0;
    -webkit-overflow-scrolling: touch;
  }
  .pos-customize__footer {
    flex-grow: 0;
    gap: 10px;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .min-width-0 {
    min-width: 0;
  }

  /* ── Header ────────────────────────────────────────────────────────────── */
  .pos-thumb {
    width: 72px;
    height: 72px;
    border-radius: 14px;
    overflow: hidden;
    background: var(--pos-neutral-bg);
  }
  .pos-thumb__img {
    width: 100%;
    height: 100%;
  }
  .pos-thumb__placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .pos-name {
    font-size: 18px;
    font-weight: 700;
    line-height: 1.3;
  }
  .pos-name-km {
    font-size: 13px;
    color: rgba(var(--v-theme-on-surface), 0.6);
  }
  .pos-price {
    font-size: 14px;
    font-weight: 600;
    color: rgb(var(--v-theme-primary));
  }

  /* ── Icon button (close) ──────────────────────────────────────────────── */
  .pos-icon-btn {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    border: none;
    background: var(--pos-neutral-bg);
    color: rgba(var(--v-theme-on-surface), 0.7);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  }
  .pos-customize__close {
    position: absolute;
    top: 16px;
    right: 16px;
  }

  /* ── Group header ──────────────────────────────────────────────────────── */
  .pos-group-name {
    font-size: 15px;
    font-weight: 700;
  }
  .pos-group-name-km {
    font-size: 13px;
    color: rgba(var(--v-theme-on-surface), 0.6);
  }
  .pos-group-rule {
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.6);
    margin-top: 2px;
  }

  /* ── Status pill ───────────────────────────────────────────────────────── */
  .pos-pill {
    flex-shrink: 0;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.6;
    white-space: nowrap;
  }
  .pos-pill--required {
    background: var(--pos-pill-required-bg);
    color: var(--pos-pill-required-text);
  }
  .pos-pill--done {
    background: var(--pos-pill-done-bg);
    color: var(--pos-pill-done-text);
  }
  .pos-pill--optional {
    background: var(--pos-pill-optional-bg);
    color: var(--pos-pill-optional-text);
  }

  /* ── Choice chips ──────────────────────────────────────────────────────── */
  .pos-choices {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .pos-choice {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 14px;
    border-radius: 12px;
    border: 1px solid var(--pos-choice-border);
    background: transparent;
    color: var(--pos-choice-text);
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    text-align: start;
    cursor: pointer;
    transition:
      border-color 0.15s,
      background-color 0.15s,
      color 0.15s;
  }
  .pos-choice--selected {
    border-width: 2px;
    border-color: var(--pos-choice-selected-border);
    background: var(--pos-choice-selected-bg);
    color: var(--pos-choice-selected-text);
  }
  .pos-choice--disabled {
    background: var(--pos-choice-disabled-bg);
    border-color: var(--pos-choice-disabled-border);
    color: var(--pos-choice-disabled-text);
    cursor: not-allowed;
  }
  .pos-choice__mark {
    font-weight: 700;
  }
  .pos-choice__price {
    font-weight: 700;
    margin-inline-start: 2px;
  }
  .pos-choice__sold-out {
    margin-inline-start: 4px;
  }

  /* ── Custom number input ───────────────────────────────────────────────── */
  .pos-custom-row__label {
    font-size: 13px;
    color: rgba(var(--v-theme-on-surface), 0.7);
  }
  .pos-custom-row__suffix {
    font-size: 14px;
    color: rgba(var(--v-theme-on-surface), 0.7);
  }
  .pos-custom-input {
    width: 72px;
    height: 40px;
    padding: 0 8px;
    text-align: right;
    font-size: 16px;
    font-family: inherit;
    border-radius: 10px;
    border: 2px solid var(--pos-choice-border);
    background: transparent;
    color: var(--pos-choice-text);
  }
  .pos-custom-input:focus {
    outline: none;
    border-color: var(--pos-choice-selected-border);
  }
  .pos-custom-input--selected {
    border-color: var(--pos-choice-selected-border);
    background: var(--pos-choice-selected-bg);
    color: var(--pos-choice-selected-text);
  }

  /* ── Note button ───────────────────────────────────────────────────────── */
  .pos-note-btn {
    border: 1px dashed rgb(var(--v-theme-primary));
    background: transparent;
    color: rgb(var(--v-theme-primary));
  }

  /* ── Footer: stepper + add button ─────────────────────────────────────── */
  .pos-stepper {
    display: flex;
    align-items: center;
    height: 54px;
    border-radius: 14px;
    background: var(--pos-neutral-bg);
  }
  .pos-stepper__btn {
    width: 46px;
    height: 100%;
    border: none;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    color: inherit;
    cursor: pointer;
  }
  .pos-stepper__btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
  .pos-stepper__value {
    min-width: 28px;
    text-align: center;
    font-size: 17px;
    font-weight: 700;
  }
  .pos-add-btn {
    flex: 1;
    height: 54px;
    border-radius: 14px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 18px;
    font-size: 16px;
    font-weight: 700;
    font-family: inherit;
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
    cursor: pointer;
  }
  .pos-add-btn--blocked {
    background: var(--pos-blocked-bg);
    color: var(--pos-blocked-text);
    cursor: not-allowed;
  }
</style>
