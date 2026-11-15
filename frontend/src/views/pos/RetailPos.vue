<template>
  <div class="pos-page d-flex flex-column" :class="{ 'pos-page--no-branch': !branchId }">
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

    <v-alert
      v-if="!branchId"
      type="warning"
      variant="tonal"
      rounded="lg"
      class="pos-no-branch-alert mb-2"
    >
      <div class="d-flex flex-column flex-sm-row align-start align-sm-center ga-3">
        <div>
          <div class="font-weight-medium">{{ t('pos.no_branch.title') }}</div>
          <div class="text-caption">{{ t('pos.no_branch.subtitle') }}</div>
        </div>
        <v-spacer />
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          size="small"
          prepend-icon="mdi-plus"
          :to="{ name: 'settings-hub', query: { section: 'branches' } }"
        >
          {{ t('pos.no_branch.action') }}
        </v-btn>
      </div>
    </v-alert>

    <template v-else>
      <PosOrderOptionsBar
        class="flex-grow-0 pb-2"
        :class="touch ? 'px-2' : 'px-1'"
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

      <div class="pos-page__workspace flex-grow-1 d-flex ga-2">
      <div class="pos-page__products flex-grow-1">
        <PosProductGrid
          ref="productGridRef"
          :products="normalizedProducts"
          :categories="posCategories"
          :category-id="categoryId"
          :search="search"
          :loading="loading"
          :cart-quantities="cartQuantityByProduct"
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
          :payment-methods="authStore.posSettings?.payment_methods ?? ['cash', 'card', 'qr', 'pay_later']"
          :default-payment-method="authStore.posSettings?.default_payment_method ?? 'cash'"
          @update-qty="posStore.updateQty"
          @remove="posStore.removeItem"
          @clear="posStore.clear"
          @checkout="submitOrder"
          @qr-open="openQrPending"
        />
      </div>
      </div>
    </template>

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
        :payment-methods="authStore.posSettings?.payment_methods ?? ['cash', 'card', 'qr', 'pay_later']"
        :default-payment-method="authStore.posSettings?.default_payment_method ?? 'cash'"
        @update-qty="posStore.updateQty"
        @remove="posStore.removeItem"
        @clear="posStore.clear"
        @checkout="onMobileCheckout"
        @qr-open="openQrPending"
      />
    </v-bottom-sheet>

    <PosReceiptDialog
      ref="receiptDialogRef"
      v-model="receiptOpen"
      :receipt="receipt"
      :printer-name="activeBranchPrinterName"
      :qr-pending="qrPending"
      :qr-image-url="branchCdSettings.merchant_qr_image_url"
      :qr-total="posStore.subtotal"
      :qr-items="posStore.items"
      :submitting="submitting"
      @close="receiptOpen = false"
      @confirm="onQrConfirmed"
      @cancel="onQrCancelled"
    />

    <PosProductCustomizeDialog
      v-model="customizeOpen"
      :product="customizeProduct"
      @add="onAddFromDialog"
    />
  </div>
</template>

