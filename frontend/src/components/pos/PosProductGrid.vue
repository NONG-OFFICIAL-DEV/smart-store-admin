<template>
  <div class="pos-product-grid d-flex flex-column" :class="{ 'pos-product-grid--touch': touch }">
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
      class="flex-grow-0 mb-2"
      @update:model-value="$emit('update:search', $event ?? '')"
    >
      <template #append-inner>
        <kbd v-if="!touch" class="search-shortcut-hint">⌘K</kbd>
      </template>
    </v-text-field>

    <!-- Directly tappable — no dropdown to open first, one tap picks a
         category (touch-friendlier than a <select>-style control). -->
    <v-chip-group
      :model-value="categoryId"
      mandatory="force"
      selected-class="text-primary"
      class="flex-grow-0 mb-2"
      @update:model-value="$emit('update:categoryId', $event)"
    >
      <v-chip :value="null" filter variant="tonal" rounded="lg" :size="touch ? 'default' : 'small'">
        {{ $t('pos.grid.all_categories') }}
      </v-chip>
      <v-chip
        v-for="cat in categories"
        :key="cat.id"
        :value="cat.id"
        filter
        variant="tonal"
        rounded="lg"
        :size="touch ? 'default' : 'small'"
      >
        {{ cat.name }}
      </v-chip>
    </v-chip-group>

    <div class="pos-product-grid__scroll flex-grow-1">
      <div v-if="loading" class="d-flex justify-center py-10">
        <v-progress-circular indeterminate color="primary" />
      </div>

      <v-alert
        v-else-if="!products.length"
        type="info"
        variant="tonal"
        rounded="lg"
      >
        {{ $t('pos.grid.no_products') }}
      </v-alert>

      <v-row v-else :dense="!touch">
        <v-col
          v-for="product in products"
          :key="product.id"
          cols="6"
          :sm="touch ? 6 : 4"
          :md="touch ? 4 : 3"
          :lg="touch ? 3 : 2"
        >
          <v-card
            rounded="lg"
            border
            elevation="0"
            class="pos-product-card h-100 d-flex flex-column"
            @click="handleCardClick(product)"
          >
            <v-img
              v-if="product.image_url"
              :src="product.image_url"
              :height="touch ? 110 : 90"
              cover
              class="bg-grey-lighten-4"
            />
            <div v-else class="pos-product-card__placeholder" :style="touch ? 'height: 110px' : ''">
              <v-icon icon="mdi-image-off-outline" size="28" color="grey" />
            </div>

            <div class="d-flex flex-column flex-grow-1" :class="touch ? 'pa-3' : 'pa-2'">
              <div class="text-caption font-weight-bold pos-product-card__name">
                {{ product.name }}
              </div>

              <v-chip
                v-if="product.stockStatus"
                size="x-small"
                variant="text"
                :color="stockColor(product.stockStatus)"
                class="pos-product-card__stock px-0"
              >
                <v-icon start size="10" :icon="stockIcon(product.stockStatus)" />
                {{ $t(`pos.grid.stock.${product.stockStatus}`) }}
              </v-chip>

              <div class="flex-grow-1" />

              <template v-if="customizable && needsCustomize(product)">
                <v-btn
                  :size="touch ? 'default' : 'small'"
                  color="primary"
                  variant="tonal"
                  rounded="lg"
                  class="mt-1 text-none"
                  block
                  @click.stop="$emit('customize', product)"
                >
                  {{ $t('pos.grid.from_price') }} {{ formatMoney(fromPrice(product)) }}
                </v-btn>
              </template>
              <template v-else-if="product.options.length > 1">
                <div class="d-flex flex-wrap mt-1" :class="touch ? 'ga-2' : 'gap-1'">
                  <v-chip
                    v-for="opt in product.options"
                    :key="opt.key"
                    :size="touch ? 'default' : 'x-small'"
                    variant="tonal"
                    color="primary"
                    class="pos-product-card__opt"
                    @click.stop="$emit('add', { product, option: opt })"
                  >
                    {{ opt.label }} · {{ formatMoney(opt.price) }}
                  </v-chip>
                </div>
              </template>
              <v-btn
                v-else
                :size="touch ? 'default' : 'small'"
                color="primary"
                variant="tonal"
                rounded="lg"
                class="mt-1"
                block
                @click.stop="$emit('add', { product, option: product.options[0] })"
              >
                {{ formatMoney(product.options[0]?.price) }}
              </v-btn>
            </div>
          </v-card>
        </v-col>
      </v-row>
    </div>
  </div>
</template>

<script setup>
  import { ref } from 'vue'
  import { useDisplay } from 'vuetify'

  const { mdAndDown: touch } = useDisplay()

  const props = defineProps({
    products: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    categoryId: { type: String, default: null },
    search: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    // Food/coffee POS only — Retail has no modifier-group concept, so its
    // multi-unit products keep the plain inline chip picker below.
    customizable: { type: Boolean, default: false }
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

  function handleCardClick(product) {
    if (props.customizable && needsCustomize(product)) {
      emit('customize', product)
    } else if (product.options.length === 1) {
      emit('add', { product, option: product.options[0] })
    }
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value ?? 0)
  }

  function stockColor(status) {
    return { in_stock: 'success', low_stock: 'warning', out_of_stock: 'error' }[status]
  }

  function stockIcon(status) {
    return {
      in_stock: 'mdi-check-circle',
      low_stock: 'mdi-alert-circle',
      out_of_stock: 'mdi-close-circle'
    }[status]
  }
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
  .pos-product-card {
    cursor: default;
  }
  /* Whole card is the tap target for single-price products in touch mode
     (not just the small "Add" button at the bottom) — see the card's
     @click handler above. */
  .pos-product-grid--touch .pos-product-card {
    cursor: pointer;
  }
  .pos-product-card__placeholder {
    height: 90px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--v-theme-on-surface), 0.04);
  }
  .pos-product-card__name {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.2em;
  }
  .pos-product-card__opt {
    cursor: pointer;
  }
  .pos-product-card__stock :deep(.v-chip__content) {
    font-size: 0.6875rem;
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
</style>
