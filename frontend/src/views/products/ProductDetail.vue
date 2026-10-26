<template>
  <div>
    <v-container fluid class="pa-0" v-if="product">
      <!-- ── Header ──────────────────────────────────────────────────────────── -->
      <AppPageHeader
        :title="product.name"
        show-back
        :breadcrumbs="breadcrumbItems"
      >
        <template #title-after>
          <span v-if="product.name_km" class="text-body-2 text-medium-emphasis">
            · {{ product.name_km }}
          </span>
        </template>
        <template #right>
          <div class="d-flex gap-2">
            <v-btn
              variant="outlined"
              color="error"
              prepend-icon="mdi-delete-outline"
              rounded="lg"
              @click="handleDeleteProduct"
            >
              {{ t('btn.delete') }}
            </v-btn>
            <v-btn
              color="primary"
              variant="flat"
              prepend-icon="mdi-pencil-outline"
              rounded="lg"
              @click="openEditProduct"
            >
              {{ t('products.detail.edit_details') }}
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
            <v-card rounded="lg" class="mb-4 overflow-hidden">
              <v-img
                :src="product.image_url"
                :aspect-ratio="4 / 3"
                :cover="product.image_fit !== 'contain'"
                class="bg-surface-light"
              >
                <template v-if="!product.image_url" #placeholder>
                  <div class="d-flex align-center justify-center fill-height">
                    <v-icon
                      :icon="buConfig?.icon ?? 'mdi-package-variant'"
                      size="56"
                      color="grey"
                    />
                  </div>
                </template>
              </v-img>

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
                    {{
                      product.is_available
                        ? t('products.detail.available')
                        : t('products.detail.unavailable')
                    }}
                  </v-chip>
                  <v-chip
                    v-if="product.is_featured"
                    color="amber"
                    size="small"
                    variant="tonal"
                    prepend-icon="mdi-star"
                  >
                    {{ t('products.detail.featured') }}
                  </v-chip>
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

                <div class="d-flex flex-column" style="gap: 10px">
                  <div v-if="product.category?.name" class="meta-row">
                    <span class="text-caption text-medium-emphasis">
                      {{ t('products.field.category') }}
                    </span>
                    <v-chip size="x-small" variant="tonal" color="primary">
                      {{ product.category.name }}
                    </v-chip>
                  </div>
                  <div class="meta-row">
                    <span class="text-caption text-medium-emphasis">
                      {{ t('products.detail.image_fit') }}
                    </span>
                    <v-btn-toggle
                      :model-value="imageFit"
                      density="compact"
                      rounded="lg"
                      mandatory
                      color="primary"
                      @update:model-value="onImageFitChange"
                    >
                      <v-btn value="cover" size="x-small" class="text-none">
                        {{ t('products.detail.image_fit_cover') }}
                      </v-btn>
                      <v-btn value="contain" size="x-small" class="text-none">
                        {{ t('products.detail.image_fit_contain') }}
                      </v-btn>
                    </v-btn-toggle>
                  </div>
                  <v-divider class="my-1" />
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-medium-emphasis">
                      {{ t('products.detail.price') }}
                    </span>
                    <span class="text-h6 font-weight-bold text-primary">
                      {{ priceDisplay }}
                    </span>
                  </div>
                </div>
              </v-card-text>
            </v-card>

            <!-- ── FOOD: cost/margin (base price now shown above) ──────────── -->
            <v-card
              v-if="isFoodProduct && product.cost_price"
              rounded="xl"
              border
              elevation="0"
              class="mb-4"
            >
              <v-card-text class="pa-5">
                <div class="section-label mb-4">
                  <v-icon icon="mdi-cash" size="12" class="mr-1" />
                  {{ t('products.detail.cost_margin') }}
                </div>
                <div class="d-flex flex-column" style="gap: 10px">
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-grey">
                      {{ t('products.detail.cost_price') }}
                    </span>
                    <span class="text-body-2">
                      {{ format(product.cost_price) }}
                    </span>
                  </div>
                  <div class="d-flex justify-space-between align-center">
                    <span class="text-caption text-grey">
                      {{ t('products.detail.margin') }}
                    </span>
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
                (product.preparation_time || product.shelf_life_hours != null)
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
                  {{ t('products.detail.kitchen_details') }}
                </div>
                <div class="d-flex flex-column" style="gap: 8px">
                  <div v-if="product.preparation_time" class="meta-row">
                    <span class="text-caption text-grey">
                      {{ t('products.detail.prep_time') }}
                    </span>
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      color="blue"
                      prepend-icon="mdi-clock-outline"
                    >
                      {{ product.preparation_time }} min
                    </v-chip>
                  </div>
                  <div v-if="product.shelf_life_hours" class="meta-row">
                    <span class="text-caption text-grey">
                      {{ t('products.detail.shelf_life') }}
                    </span>
                    <v-chip
                      size="x-small"
                      variant="tonal"
                      color="teal"
                      prepend-icon="mdi-clock-check-outline"
                    >
                      {{ product.shelf_life_hours }}h
                    </v-chip>
                  </div>
                </div>

                <template
                  v-if="
                    product.cup_sizes?.length ||
                    product.temperature_options?.length
                  "
                >
                  <v-divider class="my-3" />
                  <div v-if="product.cup_sizes?.length" class="mb-2">
                    <p class="text-caption text-grey mb-2">
                      {{ t('products.detail.cup_sizes') }}
                    </p>
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
                    <p class="text-caption text-grey mb-2">
                      {{ t('products.detail.temperature') }}
                    </p>
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
                  {{ t('products.detail.inventory_stock') }}
                </div>
                <div class="d-flex flex-column" style="gap: 8px">
                  <div class="meta-row">
                    <span class="text-caption text-grey">
                      {{ t('products.detail.stock') }}
                    </span>
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
                    <span class="text-caption text-grey">
                      {{ t('products.detail.reorder_at') }}
                    </span>
                    <span class="text-caption font-weight-medium">
                      {{ product.reorder_level }}
                    </span>
                  </div>
                  <div class="meta-row">
                    <span class="text-caption text-grey">
                      {{ t('products.detail.track_stock') }}
                    </span>
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
                    <span class="text-caption text-grey">
                      {{ t('products.detail.expiry') }}
                    </span>
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
                    <span class="text-caption text-grey">
                      {{ t('products.detail.supplier_code') }}
                    </span>
                    <v-chip size="x-small" variant="outlined">
                      {{ product.supplier_code }}
                    </v-chip>
                  </div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>

          <!-- ══════════════════════════════════════════════════════════════════
             RIGHT COLUMN
        ═══════════════════════════════════════════════════════════════════════ -->
          <v-col cols="12" lg="8">
            <!-- ── FOOD: Sizes (was Variants) ──────────────────────────────── -->
            <v-card v-if="isFoodProduct" border rounded="lg" class="mb-4">
              <div class="d-flex align-center justify-space-between pa-2 pb-3">
                <div>
                  <div class="text-body-1 font-weight-bold">
                    {{ t('products.detail.sizes_title') }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ t('products.detail.sizes_subtitle') }}
                  </div>
                </div>
                <v-btn
                  size="small"
                  color="primary"
                  variant="tonal"
                  rounded="lg"
                  prepend-icon="mdi-plus"
                  @click="openCreateSize"
                >
                  {{ t('products.detail.add_size') }}
                </v-btn>
              </div>
              <v-divider />

              <v-table v-if="localSizes.length" density="comfortable">
                <thead>
                  <tr>
                    <th style="width: 40px"></th>
                    <th>{{ t('products.variant.name') }}</th>
                    <th>{{ t('products.detail.price') }}</th>
                    <th class="text-center">
                      {{ t('products.detail.default') }}
                    </th>
                    <th class="text-end">{{ t('products.detail.edit') }}</th>
                  </tr>
                </thead>
                <draggable
                  v-model="localSizes"
                  item-key="id"
                  handle=".size-drag"
                  tag="tbody"
                  @end="onSizesReordered"
                >
                  <template #item="{ element: v }">
                    <tr>
                      <td>
                        <v-icon
                          icon="mdi-drag-vertical"
                          class="size-drag"
                          style="cursor: grab"
                        />
                      </td>
                      <td>{{ v.name }}</td>
                      <td>{{ format(sizePrice(v)) }}</td>
                      <td class="text-center">
                        <v-radio
                          :model-value="v.is_default"
                          :value="true"
                          color="primary"
                          density="compact"
                          hide-details
                          @click="setDefaultSize(v)"
                        />
                      </td>
                      <td class="text-end">
                        <v-btn
                          icon="mdi-pencil-outline"
                          size="small"
                          variant="text"
                          @click="openEditSize(v)"
                        />
                        <v-btn
                          icon="mdi-delete-outline"
                          size="small"
                          variant="text"
                          color="error"
                          @click="confirmDeleteSize(v)"
                        />
                      </td>
                    </tr>
                  </template>
                </draggable>
              </v-table>

              <div v-else class="pa-8 text-center text-medium-emphasis">
                {{ t('products.detail.no_sizes') }}
              </div>
            </v-card>

            <!-- ── FOOD: Options (was Modifier Groups) ─────────────────────── -->
            <v-card v-if="isFoodProduct" border rounded="lg" class="mb-4">
              <div class="d-flex align-center justify-space-between pa-2 pb-3">
                <div>
                  <div class="text-body-1 font-weight-bold">
                    {{ t('modifiers.options_tab.tab_label') }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ t('products.detail.options_subtitle') }}
                  </div>
                </div>
                <v-menu>
                  <template #activator="{ props: menuProps }">
                    <v-btn
                      v-bind="menuProps"
                      size="small"
                      color="primary"
                      variant="tonal"
                      rounded="lg"
                      prepend-icon="mdi-plus"
                    >
                      {{ t('products.detail.add_option_group') }}
                    </v-btn>
                  </template>
                  <v-list density="compact">
                    <v-list-item
                      prepend-icon="mdi-link-variant"
                      @click="linkDialog = true"
                    >
                      <v-list-item-title>
                        {{ t('products.detail.link_existing_group') }}
                      </v-list-item-title>
                    </v-list-item>
                    <v-list-item
                      prepend-icon="mdi-plus-circle-outline"
                      @click="openCreateGroup"
                    >
                      <v-list-item-title>
                        {{ t('products.detail.create_new_group') }}
                      </v-list-item-title>
                    </v-list-item>
                  </v-list>
                </v-menu>
              </div>
              <v-divider />

              <draggable
                v-if="localGroups.length"
                v-model="localGroups"
                item-key="id"
                handle=".group-drag"
                tag="div"
                @end="onGroupsReordered"
              >
                <template #item="{ element: group }">
                  <div class="d-flex align-start ga-3 pa-4 group-row">
                    <v-icon
                      icon="mdi-drag-vertical"
                      class="group-drag mt-1"
                      style="cursor: grab"
                    />
                    <div class="flex-grow-1 min-width-0">
                      <div class="d-flex align-center ga-2 flex-wrap mb-1">
                        <span class="font-weight-medium">{{ group.name }}</span>
                        <v-chip size="x-small" variant="tonal" color="primary">
                          {{ ruleText(group) }}
                        </v-chip>
                        <v-chip
                          v-if="group.is_required"
                          size="x-small"
                          variant="tonal"
                          color="error"
                        >
                          {{ t('modifiers.group.required_toggle') }}
                        </v-chip>
                      </div>
                      <div class="d-flex flex-wrap ga-1 mb-1">
                        <v-chip
                          v-for="opt in group.options"
                          :key="opt.id"
                          size="x-small"
                          :variant="opt.is_default ? 'flat' : 'tonal'"
                          :color="opt.is_default ? 'primary' : undefined"
                        >
                          {{ opt.name }}
                        </v-chip>
                      </div>
                      <span
                        v-if="alsoUsedBy(group) > 0"
                        class="text-caption text-medium-emphasis"
                      >
                        {{
                          t('products.detail.also_used_by', alsoUsedBy(group), {
                            n: alsoUsedBy(group)
                          })
                        }}
                      </span>
                    </div>
                    <div class="d-flex ga-1">
                      <v-btn
                        icon="mdi-pencil-outline"
                        size="small"
                        variant="text"
                        @click="openEditGroup(group)"
                      />
                      <v-btn
                        icon="mdi-link-off"
                        size="small"
                        variant="text"
                        color="error"
                        @click="confirmUnlink(group)"
                      />
                    </div>
                  </div>
                </template>
              </draggable>

              <div v-else class="pa-8 text-center text-medium-emphasis">
                {{ t('products.detail.no_options_linked') }}
              </div>
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

    <!-- ── Dialogs / drawer ─────────────────────────────────────────────────── -->
    <ProductSizeDialog
      v-if="isFoodProduct"
      v-model="sizeDialog"
      :size="selectedSize"
      :base-price="product?.base_price"
      :loading="savingSize"
      @saved="handleSizeSaved"
    />

    <ModifierLinkDialog
      v-if="isFoodProduct"
      v-model="linkDialog"
      :product-id="product?.id"
      :linked-group-ids="linkedGroupIds"
      :loading="linking"
      @linked="handleModifierLinked"
    />

    <!-- Rendered at this component's root, not nested in the grid, so the
         drawer overlay always covers the full screen. -->
    <OptionGroupDrawer
      v-if="isFoodProduct"
      v-model="groupDrawer"
      :group="selectedGroup"
      :loading="groupSaving"
      @saved="handleGroupDrawerSaved"
    />
  </div>
