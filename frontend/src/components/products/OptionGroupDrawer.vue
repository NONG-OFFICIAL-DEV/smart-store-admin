<template>
  <AppDrawer
    :model-value="modelValue"
    :loading="loading"
    :persistent="loading"
    :confirm-close="() => confirmDiscard(isDirty)"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #title>
      {{ isEdit ? t('modifiers.drawer.edit_title') : t('modifiers.drawer.add_title') }}
    </template>
    <template #subtitle>{{ t('modifiers.drawer.subtitle') }}</template>

    <v-alert
      v-if="formError"
      type="error"
      variant="tonal"
      density="compact"
      rounded="lg"
      class="mb-4"
      :text="formError"
    />

    <!-- 1. Name EN/KM -->
    <v-row dense class="mb-2 py-2">
      <v-col cols="12" sm="6">
        <v-text-field
          v-model="form.name"
          :label="t('modifiers.drawer.name_label') + ' *'"
          variant="outlined"
          rounded="lg"
          density="comfortable"
          hide-details
          maxlength="100"
        />
      </v-col>
      <v-col cols="12" sm="6">
        <v-text-field
          v-model="form.name_km"
          :label="t('modifiers.drawer.name_km_label')"
          variant="outlined"
          rounded="lg"
          density="comfortable"
          hide-details
          maxlength="100"
        />
      </v-col>
    </v-row>

    <!-- 2. How many can staff pick -->
    <div class="text-body-2 font-weight-medium mt-4 mb-2">{{ t('modifiers.drawer.pick_label') }}</div>
    <v-row dense>
      <v-col cols="6">
        <v-card
          variant="outlined"
          rounded="lg"
          class="pa-3 pick-card"
          :class="form.selection_type === 'single' ? 'pick-card--active' : ''"
          @click="form.selection_type = 'single'"
        >
          <div class="d-flex align-center ga-2 mb-1">
            <v-icon
              :icon="form.selection_type === 'single' ? 'mdi-radiobox-marked' : 'mdi-radiobox-blank'"
              color="primary"
              size="20"
            />
            <span class="text-body-2 font-weight-medium">{{ t('modifiers.drawer.pick_one') }}</span>
          </div>
          <div class="text-caption text-medium-emphasis">{{ t('modifiers.drawer.pick_one_hint') }}</div>
        </v-card>
      </v-col>
      <v-col cols="6">
        <v-card
          variant="outlined"
          rounded="lg"
          class="pa-3 pick-card"
          :class="form.selection_type === 'multiple' ? 'pick-card--active' : ''"
          @click="form.selection_type = 'multiple'"
        >
          <div class="d-flex align-center ga-2 mb-1">
            <v-icon
              :icon="form.selection_type === 'multiple' ? 'mdi-checkbox-marked' : 'mdi-checkbox-blank-outline'"
              color="primary"
              size="20"
            />
            <span class="text-body-2 font-weight-medium">{{ t('modifiers.drawer.pick_several') }}</span>
          </div>
          <div class="text-caption text-medium-emphasis">{{ t('modifiers.drawer.pick_several_hint') }}</div>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="form.selection_type === 'multiple'" class="d-flex align-center justify-space-between mt-3 mb-2 px-1">
      <span class="text-body-2">{{ t('modifiers.drawer.up_to_label') }}</span>
      <div class="d-flex align-center ga-2">
        <v-btn
          icon="mdi-minus"
          size="x-small"
          variant="tonal"
          :disabled="form.max_selections <= 2"
          @click="form.max_selections = Math.max(2, form.max_selections - 1)"
        />
        <span class="text-body-1 font-weight-bold text-center" style="min-width: 1.5em">
          {{ form.max_selections }}
        </span>
        <v-btn
          icon="mdi-plus"
          size="x-small"
          variant="tonal"
          :disabled="form.max_selections >= 10"
          @click="form.max_selections = Math.min(10, form.max_selections + 1)"
        />
        <span class="text-caption text-medium-emphasis">{{ t('modifiers.drawer.options_suffix') }}</span>
      </div>
    </div>

    <v-divider class="my-4" />

    <!-- 3. Required -->
    <div class="d-flex align-center justify-space-between pa-3 rounded-lg bg-surface-light mb-3">
      <div>
        <div class="text-body-2 font-weight-medium">{{ t('modifiers.group.required_toggle') }}</div>
        <div class="text-caption text-medium-emphasis">{{ t('modifiers.drawer.required_hint') }}</div>
      </div>
      <v-switch v-model="form.is_required" color="primary" hide-details inset />
    </div>

    <!-- 4. Allow custom number — Pick one only -->
    <div
      v-if="form.selection_type === 'single'"
      class="d-flex align-center justify-space-between pa-3 rounded-lg bg-surface-light mb-4"
    >
      <div>
        <div class="text-body-2 font-weight-medium">{{ t('modifiers.drawer.allow_custom_label') }}</div>
        <div class="text-caption text-medium-emphasis">{{ t('modifiers.drawer.allow_custom_hint') }}</div>
      </div>
      <v-switch v-model="form.allow_custom_value" color="primary" hide-details inset />
    </div>

    <!-- 5. Choices — inline, no separate dialog -->
    <div class="text-body-2 font-weight-medium mb-2">{{ t('modifiers.drawer.choices_label') }}</div>

    <div class="d-flex align-center ga-2 mb-1 px-1 choice-header">
      <span class="choice-drag-spacer" />
      <span class="choice-name text-caption text-medium-emphasis font-weight-medium">
        {{ t('modifiers.drawer.choice_header_name') }}
      </span>
      <span class="choice-price text-caption text-medium-emphasis font-weight-medium">
        {{ t('modifiers.drawer.choice_header_price') }}
      </span>
      <span class="choice-flag text-caption text-medium-emphasis font-weight-medium">
        {{ t('modifiers.drawer.choice_header_default') }}
      </span>
      <span class="choice-flag text-caption text-medium-emphasis font-weight-medium">
        {{ t('modifiers.drawer.choice_header_available') }}
      </span>
      <span class="choice-delete-spacer" />
    </div>

    <draggable v-model="form.options" item-key="_key" handle=".choice-drag" tag="div">
      <template #item="{ element: choice, index }">
        <div
          class="d-flex align-center ga-2 mb-2"
          :class="choice.is_available === false ? 'opacity-60' : ''"
        >
          <v-icon icon="mdi-drag-vertical" class="choice-drag" style="cursor: grab" size="20" />
          <v-text-field
            :ref="el => setChoiceRef(el, choice._key)"
            v-model="choice.name"
            :placeholder="t('modifiers.drawer.choice_name_placeholder')"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            class="choice-name"
          />
          <v-text-field
            v-model="choice.price_adjustment"
            type="text"
            inputmode="decimal"
            prefix="+$"
            placeholder="0.00"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            class="choice-price"
          />
          <v-radio
            v-if="form.selection_type === 'single'"
            :model-value="choice.is_default"
            :value="true"
            color="primary"
            density="compact"
            hide-details
            class="choice-flag"
            @click="setOnlyDefault(index)"
          />
          <v-checkbox-btn
            v-else
            v-model="choice.is_default"
            color="primary"
            density="compact"
            hide-details
            class="choice-flag"
          />
          <v-switch v-model="choice.is_available" color="primary" density="compact" hide-details inset class="choice-flag" />
          <v-btn
            icon="mdi-delete-outline"
            size="small"
            variant="text"
            color="error"
            @click="removeChoice(index)"
          />
        </div>
      </template>
    </draggable>

    <v-btn
      variant="tonal"
      rounded="lg"
      class="text-none mb-3"
      prepend-icon="mdi-plus"
      @click="addChoice"
    >
      {{ t('modifiers.drawer.add_choice') }}
    </v-btn>

    <!-- Live, not tied to attempting Save — the moment the last
         available choice of a required group gets switched off. -->
    <v-alert
      v-if="requiredHasNoAvailableChoice"
      type="warning"
      variant="tonal"
      density="compact"
      rounded="lg"
      class="mb-5"
      :text="t('modifiers.drawer.error_required_needs_available_choice')"
    />

    <!-- 6. Preview -->
    <div class="text-body-2 font-weight-medium mb-2">{{ t('modifiers.drawer.preview_label') }}</div>
    <v-sheet rounded="lg" class="pa-3 bg-surface-light">
      <div class="d-flex align-center ga-2 mb-2 flex-wrap">
        <span class="font-weight-medium">{{ form.name || t('modifiers.drawer.untitled') }}</span>
        <v-chip size="x-small" variant="tonal" color="primary">{{ previewRuleText }}</v-chip>
      </div>
      <div class="d-flex flex-wrap ga-1">
        <v-chip
          v-for="choice in form.options"
          :key="choice._key"
          size="small"
          :variant="choice.is_default ? 'flat' : 'tonal'"
          :color="choice.is_default ? 'primary' : undefined"
        >
          {{ choice.name || t('modifiers.drawer.untitled_choice') }}
          <template v-if="Number(choice.price_adjustment)">
            &nbsp;· {{ formatPriceChip(choice.price_adjustment) }}
          </template>
        </v-chip>
        <v-chip
          v-if="form.selection_type === 'single' && form.allow_custom_value"
          size="small"
          variant="tonal"
          color="secondary"
        >
          {{ t('modifiers.drawer.custom_chip', { suffix: form.custom_suffix }) }}
        </v-chip>
      </div>
    </v-sheet>

    <template #footer>
      <v-btn
        variant="tonal"
        rounded="lg"
        class="flex-grow-1 text-none"
        :disabled="loading"
        @click="close"
      >
        {{ t('btn.cancel') }}
      </v-btn>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        class="flex-grow-1 text-none"
        :loading="loading"
        :disabled="!!liveValidationError"
        @click="submit"
      >
        {{ t('modifiers.drawer.save_group') }}
      </v-btn>
    </template>
  </AppDrawer>
