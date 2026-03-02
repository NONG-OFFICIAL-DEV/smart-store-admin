<template>
  <v-container fluid class="pa-0" v-if="product">
    <custom-title :title="product.name" subtitle="Product Detail">
      <template #right>
        <div class="d-flex gap-2">
          <v-btn
            variant="outlined"
            prepend-icon="mdi-pencil-outline"
            rounded="lg"
            @click="openEditProduct"
          >
            Edit
          </v-btn>
          <v-btn
            color="error"
            variant="tonal"
            prepend-icon="mdi-delete-outline"
            rounded="lg"
            class="ms-2"
            @click="handleDeleteProduct"
          >
            Delete
          </v-btn>
        </div>
      </template>
    </custom-title>

    <v-row>
      <!-- ── Left: Product Info ──────────────────────────────────────────── -->
      <v-col cols="12" lg="4">
        <v-card rounded="xl" border elevation="0" class="mb-4">
          <!-- Image -->
          <div class="product-image-wrapper">
            <v-img
              v-if="product.image_url"
              :src="product.image_url"
              height="220"
              cover
              rounded="t-xl"
            />
            <div
              v-else
              class="product-image-placeholder d-flex align-center justify-center rounded-t-xl"
            >
              <v-icon
                icon="mdi-food-outline"
                size="64"
                color="grey-lighten-2"
              />
            </div>
          </div>

          <v-card-text class="pa-5">
            <!-- Badges -->
            <div class="d-flex flex-wrap gap-2 mb-4">
              <v-chip
                :color="typeColor(product.product_type)"
                size="small"
                variant="tonal"
              >
                <v-icon
                  :icon="typeIcon(product.product_type)"
                  size="14"
                  class="mr-1"
                />
                {{ product.product_type }}
              </v-chip>
              <v-chip
                :color="product.is_available ? 'success' : 'error'"
                size="small"
                variant="tonal"
              >
                {{ product.is_available ? 'Available' : 'Unavailable' }}
              </v-chip>
              <v-chip
                v-if="product.is_featured"
                color="amber"
                size="small"
                variant="tonal"
              >
                <v-icon icon="mdi-star" size="14" class="mr-1" />
                Featured
              </v-chip>
            </div>

            <!-- Pricing -->
            <div class="mb-4 pa-3 rounded-lg bg-grey-lighten-5">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-caption text-grey">Base Price</span>
                <span class="text-h6 font-weight-bold text-primary">
                  ${{ Number(product.base_price).toFixed(2) }}
                </span>
              </div>
              <div
                v-if="product.cost_price"
                class="d-flex justify-space-between align-center"
              >
                <span class="text-caption text-grey">Cost Price</span>
                <span class="text-body-2">
                  ${{ Number(product.cost_price).toFixed(2) }}
                </span>
              </div>
              <div
                v-if="product.cost_price"
                class="d-flex justify-space-between align-center mt-1"
              >
                <span class="text-caption text-grey">Margin</span>
                <span class="text-body-2 text-success font-weight-medium">
                  {{ margin }}%
                </span>
              </div>
            </div>

            <!-- Meta -->
            <div class="d-flex flex-column gap-2">
              <div v-if="product.sku" class="d-flex justify-space-between">
                <span class="text-caption text-grey">SKU</span>
                <v-chip size="x-small" variant="outlined">
                  {{ product.sku }}
                </v-chip>
              </div>
              <div v-if="product.barcode" class="d-flex justify-space-between">
                <span class="text-caption text-grey">Barcode</span>
                <span class="text-caption font-weight-medium">
                  {{ product.barcode }}
                </span>
              </div>
              <div
                v-if="product.preparation_time"
                class="d-flex justify-space-between"
              >
                <span class="text-caption text-grey">Prep Time</span>
                <span class="text-caption font-weight-medium">
                  {{ product.preparation_time }} min
                </span>
              </div>
              <div v-if="product.calories" class="d-flex justify-space-between">
                <span class="text-caption text-grey">Calories</span>
                <span class="text-caption font-weight-medium">
                  {{ product.calories }} kcal
                </span>
              </div>
              <div
                v-if="product.tax_category"
                class="d-flex justify-space-between"
              >
                <span class="text-caption text-grey">Tax Category</span>
                <span class="text-caption font-weight-medium">
                  {{ product.tax_category }}
                </span>
              </div>
            </div>

            <!-- Description -->
            <div v-if="product.description" class="mt-4">
              <p class="text-caption text-grey mb-1">Description</p>
              <p class="text-body-2">{{ product.description }}</p>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- ── Right: Variants + Modifiers ────────────────────────────────── -->
      <v-col cols="12" lg="8">
        <!-- Variants -->
        <v-card rounded="xl" border elevation="0" class="mb-4">
          <div class="d-flex align-center justify-space-between px-5 pt-5 pb-3">
            <div>
              <p class="text-body-1 font-weight-semibold mb-0">Variants</p>
              <p class="text-caption text-grey mb-0">
                Size, flavor or other variations
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
              Add Variant
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

            <template v-else-if="product.variants.length">
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
                      {{ variant.price_adjustment >= 0 ? '+' : '' }}${{
                        Number(variant.price_adjustment).toFixed(2)
                      }}
                    </span>
                    <span v-if="variant.sku_suffix">
                      · SKU suffix: {{ variant.sku_suffix }}
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
                <v-divider v-if="idx < variants.length - 1" />
              </template>
            </template>

            <div v-else class="text-center py-8">
              <v-icon
                icon="mdi-layers-off-outline"
                size="40"
                color="grey-lighten-2"
                class="mb-2"
              />
              <p class="text-body-2 text-medium-emphasis">No variants yet</p>
            </div>
          </v-list>
        </v-card>

        <!-- Modifier Groups attached to product -->
        <v-card rounded="xl" border elevation="0">
          <div class="d-flex align-center justify-space-between px-5 pt-5 pb-3">
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
                    {{ group.selection_type }} selection ·
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
                <v-divider v-if="idx < product.modifier_groups.length - 1" />
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
      </v-col>
    </v-row>
  </v-container>

  <!-- ── Product Edit Dialog ─────────────────────────────────────────────── -->
  <ProductFormDialog
    v-model="productDialog"
    :product="product"
    :loading="saving"
    @saved="handleProductSaved"
  />

  <!-- ── Variant Dialog ──────────────────────────────────────────────────── -->
  <ProductVariantDialog
    v-model="variantDialog"
    :variant="selectedVariant"
    :product-id="product?.id"
    :loading="variantSaving"
    @saved="handleVariantSaved"
  />
  <ModifierLinkDialog
    v-model="modifierLinkDialog"
    :product-id="product?.id"
    :linked-group-ids="product?.modifier_groups.map(g => g.id)"
    @linked="handleModifierLinked"
  />
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useRoute, useRouter } from 'vue-router'
  import { useProductStore } from '@/stores/productStore'
  import { useProductVariantStore } from '@/stores/productVariantStore'
  import ProductFormDialog from '@/components/products/ProductFormDialog.vue'
  import ProductVariantDialog from '@/components/products/ProductVariantDialog.vue'
  import ModifierLinkDialog from '@/components/products/ModifierLinkDialog.vue'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useI18n } from 'vue-i18n'
  
  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const route = useRoute()
  const router = useRouter()

  const productStore = useProductStore()
  const variantStore = useProductVariantStore()

  const { product } = storeToRefs(productStore)
  const { productVariants: variants, loading: variantLoading } =
    storeToRefs(variantStore)

  const saving = ref(false)
  const variantSaving = ref(false)
  const productDialog = ref(false)
  const variantDialog = ref(false)
  const deleteProductDialog = ref(false)
  const modifierLinkDialog = ref(false)
  const selectedVariant = ref(null)
  const snackbar = ref({ show: false, message: '', color: 'success' })

  const showSnack = (message, color = 'success') => {
    snackbar.value = { show: true, message, color }
  }

  // ── Computed ─────────────────────────────────────────────────────────────────
  const margin = computed(() => {
    if (!product.value?.cost_price || !product.value?.base_price) return 0
    return (
      ((product.value.base_price - product.value.cost_price) /
        product.value.base_price) *
      100
    ).toFixed(1)
  })

  // ── Helpers ──────────────────────────────────────────────────────────────────
  const typeColor = t =>
    ({ food: 'orange', beverage: 'blue', retail: 'purple', combo: 'teal' })[
      t
    ] ?? 'grey'
  const typeIcon = t =>
    ({
      food: 'mdi-food',
      beverage: 'mdi-cup',
      retail: 'mdi-shopping',
      combo: 'mdi-food-variant'
    })[t] ?? 'mdi-package'

  // ── Product Actions ───────────────────────────────────────────────────────────
  const openEditProduct = () => {
    productDialog.value = true
  }

  const handleDeleteProduct = async () => {
    confirm({
      title: 'Variant deleted?',
      message: `Are you sure delete this product"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await productStore.deleteProduct(product.value.id)
        notif(t('messages.deleted_success'), {
          type: 'success'
        })
        router.push('/products')
      }
    })
  }

  const handleProductSaved = async (payload, callbacks) => {
    saving.value = true
    try {
      await productStore.updateProduct(payload.id, payload)
      await productStore.fetchProductById(route.params.id)
      notif('Product updated', {
        type: 'success'
      })
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)
      notif('Failed to update product', {
        type: 'error'
      })
    } finally {
      saving.value = false
    }
  }

  // ── Variant Actions ───────────────────────────────────────────────────────────
  const openCreateVariant = () => {
    selectedVariant.value = null
    variantDialog.value = true
  }
  const openEditVariant = v => {
    selectedVariant.value = { ...v }
    variantDialog.value = true
  }

  const handleVariantSaved = async (payload, callbacks) => {
    variantSaving.value = true
    try {
      payload.id
        ? await variantStore.updateProductVariant(payload.id, payload)
        : await variantStore.createProductVariant({
            ...payload,
            product_id: product.value.id
          })
      notif(payload.id ? 'Variant updated' : 'Variant added', {
        type: 'success'
      })
      callbacks?.resolve?.()
    } catch (err) {
      callbacks?.reject?.(err)
      notif('Failed to save variant', {
        type: 'error'
      })
    } finally {
      variantSaving.value = false
    }
  }

  const handleDeleteVariant = async variant => {
    confirm({
      title: 'Variant deleted?',
      message: `Are you sure delete "${variant.name}"?`,
      options: { type: 'warning', color: 'warning', width: 400 },
      agree: async () => {
        await variantStore.deleteProductVariant(variant.id)
        notif(t('messages.deleted_success'), {
          type: 'success'
        })
      }
    })
  }

  const unlinkModifierGroup = async group => {
    // Call your unlink API here
    notif(`Unlinked "${group.name}"`, {
      type: 'success'
    })
  }

  const handleModifierLinked = async (payload, { resolve, reject }) => {
    try {
      await productStore.attachModifierGroups(payload) // POST to your pivot API
      await productStore.fetchProductById(route.params.id) // refresh
      resolve()
    } catch (err) {
      reject(err)
    }
  }

  onMounted(async () => {
    await Promise.all([
      productStore.fetchProductById(route.params.id),
      variantStore.fetchProductVariants({ product_id: route.params.id })
    ])
  })
</script>

<style scoped>
  .product-image-placeholder {
    height: 220px;
    background: #f5f5f5;
  }
</style>
