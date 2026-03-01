<template>
  <v-container fluid class="pa-6">
    <!-- ── Back + Header ──────────────────────────────────────────────────── -->
    <div class="d-flex align-center gap-3 mb-6">
      <v-btn
        icon="mdi-arrow-left"
        variant="tonal"
        rounded="lg"
        @click="$router.back()"
      />
      <div class="flex-1">
        <div class="d-flex align-center gap-3">
          <v-avatar size="48" rounded="xl" color="grey-lighten-2">
            <v-img v-if="product.image_url" :src="product.image_url" cover />
            <v-icon v-else :icon="typeIcon(product.product_type)" size="24" color="grey" />
          </v-avatar>
          <div>
            <h1 class="text-h5 font-weight-bold">{{ product.name }}</h1>
            <div class="d-flex align-center gap-2 mt-1">
              <v-chip :color="typeColor(product.product_type)" variant="tonal" size="x-small" rounded="lg">
                {{ product.product_type }}
              </v-chip>
              <span class="text-caption text-medium-emphasis">{{ product.sku || 'No SKU' }}</span>
              <span class="text-caption text-medium-emphasis">·</span>
              <span class="text-caption font-weight-medium">${{ Number(product.base_price).toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>
      <v-btn
        color="primary"
        variant="tonal"
        rounded="lg"
        prepend-icon="mdi-pencil"
        @click="editProduct"
      >
        Edit Product
      </v-btn>
    </div>

    <!-- ── Status chips ───────────────────────────────────────────────────── -->
    <div class="d-flex gap-2 mb-6 flex-wrap">
      <v-chip :color="product.is_available ? 'success' : 'error'" variant="flat" size="small" rounded="lg">
        <v-icon :icon="product.is_available ? 'mdi-check-circle' : 'mdi-close-circle'" size="14" class="mr-1" />
        {{ product.is_available ? 'Available' : 'Unavailable' }}
      </v-chip>
      <v-chip v-if="product.is_featured" color="warning" variant="flat" size="small" rounded="lg">
        <v-icon icon="mdi-star" size="14" class="mr-1" />
        Featured
      </v-chip>
      <v-chip v-if="product.preparation_time" color="blue" variant="tonal" size="small" rounded="lg">
        <v-icon icon="mdi-clock-outline" size="14" class="mr-1" />
        {{ product.preparation_time }} min prep
      </v-chip>
      <v-chip v-if="product.calories" color="orange" variant="tonal" size="small" rounded="lg">
        <v-icon icon="mdi-fire" size="14" class="mr-1" />
        {{ product.calories }} kcal
      </v-chip>
    </div>

    <v-row>
      <!-- Left column -->
      <v-col cols="12" lg="8">
        <!-- Variants Panel -->
        <ProductVariantsPanel
          :product-id="product.id"
          :product-name="product.name"
          :variants="variants"
          class="mb-4"
          @saved="onVariantSaved"
          @deleted="onVariantDeleted"
        />

        <!-- Modifier Groups Panel -->
        <ProductModifierGroupsPanel
          :product-id="product.id"
          :product-name="product.name"
          :assigned="assignedModifiers"
          :all-groups="allModifierGroups"
          @assigned="onModifierAssigned"
          @unassigned="onModifierUnassigned"
          @updated="onModifierUpdated"
        />
      </v-col>

      <!-- Right column — summary -->
      <v-col cols="12" lg="4">
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3 text-subtitle-1 font-weight-bold">
            <v-icon icon="mdi-information-outline" size="18" class="mr-2" />
            Product Info
          </v-card-title>
          <v-divider />
          <v-list density="compact" class="pa-2">
            <v-list-item v-for="info in productInfo" :key="info.label">
              <template #prepend>
                <v-icon :icon="info.icon" size="16" color="grey" class="mr-2" />
              </template>
              <v-list-item-title class="text-caption text-medium-emphasis">{{ info.label }}</v-list-item-title>
              <template #append>
                <span class="text-body-2 font-weight-medium">{{ info.value }}</span>
              </template>
            </v-list-item>
          </v-list>
        </v-card>

        <!-- Pricing summary -->
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-3 text-subtitle-1 font-weight-bold">
            <v-icon icon="mdi-cash" size="18" class="mr-2" />
            Pricing
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-5">
            <div class="d-flex justify-space-between mb-2">
              <span class="text-body-2 text-medium-emphasis">Base Price</span>
              <span class="font-weight-bold">${{ Number(product.base_price).toFixed(2) }}</span>
            </div>
            <div v-if="product.cost_price" class="d-flex justify-space-between mb-2">
              <span class="text-body-2 text-medium-emphasis">Cost Price</span>
              <span class="font-weight-medium">${{ Number(product.cost_price).toFixed(2) }}</span>
            </div>
            <v-divider v-if="product.cost_price" class="my-3" />
            <div v-if="product.cost_price" class="d-flex justify-space-between">
              <span class="text-body-2 text-medium-emphasis">Margin</span>
              <v-chip color="success" variant="tonal" size="small">
                {{ (((product.base_price - product.cost_price) / product.base_price) * 100).toFixed(1) }}%
              </v-chip>
            </div>

            <div v-if="variants.length" class="mt-4">
              <v-divider class="mb-3" />
              <p class="text-caption text-medium-emphasis mb-2">Variant Price Range</p>
              <div class="d-flex justify-space-between">
                <span class="text-body-2">Min</span>
                <span class="font-weight-medium">${{ variantPriceMin }}</span>
              </div>
              <div class="d-flex justify-space-between mt-1">
                <span class="text-body-2">Max</span>
                <span class="font-weight-medium">${{ variantPriceMax }}</span>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Product Edit Dialog ────────────────────────────────────────────── -->
    <ProductFormDialog
      v-model="productDialog"
      :edit-item="product"
      :categories="categories"
      @saved="onProductSaved"
    />

    <!-- Snackbar -->
    <v-snackbar v-model="snack.show" :color="snack.color" rounded="lg" location="bottom right">
      {{ snack.text }}
      <template #actions>
        <v-btn icon="mdi-close" size="small" variant="text" @click="snack.show = false" />
      </template>
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, computed } from 'vue'
import ProductVariantsPanel from './ProductVariantsPanel.vue'
import ProductModifierGroupsPanel from './ProductModifierGroupsPanel.vue'
import ProductFormDialog from '@/components/products/ProductFormDialog.vue'

// ── Mock product data (replace with route param + store/API) ──────────────────
const product = ref({
  id: '1', name: 'Margherita Pizza', sku: 'PIZ-001',
  product_type: 'food', base_price: 12.99, cost_price: 4.50,
  is_available: true, is_featured: true, preparation_time: 20,
  calories: 850, sort_order: 1, image_url: '', category_id: 'cat-1',
  description: 'Classic tomato and mozzarella', tax_category: null,
  barcode: null,
})

const categories = ref([
  { id: 'cat-1', name: 'Pizza' },
  { id: 'cat-2', name: 'Beverages' },
])

// ── Variants ──────────────────────────────────────────────────────────────────
const variants = ref([
  { id: 'v1', product_id: '1', name: 'Small',  price_adjustment: -2.00, sku_suffix: '-SM', is_default: false, sort_order: 0 },
  { id: 'v2', product_id: '1', name: 'Medium', price_adjustment:  0.00, sku_suffix: '-MD', is_default: true,  sort_order: 1 },
  { id: 'v3', product_id: '1', name: 'Large',  price_adjustment:  3.00, sku_suffix: '-LG', is_default: false, sort_order: 2 },
])

// ── Modifier Groups ───────────────────────────────────────────────────────────
const allModifierGroups = ref([
  { id: 'g1', name: 'Crust Type',     selection_type: 'single',   is_required: true,  options: [{}, {}, {}] },
  { id: 'g2', name: 'Extra Toppings', selection_type: 'multiple', is_required: false, options: [{}, {}, {}] },
  { id: 'g3', name: 'Sauce',          selection_type: 'single',   is_required: false, options: [{}, {}] },
])

const assignedModifiers = ref([
  { modifier_group_id: 'g1', sort_order: 0, group: allModifierGroups.value[0] },
  { modifier_group_id: 'g2', sort_order: 1, group: allModifierGroups.value[1] },
])

// ── UI ────────────────────────────────────────────────────────────────────────
const productDialog = ref(false)
const snack = ref({ show: false, text: '', color: 'success' })

const showSnack = (text, color = 'success') => {
  snack.value = { show: true, text, color }
}

// ── Computed ──────────────────────────────────────────────────────────────────
const productInfo = computed(() => [
  { label: 'Category',  icon: 'mdi-tag-outline',    value: 'Pizza' },
  { label: 'SKU',       icon: 'mdi-identifier',     value: product.value.sku || '—' },
  { label: 'Barcode',   icon: 'mdi-barcode',        value: product.value.barcode || '—' },
  { label: 'Tax Cat.',  icon: 'mdi-percent',        value: product.value.tax_category || '—' },
  { label: 'Sort Order',icon: 'mdi-sort',           value: product.value.sort_order },
  { label: 'Variants',  icon: 'mdi-layers-triple',  value: variants.value.length },
  { label: 'Modifiers', icon: 'mdi-tune',           value: assignedModifiers.value.length },
])

const variantPriceMin = computed(() => {
  if (!variants.value.length) return '—'
  const min = Math.min(...variants.value.map(v => product.value.base_price + v.price_adjustment))
  return min.toFixed(2)
})

const variantPriceMax = computed(() => {
  if (!variants.value.length) return '—'
  const max = Math.max(...variants.value.map(v => product.value.base_price + v.price_adjustment))
  return max.toFixed(2)
})

const typeColor = t => ({ food: 'orange', beverage: 'blue', retail: 'purple', combo: 'teal' }[t] || 'grey')
const typeIcon  = t => ({ food: 'mdi-food', beverage: 'mdi-cup', retail: 'mdi-shopping', combo: 'mdi-layers' }[t] || 'mdi-package')

// ── Handlers ──────────────────────────────────────────────────────────────────
const editProduct = () => { productDialog.value = true }

const onProductSaved = payload => {
  Object.assign(product.value, payload)
  showSnack('Product updated')
}

const onVariantSaved = payload => {
  if (payload.id) {
    const idx = variants.value.findIndex(v => v.id === payload.id)
    if (idx !== -1) variants.value[idx] = { ...variants.value[idx], ...payload }
    showSnack('Variant updated')
  } else {
    variants.value.push({ ...payload, id: Date.now().toString() })
    showSnack('Variant added')
  }
}

const onVariantDeleted = id => {
  variants.value = variants.value.filter(v => v.id !== id)
  showSnack('Variant deleted', 'error')
}

const onModifierAssigned = payload => {
  const group = allModifierGroups.value.find(g => g.id === payload.modifier_group_id)
  assignedModifiers.value.push({ ...payload, group })
  showSnack('Modifier group assigned')
}

const onModifierUnassigned = payload => {
  assignedModifiers.value = assignedModifiers.value.filter(
    a => a.modifier_group_id !== payload.modifier_group_id
  )
  showSnack('Modifier group removed', 'error')
}

const onModifierUpdated = payload => {
  const a = assignedModifiers.value.find(a => a.modifier_group_id === payload.modifier_group_id)
  if (a) a.sort_order = payload.sort_order
  showSnack('Sort order updated')
}
</script>