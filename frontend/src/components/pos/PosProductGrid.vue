<template>
  <div class="pos-product-grid d-flex flex-column" :style="paletteStyle">
    <v-text-field
      ref="searchFieldRef"
      :model-value="search"
      :placeholder="$t('pos.grid.search_placeholder')"
      prepend-inner-icon="mdi-magnify"
      variant="outlined"
      :density="touch ? 'comfortable' : 'compact'"
      rounded="lg"
      hide-details
      clearable
      type="search"
      enterkeyhint="search"
      autocapitalize="off"
      autocorrect="off"
      spellcheck="false"
      class="flex-grow-0 flex-shrink-0 mb-2"
      @update:model-value="$emit('update:search', $event ?? '')"
      @keydown.enter="$event.target.blur()"
    >
      <template #append-inner>
        <kbd v-if="!touch" class="search-shortcut-hint">⌘K</kbd>
      </template>
    </v-text-field>

    <!-- Directly tappable — no dropdown to open first, one tap picks a
         category (touch-friendlier than a <select>-style control). Custom
         markup, not v-chip-group — the round icon badge doesn't map onto
         v-chip's own slots. -->
    <div class="pos-pill-row flex-grow-0 flex-shrink-0 mb-2" role="tablist">
      <button
        type="button"
        class="pos-pill"
        :class="{ 'pos-pill--selected': !categoryId }"
        role="tab"
        :aria-pressed="!categoryId"
        @click="$emit('update:categoryId', null)"
      >
        <span class="pos-pill__icon"><v-icon icon="mdi-view-grid-outline" size="18" /></span>
        <span class="pos-pill__name">{{ $t('pos.grid.all_categories') }}</span>
      </button>
      <button
        v-for="cat in categories"
        :key="cat.id"
        type="button"
        class="pos-pill"
        :class="{ 'pos-pill--selected': categoryId === cat.id }"
        role="tab"
        :aria-pressed="categoryId === cat.id"
        @click="$emit('update:categoryId', cat.id)"
      >
        <span class="pos-pill__icon">
          <v-icon :icon="cat.icon || 'mdi-silverware-fork-knife'" size="18" />
        </span>
        <span class="pos-pill__name">{{ cat.name }}</span>
      </button>
    </div>

    <div class="pos-product-grid__scroll flex-grow-1">
      <!-- Skeleton placeholders — same column layout as the real grid
           below, so nothing shifts once products actually arrive. Shown
           for every fetch (initial load, branch switch, search), not just
           the first one, so a fast re-search never flashes "No products
           found" before the new results land. -->
      <v-row v-if="loading" density="compact">
        <v-col v-for="n in 10" :key="`skeleton-${n}`" cols="6" sm="4" md="3" lg="2">
          <div class="pos-card pos-card--skeleton">
            <div class="pos-card__image-wrap">
              <v-skeleton-loader type="image" class="pos-card__skeleton-fill" />
            </div>
            <div class="pos-card__body">
              <v-skeleton-loader type="text" />
              <v-skeleton-loader type="text" width="60%" />
            </div>
          </div>
        </v-col>
      </v-row>

      <v-alert
        v-else-if="!products.length"
        type="info"
        variant="tonal"
        rounded="lg"
      >
        {{ $t('pos.grid.no_products') }}
      </v-alert>

      <v-row v-else density="compact">
        <v-col v-for="product in products" :key="product.id" cols="6" sm="4" md="3" lg="2">
          <div
            class="pos-card"
            :class="{
              'pos-card--selected': quantityFor(product) > 0,
              'pos-card--disabled': isDisabled(product)
            }"
            role="button"
            :tabindex="isDisabled(product) ? -1 : 0"
            :aria-disabled="isDisabled(product)"
            :aria-label="cardAriaLabel(product)"
            @click="handleCardClick(product)"
            @keydown.enter.prevent="handleCardClick(product)"
            @keydown.space.prevent="handleCardClick(product)"
          >
            <div class="pos-card__image-wrap">
              <v-img
                v-if="product.image_url"
                :src="product.image_url"
                :aspect-ratio="4 / 3"
                :cover="product.image_fit !== 'contain'"
                class="pos-card__image"
                :class="{ 'pos-card__image--disabled': isDisabled(product) }"
              >
                <template #placeholder>
                  <div class="pos-card__image-fallback d-flex align-center justify-center">
                    <v-progress-circular indeterminate size="18" width="2" color="grey" />
                  </div>
                </template>
                <template #error>
                  <div class="pos-card__image-fallback d-flex align-center justify-center">
                    <v-icon icon="mdi-food" size="32" color="grey" />
                  </div>
                </template>
              </v-img>
              <div v-else class="pos-card__image-fallback d-flex align-center justify-center">
                <v-icon icon="mdi-food" size="32" color="grey" />
              </div>

              <span v-if="quantityFor(product) > 0" class="pos-card__qty-badge">
                {{ quantityFor(product) }}
              </span>

              <template v-if="topLeftBadge(product)">
                <v-tooltip
                  v-if="topLeftBadge(product).tooltip"
                  :text="topLeftBadge(product).tooltip"
                  location="bottom"
                >
                  <template #activator="{ props: tip }">
                    <span v-bind="tip" class="pos-card__pill pos-card__pill--warn">
                      {{ topLeftBadge(product).text }}
                    </span>
                  </template>
                </v-tooltip>
                <span v-else class="pos-card__pill pos-card__pill--warn">
                  {{ topLeftBadge(product).text }}
                </span>
              </template>

              <span v-if="!isDisabled(product) && needsCustomize(product)" class="pos-card__pill pos-card__pill--options">
                {{ $t('pos.grid.options_badge') }}
              </span>
            </div>

            <div class="pos-card__body">
              <div class="pos-card__name">{{ product.name }}</div>
              <div class="pos-card__price">{{ priceText(product) }}</div>
            </div>
          </div>
        </v-col>
      </v-row>
    </div>
  </div>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useDisplay, useTheme } from 'vuetify'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'

  const { mdAndDown: touch } = useDisplay()
  const { t } = useI18n()
  const theme = useTheme()
  const { format: formatMoney } = useCurrency()

  const props = defineProps({
    products: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    categoryId: { type: String, default: null },
    search: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    // product_id -> qty currently in the cart — drives the teal qty badge
    // and the selected (teal 2px border) state on each card.
    cartQuantities: { type: Object, default: () => ({}) }
  })

  const emit = defineEmits(['add', 'customize', 'update:search', 'update:categoryId'])

  const searchFieldRef = ref(null)
  defineExpose({
    focus: () => searchFieldRef.value?.focus()
  })

  function needsCustomize(product) {
    return product.options.length > 1 || (product.modifier_groups?.length ?? 0) > 0
  }

  function fromPrice(product) {
    return Math.min(...product.options.map(o => o.price))
  }

  // A required group with zero available choices can never be satisfied —
  // opening the dialog would just present a permanently-disabled "Add to
  // order" with no way out. `.every()` on an empty options array is
  // vacuously true, so this also catches a required group saved with no
  // choices at all, not just one that's since sold out entirely.
  function blockingRequiredGroup(product) {
    return (product.modifier_groups ?? []).find(
      g => g.is_required && g.options.every(o => o.is_available === false)
    ) ?? null
  }

  function quantityFor(product) {
    return props.cartQuantities[product.id] ?? 0
  }

  // Retail's "no stock left" and Food's "required modifier group has
  // nothing available" are the only two ways a product can be unorderable
  // today — they never co-occur on the same product (stock is a Retail-only
  // concept, modifier groups a Food/coffee-only one), so topLeftBadge()
  // below never has to pick between them.
  function isDisabled(product) {
    return product.stockStatus === 'out_of_stock' || !!blockingRequiredGroup(product)
  }

  function topLeftBadge(product) {
    if (product.stockStatus === 'out_of_stock') {
      return { text: t('pos.grid.sold_out'), tooltip: null }
    }
    const blocker = blockingRequiredGroup(product)
    if (blocker) {
      return { text: t('pos.grid.unavailable'), tooltip: t('pos.grid.unavailable_reason', { name: blocker.name }) }
    }
    return null
  }

  // "From {min}" whenever a product has more than one priced option (sizes/
  // units) — never the bare (possibly 0) single-option price for those.
  function priceText(product) {
    return product.options.length > 1
      ? `${t('pos.grid.from_price')} ${formatMoney(fromPrice(product))}`
      : formatMoney(product.options[0]?.price)
  }

  function cardAriaLabel(product) {
    return t('pos.grid.card_aria_label', {
      name: product.name,
      price: priceText(product),
      qty: quantityFor(product)
    })
  }

  function handleCardClick(product) {
    if (isDisabled(product)) return
    if (needsCustomize(product)) {
      emit('customize', product)
    } else {
      emit('add', { product, option: product.options[0] })
    }
  }

  // ── Light/dark palette ────────────────────────────────────────────────────
  // Same approach as PosProductCustomizeDialog.vue — CSS custom properties
  // driven by the app's real theme state, light values taken verbatim from
  // the design spec, dark values reusing this app's own theme tokens where
  // the spec didn't give one.
  const paletteStyle = computed(() => {
    const dark = theme.global.current.value.dark
    return dark
      ? {
          '--pos-card-border': 'rgba(var(--v-theme-on-surface), 0.16)',
          '--pos-card-border-hover': 'rgba(var(--v-theme-on-surface), 0.28)',
          '--pos-card-image-bg': 'rgba(var(--v-theme-on-surface), 0.06)',
          '--pos-card-warn-bg': 'rgba(var(--v-theme-error), 0.2)',
          '--pos-card-warn-text': 'rgb(var(--v-theme-error))',
          '--pos-pill-text': 'rgba(var(--v-theme-on-surface), 0.9)',
          '--pos-pill-icon-bg': 'rgba(var(--v-theme-primary), 0.16)'
        }
      : {
          '--pos-card-border': '#E4E7EC',
          '--pos-card-border-hover': '#D0D5DD',
          '--pos-card-image-bg': '#F2F4F7',
          '--pos-pill-text': '#344054',
          '--pos-pill-icon-bg': '#E6F4F5',
          '--pos-card-warn-bg': 'rgba(var(--v-theme-error), 0.1)',
          '--pos-card-warn-text': 'rgb(var(--v-theme-error))'
        }
  })
