<template>
  <div>
    <AppToolbar :title="t('pos.food.title')">
      <template #actions>
        <v-btn-toggle
          v-if="enabledOrderTypes.length > 1"
          :model-value="posStore.orderType"
          color="primary"
          variant="tonal"
          :density="touch ? 'default' : 'compact'"
          rounded="lg"
          mandatory
          @update:model-value="posStore.setOrderType"
        >
          <v-btn
            v-for="ot in enabledOrderTypes"
            :key="ot.value"
            :value="ot.value"
            :size="touch ? 'default' : 'small'"
            class="text-none"
            :class="touch ? 'px-5' : 'px-3'"
          >
            {{ ot.label }}
          </v-btn>
        </v-btn-toggle>

        <v-btn
          v-if="posStore.orderType === 'dine_in' && tableOptions.length"
          :color="posStore.tableId ? 'primary' : 'warning'"
          :variant="posStore.tableId ? 'tonal' : 'flat'"
          rounded="lg"
          prepend-icon="mdi-table-furniture"
          @click="tableSheetOpen = true"
        >
          {{ tableButtonLabel }}
        </v-btn>
      </template>
    </AppToolbar>

    <v-bottom-sheet v-model="tableSheetOpen">
      <v-card rounded="t-xl">
        <div class="pa-4 d-flex align-center">
          <span class="text-body-large font-weight-bold">
            {{ t('pos.cart.choose_table') }}
          </span>
          <v-spacer />
          <v-btn
            v-if="posStore.tableId"
            variant="text"
            size="small"
            @click="selectTable(null)"
          >
            {{ t('pos.cart.clear_table') }}
          </v-btn>
          <v-btn
            icon="mdi-close"
            variant="text"
            @click="tableSheetOpen = false"
          />
        </div>
        <v-divider />
        <v-row class="pa-4 ma-0" density="compact">
          <v-col v-for="tb in tableOptions" :key="tb.id" cols="4" sm="3" md="2">
            <v-card
              :color="
                posStore.tableId === tb.id
                  ? 'primary'
                  : tableStatusColor(tb.status)
              "
              :variant="posStore.tableId === tb.id ? 'flat' : 'tonal'"
              rounded="lg"
              class="pa-3 text-center table-sheet-card"
              :class="{ 'table-sheet-card--disabled': !isTablePickable(tb) }"
              @click="isTablePickable(tb) && selectTable(tb.id)"
            >
              <div class="text-title-large font-weight-bold">{{ tb.table_number }}</div>
              <div class="d-flex align-center justify-center ga-1 text-body-small">
                <v-icon icon="mdi-account-group-outline" size="14" />
                {{ tb.capacity }}
              </div>
              <div class="text-body-small font-weight-medium mt-1">
                {{ tableStatusLabel(tb.status) }}
              </div>
            </v-card>
          </v-col>
        </v-row>
        <v-alert
          v-if="!tableOptions.length"
          type="info"
          variant="tonal"
          rounded="lg"
          class="ma-4"
        >
          {{ t('pos.cart.no_tables_available') }}
        </v-alert>
      </v-card>
    </v-bottom-sheet>
    <div
      class="pos-page d-flex flex-column"
      :class="{ 'pos-page--no-branch': !branchId }"
    >
      <v-alert
        v-if="!branchId"
        type="warning"
        variant="tonal"
        rounded="lg"
        class="pos-no-branch-alert mb-2"
      >
        <div
          class="d-flex flex-column flex-sm-row align-start align-sm-center ga-3"
        >
          <div>
            <div class="font-weight-medium">{{ t('pos.no_branch.title') }}</div>
            <div class="text-body-small">{{ t('pos.no_branch.subtitle') }}</div>
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
          :loyalty-points="attachedCustomerLoyaltyPoints"
          :show-notes="showNotes"
          :note="posStore.note"
          @search-customer="onSearchCustomer"
          @update-customer="posStore.setCustomer"
          @update-note="posStore.setNote"
          @open-redeem="redeemDialogOpen = true"
        />

        <LoyaltyRedeemDialog
          v-model="redeemDialogOpen"
          @applied="onRedemptionApplied"
        />

        <MobileOrdersCarousel
          v-if="showMobileOrders"
          :branch-id="branchId"
          class="flex-grow-0 mb-2"
          :class="touch ? 'px-2' : 'px-1'"
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
          <div
            v-if="!xs"
            class="pos-page__cart"
            :style="{ flexBasis: cartWidth + 'px', width: cartWidth + 'px' }"
          >
            <PosCartPanel
              ref="cartPanelRef"
              :items="posStore.items"
              :subtotal="posStore.subtotal"
              :discount-amount="posStore.discountAmount"
              :loading="submitting"
              :payment-methods="
                authStore.posSettings?.payment_methods ?? [
                  'cash',
                  'card',
                  'qr',
                  'pay_later'
                ]
              "
              :default-payment-method="
                authStore.posSettings?.default_payment_method ?? 'cash'
              "
              :before-checkout="validateBeforeCheckout"
              :qr-image-url="branchCdSettings.merchant_qr_image_url"
              show-notes
              @update-qty="posStore.updateQty"
              @update-notes="posStore.updateNotes"
              @remove="posStore.removeItem"
              @clear="posStore.clear"
              @checkout="submitOrder"
              @checkout-opened="onCheckoutOpened"
              @checkout-cancelled="onCheckoutCancelled"
              @remove-redemption="posStore.setRedemption(null)"
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
        <v-badge
          :content="posStore.itemCount"
          color="white"
          inline
          class="me-2"
        />
        <span class="text-body-large font-weight-medium">
          {{ t('pos.cart.view_order') }}
        </span>
        <v-spacer />
        <span class="text-title-large font-weight-black">
          {{ formatMoney(posStore.payableTotal) }}
        </span>
      </div>

      <v-bottom-sheet v-if="xs" v-model="mobileCartOpen">
        <PosCartPanel
          class="pos-page__mobile-cart"
          :items="posStore.items"
          :subtotal="posStore.subtotal"
          :discount-amount="posStore.discountAmount"
          :loading="submitting"
          :payment-methods="
            authStore.posSettings?.payment_methods ?? [
              'cash',
              'card',
              'qr',
              'pay_later'
            ]
          "
          :default-payment-method="
            authStore.posSettings?.default_payment_method ?? 'cash'
          "
          :before-checkout="validateBeforeCheckout"
          :qr-image-url="branchCdSettings.merchant_qr_image_url"
          show-notes
          @update-qty="posStore.updateQty"
          @update-notes="posStore.updateNotes"
          @remove="posStore.removeItem"
          @clear="posStore.clear"
          @checkout="onMobileCheckout"
          @checkout-opened="onCheckoutOpened"
          @checkout-cancelled="onCheckoutCancelled"
          @remove-redemption="posStore.setRedemption(null)"
        />
      </v-bottom-sheet>

      <PosReceiptDialog
        v-model="receiptOpen"
        :receipt="receipt"
        :printer-name="activeBranchPrinterName"
        @close="receiptOpen = false"
      />

      <PosProductCustomizeDialog
        v-model="customizeOpen"
        :product="customizeProduct"
        @add="onAddFromDialog"
      />
    </div>
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
  import { confirmLoyaltyRedemptionApi } from '@/api/loyaltyPosService'
  import PosOrderOptionsBar from '@/components/pos/PosOrderOptionsBar.vue'
  import PosProductGrid from '@/components/pos/PosProductGrid.vue'
  import PosCartPanel from '@/components/pos/PosCartPanel.vue'
  import LoyaltyRedeemDialog from '@/components/pos/LoyaltyRedeemDialog.vue'
  import PosReceiptDialog from '@/components/pos/PosReceiptDialog.vue'
  import PosProductCustomizeDialog from '@/components/pos/PosProductCustomizeDialog.vue'
  import MobileOrdersCarousel from '@/components/pos/MobileOrdersCarousel.vue'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useReceipt, printTicketBrowser } from '@/utils/printReceipt'
  import { AppToolbar } from '@nong-official-dev/core'
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

  const activeBranchPrinterName = computed(
    () =>
      branchStore.branches.find(b => b.id === branchId.value)?.printer_name ??
      null
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
    // Merge identity: same product + size + every selected option + any
    // custom values + the note, all identical — anything that differs
    // (including the note) must stay its own cart line.
    const modifierKey = payload.modifier_option_ids.slice().sort().join(',')
    const customKey = (payload.custom_modifier_values ?? [])
      .map(c => `${c.group_id}=${c.value}`)
      .sort()
      .join(',')
    const noteKey = (payload.notes ?? '').trim().toLowerCase()
    posStore.addItem({
      key: `${payload.product.id}:${payload.variant_id ?? 'base'}:${modifierKey}:${customKey}:${noteKey}`,
      product_id: payload.product.id,
      variant_id: payload.variant_id,
      name: payload.product.name,
      name_km: payload.product.name_km,
      image_url: payload.product.image_url,
      variant_name: payload.variant_name,
      unit_price: payload.unit_price,
      qty: payload.quantity,
      notes: payload.notes,
      customizations: payload.customizations,
      modifier_option_ids: payload.modifier_option_ids,
      custom_modifier_values: payload.custom_modifier_values
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
    const enabled = authStore.posSettings?.order_types ?? [
      'dine_in',
      'takeaway',
      'delivery'
    ]
    return Object.keys(ORDER_TYPE_LABELS)
      .filter(key => enabled.includes(key))
      .map(key => ({ value: key, label: ORDER_TYPE_LABELS[key]() }))
  })
  const showCustomer = computed(
    () => authStore.posSettings?.customer_selection ?? true
  )
  const showNotes = computed(() => authStore.posSettings?.order_notes ?? true)
  const showMobileOrders = computed(
    () => authStore.posSettings?.show_mobile_menu_orders !== false
  )

  const categories = ref([])
  const rawProducts = ref([])
  const categoryId = ref(null)
  const search = ref('')
  // Starts true, not false — onMounted awaits branchStore.fetchBranches()
  // and loadCategories() BEFORE loadProducts() ever runs (that's the only
  // place this flips to false), so a false initial value left a real gap
  // where products.length was still 0 but loading hadn't been set yet,
  // flashing "No products found" before the skeleton ever showed.
  const loading = ref(true)

  const receiptOpen = ref(false)
  const receipt = ref(null)
  const submitting = ref(false)

  // Fire-and-forget kitchen/barista ticket print — independent of whether
  // the customer receipt itself is enabled (a tenant may want prep tickets
  // only, receipt only, both, or neither). A failed kitchen printer must
  // never block checkout, so errors are swallowed here the same way
  // useReceipt().print()'s own failures are (logged, not surfaced to the UI).
  const { printTicket } = useReceipt()
  async function maybePrintKitchenTicket(ticket) {
    if (!ticket || !authStore.posSettings?.print_kitchen_ticket) return
    try {
      const ok = await printTicket(ticket, activeBranchPrinterName.value)
      if (!ok) await printTicketBrowser(ticket)
    } catch (e) {
      console.warn('[FoodPos] kitchen ticket print failed:', e)
    }
  }

  const tableOptions = ref([])
  const customerOptions = ref([])
  const tableSheetOpen = ref(false)

  // ── Customer display sync ──────────────────────────────────────────────
  const customerDisplay = useCustomerDisplayPublisher(branchId)
  // Held true from the moment an order's final 'thanks' state is published
  // until the thank-you delay elapses — otherwise posStore.clear() right
  // after submit would make the reactive cart watcher below immediately
  // stomp 'thanks' with 'idle'.
  const suppressCartPublish = ref(false)

  const activeBranch = computed(() =>
    branchStore.branches.find(b => b.id === branchId.value)
  )
  const currentBranchName = computed(() => activeBranch.value?.name ?? '')
  const branchCdSettings = computed(
    () => activeBranch.value?.customer_display_settings ?? {}
  )
  const currentTableName = computed(
    () =>
      tableOptions.value.find(tb => tb.id === posStore.tableId)?.table_number ??
      null
  )

  function publishCartState() {
    if (suppressCartPublish.value) return
    customerDisplay.publish(
      buildCustomerDisplayState({
        state: posStore.items.length ? 'order' : 'idle',
        authStore,
        posStore,
        branchName: currentBranchName.value,
        branchSettings: branchCdSettings.value,
        tableName:
          posStore.orderType === 'dine_in' ? currentTableName.value : null
      })
    )
  }

  watch(() => posStore.items, publishCartState, { deep: true, immediate: true })
  watch(() => [posStore.orderType, posStore.tableId], publishCartState)

  const tableButtonLabel = computed(() => {
    const table = tableOptions.value.find(tb => tb.id === posStore.tableId)
    return table
      ? `${t('tables.table_prefix')} ${table.table_number}`
      : t('pos.cart.table_label')
  })

  function selectTable(id) {
    posStore.setTable(id)
    tableSheetOpen.value = false
  }

  // Occupied is pickable on purpose — rings up another round for a
  // customer who's already seated. Reserved/cleaning are not: those tables
  // aren't in a state where taking an order makes sense yet.
  const isTablePickable = tb => ['available', 'occupied'].includes(tb.status)

  const tableStatusColor = status =>
    ({
      available: 'success',
      occupied: 'warning',
      reserved: 'grey',
      cleaning: 'grey'
    })[status] || 'grey'

  const tableStatusLabel = status =>
    ({
      available: t('tables.status.available'),
      occupied: t('tables.status.occupied'),
      reserved: t('tables.status.reserved'),
      cleaning: t('tables.status.cleaning')
    })[status] || status

  // Fetches every table regardless of status — an occupied table must still
  // be pickable so staff can ring up a second (or third) round for a
  // customer who's already seated, not just a brand-new table. The sheet
  // itself disables reserved/cleaning tables rather than hiding them.
  async function loadTables() {
    if (!branchId.value) return
    const { data } = await getAllTablesApi({
      branch_id: branchId.value,
      perPage: -1
    })
    tableOptions.value = data.data
  }

  async function onSearchCustomer(search) {
    const { data } = await getAllCustomersApi({
      search: search || undefined,
      perPage: 20
    })
    customerOptions.value = data.data
  }

  // CustomerResource already includes loyalty_points — reusing the search
  // result instead of a separate lookup once a customer is attached.
  const attachedCustomerLoyaltyPoints = computed(
    () =>
      customerOptions.value.find(c => c.id === posStore.customerId)
        ?.loyalty_points ?? null
  )

  const redeemDialogOpen = ref(false)
  function onRedemptionApplied(redemption) {
    posStore.setRedemption(redemption)
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
          name_km: g.name_km,
          selection_type: g.selection_type,
          is_required: g.is_required,
          min_selections: g.min_selections,
          max_selections: g.max_selections,
          allow_custom_value: g.allow_custom_value,
          custom_suffix: g.custom_suffix,
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
          name_km: p.name_km,
          image_url: p.image_url,
          image_fit: p.image_fit ?? 'cover',
          base_price: Number(p.base_price ?? 0),
          options,
          modifier_groups: modifierGroups
        }
      })
  )

  // product_id -> total qty across every size/variant currently in the
  // cart — PosProductGrid uses this for the per-card qty badge/selected
  // border, it has no other awareness of cart state.
  const cartQuantityByProduct = computed(() =>
    posStore.items.reduce((acc, item) => {
      acc[item.product_id] = (acc[item.product_id] ?? 0) + item.qty
      return acc
    }, {})
  )

  async function loadCategories() {
    const { data } = await getAllCategoriesApi({ perPage: -1 })
    categories.value = data.data
  }

  // Quick Sale chips: only categories flagged for POS, sorted by sort_order,
  // Khmer name shown when the active locale is Khmer (falling back to the
  // English name if no translation was set), and hidden entirely once every
  // product in it has sold out or gone unavailable — rawProducts only ever
  // contains available products (see getFoodPosProductsApi's is_available
  // filter), so a category absent from that count has nothing left to sell.
  const posCategories = computed(() => {
    const availableCounts = rawProducts.value.reduce((acc, p) => {
      acc[p.category_id] = (acc[p.category_id] ?? 0) + 1
      return acc
    }, {})

    return categories.value
      .filter(c => c.show_on_pos !== false && availableCounts[c.id] > 0)
      .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
      .map(c => ({
        ...c,
        name: locale.value === 'km' && c.name_km ? c.name_km : c.name
      }))
  })

  // Every keystroke in the search box re-fires this (see watch(search, ...)
  // below) with no debounce — fast typing can have an earlier request
  // resolve AFTER a later one, which would otherwise overwrite the correct
  // (newer) results with stale ones and flash "No products found" for a
  // query that no longer matches what's in the search box. loadSeq makes
  // only the most-recently-dispatched call allowed to touch state.
  let loadProductsSeq = 0
  async function loadProducts() {
    if (!branchId.value) return
    const seq = ++loadProductsSeq
    loading.value = true
    try {
      const { data } = await getFoodPosProductsApi({
        branch_id: branchId.value,
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
      variant_id: option.variant_id,
      name: product.name,
      name_km: product.name_km,
      image_url: product.image_url,
      variant_name: option.label,
      unit_price: option.price
    })
  }

  // Runs before ANY payment dialog opens (cash, qr) or an order submits —
  // dine-in without a table is a checkout we'd rather not allow when
  // tables actually exist to pick from, so this must gate BEFORE the QR
  // dialog too, not just inside submitOrder() — otherwise the cashier
  // only discovers the missing table after the customer already scanned
  // and paid. If the tenant has no tables configured at all, there's
  // nothing to pick, so fall through and let it through as before.
  function validateBeforeCheckout() {
    if (
      posStore.orderType === 'dine_in' &&
      !posStore.tableId &&
      tableOptions.value.length
    ) {
      tableSheetOpen.value = true
      notif(t('pos.cart.choose_table_prompt'), { type: 'warning' })
      return false
    }
    return true
  }

  // Pushes the QR screen to the customer display the instant the cashier
  // picks QR, before the order even exists — independent of the cashier's
  // own checkout dialog (PosCartPanel owns opening/closing that for every
  // payment method now). A no-op for the other three methods.
  function onCheckoutOpened({ payment_method }) {
    if (payment_method !== 'qr') return
    customerDisplay.publish(
      buildCustomerDisplayState({
        state: 'pay_qr',
        authStore,
        posStore,
        branchName: currentBranchName.value,
        branchSettings: branchCdSettings.value,
        tableName:
          posStore.orderType === 'dine_in' ? currentTableName.value : null,
        payment: { method: 'qr' }
      })
    )
  }

  // Checkout dialog dismissed without confirming, for any payment method —
  // revert the customer display back to the normal cart mirror (a no-op for
  // cash/card/pay_later, which never published anything else to begin with).
  function onCheckoutCancelled() {
    publishCartState()
  }

  async function submitOrder({ payment_method, cash_tendered }) {
    // Belt-and-suspenders — PosCartPanel's beforeCheckout prop already
    // gates this before any dialog opens, but submitOrder() is reachable
    // independently (e.g. a future caller), so re-check rather than rely
    // solely on the UI layer.
    if (!validateBeforeCheckout()) return

    // Captured before posStore.clear() below wipes it — the redemption
    // can only be confirmed once the order it applies to actually exists.
    const redemption = posStore.redemption

    submitting.value = true
    try {
      const { data } = await submitFoodOrderApi({
        branch_id: branchId.value,
        payment_method,
        cash_tendered,
        order_type: posStore.orderType,
        table_id:
          posStore.orderType === 'dine_in'
            ? posStore.tableId || undefined
            : undefined,
        customer_id: posStore.customerId || undefined,
        discount_amount: posStore.discountAmount || undefined,
        notes: posStore.note || undefined,
        items: posStore.items.map(i => ({
          product_id: i.product_id,
          variant_id: i.variant_id || undefined,
          quantity: i.qty,
          note: i.notes || undefined,
          customizations: i.customizations?.length
            ? i.customizations
            : undefined,
          modifier_option_ids: i.modifier_option_ids?.length
            ? i.modifier_option_ids
            : undefined,
          custom_modifier_values: i.custom_modifier_values?.length
            ? i.custom_modifier_values
            : undefined
        }))
      })
      // Kitchen needs to prep regardless of payment timing — fires even for
      // pay_later, independent of whether the customer receipt is enabled.
      maybePrintKitchenTicket(data.data.prints.order_ticket)

      // Pay later collects nothing at checkout — there's no payment to
      // receipt yet, so there's nothing worth printing until someone
      // actually settles it from the Orders page.
      if (payment_method !== 'pay_later') {
        receipt.value = data.data.prints.receipt
        if (authStore.posSettings?.print_customer_receipt !== false) {
          receiptOpen.value = true
        }
      }

      const receiptData = data.data.prints.receipt
      const delaySeconds =
        Number(branchCdSettings.value.thank_you_delay_seconds) || 10

      suppressCartPublish.value = true
      customerDisplay.publish(
        buildCustomerDisplayState({
          state: 'thanks',
          authStore,
          posStore,
          branchName: currentBranchName.value,
          branchSettings: branchCdSettings.value,
          orderNumber: receiptData.order_number,
          totals: {
            totalUsd: receiptData.total,
            discount: receiptData.discount
          },
          payment: {
            method: receiptData.payment_method,
            paidBase: receiptData.cash_tendered,
            changeBase: receiptData.change_given
          }
        })
      )

      // The reward's discount already landed on this order's
      // discount_amount above — this just marks the redemption as
      // consumed and links it back to the order it paid for. The order
      // itself is already fully placed by this point either way; a
      // failure here (e.g. the customer cancelled it from their phone in
      // the same instant) surfaces as a toast for staff to handle
      // manually rather than silently losing track of it.
      if (redemption) {
        try {
          await confirmLoyaltyRedemptionApi(redemption.code, {
            order_id: data.data.id,
            branch_id: branchId.value
          })
        } catch (e) {
          notif(e?.response?.data?.message || t('pos.loyalty.confirm_failed'), {
            type: 'error'
          })
        }
      }

      posStore.clear()
      loadTables() // the table just used, if any, is now occupied
      notif(t('pos.checkout.success'), { type: 'success' })

      setTimeout(() => {
        customerDisplay.publish(
          buildCustomerDisplayState({
            state: 'idle',
            authStore,
            posStore,
            branchName: currentBranchName.value,
            branchSettings: branchCdSettings.value
          })
        )
        suppressCartPublish.value = false
      }, delaySeconds * 1000)
    } catch (err) {
      // The order never happened — if the customer display was mid-QR, put
      // it back to the normal cart mirror rather than leaving it stuck on a
      // QR screen for a payment that didn't go through (a no-op revert for
      // the other methods, which never changed the customer display here).
      publishCartState()
      notif(
        err.response?.data?.message ??
          err.response?.data?.errors?.[0] ??
          t('pos.checkout.failed'),
        { type: 'error' }
      )
    } finally {
      submitting.value = false
    }
  }

  // If the tenant disables whichever order type is currently selected
  // (including the default 'takeaway'), fall back to the first one still enabled.
  watch(
    enabledOrderTypes,
    types => {
      if (types.length && !types.some(o => o.value === posStore.orderType)) {
        posStore.setOrderType(types[0].value)
      }
    },
    { immediate: true }
  )

  watch(branchId, () => {
    loadProducts()
    loadTables()
  })
  watch(search, loadProducts)

  function handlePosShortcuts(e) {
    if (
      ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') ||
      e.key === 'F2'
    ) {
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
    // blindly; the app bar's own ShopBranchSwitcher does the same
    // self-repair on its own mount, but that's a separate component with
    // no guaranteed ordering against this page's mount.
    if (
      branchId.value &&
      !branchStore.branches.some(b => b.id === branchId.value)
    ) {
      authStore.setActiveBranch(branchStore.branches[0]?.id ?? null)
    }

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
  .table-sheet-card {
    cursor: pointer;
  }
  .table-sheet-card--disabled {
    cursor: not-allowed;
    opacity: 0.5;
  }
  .pos-page {
    /* Approximate remaining viewport below the app bar + page container
       padding — the workspace below scrolls internally instead of the
       whole page, so a few px of slack here is harmless. */
    /* dvh (not vh) so the on-screen keyboard shrinking the visual viewport
       doesn't just get the page clipped by a stale 94vh. */
    height: calc(94dvh - 97px);
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
    min-height: calc(94dvh - 96px);
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
