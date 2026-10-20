<template>
  <v-container fluid class="pa-0">
    <AppToolbar
      :title="$t('settings_hub.title')"
      :subtitle="$t('settings_hub.subtitle')"
    />

    <v-tabs v-model="activeTab" color="primary" class="mb-4" show-arrows>
      <v-tab value="company">{{ t('settings_hub.tabs.company') }}</v-tab>
      <v-tab value="branch">{{ t('settings_hub.tabs.branch') }}</v-tab>
      <v-tab value="pos">{{ t('settings_hub.tabs.pos') }}</v-tab>
      <v-tab value="printer">{{ t('settings_hub.tabs.printer') }}</v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- ── Company Info ────────────────────────────────────────────────── -->
      <v-window-item value="company">
        <v-card rounded="lg" elevation="0" border class="pa-6">
          <v-alert
            v-if="!canEdit"
            type="info"
            variant="tonal"
            density="compact"
            rounded="lg"
            class="mb-5"
            :text="t('settings_hub.owner_only')"
          />

          <ProductImageUpload
            v-model:image-file="logoFile"
            v-model:image-preview="logoPreview"
            compact
            :title="t('settings_hub.logo_upload.title')"
            :format-hint="t('settings_hub.logo_upload.format_hint')"
          />

          <v-form ref="formRef" :disabled="!canEdit || saving">
            <v-row dense>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="form.name"
                  :label="t('tenant_create.field.business_name')"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-domain"
                  :rules="[v => !!v || t('validation.required')]"
                  maxlength="150"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-color-input
                  v-model="form.primary_color"
                  color-pip
                  :label="t('tenant_create.field.brand_color')"
                  variant="outlined"
                  density="comfortable"
                  pip-location="prepend-inner"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.currency"
                  :items="currencyOptions"
                  item-title="label"
                  item-value="value"
                  :label="t('tenant_create.field.currency')"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-currency-usd"
                />
              </v-col>
            </v-row>
          </v-form>

          <div v-if="canEdit" class="d-flex justify-end mt-2">
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              :loading="saving"
              prepend-icon="mdi-content-save-outline"
              @click="save"
            >
              {{ t('btn.save') }}
            </v-btn>
          </div>
        </v-card>
      </v-window-item>

      <!-- ── Branch ───────────────────────────────────────────────────────── -->
      <v-window-item value="branch">
        <Branch />
      </v-window-item>

      <!-- ── POS ──────────────────────────────────────────────────────────── -->
      <v-window-item value="pos">
        <v-card rounded="lg" elevation="0" border class="pa-6">
          <v-alert
            v-if="!canEdit"
            type="info"
            variant="tonal"
            density="compact"
            rounded="lg"
            class="mb-5"
            :text="t('settings_hub.owner_only')"
          />

          <p class="text-caption text-medium-emphasis mb-4">
            {{ t('settings_hub.pos_settings_helper') }}
          </p>

          <div class="mb-5">
            <div class="text-body-2 font-weight-bold mb-2">
              {{ t('settings_hub.pos_order_types') }}
            </div>
            <v-switch
              v-for="ot in orderTypeOptions"
              :key="ot.value"
              v-model="form.pos_settings.order_types"
              :value="ot.value"
              :label="ot.label"
              :disabled="!canEdit || saving"
              color="primary"
              density="compact"
              hide-details
              class="mb-1"
            />
          </div>

          <v-divider class="mb-5" />

          <v-switch
            v-model="form.pos_settings.customer_selection"
            :label="t('settings_hub.pos_customer_selection')"
            :disabled="!canEdit || saving"
            color="primary"
            density="compact"
            hide-details
            class="mb-3"
          />
          <v-switch
            v-model="form.pos_settings.order_notes"
            :label="t('settings_hub.pos_order_notes')"
            :disabled="!canEdit || saving"
            color="primary"
            density="compact"
            hide-details
            class="mb-3"
          />
          <v-divider class="mb-5" />

          <div class="mb-3">
            <div class="text-body-2 font-weight-bold mb-2">
              {{ t('settings_hub.pos_payment_methods') }}
            </div>
            <v-switch
              v-for="pm in paymentMethodOptions"
              :key="pm.value"
              v-model="form.pos_settings.payment_methods"
              :value="pm.value"
              :label="pm.label"
              :disabled="!canEdit || saving"
              color="primary"
              density="compact"
              hide-details
              class="mb-1"
            />
          </div>

          <v-select
            v-model="form.pos_settings.default_payment_method"
            :items="enabledPaymentMethodOptions"
            item-title="label"
            item-value="value"
            :label="t('settings_hub.pos_default_payment_method')"
            :disabled="!canEdit || saving"
            variant="outlined"
            rounded="lg"
            density="compact"
            hide-details
          />

          <v-divider class="my-5" />

          <div class="mb-3">
            <div class="text-body-2 font-weight-bold mb-2">
              {{ t('settings_hub.table_payment_methods') }}
            </div>
            <div class="text-caption text-medium-emphasis mb-2">
              {{ t('settings_hub.table_payment_methods_hint') }}
            </div>
            <v-switch
              v-for="pm in paymentMethodOptions"
              :key="pm.value"
              v-model="form.pos_settings.table_payment_methods"
              :value="pm.value"
              :label="pm.label"
              :disabled="!canEdit || saving"
              color="primary"
              density="compact"
              hide-details
              class="mb-1"
            />
          </div>

          <v-select
            v-model="form.pos_settings.default_table_payment_method"
            :items="enabledTablePaymentMethodOptions"
            item-title="label"
            item-value="value"
            :label="t('settings_hub.table_default_payment_method')"
            :disabled="!canEdit || saving"
            variant="outlined"
            rounded="lg"
            density="compact"
            hide-details
          />

          <v-divider class="my-5" />

          <div class="text-body-2 font-weight-bold mb-2">
            {{ t('settings_hub.pos_exchange_rate_section') }}
          </div>
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="form.pos_settings.khr_exchange_rate"
                type="number"
                min="0.01"
                step="1"
                :label="t('settings_hub.pos_exchange_rate')"
                :hint="t('settings_hub.pos_exchange_rate_hint')"
                persistent-hint
                :disabled="!canEdit || saving"
                :rules="[v => Number(v) > 0 || t('validation.required')]"
                variant="outlined"
                rounded="lg"
                density="compact"
                prepend-inner-icon="mdi-currency-usd"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.pos_settings.khr_rounding"
                :items="khrRoundingOptions"
                :label="t('settings_hub.pos_khr_rounding')"
                :disabled="!canEdit || saving"
                variant="outlined"
                rounded="lg"
                density="compact"
                hide-details
              />
            </v-col>
          </v-row>

          <div v-if="canEdit" class="d-flex justify-end mt-5">
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              :loading="saving"
              prepend-icon="mdi-content-save-outline"
              @click="save"
            >
              {{ t('btn.save') }}
            </v-btn>
          </div>
        </v-card>
      </v-window-item>

      <!-- ── Printer ──────────────────────────────────────────────────────── -->
      <v-window-item value="printer">
        <v-card rounded="lg" elevation="0" border class="pa-6">
          <div class="text-body-2 text-medium-emphasis mb-5">
            {{ t('settings_hub.printer.helper') }}
          </div>

          <template v-if="usbSupported">
            <div class="text-body-2 font-weight-bold mb-2">
              {{ t('settings_hub.printer.usb_section') }}
            </div>
            <div class="text-caption text-medium-emphasis mb-3">
              {{ t('settings_hub.printer.usb_hint') }}
            </div>
            <div v-if="usbConnected" class="d-flex align-center flex-wrap ga-3 mb-5">
              <v-chip color="success" variant="tonal" rounded="lg" prepend-icon="mdi-check-circle">
                {{ t('settings_hub.printer.usb_connected') }}
              </v-chip>
              <v-btn variant="tonal" rounded="lg" prepend-icon="mdi-usb-port" @click="onDisconnectUsb">
                {{ t('settings_hub.printer.usb_disconnect') }}
              </v-btn>
            </div>
            <div v-else class="mb-5">
              <v-btn
                color="primary"
                variant="flat"
                rounded="lg"
                :loading="connectingUsb"
                prepend-icon="mdi-usb-port"
                @click="onConnectUsb"
              >
                {{ t('settings_hub.printer.usb_connect') }}
              </v-btn>
            </div>
            <v-divider class="mb-5" />
            <div class="text-body-2 font-weight-bold mb-2">
              {{ t('settings_hub.printer.qz_section') }}
            </div>
            <div class="text-caption text-medium-emphasis mb-3">
              {{ t('settings_hub.printer.qz_hint') }}
            </div>
          </template>

          <v-select
            v-if="branchStore.branches.length > 1"
            v-model="selectedBranchId"
            :items="branchStore.branches"
            item-title="name"
            item-value="id"
            :label="t('settings_hub.printer.branch_label')"
            variant="outlined"
            rounded="lg"
            density="comfortable"
            prepend-inner-icon="mdi-store-outline"
            class="mb-5"
            style="max-width: 320px"
          />

          <v-text-field
            v-model="printerNameInput"
            :label="t('settings_hub.printer.printer_name_label')"
            variant="outlined"
            density="comfortable"
            rounded="lg"
            prepend-inner-icon="mdi-printer-outline"
            :placeholder="t('settings_hub.printer.printer_name_placeholder')"
            :hint="t('settings_hub.printer.printer_name_hint')"
            persistent-hint
            class="mb-3"
            style="max-width: 420px"
          />
          <div class="d-flex justify-end mb-5" style="max-width: 420px">
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              :loading="savingPrinterName"
              :disabled="printerNameInput === (selectedBranch?.printer_name ?? '')"
              prepend-icon="mdi-content-save-outline"
              @click="savePrinterName"
            >
              {{ t('btn.save') }}
            </v-btn>
          </div>

          <v-alert
            v-if="!selectedBranch?.printer_name"
            type="info"
            variant="tonal"
            density="compact"
            rounded="lg"
            class="mb-5"
          >
            {{ t('settings_hub.printer.save_to_enable_checks') }}
          </v-alert>

          <template v-else>
            <div class="d-flex align-center flex-wrap ga-3 mb-5">
              <v-chip
                :color="statusColor"
                variant="tonal"
                rounded="lg"
                :prepend-icon="statusIcon"
              >
                {{ statusLabel }}
              </v-chip>
              <v-btn
                variant="tonal"
                rounded="lg"
                :loading="checking"
                prepend-icon="mdi-connection"
                @click="runCheckConnection"
              >
                {{ t('settings_hub.printer.check_connection') }}
              </v-btn>
            </div>

            <div class="d-flex flex-wrap ga-3">
              <v-btn
                color="primary"
                variant="flat"
                rounded="lg"
                :loading="testPrinting"
                prepend-icon="mdi-printer-check"
                @click="sendTestPrint"
              >
                {{ t('settings_hub.printer.test_print') }}
              </v-btn>
              <v-btn
                variant="tonal"
                rounded="lg"
                :loading="testingQzEncoding"
                prepend-icon="mdi-translate"
                @click="sendTestPrintQzEncoding"
              >
                {{ t('settings_hub.printer.test_qz_encoding') }}
              </v-btn>
            </div>
          </template>
        </v-card>
      </v-window-item>
    </v-window>
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { useReceipt } from '@/utils/printReceipt'
  import { getTenantByIdApi, updateTenantProfileApi } from '@/api/tenantService'
  import Branch from '@/views/branches/Branch.vue'
  import ProductImageUpload from '@/components/products/ProductImageUpload.vue'
  import AppToolbar from '@/components/common/AppToolbar.vue'

  const { t } = useI18n()
  const route = useRoute()
  const authStore = useAuthStore()
  const branchStore = useBranchStore()
  const { notif } = useAppUtils()
  const {
    print: printThermal,
    checkConnection,
    error: printError,
    connectUsb,
    disconnectUsb,
    usbConnected,
    usbSupported,
    testPrintQz
  } = useReceipt()

  const requestedTab = typeof route.query.tab === 'string' ? route.query.tab : null
  const activeTab = ref(['branch', 'pos', 'printer'].includes(requestedTab) ? requestedTab : 'company')

  const canEdit = computed(() => authStore.isOwner || authStore.isSuperAdmin)

  const formRef = ref(null)
  const saving = ref(false)
  const logoFile = ref(null)
  const logoPreview = ref(null)
  const form = reactive({
    name: '',
    primary_color: '#6366f1',
    currency: 'USD',
    pos_settings: {
      order_types: ['dine_in', 'takeaway', 'delivery'],
      customer_selection: true,
      order_notes: true,
      payment_methods: ['cash', 'card', 'qr', 'pay_later'],
      default_payment_method: 'cash',
      table_payment_methods: ['cash', 'card'],
      default_table_payment_method: 'cash',
      khr_exchange_rate: 4100,
      khr_rounding: 100
    }
  })

  const khrRoundingOptions = [
    { value: 100, title: '100 ៛' },
    { value: 500, title: '500 ៛' },
    { value: 1000, title: '1,000 ៛' }
  ]

  const orderTypeOptions = [
    { value: 'dine_in', label: t('pos.cart.order_type.dine_in') },
    { value: 'takeaway', label: t('pos.cart.order_type.takeaway') },
    { value: 'delivery', label: t('pos.cart.order_type.delivery') }
  ]

  const paymentMethodOptions = [
    { value: 'cash', label: t('pos.checkout.methods.cash') },
    { value: 'card', label: t('pos.checkout.methods.card') },
    { value: 'qr', label: t('pos.checkout.methods.qr') },
    { value: 'pay_later', label: t('pos.checkout.methods.pay_later') }
  ]

  // The default can only ever be one of the currently-enabled methods —
  // narrows the select's own options rather than letting the form save a
  // default that checkout would never actually offer.
  const enabledPaymentMethodOptions = computed(() =>
    paymentMethodOptions.filter(pm => form.pos_settings.payment_methods.includes(pm.value))
  )
  // Same option list as POS checkout above — a tenant may still want a
  // different subset/default enabled for settling a table's bill than for
  // ringing up a walk-in sale, so it's tracked as its own setting.
  const enabledTablePaymentMethodOptions = computed(() =>
    paymentMethodOptions.filter(pm => form.pos_settings.table_payment_methods.includes(pm.value))
  )

  // If the tenant unchecks whichever method is currently the default, fall
  // back to the first method still enabled instead of saving a default
  // that's no longer a valid choice.
  watch(
    () => form.pos_settings.payment_methods,
    methods => {
      if (!methods.includes(form.pos_settings.default_payment_method)) {
        form.pos_settings.default_payment_method = methods[0] ?? 'cash'
      }
    },
    { deep: true }
  )
  watch(
    () => form.pos_settings.table_payment_methods,
    methods => {
      if (!methods.includes(form.pos_settings.default_table_payment_method)) {
        form.pos_settings.default_table_payment_method = methods[0] ?? 'cash'
      }
    },
    { deep: true }
  )

  onMounted(async () => {
    if (!authStore.tenant_id) return
    const res = await getTenantByIdApi(authStore.tenant_id).catch(() => null)
    const tenant = res?.data?.data?.tenant
    if (!tenant) return
    form.name = tenant.name ?? ''
    logoPreview.value = tenant.logo_url ?? null
    form.primary_color = tenant.primary_color ?? '#6366f1'
    form.currency = tenant.currency ?? 'USD'
    if (tenant.pos_settings) {
      form.pos_settings.order_types = tenant.pos_settings.order_types ?? form.pos_settings.order_types
      form.pos_settings.customer_selection = tenant.pos_settings.customer_selection ?? true
      form.pos_settings.order_notes = tenant.pos_settings.order_notes ?? true
      form.pos_settings.payment_methods =
        tenant.pos_settings.payment_methods ?? form.pos_settings.payment_methods
      form.pos_settings.default_payment_method =
        tenant.pos_settings.default_payment_method ?? 'cash'
      form.pos_settings.table_payment_methods =
        tenant.pos_settings.table_payment_methods ?? form.pos_settings.table_payment_methods
      form.pos_settings.default_table_payment_method =
        tenant.pos_settings.default_table_payment_method ?? 'cash'
      form.pos_settings.khr_exchange_rate =
        tenant.pos_settings.khr_exchange_rate ?? 4100
      form.pos_settings.khr_rounding =
        tenant.pos_settings.khr_rounding ?? 100
    }
  })

  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    saving.value = true
    try {
      // No new logo picked → plain JSON, existing logo_url stays untouched
      // server-side. A new file → multipart, the file always wins over
      // whatever's already stored (see TenantController::updateProfile()).
      let body = { ...form }
      if (logoFile.value) {
        const fd = new FormData()
        fd.append('logo', logoFile.value)
        Object.entries(form).forEach(([key, value]) => {
          if (value === null || value === undefined) return
          fd.append(key, typeof value === 'object' ? JSON.stringify(value) : value)
        })
        body = fd
      }

      await updateTenantProfileApi(authStore.tenant_id, body)
      await authStore.fetchMe()
      logoFile.value = null
      notif(t('settings_hub.saved'), { type: 'success'})
    } catch (err) {
      notif(err?.response?.data?.message || t('settings_hub.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  const currencyOptions = [
    { value: 'USD', label: t('tenant_create.currency.usd') },
    { value: 'KHR', label: t('tenant_create.currency.khr') },
    { value: 'EUR', label: t('tenant_create.currency.eur') },
    { value: 'GBP', label: t('tenant_create.currency.gbp') },
    { value: 'SGD', label: t('tenant_create.currency.sgd') },
    { value: 'MYR', label: t('tenant_create.currency.myr') }
  ]

  // ── Printer tab ──────────────────────────────────────────────────────────
  const selectedBranchId = ref(authStore.activeBranchId ?? null)
  const selectedBranch = computed(
    () =>
      branchStore.branches.find(b => b.id === selectedBranchId.value) ??
      branchStore.branches[0] ??
      null
  )

  // connectionStatus: null (never checked) | 'connected' | 'disconnected'
  const connectionStatus = ref(null)
  const checking = ref(false)
  const testPrinting = ref(false)
  const testingQzEncoding = ref(false)
  const savingPrinterName = ref(false)
  const printerNameInput = ref('')

  // Reload the input (and drop any stale check result) whenever the
  // selected branch changes or its printer_name is updated elsewhere.
  watch(
    () => selectedBranch.value?.printer_name,
    val => {
      printerNameInput.value = val ?? ''
      connectionStatus.value = null
    },
    { immediate: true }
  )

  async function savePrinterName() {
    if (!selectedBranch.value) return
    savingPrinterName.value = true
    try {
      await branchStore.updateBranch(selectedBranch.value.id, {
        // Explicit null, not undefined — undefined gets dropped from the
        // JSON body entirely, so clearing the field would silently leave
        // the old value untouched server-side instead of actually clearing it.
        printer_name: printerNameInput.value || null
      })
      await branchStore.fetchBranches({ perPage: -1 })
      notif(t('settings_hub.printer.saved'), { type: 'success' })
    } catch (err) {
      notif(err?.response?.data?.message || t('settings_hub.printer.save_failed'), { type: 'error' })
    } finally {
      savingPrinterName.value = false
    }
  }

  const connectingUsb = ref(false)

  // Opens the browser's own device picker (navigator.usb.requestDevice) —
  // the only way to grant this origin permission to talk to the printer.
  // Once granted it's remembered by the browser, so future visits silently
  // reconnect via checkConnection()/print() without this prompt again.
  async function onConnectUsb() {
    connectingUsb.value = true
    try {
      const ok = await connectUsb()
      connectionStatus.value = ok ? 'connected' : 'disconnected'
      if (ok) {
        notif(t('settings_hub.printer.usb_connected'), { type: 'success' })
      } else {
        // Surface the real browser/USB error (e.g. "Access denied", "Unable
        // to claim interface", "No device selected") instead of only a
        // generic message — this is what actually tells you whether it's a
        // Windows driver conflict, a cancelled picker, or something else.
        notif(
          t('settings_hub.printer.usb_connect_failed', { reason: printError.value ?? '—' }),
          { type: 'error' }
        )
      }
    } finally {
      connectingUsb.value = false
    }
  }

  function onDisconnectUsb() {
    disconnectUsb()
    connectionStatus.value = null
  }

  const statusColor = computed(() =>
    connectionStatus.value === 'connected' ? 'success' : connectionStatus.value === 'disconnected' ? 'error' : 'grey'
  )
  const statusIcon = computed(() =>
    connectionStatus.value === 'connected'
      ? 'mdi-check-circle'
      : connectionStatus.value === 'disconnected'
        ? 'mdi-close-circle'
        : 'mdi-help-circle-outline'
  )
  const statusLabel = computed(() =>
    connectionStatus.value === 'connected'
      ? t('settings_hub.printer.status.connected')
      : connectionStatus.value === 'disconnected'
        ? t('settings_hub.printer.status.disconnected')
        : t('settings_hub.printer.status.unknown')
  )

  async function runCheckConnection() {
    checking.value = true
    try {
      const result = await checkConnection(selectedBranch.value?.printer_name)
      connectionStatus.value = result.connected ? 'connected' : 'disconnected'
    } finally {
      checking.value = false
    }
  }

  function buildTestReceipt() {
    return {
      order_number: 'TEST-PRINT',
      branch_name: selectedBranch.value?.name ?? '-',
      branch_address: selectedBranch.value?.address_line1 ?? null,
      branch_phone: selectedBranch.value?.phone ?? null,
      date: new Date().toLocaleString(),
      items: [{ name: t('settings_hub.printer.test_item'), unit: null, qty: 1, unit_price: 0, total_price: 0 }],
      subtotal: 0,
      discount: 0,
      tax: 0,
      service_charge: 0,
      total: 0,
      payment_method: null
    }
  }

  // Calls the real print path directly (not the POS/Table-modal wrappers,
  // which silently fall back to the browser print dialog on failure) so a
  // failure here is an honest signal, not a popup that looks like success.
  async function sendTestPrint() {
    testPrinting.value = true
    try {
      const ok = await printThermal(buildTestReceipt(), selectedBranch.value?.printer_name)
      notif(
        ok
          ? t('settings_hub.printer.test_print_sent')
          : t('settings_hub.printer.test_print_failed', { reason: printError.value ?? '' }),
        { type: ok ? 'success' : 'error' }
      )
      connectionStatus.value = ok ? 'connected' : 'disconnected'
    } finally {
      testPrinting.value = false
    }
  }

  // QZ-Tray-only diagnostic — isolates whether plain ASCII raw-command
  // text and Khmer-as-image both render correctly through QZ specifically
  // (separate from sendTestPrint()'s full receipt, which already goes
  // through the real whole-receipt-as-image fix).
  async function sendTestPrintQzEncoding() {
    if (!selectedBranch.value?.printer_name) return
    testingQzEncoding.value = true
    try {
      await testPrintQz(selectedBranch.value.printer_name)
      notif(t('settings_hub.printer.test_print_sent'), { type: 'success' })
    } catch (err) {
      notif(
        t('settings_hub.printer.test_print_failed', { reason: err?.message ?? '' }),
        { type: 'error' }
      )
    } finally {
      testingQzEncoding.value = false
    }
  }

  onMounted(() => {
    if (!branchStore.branches.length) branchStore.fetchBranches({ perPage: -1 })
  })
</script>