</template>

<script setup>
  import { ref, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRoute, useRouter } from 'vue-router'
  import draggable from 'vuedraggable'
  import { useProductStore } from '@/stores/productStore'
  import { useProductVariantStore } from '@/stores/productVariantStore'
  import { useModifierGroupStore } from '@/stores/modifierGroupStore'
  import { useAuthStore } from '@/stores/authStore'
  import { BUSINESS_TYPES } from '@/constants/businessTypes'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import ProductSizeDialog from '@/components/products/ProductSizeDialog.vue'
  import ModifierLinkDialog from '@/components/products/ModifierLinkDialog.vue'
  import OptionGroupDrawer from '@/components/products/OptionGroupDrawer.vue'
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
  const groupStore = useModifierGroupStore()
  const authStore = useAuthStore()

  const { product } = storeToRefs(productStore)

  const sizeDialog = ref(false)
  const selectedSize = ref(null)
  const savingSize = ref(false)

  const linkDialog = ref(false)
  const linking = ref(false)

  const groupDrawer = ref(false)
  const selectedGroup = ref(null)
  const groupSaving = ref(false)

  // ── Business type resolution ──────────────────────────────────────────────────
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
  const productNature = computed(
    () =>
      product.value?.tenant?.business_type?.category ??
      authStore.bu_category ??
      'food'
  )
  const isFoodProduct = computed(() => productNature.value === 'food')
  const isMartProduct = computed(() => productNature.value === 'mart')

  // ── Breadcrumb: Products › category ──────────────────────────────────────────
  const breadcrumbItems = computed(() => {
    const items = [{ title: t('products.title'), to: '/products' }]
    if (product.value?.category?.name)
      items.push({ title: product.value.category.name })
    return items
  })

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

  function sizePrice(variant) {
    return (
      Number(product.value?.base_price ?? 0) +
      Number(variant.price_adjustment ?? 0)
    )
  }

  const priceDisplay = computed(() => {
    if (!product.value) return ''
    if (product.value.variants?.length) {
      const prices = product.value.variants.map(v => sizePrice(v))
      return t('products.detail.from_price', {
        price: format(Math.min(...prices))
      })
    }
    return format(product.value.base_price)
  })

  // ── Image fit — a quick, instantly-saving toggle (like the Available
  // switch elsewhere) rather than requiring a trip to the full edit form
  // for one setting. ────────────────────────────────────────────────────────
  const imageFit = ref('cover')
  watch(
    () => product.value?.image_fit,
    val => {
      imageFit.value = val || 'cover'
    },
    { immediate: true }
  )
  async function onImageFitChange(val) {
    if (!val || val === imageFit.value) return
    imageFit.value = val
    await productStore.updateProduct(product.value.id, { image_fit: val })
    product.value.image_fit = val
  }

  // ── Product actions ───────────────────────────────────────────────────────────
  const openEditProduct = () => router.push(`/products/${route.params.id}/edit`)

  const handleDeleteProduct = () => {
    confirm({
      title: t('products.detail.delete_product_title'),
      message: t('products.detail.delete_product_message', {
        name: product.value.name
      }),
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await productStore.deleteProduct(product.value.id)
        notif(t('messages.deleted_success'), { type: 'success' })
        router.push('/products')
      }
    })
  }

  // ── Sizes (variants) actions — food only ──────────────────────────────────────
  const localSizes = ref([])
  watch(
    () => product.value?.variants,
    val => {
      localSizes.value = val ? [...val] : []
    },
    { immediate: true }
  )

  async function onSizesReordered() {
    await variantStore.reorderProductVariants(
      product.value.id,
      localSizes.value.map(v => v.id)
    )
  }

  const openCreateSize = () => {
    selectedSize.value = null
    sizeDialog.value = true
  }
  const openEditSize = v => {
    selectedSize.value = { ...v }
    sizeDialog.value = true
  }

  const confirmDeleteSize = v => {
    confirm({
      title: t('products.detail.delete_size_title'),
      message: t('products.detail.delete_size_message', { name: v.name }),
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        try {
          await variantStore.deleteProductVariant(v.id)
          await productStore.fetchProductById(route.params.id)
          notif(t('messages.deleted_success'), { type: 'success' })
        } catch {
          notif(t('products.detail.delete_size_failed'), { type: 'error' })
        }
      }
    })
  }

  const handleSizeSaved = async payload => {
    savingSize.value = true
    try {
      if (payload.id) {
        // product_id included so the model's "only one default variant per
        // product" exclusivity query actually has something to match
        // against — omitting it silently no-ops that logic (it did before).
        await variantStore.updateProductVariant(payload.id, {
          ...payload,
          product_id: product.value.id
        })
      } else {
        await variantStore.createProductVariant({
          ...payload,
          product_id: product.value.id
        })
      }
      await productStore.fetchProductById(route.params.id)
      sizeDialog.value = false
      notif(
        payload.id
          ? t('products.detail.size_updated')
          : t('products.detail.size_added'),
        { type: 'success' }
      )
    } catch (err) {
      if (err?.response?.status !== 422)
        notif(t('products.detail.size_save_failed'), { type: 'error' })
    } finally {
      savingSize.value = false
    }
  }

  const setDefaultSize = async variant => {
    if (variant.is_default) return
    await variantStore.updateProductVariant(variant.id, {
      product_id: product.value.id,
      name: variant.name,
      price_adjustment: variant.price_adjustment,
      is_default: true,
      sort_order: variant.sort_order
    })
    await productStore.fetchProductById(route.params.id)
  }

  // ── Options (modifier groups) actions — food only ─────────────────────────────
  const linkedGroupIds = computed(
    () => product.value?.modifier_groups?.map(g => g.id) ?? []
  )

  const localGroups = ref([])
  watch(
    () => product.value?.modifier_groups,
    val => {
      localGroups.value = val ? [...val] : []
    },
    { immediate: true }
  )

  function ruleText(group) {
    if (group.selection_type === 'single')
      return t('modifiers.options_tab.rule_pick_one')
    return group.max_selections
      ? t('modifiers.options_tab.rule_pick_up_to', { n: group.max_selections })
      : t('modifiers.options_tab.rule_pick_several')
  }

  // "Also" used by — excludes this product itself from the count.
  function alsoUsedBy(group) {
    return Math.max(0, (group.products_count ?? 1) - 1)
  }

  async function onGroupsReordered() {
    await productStore.reorderProductModifierGroups(
      product.value.id,
      localGroups.value.map(g => g.id)
    )
  }

  const handleModifierLinked = async (payload, { resolve, reject }) => {
    linking.value = true
    try {
      await productStore.attachModifierGroups(payload)
      resolve()
    } catch (err) {
      reject(err)
    } finally {
      linking.value = false
    }
  }

  const confirmUnlink = group => {
    confirm({
      title: t('products.detail.unlink_confirm_title'),
      message: t('products.detail.unlink_confirm_message', {
        name: group.name
      }),
      options: { type: 'warning', color: 'warning', width: 420 },
      agree: async () => {
        await productStore.detachModifierGroup(product.value.id, group.id)
        notif(t('products.detail.unlinked'), { type: 'success' })
      }
    })
  }

  const openCreateGroup = () => {
    selectedGroup.value = null
    groupDrawer.value = true
  }
  const openEditGroup = group => {
    selectedGroup.value = { ...group }
    groupDrawer.value = true
  }

  const handleGroupDrawerSaved = async (payload, callbacks) => {
    groupSaving.value = true
    try {
      if (payload.id) {
        await groupStore.updateModifierGroup(payload.id, payload)
        await productStore.fetchProductById(route.params.id)
        notif(t('modifiers.messages.group_updated'), { type: 'success' })
      } else {
        const created = await groupStore.createModifierGroup(payload)
        await productStore.attachModifierGroups({
          product_id: product.value.id,
          modifier_group_ids: [created.id]
        })
        notif(t('modifiers.messages.group_created'), { type: 'success' })
      }
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)
      if (err?.response?.status !== 422) {
        notif(t('modifiers.messages.group_save_failed'), { type: 'error' })
      }
    } finally {
      groupSaving.value = false
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
  .group-row + .group-row {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
  .min-width-0 {
    min-width: 0;
  }
</style>