</script>

<style scoped>
  .pos-product-grid {
    height: 100%;
    min-height: 0;
  }
  .pos-product-grid__scroll {
    overflow-y: auto;
    min-height: 0;
    /* Native momentum scrolling on touch devices. */
    -webkit-overflow-scrolling: touch;
  }
  /* ── Card ──────────────────────────────────────────────────────────────── */
.pos-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 10px;                       /* NEW: gives the inset image look */
  border-radius: 16px;
  border: 1px solid var(--pos-card-border);
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 1px 3px rgba(16, 24, 40, 0.08);   /* NEW: soft shadow */
  overflow: hidden;
  cursor: pointer;
  outline: none;
  transition:
    border-color 0.15s,
    box-shadow 0.15s,
    transform 0.1s;
}
.pos-card:hover {
  border-color: var(--pos-card-border-hover);
  box-shadow: 0 4px 12px rgba(16, 24, 40, 0.1);
}

  .pos-card:active {
    transform: scale(0.98);
  }
  .pos-card:focus-visible {
    border-color: rgb(var(--v-theme-primary));
  }
  .pos-card--selected {
    border-width: 2px;
    border-color: rgb(var(--v-theme-primary));
  }
  .pos-card--disabled {
    cursor: not-allowed;
  }
  .pos-card--disabled:active {
    transform: none;
  }

  /* ── Image area ────────────────────────────────────────────────────────── */
  .pos-card__image-wrap {
    position: relative;
    aspect-ratio: 4 / 3;
    width: 100%;
    overflow: hidden;
    border-radius: 12px;    
    background: var(--pos-card-image-bg);
    flex-shrink: 0;
  }
  .pos-card__image {
    width: 100%;
    height: 100%;
  }
  .pos-card__image--disabled :deep(img) {
    filter: grayscale(1);
    opacity: 0.55;
  }
  .pos-card__image-fallback {
    width: 100%;
    height: 100%;
  }

  /* Qty-in-order badge — teal circle, top-right. */
  .pos-card__qty-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    min-width: 26px;
    height: 26px;
    padding: 0 6px;
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
    border: 2px solid rgb(var(--v-theme-surface));
    font-size: 12px;
    font-weight: 700;
    line-height: 22px;
    text-align: center;
  }

  /* Shared pill base — "Options" (bottom-left) and "Sold out"/"Unavailable"
     (top-left) both use this, only the modifier class differs. */
  .pos-card__pill {
    position: absolute;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.5;
    white-space: nowrap;
  }
  .pos-card__pill--options {
    left: 8px;
    bottom: 8px;
    background: rgba(var(--v-theme-surface), 0.92);
    color: rgba(var(--v-theme-on-surface), 0.85);
  }
  .pos-card__pill--warn {
    left: 8px;
    top: 8px;
    background: var(--pos-card-warn-bg);
    color: var(--pos-card-warn-text);
    cursor: default;
  }

  /* ── Text area ─────────────────────────────────────────────────────────── */
  .pos-card__body {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px 2px 2px;  
    flex-grow: 1;
  }
  .pos-card__name {
    font-size: 14px;
    font-weight: 600;
    line-height: 20px;
    min-height: 40px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: rgb(var(--v-theme-on-surface));
  }
  .pos-card__price {
    font-size: 16px;
    font-weight: 700;
    color: rgb(var(--v-theme-primary));
    font-variant-numeric: tabular-nums;
  }
  .pos-card--disabled .pos-card__price {
    color: rgba(var(--v-theme-on-surface), 0.5);
  }

  /* ── Skeleton ──────────────────────────────────────────────────────────── */
  .pos-card--skeleton {
    cursor: default;
  }
  .pos-card__skeleton-fill,
  .pos-card__skeleton-fill :deep(.v-skeleton-loader__image) {
    width: 100%;
    height: 100%;
  }

  .search-shortcut-hint {
    font-family: inherit;
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 5px;
    background: rgba(var(--v-theme-on-surface), 0.08);
    border: 1px solid rgba(var(--v-theme-on-surface), 0.14);
  }

  /* ── Category pills ────────────────────────────────────────────────────── */
  .pos-pill-row {
    display: flex;
    flex-wrap: nowrap;
    gap: 8px;
    overflow-x: auto;
    /* Scrollable, but no visible scrollbar on touch devices. */
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .pos-pill-row::-webkit-scrollbar {
    display: none;
  }
  .pos-pill {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 0 16px 0 6px;
    border-radius: 999px;
    border: 1px solid var(--pos-card-border);
    background: rgb(var(--v-theme-surface));
    color: var(--pos-pill-text);
    font-family: inherit;
    cursor: pointer;
    outline: none;
    transition:
      background-color 0.15s,
      border-color 0.15s,
      color 0.15s;
  }
  .pos-pill:focus-visible {
    border-color: rgb(var(--v-theme-primary));
  }
  .pos-pill__icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--pos-pill-icon-bg);
    color: rgb(var(--v-theme-primary));
  }
  .pos-pill__name {
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
  }
  .pos-pill--selected {
    background: rgb(var(--v-theme-primary));
    border-color: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
  }
  .pos-pill--selected .pos-pill__icon {
    background: rgba(var(--v-theme-on-primary), 0.18);
    color: rgb(var(--v-theme-on-primary));
  }
</style>
