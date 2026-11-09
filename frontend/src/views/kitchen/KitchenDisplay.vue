<template>
  <v-container fluid class="pa-0">
    <AppToolbar
      :title="t('kitchen.title')"
      :subtitle="t('kitchen.subtitle')"
    />

    <v-alert v-if="!branchId" type="warning" variant="tonal" rounded="lg">
      {{ t('pos.select_branch') }}
    </v-alert>

    <v-row v-else dense>
      <v-col v-for="col in columns" :key="col.status" cols="12" md="4">
        <v-card rounded="lg" border elevation="0" class="kitchen-column">
          <div class="pa-3 d-flex align-center" :class="`bg-${col.color}`">
            <v-icon :icon="col.icon" class="me-2" />
            <span class="text-body-1 font-weight-bold">{{ col.title }}</span>
            <v-spacer />
            <v-chip size="small" variant="flat" color="white" class="font-weight-bold">
              {{ ticketsByStatus[col.status].length }}
            </v-chip>
          </div>
          <v-divider />

          <div class="kitchen-column__body pa-2">
            <div v-if="loading" class="d-flex justify-center py-6">
              <v-progress-circular indeterminate color="primary" />
            </div>

            <v-alert
              v-else-if="!ticketsByStatus[col.status].length"
              type="info"
              variant="text"
              density="comfortable"
            >
              {{ t('kitchen.no_tickets') }}
            </v-alert>

            <v-card
              v-for="ticket in ticketsByStatus[col.status]"
              :key="ticket.id"
              rounded="lg"
              border
              elevation="0"
              class="pa-3 mb-2"
            >
              <div class="d-flex justify-space-between align-start mb-1">
                <span class="text-body-2 font-weight-bold">
                  {{ orderLabel(ticket) }}
                </span>
                <v-chip size="x-small" :color="priorityColor(ticket.priority)" variant="tonal">
                  P{{ ticket.priority ?? 3 }}
                </v-chip>
              </div>

              <div v-if="orderCache[ticket.order_id]?.table" class="text-caption text-medium-emphasis">
                {{ t('kitchen.table_prefix') }} {{ orderCache[ticket.order_id].table.number }}
              </div>
              <div v-if="ticket.station" class="text-caption text-medium-emphasis">
                {{ ticket.station }}
              </div>

              <v-divider v-if="orderCache[ticket.order_id]?.items?.length" class="my-2" />
              <div
                v-for="item in orderCache[ticket.order_id]?.items ?? []"
                :key="item.id"
                class="text-caption mb-1"
              >
                <div class="d-flex justify-space-between">
                  <span class="font-weight-medium">{{ item.quantity }}× {{ item.product_name }}</span>
                </div>
                <div v-if="item.variant_name || item.modifiers?.length" class="text-medium-emphasis pl-3">
                  {{ [item.variant_name, ...(item.modifiers ?? []).map(m => m.name)].filter(Boolean).join(', ') }}
                </div>
                <div v-if="item.notes" class="text-medium-emphasis pl-3 font-italic">
                  {{ item.notes }}
                </div>
              </div>

              <div class="d-flex align-center justify-space-between mt-2">
                <span class="text-caption font-weight-medium" :class="elapsedClass(ticket)">
                  <v-icon icon="mdi-clock-outline" size="12" class="me-1" />
                  {{ elapsedLabel(ticket) }}
                </span>
                <div class="d-flex gap-1">
                  <v-btn
                    v-if="ticket.status === 'new'"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    @click="doStart(ticket)"
                  >
                    {{ t('kitchen.start') }}
                  </v-btn>
                  <v-btn
                    v-if="ticket.status === 'in_progress'"
                    size="x-small"
                    color="success"
                    variant="tonal"
                    @click="doComplete(ticket)"
                  >
                    {{ t('kitchen.complete') }}
                  </v-btn>
                  <v-btn
                    v-if="ticket.status === 'done'"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    @click="doServe(ticket)"
                  >
                    {{ t('pos.mobile_orders.serve') }}
                  </v-btn>
                  <v-btn
                    v-if="ticket.status !== 'done'"
                    size="x-small"
                    color="error"
                    variant="text"
                    @click="doCancel(ticket)"
                  >
                    {{ t('btn.cancel') }}
                  </v-btn>
                </div>
              </div>
            </v-card>
          </div>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import {
    getAllKitchenDisplayTicketsApi,
    startKitchenDisplayTicketApi,
    completeKitchenDisplayTicketApi,
    cancelKitchenDisplayTicketApi
  } from '@/api/kitchenDisplayTicketService'
  import { markOrderServedApi } from '@/api/orderService'
  import { getEcho } from '@/utils/echo'
  import { playNewOrderSound } from '@/utils/playNewOrderSound'
  import AppToolbar from '@/components/common/AppToolbar.vue'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const authStore = useAuthStore()

  // No local picker — branch comes from the global BranchSwitcher (see
  // AppBar), same source FoodPos.vue/OrdersManagement.vue already read.
  const branchId = computed(() => authStore.activeBranchId || authStore.branch_id)
  const loading = ref(false)
  const tickets = ref([])
  const orderCache = ref({})
  const now = ref(Date.now())
  let clockTimer = null

  const columns = [
    { status: 'new', title: t('kitchen.columns.new'), icon: 'mdi-bell-ring-outline', color: 'warning' },
    { status: 'in_progress', title: t('kitchen.columns.preparing'), icon: 'mdi-pot-steam-outline', color: 'info' },
    { status: 'done', title: t('kitchen.columns.ready'), icon: 'mdi-check-circle-outline', color: 'success' }
  ]

  // A kitchen ticket has no status past 'done' — the only thing that can
  // ever move an order to 'served' is the explicit Serve action below, so
  // this can never accidentally hide an otherwise-untouched ticket (e.g. a
  // POS order, which is already 'completed' the instant its ticket is
  // created — filtering on that status too would wipe out the whole board).
  const activeTickets = computed(() =>
    tickets.value.filter(tk => orderCache.value[tk.order_id]?.status !== 'served')
  )
  const ticketsByStatus = computed(() => ({
    new: activeTickets.value.filter(t => t.status === 'new'),
    in_progress: activeTickets.value.filter(t => t.status === 'in_progress'),
    done: activeTickets.value.filter(t => t.status === 'done')
  }))

  // A new order can fire `.order.new` several times in quick succession (a
  // few customers checking out around the same moment) — without this
  // guard each event kicked off its own full reload (GET kitchen-tickets +
  // one GET /orders/{id} per ticket), piling up duplicate in-flight
  // requests for the exact same data. Calls that land while a load is
  // already running just await that same in-flight call instead of
  // starting a new one.
  let loadInFlight = null
  function loadTickets() {
    if (!branchId.value) return Promise.resolve()
    if (loadInFlight) return loadInFlight
    loadInFlight = (async () => {
      loading.value = true
      try {
        const { data } = await getAllKitchenDisplayTicketsApi({
          branch_id: branchId.value,
          perPage: -1
        })
        tickets.value = data.data.filter(tk => tk.status !== 'cancelled')
        // Each ticket already carries a trimmed summary of its order
        // (order_number/status/table/items — see
        // KitchenDisplayTicketResource) — used to be one extra
        // GET /orders/{id} PER TICKET just to render the card, which is
        // exactly what was flooding the network tab on this page.
        const summaries = {}
        for (const tk of tickets.value) {
          if (tk.order) summaries[tk.order_id] = tk.order
        }
        orderCache.value = { ...orderCache.value, ...summaries }
      } finally {
        loading.value = false
        loadInFlight = null
      }
    })()
    return loadInFlight
  }

  function orderLabel(ticket) {
    const order = orderCache.value[ticket.order_id]
    return order?.order_number ?? `#${ticket.order_id.slice(0, 8)}`
  }

  function priorityColor(priority) {
    if (priority <= 2) return 'error'
    if (priority >= 4) return 'grey'
    return 'warning'
  }

  function elapsedMinutes(ticket) {
    return Math.max(0, Math.floor((now.value - new Date(ticket.created_at).getTime()) / 60000))
  }

  function elapsedLabel(ticket) {
    return t('kitchen.elapsed_minutes', { n: elapsedMinutes(ticket) })
  }

  function elapsedClass(ticket) {
    const mins = elapsedMinutes(ticket)
    if (mins >= 15) return 'text-error'
    if (mins >= 8) return 'text-warning'
    return 'text-medium-emphasis'
  }

  async function doStart(ticket) {
    try {
      await startKitchenDisplayTicketApi(ticket.id)
      await loadTickets()
    } catch {
      notif(t('kitchen.action_failed'), { type: 'error' })
    }
  }

  async function doComplete(ticket) {
    try {
      await completeKitchenDisplayTicketApi(ticket.id)
      await loadTickets()
    } catch {
      notif(t('kitchen.action_failed'), { type: 'error' })
    }
  }

  async function doCancel(ticket) {
    try {
      await cancelKitchenDisplayTicketApi(ticket.id)
      await loadTickets()
    } catch {
      notif(t('kitchen.action_failed'), { type: 'error' })
    }
  }

  // The ticket itself has no status past 'done' — this marks the ORDER
  // served instead, which is what makes it disappear (see activeTickets).
  async function doServe(ticket) {
    try {
      const res = await markOrderServedApi(ticket.order_id)
      // hydrateOrderCache() only fetches ids it doesn't already have — the
      // order for this ticket was already cached (that's how the ticket
      // got rendered in the first place), so without patching it here the
      // cached copy keeps its pre-serve status and activeTickets never
      // excludes it no matter how many times loadTickets() reruns.
      orderCache.value = { ...orderCache.value, [ticket.order_id]: res.data.data }
      await loadTickets()
    } catch {
      notif(t('kitchen.action_failed'), { type: 'error' })
    }
  }

  // ── Live push (new order placed from the e-menu or rung up on the POS) ─
  // No polling fallback — tickets update purely from this socket event.
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
        playNewOrderSound()
        loadTickets()
      })
    }
  }

  watch(branchId, id => {
    loadTickets()
    subscribeOrderChannel(id)
  })

  onMounted(async () => {
    if (branchId.value) await loadTickets()
    subscribeOrderChannel(branchId.value)
    clockTimer = setInterval(() => {
      now.value = Date.now()
    }, 30000)
  })

  onUnmounted(() => {
    clearInterval(clockTimer)
    if (orderChannelName) getEcho()?.leave(orderChannelName)
  })
</script>

<style scoped>
  .kitchen-column {
    height: calc(100vh - 220px);
    display: flex;
    flex-direction: column;
  }
  .kitchen-column__body {
    flex-grow: 1;
    overflow-y: auto;
  }
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
</style>
