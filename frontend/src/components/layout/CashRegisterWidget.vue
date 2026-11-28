<template>
  <!-- Trigger — shown for staff AND owners (an owner running their own shop
       has no Staff row, but must still be able to open/manage their own
       register — staff_id is simply null for them, same as
       Order.cashier_id/Payment.staff_id already tolerate) — also gated by
       permission (cash_drawers.manage was seeded but never actually checked
       here before) and the tenant's cash_register_enabled pos_setting. Kept
       in a shared composable (not inline here) so a parent wrapper (e.g.
       SidebarFooter.vue's bordered row) can check the same condition and
       avoid rendering an empty bordered row when this is hidden. -->
  <template v-if="hasCashRegisterAccess">
    <v-chip
      v-if="!rail && !block"
      size="small"
      variant="tonal"
      :color="registerOpen ? 'success' : 'default'"
      class="d-none d-md-inline-flex"
      rounded="lg"
      :prepend-icon="registerOpen ? 'mdi-circle' : 'mdi-circle-outline'"
      @click="openRegisterPanel"
    >
      {{ registerOpen ? t('cash_register.open_label') : t('cash_register.closed_label') }}
    </v-chip>

    <!-- Own full-width row (Sidebar-only footer, expanded) — plain text
         label, not squeezed into a grid cell, so longer translated status
         text always has room. -->
    <button v-else-if="block" type="button" class="register-row" @click="openRegisterPanel">
      <v-icon :icon="registerOpen ? 'mdi-circle' : 'mdi-circle-outline'" size="14" :color="registerOpen ? 'success' : undefined" />
      <span>{{ registerOpen ? t('cash_register.open_label') : t('cash_register.closed_label') }}</span>
    </button>

    <v-tooltip v-else location="end" :text="registerOpen ? t('cash_register.open_label') : t('cash_register.closed_label')">
      <template #activator="{ props: tip }">
        <button type="button" v-bind="tip" class="rail-btn" :aria-label="t('cash_register.panel_title')" @click="openRegisterPanel">
          <v-icon :icon="registerOpen ? 'mdi-circle' : 'mdi-circle-outline'" size="16" :color="registerOpen ? 'success' : undefined" />
        </button>
      </template>
    </v-tooltip>
  </template>

  <!-- Cash Register panel -->
  <AppDialog
    v-model="registerDialog"
    :title="t('cash_register.panel_title')"
    :max-width="registerOpen && panelView === 'closing' ? 460 : 380"
    :loading="registerBusy"
  >
    <!-- ── Active Shift Info ─────────────────────────────────────────── -->
    <div v-if="registerOpen && panelView === 'info'">
      <div class="d-flex justify-space-between text-body-medium mb-1">
        <span class="text-medium-emphasis">{{ t('cash_register.cashier') }}</span>
        <span class="font-weight-bold">{{ cashierName }}</span>
      </div>
      <div v-if="currentDrawer?.cash_register_id" class="d-flex justify-space-between text-body-medium mb-1">
        <span class="text-medium-emphasis">{{ t('cash_register.register') }}</span>
        <span class="font-weight-bold">{{ registerName(currentDrawer.cash_register_id) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-medium mb-1">
        <span class="text-medium-emphasis">{{ t('cash_register.opening_float') }}</span>
        <span class="font-weight-bold">{{ formatMoney(currentDrawer?.opening_float) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-medium mb-3">
        <span class="text-medium-emphasis">{{ t('cash_register.opened_at') }}</span>
        <span class="font-weight-bold">{{ formatDateTime(currentDrawer?.opened_at) }}</span>
      </div>

      <v-divider class="mb-3" />

      <div class="d-flex justify-space-between text-body-medium mb-1">
        <span class="text-medium-emphasis">{{ t('cash_register.cash_sales') }}</span>
        <span class="font-weight-medium">{{ formatMoney(status?.cash_sales) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-medium mb-1">
        <span class="text-medium-emphasis">{{ t('cash_register.non_cash_sales') }}</span>
        <span class="font-weight-medium">{{ formatMoney(status?.non_cash_sales) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-medium mb-1">
        <span class="text-medium-emphasis">{{ t('cash_register.cash_in') }}</span>
        <span class="font-weight-medium text-success">+{{ formatMoney(status?.cash_in) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-medium mb-1">
        <span class="text-medium-emphasis">{{ t('cash_register.cash_out') }}</span>
        <span class="font-weight-medium text-error">-{{ formatMoney(status?.cash_out) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-medium mb-3">
        <span class="text-medium-emphasis">{{ t('cash_register.cash_refunds') }}</span>
        <span class="font-weight-medium text-error">-{{ formatMoney(status?.cash_refunds) }}</span>
      </div>
      <div class="d-flex justify-space-between text-body-large mb-4">
        <span class="font-weight-bold">{{ t('cash_register.expected_cash') }}</span>
        <span class="font-weight-bold">{{ formatMoney(status?.expected_cash) }}</span>
      </div>

      <div class="d-flex gap-2 mb-2">
        <v-btn variant="tonal" rounded="lg" class="flex-1-1" prepend-icon="mdi-cash-plus" @click="panelView = 'cash_in'">
          {{ t('cash_register.cash_in') }}
        </v-btn>
        <v-btn variant="tonal" rounded="lg" class="flex-1-1" prepend-icon="mdi-cash-minus" @click="panelView = 'cash_out'">
          {{ t('cash_register.cash_out') }}
        </v-btn>
      </div>
      <v-btn
        variant="text"
        rounded="lg"
        block
        class="mb-2"
        prepend-icon="mdi-cash-register"
        @click="handleOpenDrawer"
      >
        {{ t('cash_register.open_drawer') }}
      </v-btn>
      <v-btn
        color="error"
        variant="tonal"
        block
        rounded="lg"
        prepend-icon="mdi-lock-outline"
        @click="panelView = 'closing'"
      >
        {{ t('cash_register.close_register') }}
      </v-btn>
    </div>

    <!-- ── Cash In / Cash Out ───────────────────────────────────────── -->
    <div v-else-if="registerOpen && (panelView === 'cash_in' || panelView === 'cash_out')">
      <v-text-field
        v-model.number="movementAmount"
        type="number"
        min="0.01"
        step="0.01"
        :label="t('cash_register.amount')"
        variant="outlined"
        rounded="lg"
        :error-messages="movementError"
      />
      <v-textarea
        v-model="movementReason"
        :label="t('cash_register.reason') + ' *'"
        variant="outlined"
        rounded="lg"
        rows="2"
      />
      <div class="d-flex gap-2">
        <v-btn variant="tonal" rounded="lg" class="flex-1-1" @click="panelView = 'info'">
          {{ t('btn.cancel') }}
        </v-btn>
        <v-btn
          :color="panelView === 'cash_in' ? 'success' : 'error'"
          variant="flat"
          rounded="lg"
          class="flex-1-1"
          :disabled="!movementAmount || !movementReason"
          @click="confirmMovement"
        >
          {{ t('btn.confirm') }}
        </v-btn>
      </div>
    </div>

    <!-- ── Close Shift & Reconciliation ─────────────────────────────── -->
    <div v-else-if="registerOpen && panelView === 'closing'">
      <v-table density="compact" class="mb-3">
        <tbody>
          <tr>
            <td class="text-medium-emphasis">{{ t('cash_register.opening_float') }}</td>
            <td class="text-right font-weight-medium">{{ formatMoney(status?.opening_float) }}</td>
          </tr>
          <tr>
            <td class="text-medium-emphasis">{{ t('cash_register.cash_sales') }}</td>
            <td class="text-right font-weight-medium">{{ formatMoney(status?.cash_sales) }}</td>
          </tr>
          <tr>
            <td class="text-medium-emphasis">{{ t('cash_register.cash_in') }}</td>
            <td class="text-right font-weight-medium">{{ formatMoney(status?.cash_in) }}</td>
          </tr>
          <tr>
            <td class="text-medium-emphasis">{{ t('cash_register.cash_out') }}</td>
            <td class="text-right font-weight-medium">-{{ formatMoney(status?.cash_out) }}</td>
          </tr>
          <tr>
            <td class="text-medium-emphasis">{{ t('cash_register.cash_refunds') }}</td>
            <td class="text-right font-weight-medium">-{{ formatMoney(status?.cash_refunds) }}</td>
          </tr>
          <tr>
            <td class="font-weight-bold">{{ t('cash_register.expected_cash') }}</td>
            <td class="text-right font-weight-bold">{{ formatMoney(status?.expected_cash) }}</td>
          </tr>
        </tbody>
      </v-table>

      <v-text-field
        v-model.number="actualCash"
        type="number"
        min="0"
        step="0.01"
        :label="t('cash_register.actual_cash')"
        variant="outlined"
        rounded="lg"
      />

      <div v-if="actualCash !== null && actualCash !== ''" class="d-flex justify-space-between align-center mb-3 pa-3 rounded-lg" :class="differenceBg">
        <span class="font-weight-medium">{{ differenceLabel }}</span>
        <span class="font-weight-bold">{{ formatMoney(Math.abs(difference)) }}</span>
      </div>

      <v-textarea
        v-model="closeNotes"
        :label="t('cash_register.notes_optional')"
        variant="outlined"
        rounded="lg"
        rows="2"
      />
      <div class="d-flex gap-2">
        <v-btn variant="tonal" rounded="lg" class="flex-1-1" @click="panelView = 'info'">
          {{ t('btn.cancel') }}
        </v-btn>
        <v-btn
          color="error"
          variant="flat"
          rounded="lg"
          class="flex-1-1"
          :disabled="actualCash === null || actualCash === ''"
          @click="confirmClose"
        >
          {{ t('cash_register.confirm_close') }}
        </v-btn>
      </div>
    </div>

    <!-- ── Open Shift ────────────────────────────────────────────────── -->
    <div v-else>
      <p class="text-body-medium text-medium-emphasis mb-3">{{ t('cash_register.open_prompt') }}</p>
      <v-select
        v-if="activeRegisters.length"
        v-model="selectedRegisterId"
        :items="activeRegisters"
        item-title="name"
        item-value="id"
        :label="t('cash_register.register')"
        variant="outlined"
        rounded="lg"
        clearable
      />
      <v-text-field
        v-model.number="openingFloat"
        type="number"
        min="0"
        step="0.01"
        :label="t('cash_register.opening_float')"
        variant="outlined"
        rounded="lg"
      />
      <v-btn
        color="success"
        variant="flat"
        block
        rounded="lg"
        prepend-icon="mdi-lock-open-variant-outline"
        @click="confirmOpen"
      >
        {{ t('cash_register.open_register') }}
      </v-btn>
    </div>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch, onMounted } from 'vue'
  import { useAuthStore } from '@/stores/authStore'
  import { useI18n } from 'vue-i18n'
  import { useCashDrawerStore } from '@/stores/cashDrawerStore'
  import { useCashRegisterStore } from '@/stores/cashRegisterStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useCashRegisterAccess } from '@/composables/useCashRegisterAccess'
  import { useReceipt } from '@/utils/printReceipt'
  import { useAppUtils, AppDialog } from '@nong-official-dev/core'

  defineProps({
    // Rail (collapsed icon-rail / Sidebar-only rail mode) renders a compact
    // icon-button trigger instead of the app-bar chip — the dialog/panel
    // content below is identical either way.
    rail: { type: Boolean, default: false },
    // Full-width row trigger (Sidebar-only footer, expanded mode) — a squeezed
    // grid cell next to language/notifications doesn't leave room for longer
    // translated status text, so this gets its own full-width row instead.
    block: { type: Boolean, default: false }
  })

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const { hasCashRegisterAccess } = useCashRegisterAccess()
  const { openDrawer } = useReceipt()

  const authStore = useAuthStore()
  const cashDrawerStore = useCashDrawerStore()
  const cashRegisterStore = useCashRegisterStore()
  const branchStore = useBranchStore()

  // ── Cash register ──────────────────────────────────────────────────────
  const registerOpen = ref(false)
  const currentDrawer = ref(null)
  const registerDialog = ref(false)
  const registerBusy = ref(false)
  // 'info' (active shift) | 'cash_in' | 'cash_out' | 'closing' | fallback 'open'
  const panelView = ref('info')
  const actualCash = ref(null)
  const closeNotes = ref('')
  const openingFloat = ref(0)
  const selectedRegisterId = ref(null)
  const movementAmount = ref(null)
  const movementReason = ref('')
  const movementError = ref('')

  const status = computed(() => cashDrawerStore.status)

  const activeRegisters = computed(() =>
    cashRegisterStore.cashRegisters.data?.filter(
      r => r.is_active && r.branch_id === authStore.activeBranchId
    ) ?? []
  )

  function registerName(id) {
    return cashRegisterStore.cashRegisters.data?.find(r => r.id === id)?.name ?? '—'
  }

  const cashierName = computed(() =>
    [authStore.user?.first_name, authStore.user?.last_name].filter(Boolean).join(' ') || '—'
  )

  const difference = computed(() => {
    if (actualCash.value === null || actualCash.value === '' || !status.value) return 0
    return Number(actualCash.value) - Number(status.value.expected_cash ?? 0)
  })

  const differenceLabel = computed(() => {
    if (difference.value > 0) return t('cash_register.overage')
    if (difference.value < 0) return t('cash_register.shortage')
    return t('cash_register.balanced')
  })

  const differenceBg = computed(() => {
    if (difference.value > 0) return 'bg-info-lighten-4 text-info'
    if (difference.value < 0) return 'bg-error-lighten-4 text-error'
    return 'bg-success-lighten-4 text-success'
  })

  async function loadRegisterState() {
    if (!authStore.staff_id && !authStore.isOwner) return
    // branch_id, not staff_id — the real invariant is one open drawer per
    // BRANCH (see CashDrawerService::findOpenForBranch), and an owner has
    // no staff_id to filter by at all.
    await cashDrawerStore.fetchCashDrawers({
      branch_id: authStore.activeBranchId,
      is_open: true,
      perPage: 1
    })
    const drawer = cashDrawerStore.cashDrawers[0] ?? null
    currentDrawer.value = drawer
    registerOpen.value = !!drawer
    if (drawer) await cashDrawerStore.fetchCashDrawerStatus(drawer.id)
  }

  async function openRegisterPanel() {
    panelView.value = registerOpen.value ? 'info' : 'open'
    actualCash.value = null
    closeNotes.value = ''
    openingFloat.value = 0
    movementAmount.value = null
    movementReason.value = ''
    movementError.value = ''
    selectedRegisterId.value = null
    registerDialog.value = true

    if (!cashRegisterStore.cashRegisters.data?.length) {
      await cashRegisterStore.fetchCashRegisters({ perPage: -1, branch_id: authStore.activeBranchId })
    }
    if (registerOpen.value && currentDrawer.value) {
      await cashDrawerStore.fetchCashDrawerStatus(currentDrawer.value.id)
    }
  }

  async function confirmOpen() {
    registerBusy.value = true
    try {
      await cashDrawerStore.openCashDrawer({
        branch_id: authStore.activeBranchId,
        // staff_id is resolved server-side from the authenticated user now
        // (null for an owner) — no longer sent from the client.
        cash_register_id: selectedRegisterId.value || undefined,
        opening_float: openingFloat.value || 0
      })
      await loadRegisterState()
      notif(t('cash_register.opened_success'), { type: 'success' })
      panelView.value = 'info'
    } catch (e) {
      notif(e?.response?.data?.message ?? t('cash_register.action_failed'), { type: 'error' })
    } finally {
      registerBusy.value = false
    }
  }

  async function confirmClose() {
    if (!currentDrawer.value) return
    registerBusy.value = true
    try {
      await cashDrawerStore.closeCashDrawer(currentDrawer.value.id, {
        actual_cash: actualCash.value,
        notes: closeNotes.value || undefined
      })
      await loadRegisterState()
      notif(t('cash_register.closed_success'), { type: 'success' })
      registerDialog.value = false
    } catch (e) {
      notif(e?.response?.data?.message ?? t('cash_register.action_failed'), { type: 'error' })
    } finally {
      registerBusy.value = false
    }
  }

  async function confirmMovement() {
    if (!currentDrawer.value) return
    registerBusy.value = true
    movementError.value = ''
    try {
      if (panelView.value === 'cash_in') {
        await cashDrawerStore.cashIn(currentDrawer.value.id, {
          amount: movementAmount.value,
          reason: movementReason.value
        })
        notif(t('cash_register.cash_in_recorded'), { type: 'success' })
      } else {
        await cashDrawerStore.cashOut(currentDrawer.value.id, {
          amount: movementAmount.value,
          reason: movementReason.value
        })
        notif(t('cash_register.cash_out_recorded'), { type: 'success' })
      }
      panelView.value = 'info'
    } catch (e) {
      movementError.value = e?.response?.data?.errors?.amount?.[0] ?? e?.response?.data?.message ?? t('cash_register.action_failed')
    } finally {
      registerBusy.value = false
    }
  }

  // Manual drawer-kick — gracefully no-ops if no USB/QZ Tray printer is
  // connected/configured (openDrawer() never throws, see printReceipt.js).
  async function handleOpenDrawer() {
    const printerName = branchStore.branches.find(b => b.id === authStore.activeBranchId)?.printer_name ?? null
    const ok = await openDrawer(printerName)
    if (!ok) notif(t('cash_register.no_drawer_hardware'), { type: 'warning' })
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value ?? 0)
  }

  function formatDateTime(value) {
    return value ? new Date(value).toLocaleString() : '—'
  }

  onMounted(() => {
    loadRegisterState()
    if (!branchStore.branches.length) branchStore.fetchBranches({ perPage: -1 })
  })

  // Shift state is per-branch — switching the active branch (sidebar
  // switcher) without re-checking left the chip showing stale state from
  // whichever branch was active before, even though confirmOpen() itself
  // always reads the CURRENT activeBranchId and so correctly rejects an
  // open attempt against a branch that already has a shift running.
  watch(() => authStore.activeBranchId, loadRegisterState)
</script>

<style scoped>
  .rail-btn {
    width: 100%;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0;
    border: 0;
    background: transparent;
    color: rgb(var(--v-theme-on-surface));
    font: 600 13px inherit;
    cursor: pointer;
  }
  .rail-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.05);
  }
  .rail-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }

  .register-row {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 10px;
    border: 0;
    background: transparent;
    color: rgb(var(--v-theme-on-surface));
    font: 600 13px inherit;
    cursor: pointer;
  }
  .register-row:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .register-row:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
</style>
