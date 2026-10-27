<template>
  <v-container fluid class="pa-0">
    <AppToolbar :title="$t('settings_hub.title')" :subtitle="headerSubtitle">
      <template v-if="section === 'printer'" #actions>
        <v-select
          v-model="selectedBranchIdModel"
          :items="branchStore.branches"
          item-title="name"
          item-value="id"
          :label="t('settings_hub.printer.branch_label')"
          variant="outlined"
          density="compact"
          rounded="lg"
          hide-details
          prepend-inner-icon="mdi-store-outline"
          style="min-width: 220px"
        />
      </template>
    </AppToolbar>

    <!-- ── Mobile/tablet — chips above content instead of a side menu ──── -->
    <v-chip-group
      v-if="smAndDown"
      :model-value="section"
      mandatory
      selected-class="text-primary"
      class="mb-4"
      @update:model-value="section = $event"
    >
      <v-chip v-for="s in SECTION_KEYS" :key="s" :value="s" filter variant="tonal" rounded="lg">
        {{ t(`settings_hub.menu.${s}.title`) }}
      </v-chip>
    </v-chip-group>

    <v-row>
      <!-- ── Left section menu (desktop/tablet) ───────────────────────── -->
      <v-col v-if="!smAndDown" cols="12" md="3">
        <v-card rounded="lg">
          <v-list nav density="comfortable">
            <v-list-item
              v-for="s in SECTION_KEYS"
              :key="s"
              :active="section === s"
              color="primary"
              variant="tonal"
              rounded="lg"
              :title="t(`settings_hub.menu.${s}.title`)"
              :subtitle="t(`settings_hub.menu.${s}.subtitle`)"
              @click="section = s"
            />
          </v-list>
        </v-card>
      </v-col>

      <v-col cols="12" :md="smAndDown ? 12 : 9">
        <!-- ── Business ──────────────────────────────────────────────── -->
        <v-card v-if="section === 'business'" rounded="lg" class="pa-6">
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
              <v-col cols="12" sm="6">
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
              <v-col cols="12" sm="6">
                <v-color-input
                  v-model="form.primary_color"
                  color-pip
                  :label="t('tenant_create.field.brand_color')"
                  variant="outlined"
                  density="comfortable"
                  pip-location="prepend-inner"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card>

        <!-- ── Currency ──────────────────────────────────────────────── -->
        <v-card v-else-if="section === 'currency'" rounded="lg" class="pa-6">
          <v-alert
            v-if="!canEdit"
            type="info"
            variant="tonal"
            density="compact"
            rounded="lg"
            class="mb-5"
            :text="t('settings_hub.owner_only')"
          />
          <v-row dense>
            <v-col cols="12" sm="4">
              <v-select
                v-model="form.currency"
                :items="currencyOptions"
                item-title="label"
                item-value="value"
                :label="t('tenant_create.field.currency')"
                :disabled="!canEdit || saving"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-currency-usd"
              />
            </v-col>
            <v-col cols="12" sm="4">
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
                prefix="៛"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-select
                v-model="form.pos_settings.khr_rounding"
                :items="khrRoundingOptions"
                :label="t('settings_hub.pos_khr_rounding')"
                :disabled="!canEdit || saving"
                variant="outlined"
                rounded="lg"
              />
            </v-col>
          </v-row>

          <v-sheet color="grey-lighten-4" rounded="lg" class="pa-4 mt-2 d-flex align-center justify-space-between flex-wrap ga-2">
            <span class="text-body-2 text-medium-emphasis">{{ t('settings_hub.currency_preview_label') }}</span>
            <span class="text-body-1 font-weight-bold">{{ currencyPreviewText }}</span>
          </v-sheet>
          <p class="text-caption text-medium-emphasis mt-3 mb-0">
            {{ t('settings_hub.currency_preview_note') }}
          </p>
        </v-card>

        <!-- ── Branches ──────────────────────────────────────────────── -->
         <v-card v-else-if="section === 'branches'" rounded="lg" class="pa-4">
           <Branch />
         </v-card>

        <!-- ── POS & payments ────────────────────────────────────────── -->
        <v-card v-else-if="section === 'pos'" rounded="lg" class="pa-6">
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
            <div class="text-body-2 font-weight-bold mb-3">
              {{ t('settings_hub.pos_order_types') }}
            </div>
            <v-row dense>
              <v-col v-for="ot in orderTypeOptions" :key="ot.value" cols="12" sm="4">
                <v-card
                  variant="outlined"
                  rounded="lg"
                  class="pa-4 d-flex align-center justify-space-between"
                  :class="isOrderTypeEnabled(ot.value) ? 'bg-teal-lighten-5' : ''"
                >
                  <span class="text-body-2 font-weight-medium">{{ ot.label }}</span>
                  <v-switch
                    :model-value="isOrderTypeEnabled(ot.value)"
                    :disabled="!canEdit || saving"
                    color="teal"
                    hide-details
                    density="compact"
                    @update:model-value="toggleOrderType(ot.value, $event)"
                  />
                </v-card>
              </v-col>
            </v-row>
          </div>

          <v-divider class="mb-5" />

          <div class="mb-5">
            <div class="text-body-2 font-weight-bold mb-3">
              {{ t('settings_hub.pos_order_options') }}
            </div>
            <v-row dense>
              <v-col v-for="opt in orderOptionOptions" :key="opt.key" cols="12" sm="6">
                <v-card
                  variant="outlined"
                  rounded="lg"
                  class="pa-4 d-flex align-center justify-space-between"
                  :class="form.pos_settings[opt.key] ? 'bg-teal-lighten-5' : ''"
                >
                  <span class="text-body-2 font-weight-medium">{{ opt.label }}</span>
                  <v-switch
                    v-model="form.pos_settings[opt.key]"
                    :disabled="!canEdit || saving"
                    color="teal"
                    hide-details
                    density="compact"
                  />
                </v-card>
              </v-col>
            </v-row>
          </div>

          <v-divider class="mb-5" />

          <div class="text-body-2 font-weight-bold mb-2">
            {{ t('settings_hub.pos_payment_methods') }}
          </div>
          <v-table density="comfortable" class="mb-3 border rounded-lg">
            <thead>
              <tr>
                <th>{{ t('settings_hub.payment_table.method') }}</th>
                <th class="text-center">{{ t('settings_hub.payment_table.quick_sale') }}</th>
                <th class="text-center">{{ t('settings_hub.payment_table.table_bill') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pm in paymentMethodOptions" :key="pm.value">
                <td>{{ pm.label }}</td>
                <td class="text-center">
                  <v-checkbox-btn
                    :model-value="form.pos_settings.payment_methods.includes(pm.value)"
                    :disabled="!canEdit || saving"
                    color="primary"
                    @update:model-value="toggleMethod('payment_methods', pm.value, $event)"
                  />
                </td>
                <td class="text-center">
                  <span v-if="pm.value === 'pay_later'" class="text-caption text-medium-emphasis">
                    {{ t('settings_hub.payment_table.not_used') }}
                  </span>
                  <v-checkbox-btn
                    v-else
                    :model-value="form.pos_settings.table_payment_methods.includes(pm.value)"
                    :disabled="!canEdit || saving"
                    color="primary"
                    @update:model-value="toggleMethod('table_payment_methods', pm.value, $event)"
                  />
                </td>
              </tr>
              <tr>
                <td class="text-body-2 font-weight-medium">
                  {{ t('settings_hub.payment_table.default_method') }}
                </td>
                <td class="py-2">
                  <v-select
                    v-model="form.pos_settings.default_payment_method"
                    :items="enabledPaymentMethodOptions"
                    item-title="label"
                    item-value="value"
                    :disabled="!canEdit || saving"
                    variant="outlined"
                    density="compact"
                    rounded="lg"
                    hide-details
                  />
                </td>
                <td class="py-2">
                  <v-select
                    v-model="form.pos_settings.default_table_payment_method"
                    :items="enabledTablePaymentMethodOptions"
                    item-title="label"
                    item-value="value"
                    :disabled="!canEdit || saving"
                    variant="outlined"
                    density="compact"
                    rounded="lg"
                    hide-details
                  />
                </td>
              </tr>
            </tbody>
          </v-table>

          <v-alert
            v-if="!posPaymentValid"
            type="warning"
            variant="tonal"
            density="compact"
            rounded="lg"
            :text="t('settings_hub.payment_table.need_one_hint')"
          />
        </v-card>

        <!-- ── Printer ───────────────────────────────────────────────── -->
        <v-card v-else rounded="lg" class="pa-6">
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
            class="mb-5"
            style="max-width: 420px"
          />

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

        <!-- ── One save bar for the whole page — Branches saves/deletes
             instantly through its own drawer instead, so this bar would be
             both dead and misleading there. ──────────────────────────────── -->
        <v-card
          v-if="section !== 'branches'"
          rounded="lg"
          class="pa-4 mt-4 d-flex flex-wrap align-center justify-space-between ga-3"
        >
          <span class="text-caption text-medium-emphasis">{{ saveBarText }}</span>
          <div class="d-flex ga-2">
            <v-btn variant="text" class="text-none" :disabled="!dirty || saving" @click="discardAll">
              {{ t('settings_hub.discard') }}
            </v-btn>
            <v-btn
              color="primary"
              variant="flat"
              class="text-none"
              :disabled="!dirty || saving"
              :loading="saving"
              @click="saveAll"
            >
              {{ t('settings_hub.save_changes') }}
            </v-btn>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- ── Unsaved-changes guard ───────────────────────────────────────── -->
    <v-dialog v-model="showLeaveConfirm" max-width="420" persistent>
      <v-card rounded="lg">
        <v-card-title>{{ t('settings_hub.unsaved_title') }}</v-card-title>
        <v-card-text>{{ t('settings_hub.unsaved_body') }}</v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="cancelLeave">
            {{ t('settings_hub.unsaved_stay') }}
          </v-btn>
          <v-btn color="error" variant="flat" class="text-none" @click="confirmLeave">
            {{ t('settings_hub.unsaved_leave') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- ── Unsaved-changes guard — switching which branch's printer you're
         configuring ──────────────────────────────────────────────────── -->
    <v-dialog v-model="showBranchSwitchConfirm" max-width="420" persistent>
      <v-card rounded="lg">
        <v-card-title>{{ t('settings_hub.branch_switch_title') }}</v-card-title>
        <v-card-text>{{ t('settings_hub.branch_switch_body') }}</v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" class="text-none" @click="cancelBranchSwitch">
            {{ t('settings_hub.branch_switch_cancel') }}
          </v-btn>
          <v-btn color="error" variant="flat" class="text-none" @click="confirmBranchSwitch">
            {{ t('settings_hub.branch_switch_confirm') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
  import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
  import { useDisplay } from 'vuetify'
  import { useAuthStore } from '@/stores/authStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { useReceipt } from '@/utils/printReceipt'
  import { useSettingsForm } from '@/composables/useSettingsForm'
  import { getTenantByIdApi, updateTenantProfileApi } from '@/api/tenantService'
  import Branch from '@/views/branches/Branch.vue'
  import ProductImageUpload from '@/components/products/ProductImageUpload.vue'
  import AppToolbar from '@/components/common/AppToolbar.vue'

  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()
  const { smAndDown } = useDisplay()
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

  const SECTION_KEYS = ['business', 'currency', 'branches', 'pos', 'printer']

  // Its own route/query (?section=...) — linkable, survives a refresh.
  const section = computed({
    get: () => (SECTION_KEYS.includes(route.query.section) ? route.query.section : 'business'),
    set: val => router.push({ query: { ...route.query, section: val } })
  })

  const headerSubtitle = computed(() =>
    section.value === 'printer'
      ? t('settings_hub.subtitle_selected_branch')
      : t('settings_hub.subtitle_all_branches')
  )

  const canEdit = computed(() => authStore.isOwner || authStore.isSuperAdmin)

  const formRef = ref(null)
  const saving = ref(false)
  const logoFile = ref(null)
  const logoPreview = ref(null)

  const DEFAULT_TENANT_FORM = {
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
  }

  // ONE shared reactive form + original baseline for the whole page's
  // Business/Currency/POS fields — see composables/useSettingsForm.js.
  // Printer/logo have their own dirty tracking below since they save
  // through a different API call (per-branch vs. tenant profile).
  const {
    form,
    isDirty: dirtyTenant,
    load: loadTenantData,
    discard: discardTenant,
    commit: commitTenant
  } = useSettingsForm(DEFAULT_TENANT_FORM)

  const khrRoundingOptions = [
    { value: 100, title: '100 ៛' },
    { value: 500, title: '500 ៛' },
    { value: 1000, title: '1,000 ៛' }
  ]

  // Fixed sample amount — only here to demonstrate the rate/rounding math
  // live as the tenant edits it, same formula as the real receipt (see
  // utils/printReceipt.js).
  const CURRENCY_PREVIEW_USD = 12.35
  const currencyPreviewText = computed(() => {
    const rate = Number(form.pos_settings.khr_exchange_rate) > 0 ? Number(form.pos_settings.khr_exchange_rate) : 4100
    const rounding = Number(form.pos_settings.khr_rounding) > 0 ? Number(form.pos_settings.khr_rounding) : 100
    const khr = Math.round((CURRENCY_PREVIEW_USD * rate) / rounding) * rounding
    const usdText = `$${CURRENCY_PREVIEW_USD.toFixed(2)}`
    const khrText = `${new Intl.NumberFormat('en-US').format(khr)} ៛`
    return `${usdText} / ${khrText}`
  })

  const orderTypeOptions = [
    { value: 'dine_in', label: t('pos.cart.order_type.dine_in') },
    { value: 'takeaway', label: t('pos.cart.order_type.takeaway') },
    { value: 'delivery', label: t('pos.cart.order_type.delivery') }
  ]

  function isOrderTypeEnabled(value) {
    return form.pos_settings.order_types.includes(value)
  }
  function toggleOrderType(value, enabled) {
    const list = form.pos_settings.order_types
    const idx = list.indexOf(value)
    if (enabled && idx === -1) list.push(value)
    if (!enabled && idx !== -1) list.splice(idx, 1)
  }

  const orderOptionOptions = [
    { key: 'customer_selection', label: t('settings_hub.pos_customer_selection') },
    { key: 'order_notes', label: t('settings_hub.pos_order_notes') }
  ]

  const paymentMethodOptions = [
    { value: 'cash', label: t('pos.checkout.methods.cash') },
    { value: 'card', label: t('pos.checkout.methods.card') },
    { value: 'qr', label: t('pos.checkout.methods.qr') },
    { value: 'pay_later', label: t('pos.checkout.methods.pay_later') }
  ]

  // Shared checkbox-cell toggler for both payment_methods (Quick Sale
  // column) and table_payment_methods (Table bill column) — pay_later is
  // never toggleable for table_payment_methods (see template: "Not used").
  function toggleMethod(key, value, enabled) {
    const list = form.pos_settings[key]
    const idx = list.indexOf(value)
    if (enabled && idx === -1) list.push(value)
    if (!enabled && idx !== -1) list.splice(idx, 1)
  }

  // The default can only ever be one of the currently-enabled methods —
  // narrows the select's own options rather than letting the form save a
  // default that checkout would never actually offer.
  const enabledPaymentMethodOptions = computed(() =>
    paymentMethodOptions.filter(pm => form.pos_settings.payment_methods.includes(pm.value))
  )
  // Same option list as POS checkout above — a tenant may still want a
  // different subset/default enabled for settling a table's bill than for
  // ringing up a walk-in sale, so it's tracked as its own setting. pay_later
  // never applies to a table bill (see template: "Not used").
  const enabledTablePaymentMethodOptions = computed(() =>
    paymentMethodOptions.filter(
      pm => pm.value !== 'pay_later' && form.pos_settings.table_payment_methods.includes(pm.value)
    )
  )

  // Each column must keep at least one method ticked — gates the shared
  // Save button (see save bar below), not just a toast at save time.
  const posPaymentValid = computed(
    () =>
      form.pos_settings.payment_methods.length > 0 &&
      form.pos_settings.table_payment_methods.length > 0
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

  // ── Dirty tracking — ONE save bar covers every section ──────────────────
  // dirtyTenant comes from useSettingsForm() above (business/currency/pos —
  // all saved through one combined tenant-profile PUT). Printer and the
  // logo file save through a different call (per-branch), so they're
  // tracked separately but still fold into the same page-wide `dirty`.
  const dirtyLogo = computed(() => !!logoFile.value)
  const dirtyPrinter = computed(
    () => printerNameInput.value !== (selectedBranch.value?.printer_name ?? '')
  )
  const dirty = computed(() => dirtyTenant.value || dirtyLogo.value || dirtyPrinter.value)

  // Informational grouping of which section(s) actually changed — Save
  // below already only calls the tenant-profile API when dirtyTenant/
  // dirtyLogo is true and the branch API when dirtyPrinter is true; this is
  // the same split surfaced at UI granularity.
  const dirtySections = computed(() => {
    const sections = []
    if (dirtyTenant.value || dirtyLogo.value) sections.push('business', 'currency', 'pos')
    if (dirtyPrinter.value) sections.push('printer')
    return sections
  })

  const saveBarText = computed(() => {
    if (saving.value) return t('settings_hub.save_bar_saving')
    return dirty.value ? t('settings_hub.save_bar_dirty') : t('settings_hub.save_bar_clean')
  })

  async function loadTenant() {
    if (!authStore.tenant_id) return
    const res = await getTenantByIdApi(authStore.tenant_id).catch(() => null)
    const tenant = res?.data?.data?.tenant
    if (!tenant) {
      notif(t('settings_hub.load_failed'), { type: 'error' })
      return
    }
    logoPreview.value = tenant.logo_url ?? null
    logoFile.value = null

    // Never load an empty list — an empty column fails the "at least one
    // method" rule and blocks the shared Save button for every section, not
    // just this one, so a bad legacy value must self-heal here rather than
    // surface as a page-wide save lockout.
    const rawPaymentMethods = tenant.pos_settings?.payment_methods
    const loadedPaymentMethods = Array.isArray(rawPaymentMethods)
      ? rawPaymentMethods
      : DEFAULT_TENANT_FORM.pos_settings.payment_methods

    // pay_later never applies to a table bill — drop it even if stale data
    // has it from before this restriction existed (the old UI allowed
    // enabling it there). If that was the tenant's only ticked method, fall
    // back to cash instead of leaving the column empty.
    const rawTablePaymentMethods = tenant.pos_settings?.table_payment_methods
    const loadedTablePaymentMethods = (
      Array.isArray(rawTablePaymentMethods)
        ? rawTablePaymentMethods
        : DEFAULT_TENANT_FORM.pos_settings.table_payment_methods
    ).filter(v => v !== 'pay_later')

    // load() replaces form AND its original baseline atomically — no code
    // path can update one without the other, so a page-wide save lockout
    // like the one this composable replaced can't happen again.
    loadTenantData({
      name: tenant.name ?? '',
      primary_color: tenant.primary_color ?? DEFAULT_TENANT_FORM.primary_color,
      currency: tenant.currency ?? DEFAULT_TENANT_FORM.currency,
      pos_settings: {
        order_types: tenant.pos_settings?.order_types ?? DEFAULT_TENANT_FORM.pos_settings.order_types,
        customer_selection: tenant.pos_settings?.customer_selection ?? true,
        order_notes: tenant.pos_settings?.order_notes ?? true,
        payment_methods: loadedPaymentMethods.length ? loadedPaymentMethods : ['cash'],
        default_payment_method: tenant.pos_settings?.default_payment_method ?? 'cash',
        table_payment_methods: loadedTablePaymentMethods.length ? loadedTablePaymentMethods : ['cash'],
        default_table_payment_method: tenant.pos_settings?.default_table_payment_method ?? 'cash',
        khr_exchange_rate: tenant.pos_settings?.khr_exchange_rate ?? 4100,
        khr_rounding: tenant.pos_settings?.khr_rounding ?? 100
      }
    })
  }

  function discardAll() {
    discardTenant()
    logoFile.value = null
    printerNameInput.value = selectedBranch.value?.printer_name ?? ''
  }

  // Sections render via v-if/v-else-if — only the currently visible one has
  // a mounted <v-form>, so its :rules can't be trusted as the save gate once
  // a different section is on screen. Validate the plain data directly
  // instead, and jump to whichever section actually has the problem.
  function validateBeforeSave() {
    if (!form.name) {
      notif(t('validation.required'), { type: 'error' })
      section.value = 'business'
      return false
    }
    if (!(Number(form.pos_settings.khr_exchange_rate) > 0)) {
      notif(t('validation.required'), { type: 'error' })
      section.value = 'currency'
      return false
    }
    if (!posPaymentValid.value) {
      notif(t('settings_hub.payment_table.need_one_hint'), { type: 'error' })
      section.value = 'pos'
      return false
    }
    return true
  }

  async function saveAll() {
    if (!dirtySections.value.length) return
    if ((dirtyTenant.value || dirtyLogo.value) && !validateBeforeSave()) return

    saving.value = true
    try {
      if (dirtyTenant.value || dirtyLogo.value) {
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
        commitTenant()
      }

      if (dirtyPrinter.value && selectedBranch.value) {
        await branchStore.updateBranch(selectedBranch.value.id, {
          // Explicit null, not undefined — undefined gets dropped from the
          // JSON body entirely, so clearing the field would silently leave
          // the old value untouched server-side instead of actually clearing it.
          printer_name: printerNameInput.value || null
        })
        await branchStore.fetchBranches({ perPage: -1 })
      }

      notif(t('settings_hub.saved'), { type: 'success' })
    } catch (err) {
      notif(err?.response?.data?.message || t('settings_hub.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Leave guard — only fires for navigating OUT of Settings entirely;
  // switching ?section= stays on the same route record and never triggers
  // this. ─────────────────────────────────────────────────────────────────
  const showLeaveConfirm = ref(false)
  let pendingNext = null
  onBeforeRouteLeave((to, from, next) => {
    if (!dirty.value) {
      next()
      return
    }
    pendingNext = next
    showLeaveConfirm.value = true
  })
  function confirmLeave() {
    showLeaveConfirm.value = false
    pendingNext?.(true)
  }
  function cancelLeave() {
    showLeaveConfirm.value = false
    pendingNext?.(false)
  }

  const currencyOptions = [
    { value: 'USD', label: t('tenant_create.currency.usd') },
    { value: 'KHR', label: t('tenant_create.currency.khr') },
    { value: 'EUR', label: t('tenant_create.currency.eur') },
    { value: 'GBP', label: t('tenant_create.currency.gbp') },
    { value: 'SGD', label: t('tenant_create.currency.sgd') },
    { value: 'MYR', label: t('tenant_create.currency.myr') }
  ]

  // ── Printer section ──────────────────────────────────────────────────────
  const selectedBranchId = ref(authStore.activeBranchId ?? null)
  const selectedBranch = computed(
    () =>
      branchStore.branches.find(b => b.id === selectedBranchId.value) ??
      branchStore.branches[0] ??
      null
  )

  // Switching which branch's printer you're configuring while anything on
  // the page is unsaved would silently lose it the moment printerNameInput
  // re-syncs to the new branch below — confirm first, same as leaving the
  // page entirely.
  const showBranchSwitchConfirm = ref(false)
  const pendingBranchId = ref(null)
  const selectedBranchIdModel = computed({
    get: () => selectedBranchId.value,
    set: val => {
      if (val === selectedBranchId.value) return
      if (dirty.value) {
        pendingBranchId.value = val
        showBranchSwitchConfirm.value = true
      } else {
        selectedBranchId.value = val
      }
    }
  })
  function confirmBranchSwitch() {
    discardAll()
    selectedBranchId.value = pendingBranchId.value
    pendingBranchId.value = null
    showBranchSwitchConfirm.value = false
  }
  function cancelBranchSwitch() {
    pendingBranchId.value = null
    showBranchSwitchConfirm.value = false
  }

  // connectionStatus: null (never checked) | 'connected' | 'disconnected'
  const connectionStatus = ref(null)
  const checking = ref(false)
  const testPrinting = ref(false)
  const testingQzEncoding = ref(false)
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

  // Vue Router's onBeforeRouteLeave guard only covers in-app navigation —
  // closing the tab or hitting browser refresh bypasses it entirely, so
  // unsaved changes need the native beforeunload prompt too.
  function handleBeforeUnload(e) {
    if (!dirty.value) return
    e.preventDefault()
    e.returnValue = ''
  }

  onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload)
    loadTenant()
    if (!branchStore.branches.length) branchStore.fetchBranches({ perPage: -1 })
  })
  onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
  })
</script>