<script setup>
  import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
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
  import PosProductCustomizeDialog from '@/components/pos/PosProductCustomizeDialog.vue'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import {
    useCustomerDisplayPublisher,
    buildCustomerDisplayState
  } from '@/composables/useCustomerDisplay'

  const { t, locale } = useI18n()
  const { format: formatMoney } = useCurrency()
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
  const activeBranch = computed(() => branchStore.branches.find(b => b.id === branchId.value))
  const selectedBranchName = computed(() => activeBranch.value?.name ?? '')
  const branchCdSettings = computed(() => activeBranch.value?.customer_display_settings ?? {})

  // ── Customer display sync ──────────────────────────────────────────────
  const customerDisplay = useCustomerDisplayPublisher(branchId)
  const suppressCartPublish = ref(false)

  function publishCartState() {
    if (suppressCartPublish.value) return
    customerDisplay.publish(
      buildCustomerDisplayState({
        state: posStore.items.length ? 'order' : 'idle',
        authStore,
        posStore,
        branchName: selectedBranchName.value,
        branchSettings: branchCdSettings.value
      })
    )
  }

  watch(() => posStore.items, publishCartState, { deep: true, immediate: true })

  const activeBranchPrinterName = computed(
    () => branchStore.branches.find(b => b.id === branchId.value)?.printer_name ?? null
  )
  const productGridRef = ref(null)
  const cartPanelRef = ref(null)

  const customizeOpen = ref(false)
  const customizeProduct = ref(null)
  function openCustomize(product) {
    customizeProduct.value = product
    customizeOpen.value = true
  }

  // Mart has no table/dine-in concept — only Customer + Notes are
  // tenant-configurable here (Settings > POS).
  const showCustomer = computed(() => authStore.posSettings?.customer_selection ?? true)
  const showNotes = computed(() => authStore.posSettings?.order_notes ?? true)

  const categories = ref([])
  const rawProducts = ref([])
  const categoryId = ref(null)
  const search = ref('')
  // Starts true, not false — onMounted awaits branchStore.fetchBranches()
  // BEFORE loadProducts() ever runs (that's the only place this flips to
  // false), so a false initial value left a real gap where products.length
  // was still 0 but loading hadn't been set yet, flashing "No products
  // found" before the skeleton ever showed.
  const loading = ref(true)

  const receiptOpen = ref(false)
  const receipt = ref(null)
  const submitting = ref(false)
  const receiptDialogRef = ref(null)
  // True while the Receipt dialog is showing the QR-confirm content (amount
  // + QR code + "Confirm & Print"/"Confirm & New Sale") instead of an
  // actual printed receipt — flips false once the order submits, swapping
  // the SAME open dialog over to the normal receipt view.
  const qrPending = ref(false)
  // Which combined action the cashier picked on the QR-pending screen —
  // read once the order actually submits (see submitOrder's success path)
  // so one click both confirms AND immediately prints/closes, instead of
  // a separate "Confirm" click followed by a separate "Print"/"New sale" click.
  const qrConfirmAction = ref(null)

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
        image_fit: p.image_fit ?? 'cover',
        options,
        stockStatus: stockStatus(p)
      }
    })
  )

  // product_id -> total qty across every unit currently in the cart —
  // PosProductGrid uses this for the per-card qty badge/selected border.
  const cartQuantityByProduct = computed(() =>
    posStore.items.reduce((acc, item) => {
      acc[item.product_id] = (acc[item.product_id] ?? 0) + item.qty
      return acc
    }, {})
  )

  function stockStatus(p) {
    const qty = Number(p.stock_quantity ?? 0)
    if (qty <= 0) return 'out_of_stock'
    if (p.reorder_level && qty <= Number(p.reorder_level)) return 'low_stock'
    return 'in_stock'
  }

  async function loadCategories() {
    if (!branchId.value) return
    try {
      const { data } = await getRetailPosCategoriesApi({ branch_id: branchId.value })
      categories.value = data.data
    } catch {
      notif(t('pos.load_failed'), { type: 'error' })
    }
  }

  // Server already limits this to show_on_pos categories with at least one
  // available product, sorted by sort_order — only the Khmer-name-fallback
  // display mapping happens client-side.
  const posCategories = computed(() =>
    categories.value.map(c => ({
      ...c,
      name: locale.value === 'km' && c.name_km ? c.name_km : c.name
    }))
  )

  // Every keystroke/category change re-fires this (see
  // watch([categoryId, search], ...) below) with no debounce — fast typing
  // can have an earlier request resolve AFTER a later one, which would
  // otherwise overwrite the correct (newer) results with stale ones and
  // flash "No products found" for a query that no longer matches what's in
  // the search box. loadProductsSeq makes only the most-recently-dispatched
  // call allowed to touch state.
  let loadProductsSeq = 0
  async function loadProducts() {
    if (!branchId.value) return
    const seq = ++loadProductsSeq
    loading.value = true
    try {
      const { data } = await getRetailPosProductsApi({
        branch_id: branchId.value,
        category_id: categoryId.value || undefined,
        search: search.value || undefined
      })
      if (seq !== loadProductsSeq) return
      rawProducts.value = data.data.data
    } catch {
      if (seq !== loadProductsSeq) return
      notif(t('pos.load_failed'), { type: 'error' })
    } finally {
      if (seq === loadProductsSeq) loading.value = false
    }
  }

  function onAdd({ product, option }) {
    posStore.addItem({
      key: option.key,
      product_id: option.product_id,
      product_unit_id: option.product_unit_id,
      name: product.name,
      name_km: product.name_km,
      image_url: product.image_url,
      unit_label: option.label,
      unit_price: option.price
    })
  }

  // PosProductCustomizeDialog.vue's unit picker, reused here for products
  // with more than one unit — it emits the full selected option object
  // (product_unit_id, label, price), same shape onAdd() already consumes.
  function onAddFromDialog(payload) {
    posStore.addItem({
      key: payload.option.key,
      product_id: payload.option.product_id,
      product_unit_id: payload.option.product_unit_id,
      name: payload.product.name,
      name_km: payload.product.name_km,
      image_url: payload.product.image_url,
      unit_label: payload.option.label,
      unit_price: payload.unit_price,
      qty: payload.quantity,
      notes: payload.notes
    })
  }

  // Opens the Receipt dialog in its QR-pending stage — amount + QR code +
  // "Confirm receive" — and pushes the same QR screen to the customer
  // display, both the instant the cashier picks QR, before the order even
  // exists. The dialog stays open and later swaps to the real receipt (see
  // onQrConfirmed) rather than closing and reopening a second dialog.
  function openQrPending() {
    qrPending.value = true
    receiptOpen.value = true
    customerDisplay.publish(
      buildCustomerDisplayState({
        state: 'pay_qr',
        authStore,
        posStore,
        branchName: selectedBranchName.value,
        branchSettings: branchCdSettings.value,
        payment: { method: 'qr' }
      })
    )
  }

  function onQrConfirmed(action) {
    qrConfirmAction.value = action
    submitOrder({ payment_method: 'qr', cash_tendered: undefined })
  }

  function onQrCancelled() {
    qrPending.value = false
    receiptOpen.value = false
    publishCartState()
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
      // Pay later collects nothing at checkout — there's no payment to
      // receipt yet, so there's nothing worth printing until someone
      // actually settles it from the Orders page.
      if (payment_method !== 'pay_later') {
        receipt.value = data.data.receipt
        receiptOpen.value = true
      }
      // The QR-pending view (items + QR + total) never swaps to a
      // different "receipt" layout — one click both confirms AND
      // immediately prints/closes, with no visible change in between.
      // print() still uses the real `receipt` set just above; closing
      // afterward is what actually ends the QR dialog, not a qrPending flip.
      if (payment_method === 'qr') {
        ;(async () => {
          if (qrConfirmAction.value === 'print') {
            await nextTick()
            await receiptDialogRef.value?.print()
          }
          receiptOpen.value = false
          qrPending.value = false
        })()
        qrConfirmAction.value = null
      }

      const receiptData = data.data.receipt
      const delaySeconds = Number(branchCdSettings.value.thank_you_delay_seconds) || 10

      suppressCartPublish.value = true
      customerDisplay.publish(
        buildCustomerDisplayState({
          state: 'thanks',
          authStore,
          posStore,
          branchName: selectedBranchName.value,
          branchSettings: branchCdSettings.value,
          orderNumber: receiptData.order_number,
          totals: { totalUsd: receiptData.total, discount: receiptData.discount },
          payment: {
            method: receiptData.payment_method,
            paidBase: receiptData.cash_tendered,
            changeBase: receiptData.change_given
          }
        })
      )

      posStore.clear()
      notif(t('pos.checkout.success'), { type: 'success' })

      setTimeout(() => {
        customerDisplay.publish(
          buildCustomerDisplayState({
            state: 'idle',
            authStore,
            posStore,
            branchName: selectedBranchName.value,
            branchSettings: branchCdSettings.value
          })
        )
        suppressCartPublish.value = false
      }, delaySeconds * 1000)
    } catch (err) {
      if (payment_method === 'qr') {
        qrPending.value = false
        qrConfirmAction.value = null
        receiptOpen.value = false
        publishCartState()
      }
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

    // authStore.activeBranchId can be stale (persisted from a previous
    // session/tenant, or a branch that's since been deleted) — the API
    // 404s on a branch_id that doesn't resolve for the current tenant.
    // Re-validate against the just-fetched list rather than trusting it
    // blindly; the sidebar's own BranchSwitcher does the same self-repair
    // on its own mount, but that's a separate component with no
    // guaranteed ordering against this page's mount.
    if (branchId.value && !branchStore.branches.some(b => b.id === branchId.value)) {
      authStore.setActiveBranch(branchStore.branches[0]?.id ?? null)
    }

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
    /* dvh (not vh) so the on-screen keyboard shrinking the visual viewport
       doesn't just get the page clipped by a stale 100vh. */
    height: calc(100dvh - 96px);
    min-height: 0;
    overflow: hidden;
  }
  /* No-branch empty state has no scrollable workspace to constrain —
     drop the fixed height/overflow-hidden entirely so the alert (and its
     wrapped title/subtitle/button content) always renders at its natural
     size instead of being squeezed and clipped by a flex ancestor that
     assumes there's a product grid + cart filling the rest of the page. */
  .pos-page--no-branch {
    height: auto;
    min-height: calc(100dvh - 96px);
    overflow: visible;
  }
  /* Vuetify's own .v-alert base rule sets `overflow: hidden` and
     `flex: 1 1` (i.e. flex-basis: 0%, shrinkable) unconditionally — that's
     what was actually clipping the button, independent of any sizing on
     ancestor elements. Override both directly rather than relying on
     flex-grow-0/flex-shrink-0 utility classes, whose cascade order against
     Vuetify's own component styles isn't guaranteed. */
  .pos-no-branch-alert {
    overflow: visible !important;
    flex: 0 0 auto !important;
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
