<template>
  <div>
    <v-row dense class="mb-3" align="center">
      <v-col cols="12" sm="7">
        <v-text-field
          ref="searchFieldRef"
          :model-value="search"
          :placeholder="$t('pos.grid.search_placeholder')"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="comfortable"
          rounded="lg"
          hide-details
          clearable
          @update:model-value="$emit('update:search', $event ?? '')"
        >
          <template #append-inner>
            <kbd class="search-shortcut-hint">⌘K</kbd>
          </template>
        </v-text-field>
      </v-col>
      <v-col cols="12" sm="5">
        <v-chip-group
          :model-value="categoryId"
          mandatory="force"
          selected-class="text-primary"
          @update:model-value="$emit('update:categoryId', $event)"
        >
          <v-chip :value="null" filter variant="tonal" rounded="lg">
            {{ $t('pos.grid.all_categories') }}
          </v-chip>
          <v-chip
            v-for="cat in categories"
            :key="cat.id"
            :value="cat.id"
            filter
            variant="tonal"
            rounded="lg"
          >
            {{ cat.name }}
          </v-chip>
        </v-chip-group>
      </v-col>
    </v-row>

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

    <v-row v-else dense>
      <v-col
        v-for="product in products"
        :key="product.id"
        cols="6"
        sm="4"
        md="3"
        lg="2"
      >
        <v-card
          rounded="lg"
          border
          elevation="0"
          class="pos-product-card h-100 d-flex flex-column"
        >
          <v-img
            v-if="product.image_url"
            :src="product.image_url"
            height="90"
            cover
            class="bg-grey-lighten-4"
          />
          <div v-else class="pos-product-card__placeholder">
            <v-icon icon="mdi-image-off-outline" size="28" color="grey" />
          </div>

          <div class="pa-2 d-flex flex-column flex-grow-1">
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

            <template v-if="product.options.length > 1">
              <div class="d-flex flex-wrap gap-1 mt-1">
                <v-chip
                  v-for="opt in product.options"
                  :key="opt.key"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  class="pos-product-card__opt"
                  @click="$emit('add', { product, option: opt })"
                >
                  {{ opt.label }} · {{ formatMoney(opt.price) }}
                </v-chip>
              </div>
            </template>
            <v-btn
              v-else
              size="small"
              color="primary"
              variant="tonal"
              rounded="lg"
              class="mt-1"
              block
              @click="$emit('add', { product, option: product.options[0] })"
            >
              {{ formatMoney(product.options[0]?.price) }}
            </v-btn>
          </div>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
  import { ref } from 'vue'

  defineProps({
    products: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    categoryId: { type: String, default: null },
    search: { type: String, default: '' },
    loading: { type: Boolean, default: false }
  })

  defineEmits(['add', 'update:search', 'update:categoryId'])

  const searchFieldRef = ref(null)
  defineExpose({
    focus: () => searchFieldRef.value?.focus()
  })

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
  .pos-product-card {
    cursor: default;
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
