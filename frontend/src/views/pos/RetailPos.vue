<template>
  <div class="pos-page d-flex flex-column">
    <!-- Compact header — back + title + branch + settings, nothing else -->
     <AppToolbar :title="t('pos.retail.title')" :subtitle="t('pos.retail.subtitle')">
      <template #actions>
        <v-chip
          :size="touch ? 'default' : 'small'"
          variant="tonal"
          rounded="lg"
          prepend-icon="mdi-store-outline"
          class="me-1"
        >
          {{ selectedBranchName || t('pos.select_branch') }}
        </v-chip>
        <v-btn
          icon="mdi-cog-outline"
          variant="text"
          :density="touch ? 'default' : 'comfortable'"
          :size="touch ? 'default' : 'small'"
          @click="settingsOpen = true"
        />
      </template>
    </AppToolbar>

    <AppDialog
      v-model="settingsOpen"
      :title="t('pos.settings.title')"
      :max-width="380"
    >
      <v-select
        v-model="customerType"
        :items="customerTypeOptions"
        item-title="label"
        item-value="value"
        :label="t('pos.retail.customer_type')"
        variant="outlined"
        rounded="lg"
      />
      <template #actions="{ loading }">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="settingsOpen = false">
          {{ t('btn.close') }}
        </v-btn>
      </template>
    </AppDialog>

    <PosOrderOptionsBar
      class="flex-grow-0 pb-2"
      :class="touch ? 'px-2' : 'px-1'"
      :order-type-options="[]"
      :show-customer="showCustomer"
      :customers="customerOptions"
      :customer-id="posStore.customerId"
      :customer-name="posStore.customerName"
      :show-notes="showNotes"
      :note="posStore.note"
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
          :products="normalizedProducts"
          :categories="categories"
          :category-id="categoryId"
          :search="search"
          :loading="loading"
          @update:search="search = $event"
          @update:category-id="categoryId = $event"
          @add="onAdd"
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
          @update-qty="posStore.updateQty"
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
        @update-qty="posStore.updateQty"
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
  import {
    getRetailPosProductsApi,
    getRetailPosCategoriesApi,
    submitRetailOrderApi
  } from '@/api/posService'
  import { getAllCustomersApi } from '@/api/customerService'
  import { AppDialog, AppToolbar } from '@nong-official-dev/core'
  import PosOrderOptionsBar from '@/components/pos/PosOrderOptionsBar.vue'
  import PosProductGrid from '@/components/pos/PosProductGrid.vue'
  import PosCartPanel from '@/components/pos/PosCartPanel.vue'
  import PosReceiptDialog from '@/components/pos/PosReceiptDialog.vue'

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
  const customerType = ref('retail')
  const customerTypeOptions = computed(() => [
    { value: 'retail', label: t('pos.retail.customer_types.retail') },
    { value: 'wholesale', label: t('pos.retail.customer_types.wholesale') }
  ])
  const settingsOpen = ref(false)
  const mobileCartOpen = ref(false)
  function onMobileCheckout(payload) {
    mobileCartOpen.value = false
    submitOrder(payload)
  }
  const selectedBranchName = computed(
    () => branchStore.branches.find(b => b.id === branchId.value)?.name ?? ''
  )
  const productGridRef = ref(null)
  const cartPanelRef = ref(null)

  // Mart has no table/dine-in concept — only Customer + Notes are
  // tenant-configurable here (Settings > POS).
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

  const customerOptions = ref([])
  async function onSearchCustomer(search) {
    const { data } = await getAllCustomersApi({ search: search || undefined, perPage: 20 })
    customerOptions.value = data.data
  }

  const normalizedProducts = computed(() =>
    rawProducts.value.map(p => {
      const units = (p.active_units ?? [])
        .filter(u => u.is_active)
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))

      const options = units.length
        ? units.map(u => ({
            key: `${p.id}:${u.id}`,
            product_id: p.id,
            product_unit_id: u.id,
            label: u.unit_label || u.unit_name,
            price:
              customerType.value === 'wholesale'
                ? Number(u.wholesale_price ?? u.retail_price ?? 0)
                : Number(u.retail_price ?? 0)
          }))
        : [
            {
              key: p.id,
              product_id: p.id,
              product_unit_id: null,
              label: null,
              price: Number(p.selling_price ?? p.base_price ?? 0)
            }
          ]

      return {
        id: p.id,
        name: p.name,
        image_url: p.image_url,
        options,
        stockStatus: stockStatus(p)
      }
    })
  )

  function stockStatus(p) {
    const qty = Number(p.stock_quantity ?? 0)
    if (qty <= 0) return 'out_of_stock'
    if (p.reorder_level && qty <= Number(p.reorder_level)) return 'low_stock'
    return 'in_stock'
  }

  async function loadCategories() {
    if (!branchId.value) return
    const { data } = await getRetailPosCategoriesApi({ branch_id: branchId.value })
    categories.value = data.data
  }

  async function loadProducts() {
    if (!branchId.value) return
    loading.value = true
    try {
      const { data } = await getRetailPosProductsApi({
        branch_id: branchId.value,
        category_id: categoryId.value || undefined,
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
      product_unit_id: option.product_unit_id,
      name: product.name,
      unit_label: option.label,
      unit_price: option.price
    })
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value ?? 0)
  }

  async function submitOrder({ payment_method, cash_tendered }) {
    submitting.value = true
    try {
      const { data } = await submitRetailOrderApi({
        branch_id: branchId.value,
        payment_method,
        cash_tendered,
        customer_type: customerType.value,
        customer_id: posStore.customerId || undefined,
        notes: posStore.note || undefined,
        items: posStore.items.map(i => ({
          product_id: i.product_id,
          product_unit_id: i.product_unit_id || undefined,
          quantity: i.qty
        }))
      })
      receipt.value = data.data.receipt
      receiptOpen.value = true
      posStore.clear()
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

  watch(branchId, () => {
    loadCategories()
    loadProducts()
  })
  watch([categoryId, search], loadProducts)

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
    if (branchId.value) {
      await Promise.all([loadCategories(), loadProducts()])
    }
  })

  onUnmounted(() => {
    window.removeEventListener('keydown', handlePosShortcuts)
  })
</script>

<style scoped>
  .pos-page {
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
