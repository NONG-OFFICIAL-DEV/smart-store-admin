<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="480"
    :fullscreen="xs"
    scrollable
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card v-if="product" rounded="lg" class="pos-customize d-flex flex-column">
      <!-- Image + close button + name/price — always visible, never scrolls. -->
      <div class="pos-customize__media">
        <v-img
          v-if="product.image_url"
          :src="product.image_url"
          height="180"
          cover
          class="bg-grey-lighten-4"
        />
        <div v-else class="pos-customize__media-placeholder">
          <v-icon icon="mdi-image-off-outline" size="40" color="grey" />
        </div>
        <v-btn
          icon="mdi-close"
          size="small"
          variant="flat"
          color="surface"
          class="pos-customize__close"
          @click="$emit('update:modelValue', false)"
        />
      </div>

      <div class="px-4 pt-3 pb-1 flex-grow-0">
        <div class="text-h6 font-weight-bold">{{ product.name }}</div>
        <div class="text-body-2 text-medium-emphasis">{{ formatMoney(product.base_price) }}</div>
      </div>

      <v-divider class="mt-2" />

      <!-- Only the sections this product is actually configured for. -->
      <div class="pos-customize__body flex-grow-1 px-4 py-3">
        <div v-if="sizeOptions.length > 1" class="mb-4">
          <div class="text-subtitle-2 font-weight-bold mb-2">{{ $t('pos.customize.size') }}</div>
          <v-chip-group v-model="variantId" mandatory="force" selected-class="text-primary" column>
            <v-chip
              v-for="opt in sizeOptions"
              :key="opt.variant_id"
              :value="opt.variant_id"
              filter
              variant="tonal"
              rounded="lg"
              size="large"
              class="pos-customize__chip"
            >
              {{ opt.label }}
              <template v-if="opt.price - product.base_price !== 0">
                &nbsp;· {{ formatMoney(opt.price) }}
              </template>
            </v-chip>
          </v-chip-group>
        </div>

        <div v-for="group in product.modifier_groups" :key="group.id" class="mb-4">
          <div class="d-flex align-center mb-2 ga-2">
            <span class="text-subtitle-2 font-weight-bold">{{ group.name }}</span>
            <v-chip v-if="group.is_required" size="x-small" color="error" variant="tonal">
              {{ $t('pos.customize.required') }}
            </v-chip>
          </div>

          <v-chip-group
            v-if="group.selection_type === 'single'"
            :model-value="selections[group.id]"
            :mandatory="group.is_required"
            selected-class="text-primary"
            column
            @update:model-value="selections[group.id] = $event"
          >
            <v-chip
              v-for="opt in group.options"
              :key="opt.id"
              :value="opt.id"
              filter
              variant="tonal"
              rounded="lg"
              size="large"
              class="pos-customize__chip"
            >
              {{ opt.name }}
              <template v-if="opt.price_adjustment">
                &nbsp;· +{{ formatMoney(opt.price_adjustment) }}
              </template>
            </v-chip>
          </v-chip-group>

          <div v-else class="d-flex flex-wrap ga-2">
            <v-chip
              v-for="opt in group.options"
              :key="opt.id"
              :model-value="isMultiSelected(group, opt.id)"
              :filter="isMultiSelected(group, opt.id)"
              :variant="isMultiSelected(group, opt.id) ? 'flat' : 'tonal'"
              :color="isMultiSelected(group, opt.id) ? 'primary' : undefined"
              :disabled="!isMultiSelected(group, opt.id) && isGroupFull(group)"
              rounded="lg"
              size="large"
              class="pos-customize__chip"
              @click="toggleMultiOption(group, opt.id)"
            >
              {{ opt.name }}
              <template v-if="opt.price_adjustment">
                &nbsp;· +{{ formatMoney(opt.price_adjustment) }}
              </template>
            </v-chip>
          </div>
        </div>

        <div v-if="!noteOpen" class="mb-1">
          <v-btn variant="text" size="small" prepend-icon="mdi-note-plus-outline" class="pl-0" @click="noteOpen = true">
            {{ $t('pos.customize.note_action') }}
          </v-btn>
        </div>
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
          autofocus
          class="mb-1"
        />
      </div>

      <v-divider />

      <!-- Quantity + primary action — always visible, never scrolls. -->
      <div class="pa-4 flex-grow-0">
        <div class="d-flex align-center justify-center ga-4 mb-3">
          <v-btn
            icon="mdi-minus"
            size="large"
            variant="tonal"
            rounded="lg"
            @click="quantity = Math.max(1, quantity - 1)"
          />
          <span class="text-h5 font-weight-bold text-center" style="min-width: 2.5em">{{ quantity }}</span>
          <v-btn icon="mdi-plus" size="large" variant="tonal" rounded="lg" @click="quantity++" />
        </div>

        <v-btn
          color="primary"
          variant="flat"
          block
          size="x-large"
          rounded="lg"
          class="text-none"
          :disabled="!canAdd"
          @click="onAdd"
        >
          <span class="flex-grow-1 text-start">{{ $t('pos.customize.add_to_order') }}</span>
          <span class="font-weight-black">{{ formatMoney(totalPrice) }}</span>
        </v-btn>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useDisplay } from 'vuetify'

  const { xs } = useDisplay()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    product: { type: Object, default: null }
  })

  const emit = defineEmits(['update:modelValue', 'add'])

  const variantId = ref(null)
  const selections = ref({})
  const quantity = ref(1)
  const note = ref('')
  const noteOpen = ref(false)

  const sizeOptions = computed(() => props.product?.options ?? [])

  function resetState() {
    const opts = sizeOptions.value
    variantId.value = opts.length
      ? (opts.find(o => o.is_default)?.variant_id ?? opts[0].variant_id)
      : null

    const nextSelections = {}
    for (const group of props.product?.modifier_groups ?? []) {
      if (group.selection_type === 'multiple') {
        nextSelections[group.id] = []
      } else {
        const firstAvailable = group.options.find(o => o.is_available !== false)
        nextSelections[group.id] = group.is_required ? (firstAvailable?.id ?? null) : null
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

  const selectedVariantOption = computed(() =>
    sizeOptions.value.find(o => o.variant_id === variantId.value) ?? null
  )

  const modifiersTotal = computed(() => {
    let total = 0
    for (const group of props.product?.modifier_groups ?? []) {
      const sel = selections.value[group.id]
      const ids = group.selection_type === 'multiple' ? sel ?? [] : sel ? [sel] : []
      for (const id of ids) {
        const opt = group.options.find(o => o.id === id)
        if (opt) total += Number(opt.price_adjustment ?? 0)
      }
    }
    return total
  })

  const unitPrice = computed(() => {
    const base = Number(props.product?.base_price ?? 0)
    const variantPrice = selectedVariantOption.value ? Number(selectedVariantOption.value.price) : base
    return variantPrice + modifiersTotal.value
  })

  const totalPrice = computed(() => unitPrice.value * quantity.value)

  const canAdd = computed(() => {
    for (const group of props.product?.modifier_groups ?? []) {
      if (!group.is_required) continue
      const sel = selections.value[group.id]
      if (group.selection_type === 'multiple') {
        if ((sel?.length ?? 0) < (group.min_selections || 1)) return false
      } else if (!sel) {
        return false
      }
    }
    return true
  })

  function onAdd() {
    const customizations = []
    const modifierOptionIds = []
    for (const group of props.product?.modifier_groups ?? []) {
      const sel = selections.value[group.id]
      const ids = group.selection_type === 'multiple' ? sel ?? [] : sel ? [sel] : []
      for (const id of ids) {
        const opt = group.options.find(o => o.id === id)
        if (!opt) continue
        customizations.push({ label: group.name, value: opt.name })
        modifierOptionIds.push(opt.id)
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
      modifier_option_ids: modifierOptionIds
    })
    emit('update:modelValue', false)
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value ?? 0)
  }
</script>

<style scoped>
  .pos-customize {
    max-height: 90vh;
  }
  .pos-customize__media {
    position: relative;
    flex-shrink: 0;
  }
  .pos-customize__media-placeholder {
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--v-theme-on-surface), 0.04);
  }
  .pos-customize__close {
    position: absolute;
    top: 12px;
    right: 12px;
    opacity: 0.92;
  }
  .pos-customize__body {
    overflow-y: auto;
    min-height: 0;
    -webkit-overflow-scrolling: touch;
  }
  .pos-customize__chip {
    min-height: 44px;
  }
</style>
