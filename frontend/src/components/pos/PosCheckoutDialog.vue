<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('pos.checkout.dialog_title')"
    :max-width="paymentMethod === 'qr' ? 480 : 420"
    :persistent="false"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #actions>
      <v-spacer />
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-check-circle-outline"
        :disabled="!canConfirm"
        @click="onConfirm"
      >
        {{ $t('pos.checkout.dialog_confirm') }}
      </v-btn>
    </template>

    <!-- Same total + method chip up top regardless of which method is
         selected — this (not the body below) is what makes every method
         actually look like the same dialog. -->
    <div class="text-center mb-4">
      <v-chip :prepend-icon="methodIcon" size="small" variant="tonal" color="primary" class="mb-2">
        {{ methodLabel }}
      </v-chip>
      <div class="text-body-small text-medium-emphasis mb-1">{{ $t('order.total') }}</div>
      <div class="text-headline-large font-weight-bold">{{ formatMoney(total) }}</div>
    </div>

    <template v-if="paymentMethod === 'cash'">
      <!-- Touch: readonly + inputmode="none" so the system keyboard never
           opens — tapping the field opens the NumPad instead. Desktop
           keeps normal typing with a decimal-friendly inputmode. -->
      <v-menu v-if="isTouch" v-model="numpadOpen" :close-on-content-click="false" location="bottom">
        <template #activator="{ props: menuProps }">
          <v-text-field
            v-bind="menuProps"
            :model-value="received"
            :label="$t('tables.payment.received_label', { symbol: currencySymbol() })"
            variant="outlined"
            density="compact"
            rounded="lg"
            class="mb-3"
            hide-details
            readonly
            inputmode="none"
          />
        </template>
        <v-card rounded="lg" width="280" class="pa-3">
          <NumPad v-model="received" decimal :suffix="currencySymbol()" />
        </v-card>
      </v-menu>
      <v-text-field
        v-else
        v-model.number="received"
        type="number"
        min="0"
        :step="currency === 'KHR' ? 100 : 0.01"
        inputmode="decimal"
        :label="$t('tables.payment.received_label', { symbol: currencySymbol() })"
        variant="outlined"
        density="compact"
        rounded="lg"
        class="mb-3"
        hide-details
        @focus="keepVisible($event.target)"
        @keydown.enter="$event.target.blur()"
      />

      <div class="d-flex flex-wrap gap-2 mb-4">
        <v-chip
          v-for="btn in quickButtons"
          :key="btn.key"
          size="small"
          variant="tonal"
          rounded="lg"
          class="px-3"
          style="min-height: 32px"
          @click="applyQuickButton(btn)"
        >
          {{ btn.label }}
        </v-chip>
      </div>

      <v-card flat rounded="lg" class="pa-3 mb-2 pos-checkout-dialog__summary">
        <div class="d-flex justify-space-between text-body-medium mb-2">
          <span class="text-medium-emphasis">{{ $t('tables.payment.received') }}</span>
          <span class="font-weight-medium">{{ formatMoney(received ?? 0) }}</span>
        </div>
        <v-divider class="mb-2" />
        <div class="d-flex justify-space-between align-center">
          <span
            class="text-body-medium font-weight-bold"
            :class="diff >= 0 ? 'text-success' : 'text-warning'"
          >
            {{ diff >= 0 ? $t('pos.checkout.change') : $t('tables.payment.remaining') }}
          </span>
          <div
            class="text-title-large font-weight-bold"
            :class="diff >= 0 ? 'text-success' : 'text-warning'"
          >
            {{ formatMoney(Math.abs(diff)) }}
          </div>
        </div>
      </v-card>
    </template>

    <template v-else-if="paymentMethod === 'qr'">
      <div class="d-flex flex-column align-center">
        <div class="pos-checkout-dialog__qr-box mb-2">
          <img v-if="qrImageUrl" :src="qrImageUrl" alt="QR code" class="pos-checkout-dialog__qr-image" />
          <div v-else class="pos-checkout-dialog__qr-placeholder text-body-small text-medium-emphasis">
            {{ $t('pos.checkout.qr_dialog_not_configured') }}
          </div>
        </div>
        <div class="text-body-small text-medium-emphasis text-center" style="max-width: 260px">
          {{ $t('pos.checkout.qr_dialog_hint') }}
        </div>
      </div>
    </template>

    <template v-else-if="paymentMethod === 'card'">
      <p class="text-body-medium text-medium-emphasis text-center mb-0">
        {{ $t('pos.checkout.card_dialog_hint') }}
      </p>
    </template>

    <template v-else-if="paymentMethod === 'pay_later'">
      <p class="text-body-medium text-medium-emphasis text-center mb-0">
        {{ $t('pos.checkout.pay_later_dialog_hint') }}
      </p>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'
  import { useCurrency } from '@/composables/useCurrency_v2.js'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'
  import { useKeyboardSafeArea } from '@/composables/useKeyboardSafeArea.js'
  import NumPad from '@/components/shared/NumPad.vue'

  const { t } = useI18n()
  const { format: formatMoney, currency, currencySymbol } = useCurrency()
  const { isTouch } = useTouchDevice()
  const { keepVisible } = useKeyboardSafeArea()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    paymentMethod: { type: String, default: 'cash' },
    total: { type: Number, default: 0 },
    // Only relevant for the qr body — the tenant's static merchant QR image
    // (Settings → Customer display), same source the customer display's
    // own QR screen uses.
    qrImageUrl: { type: String, default: null }
  })
  const emit = defineEmits(['update:modelValue', 'confirm'])

  const METHOD_META = {
    cash: { icon: 'mdi-cash', label: () => t('pos.checkout.methods.cash') },
    card: { icon: 'mdi-credit-card-outline', label: () => t('pos.checkout.methods.card') },
    qr: { icon: 'mdi-qrcode', label: () => t('pos.checkout.methods.qr') },
    pay_later: { icon: 'mdi-clock-outline', label: () => t('pos.checkout.methods.pay_later') }
  }
  const methodIcon = computed(() => METHOD_META[props.paymentMethod]?.icon ?? 'mdi-cash')
  const methodLabel = computed(() => METHOD_META[props.paymentMethod]?.label() ?? '')

  const received = ref(null)
  const numpadOpen = ref(false)

  // Defaults to the exact amount due every time the dialog opens — staff
  // pays exact far more often than not, so this saves a tap on the Exact
  // chip every time.
  watch(
    () => props.modelValue,
    isOpen => {
      if (isOpen) received.value = props.total || null
      else numpadOpen.value = false
    }
  )

  // Same quick-button set as the table-session Payment dialog
  // (tables/PaymentDialog.vue) — denominations match the tenant's own base
  // currency, never assume USD.
  const quickButtons = computed(() => {
    const denominations = currency.value === 'KHR' ? [5000, 10000, 20000, 50000] : [5, 10, 20, 50]
    return [
      { key: 'exact', label: t('tables.payment.quick_exact'), type: 'exact' },
      ...denominations.map(amount => ({ key: `d${amount}`, label: formatMoney(amount), type: 'add', amount })),
      { key: 'clear', label: t('tables.payment.quick_clear'), type: 'clear' }
    ]
  })

  function applyQuickButton(btn) {
    if (btn.type === 'exact') {
      received.value = props.total
    } else if (btn.type === 'add') {
      received.value = Number(received.value ?? 0) + btn.amount
    } else if (btn.type === 'clear') {
      received.value = null
    }
  }

  const diff = computed(() => Number(received.value ?? 0) - props.total)

  // Cash needs enough received; every other method has nothing to validate
  // before confirming (card/QR are confirmed by eye, pay_later collects
  // nothing at all).
  const canConfirm = computed(() => (props.paymentMethod === 'cash' ? Number(received.value ?? 0) >= props.total : true))

  function onConfirm() {
    emit('confirm', props.paymentMethod === 'cash' ? Number(received.value ?? 0) : undefined)
    emit('update:modelValue', false)
  }
</script>

<style scoped>
  .pos-checkout-dialog__summary {
    background: rgba(var(--v-theme-surface-variant), 0.3);
  }

  .pos-checkout-dialog__qr-box {
    width: 220px;
    aspect-ratio: 1 / 1;
    border-radius: 16px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .pos-checkout-dialog__qr-image {
    width: 92%;
    height: 92%;
    object-fit: contain;
  }

  .pos-checkout-dialog__qr-placeholder {
    padding: 16px;
    text-align: center;
  }
</style>
