<template>
  <v-card rounded="lg" border elevation="0" class="d-flex flex-column pos-cart">
    <div class="pa-3 d-flex align-center">
      <v-icon icon="mdi-cart-outline" class="me-2" />
      <span class="text-body-1 font-weight-bold">{{ $t('pos.cart.title') }}</span>
      <v-spacer />
      <v-btn
        v-if="items.length"
        size="small"
        variant="text"
        color="error"
        @click="$emit('clear')"
      >
        {{ $t('pos.cart.clear') }}
      </v-btn>
    </div>
    <v-divider />

    <div class="pos-cart__items flex-grow-1">
      <div v-if="!items.length" class="pos-cart__empty pa-6 text-center">
        <v-icon icon="mdi-cart-outline" size="40" color="grey-lighten-1" />
        <div class="text-body-2 font-weight-bold mt-3">
          {{ $t('pos.cart.empty_title') }}
        </div>
        <div class="text-caption text-medium-emphasis mt-1">
          {{ $t('pos.cart.empty_helper') }}
        </div>
        <kbd class="pos-cart__empty-hint mt-4">⌘K — {{ $t('pos.cart.empty_search_hint') }}</kbd>
      </div>

      <div
        v-for="item in items"
        :key="item.key"
        class="pos-cart__item px-3 py-2"
      >
        <div class="d-flex align-start">
          <div class="flex-grow-1">
            <div class="text-body-2 font-weight-medium">{{ item.name }}</div>
            <div v-if="item.variant_name || item.unit_label" class="text-caption text-medium-emphasis">
              {{ item.variant_name || item.unit_label }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ formatMoney(item.unit_price) }}
            </div>
          </div>
          <v-btn
            icon="mdi-close"
            size="x-small"
            variant="text"
            @click="$emit('remove', item.key)"
          />
        </div>

        <v-text-field
          v-if="showNotes"
          :model-value="item.notes"
          :placeholder="$t('pos.cart.notes_placeholder')"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          class="mt-1"
          @update:model-value="$emit('update-notes', item.key, $event)"
        />

        <div class="d-flex align-center justify-space-between mt-2">
          <div class="d-flex align-center gap-1">
            <v-btn
              icon="mdi-minus"
              size="x-small"
              variant="tonal"
              @click="$emit('update-qty', item.key, item.qty - 1)"
            />
            <span class="px-2 text-body-2">{{ item.qty }}</span>
            <v-btn
              icon="mdi-plus"
              size="x-small"
              variant="tonal"
              @click="$emit('update-qty', item.key, item.qty + 1)"
            />
          </div>
          <span class="text-body-2 font-weight-bold">
            {{ formatMoney(item.unit_price * item.qty) }}
          </span>
        </div>
      </div>
    </div>

    <v-divider />
    <div class="pa-3">
      <div class="d-flex justify-space-between text-body-1 mb-3">
        <span class="font-weight-medium">{{ $t('pos.cart.subtotal') }}</span>
        <span class="font-weight-bold">{{ formatMoney(subtotal) }}</span>
      </div>
      <v-btn
        color="primary"
        variant="flat"
        block
        rounded="lg"
        size="large"
        :disabled="!items.length"
        @click="$emit('checkout')"
      >
        <v-icon icon="mdi-credit-card-outline" class="me-2" />
        <span class="flex-grow-1 text-start">{{ $t('pos.cart.checkout') }}</span>
        <span v-if="items.length" class="font-weight-black">{{ formatMoney(subtotal) }}</span>
      </v-btn>
    </div>
  </v-card>
</template>

<script setup>
  defineProps({
    items: { type: Array, default: () => [] },
    subtotal: { type: Number, default: 0 },
    showNotes: { type: Boolean, default: false }
  })

  defineEmits(['update-qty', 'update-notes', 'remove', 'clear', 'checkout'])

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value ?? 0)
  }
</script>

<style scoped>
  .pos-cart {
    position: sticky;
    top: 12px;
    max-height: calc(100vh - 140px);
  }
  .pos-cart__items {
    overflow-y: auto;
  }
  .pos-cart__item + .pos-cart__item {
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  }
  .pos-cart__empty-hint {
    display: inline-block;
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 6px;
    background: rgba(var(--v-theme-on-surface), 0.06);
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    color: rgba(var(--v-theme-on-surface), 0.6);
  }
</style>
