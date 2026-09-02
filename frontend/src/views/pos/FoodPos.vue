<template>
  <div class="pos-page d-flex flex-column">
    <!-- Compact header — back + title + branch, nothing else -->
    <div
      class="pos-page__header d-flex align-center flex-grow-0"
      :class="touch ? 'px-2 py-2' : 'px-1 py-1'"
    >
      <v-btn
        icon="mdi-arrow-left"
        variant="text"
        :density="touch ? 'default' : 'comfortable'"
        :size="touch ? 'default' : 'small'"
        @click="$router.back()"
      />
      <span class="font-weight-bold ml-1" :class="touch ? 'text-body-1' : 'text-body-2'">
        {{ t('pos.food.title') }}
      </span>
      <v-spacer />
      <v-chip :size="touch ? 'default' : 'small'" variant="tonal" rounded="lg" prepend-icon="mdi-store-outline">
        {{ selectedBranchName || t('pos.select_branch') }}
      </v-chip>
    </div>

    <PosOrderOptionsBar
      class="flex-grow-0 pb-2"
      :class="touch ? 'px-2' : 'px-1'"
      :order-type-options="enabledOrderTypes"
      :order-type="posStore.orderType"
      :tables="tableOptions"
      :table-id="posStore.tableId"
      :show-customer="showCustomer"
      :customers="customerOptions"
      :customer-id="posStore.customerId"
      :customer-name="posStore.customerName"
      :show-notes="showNotes"
      :note="posStore.note"
      @update-order-type="posStore.setOrderType"
      @update-table-id="posStore.setTable"
      @search-customer="onSearchCustomer"
      @update-customer="posStore.setCustomer"
      @update-note="posStore.setNote"
    />

    <v-alert v-if="!branchId" type="warning" variant="tonal" rounded="lg" class="flex-grow-0">
      {{ t('pos.select_branch') }}
    </v-alert>

    <div v-else class="pos-page__workspace flex-grow-1 d-flex ga-2">
      <div class="pos-page__products flex-grow-1">
        <PosProductGrid
          ref="productGridRef"
          customizable
          :products="normalizedProducts"
          :categories="categories"
          :category-id="categoryId"
          :search="search"
          :loading="loading"
          @update:search="search = $event"
          @update:category-id="categoryId = $event"
          @add="onAdd"
          @customize="openCustomize"
        />
      </div>

      <!-- Desktop/tablet — cart column, always visible. Narrower on tablet
           portrait so the product grid still has room to breathe. -->
      <div v-if="!xs" class="pos-page__cart" :style="{ flexBasis: cartWidth + 'px', width: cartWidth + 'px' }">
        <PosCartPanel
          ref="cartPanelRef"
          :items="posStore.items"
          :subtotal="posStore.subtotal"
          :loading="submitting"
          show-notes
          @update-qty="posStore.updateQty"
          @update-notes="posStore.updateNotes"
          @remove="posStore.removeItem"
          @clear="posStore.clear"
          @checkout="submitOrder"
        />
      </div>
    </div>

    <!-- Mobile — sticky order bar opens the cart as a bottom sheet -->
    <div
      v-if="xs && branchId && posStore.items.length"
      class="pos-page__mobile-bar flex-grow-0 d-flex align-center px-4 py-3"
      @click="mobileCartOpen = true"
    >
      <v-badge :content="posStore.itemCount" color="white" inline class="me-2" />
      <span class="text-body-1 font-weight-medium">{{ t('pos.cart.view_order') }}</span>
      <v-spacer />
      <span class="text-h6 font-weight-black">{{ formatMoney(posStore.subtotal) }}</span>
    </div>

    <v-bottom-sheet v-if="xs" v-model="mobileCartOpen">
      <PosCartPanel
        class="pos-page__mobile-cart"
        :items="posStore.items"
        :subtotal="posStore.subtotal"
        :loading="submitting"
        show-notes
        @update-qty="posStore.updateQty"
        @update-notes="posStore.updateNotes"
        @remove="posStore.removeItem"
        @clear="posStore.clear"
        @checkout="onMobileCheckout"
      />
    </v-bottom-sheet>

    <PosReceiptDialog
      v-model="receiptOpen"
      :receipt="receipt"
      @close="receiptOpen = false"
    />

    <PosProductCustomizeDialog
      v-model="customizeOpen"
      :product="customizeProduct"
      @add="onAddFromDialog"
    />
  </div>
</template>