</template>

<script setup>
  import { ref, computed, watch, nextTick } from 'vue'
  import { useI18n } from 'vue-i18n'
  import draggable from 'vuedraggable'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'
  import { useSettingsForm } from '@/composables/useSettingsForm.js'
  import { useConfirmDiscard } from '@/composables/useConfirmDiscard.js'
  import AppDrawer from '@/components/common/AppDrawer.vue'

  const { t } = useI18n()
  const { format: formatMoney } = useCurrency()
  const { isTouch } = useTouchDevice()
  const { confirmDiscard } = useConfirmDiscard()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    group: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const isEdit = computed(() => !!props.group?.id)
  const formError = ref(null)

  let keyCounter = 0
  const makeKey = () => `c${Date.now()}_${keyCounter++}`

  const defaultForm = () => ({
    name: '',
    name_km: '',
    selection_type: 'single',
    max_selections: 3,
    is_required: false,
    allow_custom_value: false,
    custom_suffix: '%',
    options: []
  })

  const { form, isDirty, load, commit } = useSettingsForm(defaultForm())

  watch(
    () => props.modelValue,
    open => {
      if (!open) return
      formError.value = null
      if (props.group) {
        load({
          ...defaultForm(),
          name: props.group.name ?? '',
          name_km: props.group.name_km ?? '',
          selection_type: props.group.selection_type ?? 'single',
          max_selections: props.group.max_selections ?? 3,
          is_required: props.group.is_required ?? false,
          allow_custom_value: props.group.allow_custom_value ?? false,
          custom_suffix: props.group.custom_suffix || '%',
          options: (props.group.options ?? []).map(o => ({
            _key: makeKey(),
            id: o.id,
            name: o.name,
            price_adjustment: Number(o.price_adjustment ?? 0),
            is_available: o.is_available !== false,
            is_default: !!o.is_default
          }))
        })
      } else {
        load(defaultForm())
      }
    },
    { immediate: true }
  )

  // ── Choices ───────────────────────────────────────────────────────────────
  const choiceInputRefs = {}
  function setChoiceRef(el, key) {
    if (el) choiceInputRefs[key] = el
    else delete choiceInputRefs[key]
  }

  function addChoice() {
    const key = makeKey()
    form.options.push({
      _key: key,
      id: null,
      name: '',
      price_adjustment: '0',
      is_available: true,
      is_default: false
    })
    if (!isTouch.value) {
      nextTick(() => choiceInputRefs[key]?.focus?.())
    }
  }

  function removeChoice(index) {
    form.options.splice(index, 1)
  }

  // Pick one — at most one default, so setting one clears every other row.
  function setOnlyDefault(index) {
    form.options.forEach((o, i) => {
      o.is_default = i === index
    })
  }

  function formatPriceChip(value) {
    const n = Number(value) || 0
    return `${n >= 0 ? '+' : ''}${formatMoney(n)}`
  }

  const previewRuleText = computed(() => {
    if (form.selection_type === 'single') return t('modifiers.options_tab.rule_pick_one')
    return t('modifiers.options_tab.rule_pick_up_to', { n: form.max_selections })
  })

  // ── Validation ────────────────────────────────────────────────────────────
  // Whether a required group still has at least one available choice —
  // shared by the live warning banner (section 1 of the spec: shown the
  // moment the last available choice gets toggled off) and validate()'s
  // blocking check (same condition, just surfaced differently).
  const requiredHasNoAvailableChoice = computed(() => {
    if (!form.is_required || !form.options.length) return false
    return !form.options.some(o => o.is_available !== false)
  })

  function validate() {
    if (!form.name?.trim()) return t('modifiers.drawer.error_name_required')
    if (!form.options.length) return t('modifiers.drawer.error_need_one_choice')
    if (form.options.some(o => !o.name?.trim())) return t('modifiers.drawer.error_choice_name_required')

    const names = form.options.map(o => o.name.trim().toLowerCase())
    if (new Set(names).size !== names.length) return t('modifiers.drawer.error_choice_name_unique')

    if (form.selection_type === 'single') {
      const defaults = form.options.filter(o => o.is_default).length
      if (defaults > 1) return t('modifiers.drawer.error_one_default')
    }

    if (form.selection_type === 'multiple' && form.max_selections > form.options.length) {
      return t('modifiers.drawer.error_max_too_high')
    }

    if (requiredHasNoAvailableChoice.value) {
      return t('modifiers.drawer.error_required_needs_available_choice')
    }

    return null
  }

  // Live (not submit-triggered) — drives the Save button's disabled state so
  // an invalid group can never be saved in the first place.
  const liveValidationError = computed(() => validate())

  // ── Save / close ──────────────────────────────────────────────────────────
  // The Cancel button emits update:modelValue directly (bypassing
  // AppDrawer's own X/scrim/Esc handling entirely), so it needs the same
  // confirm-before-discard gate applied here explicitly.
  async function close() {
    if (props.loading) return
    if (!(await confirmDiscard(isDirty.value))) return
    emit('update:modelValue', false)
  }

  async function submit() {
    const err = validate()
    if (err) {
      formError.value = err
      return
    }
    formError.value = null

    const payload = {
      ...(isEdit.value ? { id: props.group.id } : {}),
      name: form.name.trim(),
      name_km: form.name_km?.trim() || null,
      selection_type: form.selection_type,
      max_selections: form.selection_type === 'multiple' ? form.max_selections : null,
      is_required: form.is_required,
      allow_custom_value: form.selection_type === 'single' ? form.allow_custom_value : false,
      custom_suffix: form.selection_type === 'single' && form.allow_custom_value ? form.custom_suffix : null,
      options: form.options.map((o, i) => ({
        ...(o.id ? { id: o.id } : {}),
        name: o.name.trim(),
        price_adjustment: Number(o.price_adjustment) || 0,
        is_available: o.is_available !== false,
        is_default: !!o.is_default,
        sort_order: i
      }))
    }

    await new Promise((resolve, reject) => emit('saved', payload, { resolve, reject }))
      .then(() => {
        commit()
        emit('update:modelValue', false)
      })
      .catch(error => {
        const serverErrors = error?.response?.data?.errors
        formError.value = serverErrors
          ? Object.values(serverErrors).flat().join(' ')
          : null
      })
  }
</script>

<style scoped>
  .pick-card {
    cursor: pointer;
    transition:
      border-color 0.15s,
      background-color 0.15s;
  }
  .pick-card--active {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.08);
  }
  .choice-name {
    flex: 2;
    min-width: 0;
  }
  .choice-price {
    flex: 0 0 100px;
  }
  .choice-flag {
    flex: 0 0 auto;
  }
  .choice-header {
    padding-inline-start: 2px;
  }
  .choice-drag-spacer {
    width: 20px;
    flex: 0 0 20px;
  }
  .choice-delete-spacer {
    width: 40px;
    flex: 0 0 40px;
  }
</style>
