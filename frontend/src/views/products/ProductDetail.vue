<template>
  <div>
    <v-container fluid class="pa-0" v-if="product">
      <!-- ── Header ──────────────────────────────────────────────────────────── -->
      <AppPageHeader
        :title="product.name"
        show-back
        :breadcrumbs="[
          { title: t('products.title'), to: '/products' },
          { title: product.name }
        ]"
      >
        <template #right>
          <div class="d-flex gap-2">
            <v-btn
              variant="outlined"
              prepend-icon="mdi-pencil-outline"
              rounded="lg"
              @click="openEditProduct"
            >
              {{ t('btn.edit') }}
            </v-btn>
            <v-btn
              color="error"
              variant="tonal"
              prepend-icon="mdi-delete-outline"
              rounded="lg"
              @click="handleDeleteProduct"
            >
              {{ t('btn.delete') }}
            </v-btn>
          </div>
        </template>
      </AppPageHeader>

      <div class="pa-0">
        <v-row>
          <!-- ══════════════════════════════════════════════════════════════════
             LEFT COLUMN — Image + Core Info
        ═══════════════════════════════════════════════════════════════════════ -->
          <v-col cols="12" lg="4">
            <!-- Image + badges -->
            <v-card
              rounded="xl"
              border
              elevation="0"
              class="mb-4 overflow-hidden"
            >
              <div class="d-flex align-center justify-center px-5 pt-5">
                <v-avatar size="250" v-if="product.image_url" rounded="xl">
                  <v-img :src="product.image_url" cover />
                </v-avatar>
                <div
                  v-else
                  class="product-image-placeholder d-flex align-center justify-center"
                >
                <v-avatar size="200" rounded="xl">
                  <v-icon
                    :icon="buConfig?.icon ?? 'mdi-package-variant'"
                    size="72"
                    color="grey-lighten-2"
                  />
                </v-avatar>
                </div>
              </div>

              <v-card-text class="pa-5">
                <!-- Status badges -->
                <div class="d-flex flex-wrap gap-2 mb-4">
                  <v-chip
                    :color="product.is_available ? 'success' : 'error'"
                    size="small"
                    variant="tonal"
                    :prepend-icon="
                      product.is_available
                        ? 'mdi-check-circle-outline'
                        : 'mdi-close-circle-outline'
                    "
                  >
                    {{ product.is_available ? 'Available' : 'Unavailable' }}
                  </v-chip>
                  <v-chip
                    v-if="product.is_featured"
                    color="amber"
                    size="small"
                    variant="tonal"
                    prepend-icon="mdi-star"
                  >
                    Featured
                  </v-chip>
                  <!-- Business type badge -->
                  <v-chip
                    v-if="buConfig"
                    :color="buConfig.color"
                    size="small"
                    variant="tonal"
                    :prepend-icon="buConfig.icon"
                  >
                    {{ buLabel }}
                  </v-chip>
                </div>

                <!-- Description -->
                <div v-if="product.description" class="mb-4">
                  <p class="text-caption text-grey mb-1">Description</p>
                  <p class="text-body-2">{{ product.description }}</p>
                </div>

                <!-- Meta rows -->
                <div class="d-flex flex-column" style="gap: 8px">
                  <div v-if="product.category?.name" class="meta-row">
                    <span class="text-caption text-grey">Category</span>
                    <v-chip size="x-small" variant="tonal" color="primary">
                      {{ product.category.name }}
                    </v-chip>
                  </div>
                  <div v-if="product.sku" class="meta-row">
                    <span class="text-caption text-grey">SKU</span>
                    <v-chip size="x-small" variant="outlined">
                      {{ product.sku }}
                    </v-chip>
                  </div>
                  <div v-if="product.barcode" class="meta-row">
                    <span class="text-caption text-grey">Barcode</span>
                    <span class="text-caption font-weight-medium">
                      {{ product.barcode }}
                    </span>
                  </div>
                  <div v-if="product.sort_order != null" class="meta-row">
                    <span class="text-caption text-grey">Sort Order</span>
                    <span class="text-caption font-weight-medium">
                      {{ product.sort_order }}
                    </span>
                  </div>
                </div>
              </v-card-text>
            </v-card>

            <!-- ── FOOD: Pricing card ──────────────────────────────────────── -->
            <v-card
              v-if="isFoodProduct"
              rounded="xl"
              border
              elevation="0"
              class="mb-4"
            >
              <v-card-text class="pa-5">
                <div class="section-label mb-4">
                  <v-icon icon="mdi-cash" size="12" class="mr-1" />
                  Pricing
                </div>

                <div class="d-flex flex-column" style="gap: 10px">
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-grey">Base Price</span>
                    <span class="text-h6 font-weight-bold text-primary">
                      {{ format(product.base_price) }}
                    </span>
                  </div>
                  <v-divider v-if="product.cost_price" />
                  <div
                    v-if="product.cost_price"
                    class="d-flex justify-space-between align-center"
                  >
                    <span class="text-caption text-grey">Cost Price</span>
                    <span class="text-body-2">
                      {{ format(product.cost_price) }}
                    </span>
                  </div>
                  <div
                    v-if="product.cost_price"
                    class="d-flex justify-space-between align-center"
                  >
                    <span class="text-caption text-grey">Margin</span>
                    <v-chip
                      :color="
                        margin >= 30
                          ? 'success'
                          : margin >= 10
                            ? 'warning'
                            : 'error'
                      "
                      size="x-small"
                      variant="tonal"
                    >
                      {{ margin }}%
                    </v-chip>
                  </div>
                </div>
              </v-card-text>
            </v-card>

            <!-- ── FOOD: Kitchen Details card ─────────────────────────────── -->
            <v-card
              v-if="
                isFoodProduct &&
                (product.preparation_time ||
                  product.calories ||
                  product.shelf_life_hours != null)
              "
              rounded="xl"
              border
              elevation="0"
              class="mb-4"
            >
              <v-card-text class="pa-5">
                <div class="section-label mb-4">
                  <v-icon
                    icon="mdi-silverware-fork-knife"
                    size="12"
                    class="mr-1"
                  />
                  Kitchen Details
                </div>
                <div class="d-flex flex-column" style="gap: 8px">
                  <div v-if="product.preparation_time" class="meta-row">
                    <span class="text-caption text-grey">Prep Time</span>
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      color="blue"
                      prepend-icon="mdi-clock-outline"
                    >
                      {{ product.preparation_time }} min
                    </v-chip>
                  </div>
                  <div v-if="product.calories" class="meta-row">
                    <span class="text-caption text-grey">Calories</span>
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      color="orange"
                      prepend-icon="mdi-fire"
                    >
                      {{ product.calories }} kcal
                    </v-chip>
                  </div>
                  <div v-if="product.shelf_life_hours" class="meta-row">
                    <span class="text-caption text-grey">Shelf Life</span>
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      color="teal"
                      prepend-icon="mdi-clock-check-outline"
                    >
                      {{ product.shelf_life_hours }}h
                    </v-chip>
                  </div>
                  <div v-if="product.made_to_order" class="meta-row">
                    <span class="text-caption text-grey">Made to Order</span>
                    <v-icon icon="mdi-check-circle" color="success" size="16" />
                  </div>
                </div>

                <!-- Cup sizes & temp options (Coffee shop) -->
                <template
                  v-if="
                    product.cup_sizes?.length ||
                    product.temperature_options?.length
                  "
                >
                  <v-divider class="my-3" />
                  <div v-if="product.cup_sizes?.length" class="mb-2">
                    <p class="text-caption text-grey mb-2">Sizes</p>
                    <div class="d-flex flex-wrap gap-1">
                      <v-chip
                        v-for="s in product.cup_sizes"
                        :key="s"
                        size="x-small"
                        variant="tonal"
                        color="brown"
                      >
                        {{ s }}
                      </v-chip>
                    </div>
                  </div>
                  <div v-if="product.temperature_options?.length">
                    <p class="text-caption text-grey mb-2">Temperature</p>
                    <div class="d-flex flex-wrap gap-1">
                      <v-chip
                        v-for="temp in product.temperature_options"
                        :key="temp"
                        size="x-small"
                        variant="tonal"
                        color="cyan"
                      >
                        {{ temp }}
                      </v-chip>
                    </div>
                  </div>
                </template>
              </v-card-text>
            </v-card>

            <!-- ── MART: Stock & Inventory card ──────────────────────────── -->
            <v-card
              v-if="isMartProduct"
              rounded="xl"
              border
              elevation="0"
              class="mb-4"
            >
              <v-card-text class="pa-5">
                <div class="section-label mb-4">
                  <v-icon icon="mdi-warehouse" size="12" class="mr-1" />
                  Inventory & Stock
                </div>
                <div class="d-flex flex-column" style="gap: 8px">
                  <div class="meta-row">
                    <span class="text-caption text-grey">Stock</span>
                    <v-chip
                      :color="stockColor"
                      size="x-small"
                      variant="tonal"
                      prepend-icon="mdi-package-variant-closed"
                    >
                      {{ product.stock_quantity ?? 0 }}
                      {{ product.units?.[0]?.unit_label ?? 'pcs' }}
                    </v-chip>
                  </div>
                  <div v-if="product.reorder_level" class="meta-row">
                    <span class="text-caption text-grey">Reorder At</span>
                    <span class="text-caption font-weight-medium">
                      {{ product.reorder_level }}
                    </span>
                  </div>
                  <div class="meta-row">
                    <span class="text-caption text-grey">Track Stock</span>
                    <v-icon
                      :icon="
                        product.track_stock
                          ? 'mdi-check-circle'
                          : 'mdi-close-circle'
                      "
                      :color="product.track_stock ? 'success' : 'grey'"
                      size="16"
                    />
                  </div>
                  <div v-if="product.expiry_date" class="meta-row">
                    <span class="text-caption text-grey">Expiry</span>
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      color="error"
                      prepend-icon="mdi-calendar-alert"
                    >
                      {{ formatDate(product.expiry_date) }}
                    </v-chip>
                  </div>
                  <div v-if="product.supplier_code" class="meta-row">
                    <span class="text-caption text-grey">Supplier Code</span>
                    <v-chip size="x-small" variant="outlined">
                      {{ product.supplier_code }}
                    </v-chip>
                  </div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>

          <!-- ══════════════════════════════════════════════════════════════════
             RIGHT COLUMN — varies by bu type
        ═══════════════════════════════════════════════════════════════════════ -->
          <v-col cols="12" lg="8">
            <!-- ── FOOD: Variants ─────────────────────────────────────────── -->
            <v-card
              v-if="isFoodProduct"
              rounded="xl"
              border
              elevation="0"
              class="mb-4"
            >
              <div
                class="d-flex align-center justify-space-between px-5 pt-5 pb-3"
              >
                <div>
                  <p class="text-body-1 font-weight-semibold mb-0">
                    {{ t('products.variant.title') }}
                  </p>
                  <p class="text-caption text-grey mb-0">
                    {{ t('products.variant.subtitle') }}
                  </p>
                </div>
                <v-btn
                  size="small"
                  color="primary"
                  variant="tonal"
                  prepend-icon="mdi-plus"
                  rounded="lg"
                  @click="openCreateVariant"
                >
                  {{ t('products.variant.addTitle') }}
                </v-btn>
              </div>
              <v-divider />

              <v-list class="pa-0">
                <div v-if="variantLoading" class="pa-4">
                  <v-skeleton-loader
                    v-for="n in 3"
                    :key="n"
                    type="list-item-two-line"
                    class="mb-1"
                  />
                </div>

                <template v-else-if="product.variants?.length">
                  <template
                    v-for="(variant, idx) in product.variants"
                    :key="variant.id"
                  >
                    <v-list-item class="px-5 py-3">
                      <template #prepend>
                        <v-avatar
                          color="primary"
                          variant="tonal"
                          rounded="md"
                          size="36"
                        >
                          <v-icon icon="mdi-layers-outline" size="18" />
                        </v-avatar>
                      </template>
                      <v-list-item-title class="font-weight-medium">
                        {{ variant.name }}
                        <v-chip
                          v-if="variant.is_default"
                          size="x-small"
                          color="primary"
                          variant="tonal"
                          class="ml-2"
                        >
                          Default
                        </v-chip>
                      </v-list-item-title>
                      <v-list-item-subtitle class="text-caption">
                        Price adj:
                        <span
                          :class="
                            variant.price_adjustment >= 0
                              ? 'text-success'
                              : 'text-error'
                          "
                        >
                          {{ variant.price_adjustment >= 0 ? '+' : ''
                          }}{{ format(variant.price_adjustment) }}
                        </span>
                        <span v-if="variant.sku_suffix">
                          · SKU: {{ variant.sku_suffix }}
                        </span>
                      </v-list-item-subtitle>
                      <template #append>
                        <div class="d-flex gap-1">
                          <v-btn
                            icon="mdi-pencil-outline"
                            size="x-small"
                            variant="text"
                            @click="openEditVariant(variant)"
                          />
                          <v-btn
                            icon="mdi-delete-outline"
                            size="x-small"
                            variant="text"
                            color="error"
                            @click="handleDeleteVariant(variant)"
                          />
                        </div>
                      </template>
                    </v-list-item>
                    <v-divider v-if="idx < product.variants.length - 1" />
                  </template>
                </template>

                <div v-else class="text-center py-8">
                  <v-icon
                    icon="mdi-layers-off-outline"
                    size="40"
                    color="grey-lighten-2"
                    class="mb-2"
                  />
                  <p class="text-body-2 text-medium-emphasis">
                    {{ $t('products.variant.noVariantsYet') }}
                  </p>
                </div>
              </v-list>
            </v-card>

            <!-- ── FOOD: Modifier Groups ───────────────────────────────────── -->
            <v-card
              v-if="isFoodProduct"
              rounded="xl"
              border
              elevation="0"
              class="mb-4"
            >
              <div
                class="d-flex align-center justify-space-between px-5 pt-5 pb-3"
              >
                <div>
                  <p class="text-body-1 font-weight-semibold mb-0">
                    Modifier Groups
                  </p>
                  <p class="text-caption text-grey mb-0">
                    Add-ons, extras and customizations
                  </p>
                </div>
                <v-btn
                  size="small"
                  color="secondary"
                  variant="tonal"
                  prepend-icon="mdi-link-variant"
                  rounded="lg"
                  @click="modifierLinkDialog = true"
                >
                  Link Group
                </v-btn>
              </div>
              <v-divider />

              <v-list class="pa-0">
                <template v-if="product.modifier_groups?.length">
                  <template
                    v-for="(group, idx) in product.modifier_groups"
                    :key="group.id"
                  >
                    <v-list-item class="px-5 py-3">
                      <template #prepend>
                        <v-avatar
                          color="secondary"
                          variant="tonal"
                          rounded="md"
                          size="36"
                        >
                          <v-icon icon="mdi-format-list-checks" size="18" />
                        </v-avatar>
                      </template>
                      <v-list-item-title class="font-weight-medium">
                        {{ group.name }}
                      </v-list-item-title>
                      <v-list-item-subtitle class="text-caption">
                        {{ group.selection_type }} ·
                        {{ group.is_required ? 'Required' : 'Optional' }}
                        <span v-if="group.min_selections">
                          · Min: {{ group.min_selections }}
                        </span>
                        <span v-if="group.max_selections">
                          · Max: {{ group.max_selections }}
                        </span>
                      </v-list-item-subtitle>
                      <template #append>
                        <v-btn
                          icon="mdi-link-off"
                          size="x-small"
                          variant="text"
                          color="error"
                          @click="unlinkModifierGroup(group)"
                        />
                      </template>
                    </v-list-item>
                    <v-divider
                      v-if="idx < product.modifier_groups.length - 1"
                    />
                  </template>
                </template>
                <div v-else class="text-center py-8">
                  <v-icon
                    icon="mdi-format-list-checks"
                    size="40"
                    color="grey-lighten-2"
                    class="mb-2"
                  />
                  <p class="text-body-2 text-medium-emphasis">
                    No modifier groups linked
                  </p>
                </div>
              </v-list>
            </v-card>

            <!-- ── MART: Units / Pricing table ───────────────────────────── -->
            <ProductUnitsCard
              v-if="isMartProduct"
              :product-id="route.params.id"
            />
          </v-col>
        </v-row>
      </div>
    </v-container>

    <!-- ── Dialogs ──────────────────────────────────────────────────────────── -->
    <ProductVariantDialog
      v-if="isFoodProduct"
      v-model="variantDialog"
      :variant="selectedVariant"
      :base-price="product?.base_price"
      :product-id="product?.id"
      @saved="handleVariantSaved"
    />

    <ModifierLinkDialog
      v-if="isFoodProduct"
      v-model="modifierLinkDialog"
      :product-id="product?.id"
      :linked-group-ids="product?.modifier_groups?.map(g => g.id) ?? []"
      @linked="handleModifierLinked"
    />
  </div>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRoute, useRouter } from 'vue-router'
  import { useProductStore } from '@/stores/productStore'
  import { useProductVariantStore } from '@/stores/productVariantStore'
  import { useAuthStore } from '@/stores/authStore'
  import { BUSINESS_TYPES } from '@/constants/businessTypes'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import ProductVariantDialog from '@/components/products/ProductVariantDialog.vue'
  import ModifierLinkDialog from '@/components/products/ModifierLinkDialog.vue'
  import ProductUnitsCard from '@/components/products/ProductUnitsCard.vue'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useDate } from '@/composables/useDate'

  const { format } = useCurrency()
  const { formatShortDate: formatDate } = useDate()
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const route = useRoute()
  const router = useRouter()

  const productStore = useProductStore()
  const variantStore = useProductVariantStore()
  const authStore = useAuthStore()

  const { product } = storeToRefs(productStore)
  const { loading: variantLoading } = storeToRefs(variantStore)

  const variantDialog = ref(false)
  const modifierLinkDialog = ref(false)
  const selectedVariant = ref(null)

  // ── Business type resolution ──────────────────────────────────────────────────
  // Product carries tenant.business_type.code from the API response
  const buCode = computed(
    () =>
      product.value?.tenant?.business_type?.code?.toUpperCase() ??
      authStore.bu_type?.toUpperCase() ??
      null
  )

  const buConfig = computed(() =>
    buCode.value ? (BUSINESS_TYPES[buCode.value] ?? null) : null
  )

  const buLabel = computed(
    () =>
      product.value?.tenant?.business_type?.name ??
      buCode.value?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) ??
      '—'
  )

  // Dynamic (backend business_types.category), not the static icon/color
  // registry — a product's own tenant may differ from the viewer's (super
  // admin browsing another tenant's product), so prefer that when present.
  const productNature = computed(
    () =>
      product.value?.tenant?.business_type?.category ??
      authStore.bu_category ??
      'food'
  )
  const isFoodProduct = computed(() => productNature.value === 'food')
  const isMartProduct = computed(() => productNature.value === 'mart')

  // ── Computed ──────────────────────────────────────────────────────────────────
  const margin = computed(() => {
    const base = product.value?.base_price
    const cost = product.value?.cost_price
    if (!base || !cost || base === 0) return 0
    return (((base - cost) / base) * 100).toFixed(1)
  })

  const stockColor = computed(() => {
    const qty = product.value?.stock_quantity ?? 0
    const reorder = product.value?.reorder_level ?? 0
    if (qty === 0) return 'error'
    if (qty <= reorder) return 'warning'
    return 'success'
  })


  // ── Product actions ───────────────────────────────────────────────────────────
  const openEditProduct = () => {
    router.push(`/products/${route.params.id}/edit`)
  }

  const handleDeleteProduct = () => {
    confirm({
      title: 'Delete product?',
      message: `Are you sure you want to delete "${product.value.name}"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await productStore.deleteProduct(product.value.id)
        notif(t('messages.deleted_success'), { type: 'success' })
        router.push('/products')
      }
    })
  }

  // ── Variant actions (food only) ───────────────────────────────────────────────
  const openCreateVariant = () => {
    selectedVariant.value = null
    variantDialog.value = true
  }

  const openEditVariant = v => {
    selectedVariant.value = { ...v }
    variantDialog.value = true
  }

  const handleVariantSaved = async (payload, callbacks) => {
    try {
      if (payload.id) {
        await variantStore.updateProductVariant(payload.id, payload)
      } else {
        await variantStore.createProductVariant({
          ...payload,
          product_id: product.value.id
        })
      }
      await productStore.fetchProductById(route.params.id)
      notif(payload.id ? 'Variant updated' : 'Variant added', {
        type: 'success'
      })
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)
      if (err?.response?.status !== 422)
        notif('Failed to save variant', { type: 'error' })
    }
  }

  const handleDeleteVariant = variant => {
    confirm({
      title: 'Delete variant?',
      message: `Are you sure you want to delete "${variant.name}"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await variantStore.deleteProductVariant(variant.id)
        await productStore.fetchProductById(route.params.id)
        notif(t('messages.deleted_success'), { type: 'success' })
      }
    })
  }

  // ── Modifier actions (food only) ──────────────────────────────────────────────
  const unlinkModifierGroup = group => {
    notif(`Unlinked "${group.name}"`, { type: 'success' })
  }

  const handleModifierLinked = async (payload, { resolve, reject }) => {
    try {
      await productStore.attachModifierGroups(payload)
      await productStore.fetchProductById(route.params.id)
      resolve()
    } catch (err) {
      reject(err)
    }
  }

  // ── Mount ─────────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await productStore.fetchProductById(route.params.id)
  })
</script>

<style scoped>
  .section-label {
    font-size: 0.67rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgb(var(--v-theme-primary));
    display: flex;
    align-items: center;
  }
  .meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .gap-2 {
    gap: 8px;
  }
</style>