<script setup>
  import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useDisplay } from 'vuetify'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { usePosStore } from '@/stores/posStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { getAllCategoriesApi } from '@/api/categoryService'
  import { getAllTablesApi } from '@/api/tableService'
  import { getAllCustomersApi } from '@/api/customerService'
  import { getFoodPosProductsApi, submitFoodOrderApi } from '@/api/posService'
  import PosOrderOptionsBar from '@/components/pos/PosOrderOptionsBar.vue'
  import PosProductGrid from '@/components/pos/PosProductGrid.vue'
  import PosCartPanel from '@/components/pos/PosCartPanel.vue'
  import PosReceiptDialog from '@/components/pos/PosReceiptDialog.vue'
  import PosProductCustomizeDialog from '@/components/pos/PosProductCustomizeDialog.vue'

  const { t } = useI18n()
  const { xs, sm, mdAndDown: touch } = useDisplay()
  // Tablet portrait (~600-960px) gets a narrower cart column so the
  // product grid still has room to breathe; landscape tablet/desktop keep
  // the wider one.
  const cartWidth = computed(() => (sm.value ? 300 : 360))
  const { notif } = useAppUtils()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const posStore = usePosStore()

  // The active branch is chosen globally via the sidebar branch switcher
  // (or fixed to the staff's own branch) — see authStore.activeBranchId.
  const branchId = computed(() => authStore.activeBranchId)
  const selectedBranchName = computed(
    () => branchStore.branches.find(b => b.id === branchId.value)?.name ?? ''
  )
  const productGridRef = ref(null)
  const cartPanelRef = ref(null)
  const mobileCartOpen = ref(false)
  function onMobileCheckout(payload) {
    mobileCartOpen.value = false
    submitOrder(payload)
  }

  const customizeOpen = ref(false)
  const customizeProduct = ref(null)
  function openCustomize(product) {
    customizeProduct.value = product
    customizeOpen.value = true
  }
  function onAddFromDialog(payload) {
    const modifierKey = payload.modifier_option_ids.slice().sort().join(',')
    posStore.addItem({
      key: `${payload.product.id}:${payload.variant_id ?? 'base'}:${modifierKey}`,
      product_id: payload.product.id,
      variant_id: payload.variant_id,
      name: payload.product.name,
      variant_name: payload.variant_name,
      unit_price: payload.unit_price,
      qty: payload.quantity,
      notes: payload.notes,
      customizations: payload.customizations,
      modifier_option_ids: payload.modifier_option_ids
    })
  }

  // Only the order-type buttons/customer-picker/note-action the tenant has
  // actually enabled (Settings > POS) ever render — see PosOrderOptionsBar.
  const ORDER_TYPE_LABELS = {
    dine_in: () => t('pos.cart.order_type.dine_in'),
    takeaway: () => t('pos.cart.order_type.takeaway'),
    delivery: () => t('pos.cart.order_type.delivery')
  }
  const enabledOrderTypes = computed(() => {
    const enabled = authStore.posSettings?.order_types ?? ['dine_in', 'takeaway', 'delivery']
    return Object.keys(ORDER_TYPE_LABELS)
      .filter(key => enabled.includes(key))
      .map(key => ({ value: key, label: ORDER_TYPE_LABELS[key]() }))
  })
  const showCustomer = computed(() => authStore.posSettings?.customer_selection ?? true)
  const showNotes = computed(() => authStore.posSettings?.order_notes ?? true)

  const categories = ref([])
  const rawProducts = ref([])
  const categoryId = ref(null)
  const search = ref('')
  const loading = ref(false)

  const receiptOpen = ref(false)
  const receipt = ref(null)
  const submitting = ref(false)

  const tableOptions = ref([])
  const customerOptions = ref([])

  async function loadTables() {
    if (!branchId.value) return
    const { data } = await getAllTablesApi({
      branch_id: branchId.value,
      status: 'available',
      perPage: 100
    })
    tableOptions.value = data.data
  }

  async function onSearchCustomer(search) {
    const { data } = await getAllCustomersApi({ search: search || undefined, perPage: 20 })
    customerOptions.value = data.data
  }

  const normalizedProducts = computed(() =>
    rawProducts.value
      .filter(p => !categoryId.value || p.category_id === categoryId.value)
      .map(p => {
        const variants = p.variants ?? []

        const options = variants.length
          ? variants.map(v => ({
              key: `${p.id}:${v.id}`,
              product_id: p.id,
              variant_id: v.id,
              label: v.name,
              is_default: v.is_default,
              price: Number(p.base_price ?? 0) + Number(v.price_adjustment ?? 0)
            }))
          : [
              {
                key: p.id,
                product_id: p.id,
                variant_id: null,
                label: null,
                price: Number(p.base_price ?? 0)
              }
            ]

        const modifierGroups = (p.modifier_groups ?? []).map(g => ({
          id: g.id,
          name: g.name,
          selection_type: g.selection_type,
          is_required: g.is_required,
          min_selections: g.min_selections,
          max_selections: g.max_selections,
          options: (g.options ?? []).map(o => ({
            id: o.id,
            name: o.name,
            price_adjustment: Number(o.price_adjustment ?? 0),
            is_available: o.is_available
          }))
        }))

        return {
          id: p.id,
          name: p.name,
          image_url: p.image_url,
          base_price: Number(p.base_price ?? 0),
          options,
          modifier_groups: modifierGroups
        }
      })
  )

  async function loadCategories() {
    const { data } = await getAllCategoriesApi({ perPage: 100 })
    categories.value = data.data
  }

  async function loadProducts() {
    if (!branchId.value) return
    loading.value = true
    try {
      const { data } = await getFoodPosProductsApi({
        branch_id: branchId.value,
        search: search.value || undefined
      })
      rawProducts.value = data.data.data
    } finally {
      loading.value = false
    }
  }

  function onAdd({ product, option }) {
    posStore.addItem({
      key: option.key,
      product_id: option.product_id,
      variant_id: option.variant_id,
      name: product.name,
      variant_name: option.label,
      unit_price: option.price
    })
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value ?? 0)
  }

  async function submitOrder({ payment_method, cash_tendered }) {
    submitting.value = true
    try {
      const { data } = await submitFoodOrderApi({
        branch_id: branchId.value,
        payment_method,
        cash_tendered,
        order_type: posStore.orderType,
        table_id: posStore.orderType === 'dine_in' ? posStore.tableId || undefined : undefined,
        customer_id: posStore.customerId || undefined,
        notes: posStore.note || undefined,
        items: posStore.items.map(i => ({
          product_id: i.product_id,
          variant_id: i.variant_id || undefined,
          quantity: i.qty,
          note: i.notes || undefined,
          customizations: i.customizations?.length ? i.customizations : undefined,
          modifier_option_ids: i.modifier_option_ids?.length ? i.modifier_option_ids : undefined
        }))
      })
      receipt.value = data.data.prints.receipt
      receiptOpen.value = true
      posStore.clear()
      loadTables() // the table just used, if any, is now occupied
      notif(t('pos.checkout.success'), { type: 'success' })
    } catch (err) {
      notif(
        err.response?.data?.message ?? err.response?.data?.errors?.[0] ?? t('pos.checkout.failed'),
        { type: 'error' }
      )
    } finally {
      submitting.value = false
    }
  }

  // If the tenant disables whichever order type is currently selected
  // (including the default 'takeaway'), fall back to the first one still enabled.
  watch(enabledOrderTypes, types => {
    if (types.length && !types.some(o => o.value === posStore.orderType)) {
      posStore.setOrderType(types[0].value)
    }
  }, { immediate: true })

  watch(branchId, () => {
    loadProducts()
    loadTables()
  })
  watch(search, loadProducts)

  function handlePosShortcuts(e) {
    if (((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') || e.key === 'F2') {
      e.preventDefault()
      productGridRef.value?.focus()
    } else if (e.key === 'F10') {
      e.preventDefault()
      if (posStore.items.length) cartPanelRef.value?.submitCheckout()
    }
  }

  onMounted(async () => {
    window.addEventListener('keydown', handlePosShortcuts)
    await branchStore.fetchBranches?.()
    await loadCategories()
    if (branchId.value) {
      await loadProducts()
      await loadTables()
    }
  })

  onUnmounted(() => {
    window.removeEventListener('keydown', handlePosShortcuts)
  })
</script>

<style scoped>
  .pos-page {
    /* Approximate remaining viewport below the app bar + page container
       padding — the workspace below scrolls internally instead of the
       whole page, so a few px of slack here is harmless. */
    height: calc(100vh - 96px);
    min-height: 0;
    overflow: hidden;
  }
  .pos-page__workspace {
    min-height: 0;
  }
  .pos-page__products {
    min-width: 0;
    min-height: 0;
  }
  .pos-page__cart {
    flex-shrink: 0;
    min-height: 0;
  }
  .pos-page__mobile-bar {
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
    border-radius: 12px;
    cursor: pointer;
  }
  .pos-page__mobile-cart {
    max-height: 85vh;
    border-radius: 16px 16px 0 0;
  }
</style>
