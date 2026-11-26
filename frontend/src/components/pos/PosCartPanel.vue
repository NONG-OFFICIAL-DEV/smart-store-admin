<template>
  <v-card rounded="lg" border elevation="0" class="d-flex flex-column pos-cart">
    <div class="pa-3 d-flex align-center">
      <v-icon icon="mdi-cart-outline" class="me-2" />
      <span class="text-body-large font-weight-bold">{{ $t('pos.cart.title') }}</span>
      <v-spacer />
      <v-btn
        v-if="items.length"
        :size="touch ? 'default' : 'small'"
        variant="text"
        color="error"
        @click="$emit('clear')"
      >
        {{ $t('pos.cart.clear') }}
      </v-btn>
    </div>
    <v-divider />

    <div class="pos-cart__items flex-grow-1">
      <div v-if="!items.length" class="pos-cart__empty pa-6 text-center">
        <v-icon icon="mdi-cart-outline" size="40" color="grey-lighten-1" />
        <div class="text-body-medium font-weight-bold mt-3">
          {{ $t('pos.cart.empty_title') }}
        </div>
        <div class="text-body-small text-medium-emphasis mt-1">
          {{ $t('pos.cart.empty_helper') }}
        </div>
        <kbd class="pos-cart__empty-hint mt-4">⌘K — {{ $t('pos.cart.empty_search_hint') }}</kbd>
      </div>

      <div
        v-for="item in items"
        :key="item.key"
        class="pos-cart__item"
        :class="touch ? 'px-4 py-3' : 'px-3 py-2'"
      >
        <div class="d-flex align-start">
          <div class="flex-grow-1">
            <div class="text-body-medium font-weight-medium">
              {{ item.name }}
              <v-icon
                v-if="item.notes"
                icon="mdi-note-text"
                size="12"
                color="primary"
                class="ml-1"
              />
            </div>
            <div v-if="optionsLine(item)" class="text-body-small text-medium-emphasis">
              {{ optionsLine(item) }}
            </div>
            <div class="text-body-small text-medium-emphasis">
              {{ formatMoney(item.unit_price) }}
            </div>
          </div>

          <v-menu v-if="showNotes" :close-on-content-click="false" location="bottom end">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                :icon="item.notes ? 'mdi-note-text' : 'mdi-note-plus-outline'"
                :color="item.notes ? 'primary' : undefined"
                :size="touch ? 'default' : 'x-small'"
                variant="text"
                :class="touch ? 'me-1' : ''"
              />
            </template>
            <v-card rounded="lg" width="220" class="pa-2">
              <v-textarea
                :model-value="item.notes"
                :placeholder="$t('pos.cart.notes_placeholder')"
                variant="outlined"
                density="compact"
                rounded="lg"
                rows="2"
                auto-grow
                hide-details
                :autofocus="!isTouch"
                enterkeyhint="done"
                @update:model-value="$emit('update-notes', item.key, $event)"
              />
            </v-card>
          </v-menu>

          <v-btn
            icon="mdi-close"
            :size="touch ? 'default' : 'x-small'"
            variant="text"
            @click="$emit('remove', item.key)"
          />
        </div>

        <div class="d-flex align-center justify-space-between mt-2">
          <div class="d-flex align-center" :class="touch ? 'ga-3' : 'gap-1'">
            <v-btn
              icon="mdi-minus"
              :size="touch ? 'default' : 'x-small'"
              variant="tonal"
              @click="$emit('update-qty', item.key, item.qty - 1)"
            />
            <span class="px-2 text-body-medium" :class="{ 'text-body-large font-weight-medium': touch }">
              {{ item.qty }}
            </span>
            <v-btn
              icon="mdi-plus"
              :size="touch ? 'default' : 'x-small'"
              variant="tonal"
              @click="$emit('update-qty', item.key, item.qty + 1)"
            />
          </div>
          <span class="text-body-medium font-weight-bold">
            {{ formatMoney(item.unit_price * item.qty) }}
          </span>
        </div>
      </div>
    </div>

    <v-divider />
    <div :class="touch ? 'pa-4' : 'pa-3'">
      <div class="d-flex justify-space-between text-body-large" :class="discountAmount > 0 ? 'mb-1' : 'mb-3'">
        <span class="font-weight-medium">{{ $t('pos.cart.subtotal') }}</span>
        <span class="font-weight-bold">{{ formatMoney(subtotal) }}</span>
      </div>
      <div v-if="discountAmount > 0" class="d-flex justify-space-between text-body-medium mb-3 text-amber-darken-2">
        <span class="d-flex align-center">
          {{ $t('pos.loyalty.discount_line') }}
          <v-btn
            icon="mdi-close"
            size="x-small"
            variant="text"
            density="compact"
            class="ml-1"
            :aria-label="$t('pos.loyalty.remove_reward')"
            @click="$emit('remove-redemption')"
          />
        </span>
        <span class="font-weight-bold">-{{ formatMoney(discountAmount) }}</span>
      </div>

      <!-- Payment method — right here, not a separate dialog, so checkout
           is a single tap once a method (and cash amount, if any) is set. -->
      <div class="text-body-small text-medium-emphasis mb-1">
        {{ $t('pos.checkout.payment_method') }}
      </div>
      <!-- 2x2 grid, not a single-row toggle — 4 options (the 4th, longer
           "Pay Later" label included) never fit on one line in a ~300-360px
           cart column without clipping down to just "Pay". -->
      <v-row density="compact" class="mb-3">
        <v-col v-for="opt in paymentOptions" :key="opt.value" cols="6">
          <v-btn
            block
            :color="paymentMethod === opt.value ? 'primary' : undefined"
            :variant="paymentMethod === opt.value ? 'flat' : 'tonal'"
            rounded="lg"
            class="text-none"
            :size="touch ? 'large' : 'default'"
            @click="paymentMethod = opt.value"
          >
            <v-icon start :icon="opt.icon" />
            {{ opt.label }}
          </v-btn>
        </v-col>
      </v-row>

      <v-btn
        color="primary"
        variant="flat"
        block
        rounded="lg"
        :size="touch ? 'x-large' : 'large'"
        :disabled="!canCheckout"
        :loading="loading"
        @click="onCheckout"
      >
        <v-icon icon="mdi-check-circle-outline" class="me-2" />
        <span class="flex-grow-1 text-start me-2">{{ checkoutLabel }}</span>
        <span v-if="items.length" class="font-weight-black">{{ formatMoney(payableTotal) }}</span>
      </v-btn>
    </div>

    <!-- One dialog for every payment method — same header/footer chrome
         regardless of which method was picked, body content swaps inside
         (see PosCheckoutDialog.vue). -->
    <PosCheckoutDialog
      v-model="checkoutDialogOpen"
      :payment-method="paymentMethod"
      :total="payableTotal"
      :qr-image-url="qrImageUrl"
      @confirm="onDialogConfirm"
    />
  </v-card>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useDisplay } from 'vuetify'
  import { useI18n } from 'vue-i18n'
  import PosCheckoutDialog from './PosCheckoutDialog.vue'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'

  const { format: formatMoney } = useCurrency()
  const { t } = useI18n()
  const { isTouch } = useTouchDevice()

  // "M · Sugar 80% · Pearl +$0.50" — size first (falling back to the mart
  // unit label for retail items with no variant), then every chosen option,
  // showing a price only for ones that actually add to the total.
  function optionsLine(item) {
    const parts = []
    if (item.variant_name) parts.push(item.variant_name)
    else if (item.unit_label) parts.push(item.unit_label)
    for (const c of item.customizations ?? []) {
      parts.push(c.price ? `${c.value} +${formatMoney(c.price)}` : c.value)
    }
    return parts.join(' · ')
  }

  const props = defineProps({
    items: { type: Array, default: () => [] },
    subtotal: { type: Number, default: 0 },
    // A loyalty reward applied via LoyaltyRedeemDialog.vue — owned by the
    // caller's posStore so it survives the desktop/mobile cart split.
    discountAmount: { type: Number, default: 0 },
    showNotes: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    // Which methods show at all (Settings > POS), and which one pre-selects.
    paymentMethods: {
      type: Array,
      default: () => ['cash', 'card', 'qr', 'pay_later']
    },
    defaultPaymentMethod: { type: String, default: 'cash' },
    // Synchronous pre-flight check run before the checkout dialog opens or
    // an order submits — return false to block checkout entirely. Owned by
    // the caller since it's business-specific (e.g. FoodPos requires a
    // table for dine-in; RetailPos has no such concept) — this component
    // just gates on whatever the caller decides.
    beforeCheckout: { type: Function, default: () => true },
    // The tenant's static merchant QR image (Settings → Customer display) —
    // only shown when paymentMethod is 'qr', but owned by the caller since
    // it comes from branch data this component doesn't otherwise need.
    qrImageUrl: { type: String, default: null }
  })

  const { mdAndDown: touch } = useDisplay()

  const emit = defineEmits([
    'update-qty',
    'update-notes',
    'remove',
    'clear',
    'checkout',
    'checkout-opened',
    'checkout-cancelled',
    'remove-redemption'
  ])

  const payableTotal = computed(() => Math.max(0, props.subtotal - props.discountAmount))

  const paymentMethod = ref(props.defaultPaymentMethod)

  // Fixed display order regardless of how the tenant's list is stored.
  const ALL_PAYMENT_METHODS = [
    { value: 'cash', icon: 'mdi-cash', label: t('pos.checkout.methods.cash') },
    { value: 'card', icon: 'mdi-credit-card-outline', label: t('pos.checkout.methods.card') },
    { value: 'qr', icon: 'mdi-qrcode', label: t('pos.checkout.methods.qr') },
    { value: 'pay_later', icon: 'mdi-clock-outline', label: t('pos.checkout.methods.pay_later') }
  ]

  const paymentOptions = computed(() =>
    ALL_PAYMENT_METHODS.filter(opt => props.paymentMethods.includes(opt.value))
  )

  // The tenant can change which methods are enabled (Settings > POS) after
  // one was already selected in this session — fall back to the default
  // rather than leaving a now-non-existent option selected.
  watch(
    () => props.paymentMethods,
    methods => {
      if (!methods.includes(paymentMethod.value)) {
        paymentMethod.value = methods.includes(props.defaultPaymentMethod)
          ? props.defaultPaymentMethod
          : (methods[0] ?? 'cash')
      }
    }
  )
  // Reset for the next customer once the cart empties (order submitted or
  // manually cleared) — this component owns payment state locally, so it
  // has to clear it itself rather than relying on posStore.clear().
  watch(
    () => props.items.length,
    len => {
      if (len === 0) {
        paymentMethod.value = props.defaultPaymentMethod
        checkoutDialogOpen.value = false
      }
    }
  )

  // Every payment method confirms through the same dialog (PosCheckoutDialog)
  // — body content swaps inside based on paymentMethod, but opening/closing
  // it is owned here alongside the method picker above.
  const checkoutDialogOpen = ref(false)

  const canCheckout = computed(() => props.items.length > 0)

  // "Checkout" implies payment is happening right now — misleading once
  // Pay Later is selected, since no money changes hands at this step.
  const checkoutLabel = computed(() =>
    paymentMethod.value === 'pay_later' ? t('pos.cart.place_order') : t('pos.cart.checkout')
  )

  function onCheckout() {
    if (!props.beforeCheckout()) return
    checkoutDialogOpen.value = true
    // Side-channel only (no UI here) — the parent page owns the
    // customer-display publisher, and for 'qr' it needs to push the QR
    // screen to the customer display the instant the method is picked,
    // before the order even exists.
    emit('checkout-opened', { payment_method: paymentMethod.value })
  }

  // Set synchronously inside onDialogConfirm, read (and cleared) by the
  // checkoutDialogOpen watcher right below — distinguishes "closed because
  // the cashier confirmed" from "closed because they dismissed it" so
  // 'checkout-cancelled' only fires for the latter.
  let justConfirmed = false

  function onDialogConfirm(cashTendered) {
    justConfirmed = true
    emit('checkout', { payment_method: paymentMethod.value, cash_tendered: cashTendered })
  }

  watch(checkoutDialogOpen, open => {
    if (open) return
    if (justConfirmed) {
      justConfirmed = false
      return
    }
    emit('checkout-cancelled')
  })

  defineExpose({ submitCheckout: onCheckout })
</script>

<style scoped>
  .pos-cart {
    height: 100%;
    min-height: 0;
  }
  .pos-cart__items {
    overflow-y: auto;
    min-height: 0;
    -webkit-overflow-scrolling: touch;
  }
  .pos-cart__item + .pos-cart__item {
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  }
  .pos-cart__empty-hint {
    display: inline-block;
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 6px;
    background: rgba(var(--v-theme-on-surface), 0.06);
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    color: rgba(var(--v-theme-on-surface), 0.6);
  }
</style>
