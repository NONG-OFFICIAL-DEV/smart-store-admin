<template>
  <div v-if="branchId" class="mobile-orders">
    <div class="d-flex ga-2 mb-2">
      <v-chip
        size="small"
        :variant="sourceFilter === 'mobile' ? 'flat' : 'tonal'"
        :color="sourceFilter === 'mobile' ? 'primary' : undefined"
        class="cursor-pointer"
        @click="sourceFilter = 'mobile'"
      >
        {{ t('pos.mobile_orders.filter_mobile') }}
      </v-chip>
      <v-chip
        size="small"
        :variant="sourceFilter === 'all' ? 'flat' : 'tonal'"
        :color="sourceFilter === 'all' ? 'primary' : undefined"
        class="cursor-pointer"
        @click="sourceFilter = 'all'"
      >
        {{ t('pos.mobile_orders.filter_all') }}
      </v-chip>
    </div>

    <div v-if="loading && !tickets.length" class="d-flex align-center ga-2 py-2 px-1">
      <v-progress-circular indeterminate size="16" width="2" color="primary" />
      <span class="text-caption text-medium-emphasis">{{ t('pos.mobile_orders.loading') }}</span>
    </div>

    <div v-else-if="loadFailed" class="d-flex align-center ga-2 py-2 px-1">
      <span class="text-caption text-error">{{ t('pos.mobile_orders.load_failed') }}</span>
      <v-btn size="x-small" variant="text" color="primary" class="text-none" @click="loadTickets">
        {{ t('btn.retry') }}
      </v-btn>
    </div>

    <div v-else-if="!cards.length" class="py-2 px-1">
      <span class="text-caption text-medium-emphasis">
        {{ sourceFilter === 'all' ? t('pos.mobile_orders.empty_all') : t('pos.mobile_orders.empty') }}
      </span>
    </div>

    <div v-else class="mobile-orders__row">
      <button
        v-if="canScrollLeft"
        type="button"
        class="mobile-orders__nav mobile-orders__nav--left"
        :aria-label="t('pos.mobile_orders.scroll_left')"
        @click="scrollBy(-1)"
      >
        <v-icon icon="mdi-chevron-left" size="20" />
      </button>

      <div ref="scrollerRef" class="mobile-orders__scroller" @scroll="updateScrollState">
        <v-card
          v-for="card in cards"
          :key="card.ticket.id"
          variant="tonal"
          :color="card.status.color"
          rounded="lg"
          class="mobile-orders__card pa-3"
          role="button"
          tabindex="0"
          @click="openDetail(card)"
          @keydown.enter="openDetail(card)"
        >
          <div class="d-flex align-center justify-space-between ga-2 mb-1">
            <span class="d-flex align-center ga-1 min-width-0">
              <v-icon
                v-if="sourceFilter === 'all'"
                :icon="card.source === 'pos' ? 'mdi-point-of-sale' : 'mdi-cellphone'"
                size="14"
              />
              <span class="text-body-2 font-weight-bold text-truncate">{{ card.orderNumber }}</span>
            </span>
            <v-chip size="x-small" :color="card.status.color" variant="flat">
              {{ card.status.label }}
            </v-chip>
          </div>

          <div class="d-flex align-center ga-3 text-caption text-medium-emphasis mb-2">
            <span v-if="card.tableNumber" class="d-flex align-center ga-1">
              <v-icon icon="mdi-table-furniture" size="14" />
              {{ card.tableNumber }}
            </span>
            <span class="d-flex align-center ga-1">
              <v-icon icon="mdi-food-outline" size="14" />
              {{ t('pos.mobile_orders.items_count', card.itemCount) }}
            </span>
            <v-spacer />
            <span class="d-flex align-center ga-1" :class="card.elapsedMinutes >= 15 ? 'text-error' : ''">
              <v-icon icon="mdi-clock-outline" size="14" />
              {{ t('kitchen.elapsed_minutes', { n: card.elapsedMinutes }) }}
            </span>
          </div>

          <div class="d-flex ga-1" @click.stop @keydown.stop>
            <v-btn
              v-if="card.ticket.status === 'new'"
              size="x-small"
              color="primary"
              variant="flat"
              class="text-none"
              @click="doStart(card.ticket)"
            >
              {{ t('kitchen.start') }}
            </v-btn>
            <v-btn
              v-if="card.ticket.status === 'in_progress'"
              size="x-small"
              color="success"
              variant="flat"
              class="text-none"
              @click="doComplete(card.ticket)"
            >
              {{ t('kitchen.complete') }}
            </v-btn>
            <v-btn
              v-if="card.ticket.status === 'done'"
              size="x-small"
              color="primary"
              variant="flat"
              class="text-none"
              @click="doServe(card)"
            >
              {{ t('pos.mobile_orders.serve') }}
            </v-btn>
            <v-btn
              v-if="card.ticket.status !== 'done'"
              size="x-small"
              variant="text"
              color="error"
              class="text-none"
              @click="doCancel(card.ticket)"
            >
              {{ t('btn.cancel') }}
            </v-btn>
          </div>
        </v-card>
      </div>

      <button
        v-if="canScrollRight"
        type="button"
        class="mobile-orders__nav mobile-orders__nav--right"
        :aria-label="t('pos.mobile_orders.scroll_right')"
        @click="scrollBy(1)"
      >
        <v-icon icon="mdi-chevron-right" size="20" />
      </button>
    </div>

    <MobileOrderDetailDialog
      v-model="detailDialog"
      :order="selectedOrder"
      :ticket="selectedTicket"
      @updated="loadTickets"
    />
  </div>
</template>

<script setup>
  import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useOrderStore } from '@/stores/orderStore'
  import {
    getAllKitchenDisplayTicketsApi,
    startKitchenDisplayTicketApi,
    completeKitchenDisplayTicketApi,
    cancelKitchenDisplayTicketApi
  } from '@/api/kitchenDisplayTicketService'
  import { markOrderServedApi } from '@/api/orderService'
  import { getEcho } from '@/utils/echo'
  import { playNewOrderSound } from '@/utils/playNewOrderSound'
  import MobileOrderDetailDialog from '@/components/pos/MobileOrderDetailDialog.vue'

  const { t } = useI18n()
  const authStore = useAuthStore()
  const orderStore = useOrderStore()

  const props = defineProps({
    branchId: { type: String, default: null }
  })

  const ORDER_NOT_ACTIVE = ['served', 'completed', 'cancelled', 'refunded']

  const tickets = ref([])
  const orderCache = ref({})
  const loading = ref(false)
  const loadFailed = ref(false)
  const now = ref(Date.now())
  let clockTimer = null

  const detailDialog = ref(false)
  const selectedOrder = ref(null)
  const selectedTicket = ref(null)

  const scrollerRef = ref(null)
  const canScrollLeft = ref(false)
  const canScrollRight = ref(false)

  const soundEnabled = computed(() => authStore.posSettings?.mobile_menu_order_sound !== false)

  // View-only preference, not a tenant setting — resets to "mobile only"
  // on every page load/remount rather than persisting anywhere.
  const sourceFilter = ref('mobile')

  // A new mobile order can fire `.order.new` several times in quick
  // succession (a few customers checking out around the same moment) —
  // without this guard each event kicked off its own full reload
  // (GET kitchen-tickets + one GET /orders/{id} per ticket), piling up
  // duplicate in-flight requests for the exact same data. Calls that land
  // while a load is already running just await that same in-flight call
  // instead of starting a new one.
  let loadInFlight = null
  function loadTickets() {
    if (!props.branchId) return Promise.resolve()
    if (loadInFlight) return loadInFlight
    loadInFlight = (async () => {
      loading.value = true
      loadFailed.value = false
      try {
        const { data } = await getAllKitchenDisplayTicketsApi({
          branch_id: props.branchId,
          perPage: -1
        })
        tickets.value = data.data.filter(tk => tk.status !== 'cancelled')
        // Each ticket already carries a trimmed summary of its order
        // (order_number/status/source/table/items — see
        // KitchenDisplayTicketResource) — used to be one extra
        // GET /orders/{id} PER TICKET just to render the card list, which
        // is exactly what was flooding the network tab. The full order
        // (subtotal/tax/payment/etc.) is only ever fetched on demand, when
        // a card is actually opened — see openDetail().
        const summaries = {}
        for (const tk of tickets.value) {
          if (tk.order) summaries[tk.order_id] = tk.order
        }
        orderCache.value = { ...orderCache.value, ...summaries }
        await nextTick()
        updateScrollState()
      } catch {
        loadFailed.value = true
      } finally {
        loading.value = false
        loadInFlight = null
      }
    })()
    return loadInFlight
  }

  // Defaults to mobile e-menu orders only — a ticket whose order came from
  // the POS itself (source === 'pos') is excluded unless the staff member
  // has switched the view to "All" (see sourceFilter above). A ticket whose
  // order cache hasn't resolved yet is excluded rather than shown
  // optimistically, since showing then yanking a card would be more jarring
  // than a one-tick delay.
  const cards = computed(() =>
    tickets.value
      .map(ticket => {
        const order = orderCache.value[ticket.order_id]
        return { ticket, order }
      })
      .filter(({ order }) => {
        if (!order) return false
        if (sourceFilter.value === 'mobile' && order.source === 'pos') return false
        if (ORDER_NOT_ACTIVE.includes(order.status)) return false
        return true
      })
      .map(({ ticket, order }) => ({
        ticket,
        order,
        orderId: ticket.order_id,
        orderNumber: order.order_number ?? `#${ticket.order_id.slice(0, 8)}`,
        source: order.source,
        tableNumber: order.table?.number ?? null,
        itemCount: (order.items ?? []).reduce((sum, i) => sum + Number(i.quantity ?? 0), 0),
        elapsedMinutes: Math.max(0, Math.floor((now.value - new Date(ticket.created_at).getTime()) / 60000)),
        status: statusMeta(ticket.status)
      }))
  )

  function statusMeta(status) {
    if (status === 'in_progress') return { label: t('pos.mobile_orders.status_in_kitchen'), color: 'info' }
    if (status === 'done') return { label: t('pos.mobile_orders.status_ready'), color: 'success' }
    return { label: t('pos.mobile_orders.status_new'), color: 'warning' }
  }

  // The card list only ever has the trimmed order summary (see
  // loadTickets()) — the detail dialog needs the full order (subtotal,
  // tax, service charge, line-item prices), fetched here on demand rather
  // than upfront for every ticket.
  async function openDetail(card) {
    selectedTicket.value = card.ticket
    try {
      const res = await orderStore.fetchOrderById(card.orderId)
      selectedOrder.value = res.data.data
    } catch {
      selectedOrder.value = card.order
    }
    detailDialog.value = true
  }

  async function doStart(ticket) {
    try {
      await startKitchenDisplayTicketApi(ticket.id)
      await loadTickets()
    } catch {
      loadFailed.value = true
    }
  }
  async function doComplete(ticket) {
    try {
      await completeKitchenDisplayTicketApi(ticket.id)
      await loadTickets()
    } catch {
      loadFailed.value = true
    }
  }
  async function doCancel(ticket) {
    try {
      await cancelKitchenDisplayTicketApi(ticket.id)
      await loadTickets()
    } catch {
      loadFailed.value = true
    }
  }

  // The kitchen ticket itself has no status past 'done' — this marks the
  // ORDER served instead, which is what actually makes the card disappear
  // (cards already excludes orders whose status is in ORDER_NOT_ACTIVE,
  // which includes 'served').
  async function doServe(card) {
    try {
      const res = await markOrderServedApi(card.orderId)
      // hydrateOrderCache() only fetches ids it doesn't already have — the
      // order for this ticket was already cached (that's how the card got
      // rendered in the first place), so without patching it here the
      // cached copy keeps its pre-serve status and the card never
      // disappears no matter how many times loadTickets() reruns.
      orderCache.value = { ...orderCache.value, [card.orderId]: res.data.data }
      await loadTickets()
    } catch {
      loadFailed.value = true
    }
  }

  function updateScrollState() {
    const el = scrollerRef.value
    if (!el) {
      canScrollLeft.value = false
      canScrollRight.value = false
      return
    }
    canScrollLeft.value = el.scrollLeft > 4
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 4
  }

  function scrollBy(direction) {
    scrollerRef.value?.scrollBy({ left: direction * 240, behavior: 'smooth' })
  }

  function onWindowResize() {
    updateScrollState()
  }

  // ── Live push — mirrors KitchenDisplay.vue's own subscription so a new
  // mobile order shows here the instant it's placed, no polling. ──────────
  let orderChannelName = null
  function subscribeOrderChannel(id) {
    const echo = getEcho()
    if (!echo) return
    if (orderChannelName) {
      echo.leave(orderChannelName)
      orderChannelName = null
    }
    if (id) {
      orderChannelName = `branch.${id}.orders`
      echo.private(orderChannelName).listen('.order.new', () => {
        if (soundEnabled.value) playNewOrderSound()
        loadTickets()
      })
    }
  }

  watch(
    () => props.branchId,
    id => {
      loadTickets()
      subscribeOrderChannel(id)
    }
  )

  onMounted(() => {
    if (props.branchId) loadTickets()
    subscribeOrderChannel(props.branchId)
    window.addEventListener('resize', onWindowResize)
    clockTimer = setInterval(() => {
      now.value = Date.now()
    }, 30000)
  })

  onUnmounted(() => {
    clearInterval(clockTimer)
    window.removeEventListener('resize', onWindowResize)
    if (orderChannelName) getEcho()?.leave(orderChannelName)
  })
</script>

<style scoped>
  .mobile-orders__row {
    position: relative;
    display: flex;
    align-items: stretch;
  }
  .mobile-orders__scroller {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    scroll-behavior: smooth;
    padding-block: 2px;
    width: 100%;
    -webkit-overflow-scrolling: touch;
  }
  .mobile-orders__scroller::-webkit-scrollbar {
    display: none;
  }
  .mobile-orders__card {
    flex: 0 0 220px;
    width: 220px;
    cursor: pointer;
  }
  .mobile-orders__nav {
    flex-shrink: 0;
    width: 32px;
    border: none;
    border-radius: 10px;
    background: rgba(var(--v-theme-on-surface), 0.08);
    color: rgba(var(--v-theme-on-surface), 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
  }
  .mobile-orders__nav--left {
    margin-inline-end: 6px;
  }
  .mobile-orders__nav--right {
    margin-inline-start: 6px;
  }
  .min-width-0 {
    min-width: 0;
  }
  .cursor-pointer {
    cursor: pointer;
  }
</style>
