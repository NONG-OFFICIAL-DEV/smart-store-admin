<template>
  <v-card rounded="lg" border elevation="0" class="d-flex flex-column pos-cart">
    <div class="pa-3 d-flex align-center">
      <v-icon icon="mdi-cart-outline" class="me-2" />
      <span class="text-body-1 font-weight-bold">{{ $t('pos.cart.title') }}</span>
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
        <div class="text-body-2 font-weight-bold mt-3">
          {{ $t('pos.cart.empty_title') }}
        </div>
        <div class="text-caption text-medium-emphasis mt-1">
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
            <div class="text-body-2 font-weight-medium">
              {{ item.name }}
              <v-icon
                v-if="item.notes"
                icon="mdi-note-text"
                size="12"
                color="primary"
                class="ml-1"
              />
            </div>
            <div v-if="item.variant_name || item.unit_label" class="text-caption text-medium-emphasis">
              {{ item.variant_name || item.unit_label }}
            </div>
            <div v-if="item.customizations?.length" class="text-caption text-medium-emphasis">
              {{ item.customizations.map(c => c.value).join(' · ') }}
            </div>
            <div class="text-caption text-medium-emphasis">
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
                autofocus
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
            <span class="px-2 text-body-2" :class="{ 'text-body-1 font-weight-medium': touch }">
              {{ item.qty }}
            </span>
            <v-btn
              icon="mdi-plus"
              :size="touch ? 'default' : 'x-small'"
              variant="tonal"
              @click="$emit('update-qty', item.key, item.qty + 1)"
            />
          </div>
          <span class="text-body-2 font-weight-bold">
            {{ formatMoney(item.unit_price * item.qty) }}
          </span>
        </div>
      </div>
    </div>

    <v-divider />
    <div :class="touch ? 'pa-4' : 'pa-3'">
      <div class="d-flex justify-space-between text-body-1 mb-3">
        <span class="font-weight-medium">{{ $t('pos.cart.subtotal') }}</span>
        <span class="font-weight-bold">{{ formatMoney(subtotal) }}</span>
      </div>

      <!-- Payment method — right here, not a separate dialog, so checkout
           is a single tap once a method (and cash amount, if any) is set. -->
      <div class="text-caption text-medium-emphasis mb-1">
        {{ $t('pos.checkout.payment_method') }}
      </div>
      <v-btn-toggle
        v-model="paymentMethod"
        color="primary"
        variant="tonal"
        rounded="lg"
        mandatory
        class="w-100 mb-3"
        :divided="false"
      >
        <v-btn value="cash" class="flex-grow-1 text-none" :size="touch ? 'large' : 'default'">
          <v-icon start icon="mdi-cash" />
          {{ $t('pos.checkout.methods.cash') }}
        </v-btn>
        <v-btn value="card" class="flex-grow-1 text-none" :size="touch ? 'large' : 'default'">
          <v-icon start icon="mdi-credit-card-outline" />
          {{ $t('pos.checkout.methods.card') }}
        </v-btn>
        <v-btn value="qr" class="flex-grow-1 text-none" :size="touch ? 'large' : 'default'">
          <v-icon start icon="mdi-qrcode" />
          {{ $t('pos.checkout.methods.qr') }}
        </v-btn>
      </v-btn-toggle>

      <template v-if="paymentMethod === 'cash'">
        <!-- Touch devices get a custom on-screen numpad instead of the
             native OS keyboard (which eats half the screen and has no
             numeric layout worth trusting across devices). -->
        <v-menu v-if="touch" v-model="numpadOpen" :close-on-content-click="false" location="bottom">
          <template #activator="{ props: menuProps }">
            <v-text-field
              v-bind="menuProps"
              :model-value="numpadDisplay"
              :label="$t('pos.checkout.cash_tendered')"
              variant="outlined"
              density="comfortable"
              rounded="lg"
              readonly
              class="mb-2"
              @click="openNumpad"
            />
          </template>
          <v-card rounded="lg" width="260" class="pa-3">
            <div class="text-h5 font-weight-black text-center mb-3">{{ numpadDisplay }}</div>
            <PosNumpad @digit="onNumpadDigit" @decimal="onNumpadDecimal" @backspace="onNumpadBackspace" />
            <div class="d-flex ga-2 mt-2">
              <v-btn variant="tonal" rounded="lg" class="flex-grow-1" @click="clearNumpad">
                {{ $t('btn.clear') }}
              </v-btn>
              <v-btn color="primary" variant="flat" rounded="lg" class="flex-grow-1" @click="numpadOpen = false">
                {{ $t('btn.done') }}
              </v-btn>
            </div>
          </v-card>
        </v-menu>
        <v-text-field
          v-else
          :model-value="cashTendered ?? subtotal"
          type="number"
          min="0"
          step="0.01"
          :label="$t('pos.checkout.cash_tendered')"
          variant="outlined"
          density="compact"
          rounded="lg"
          class="mb-2"
          @update:model-value="cashTendered = $event === '' ? null : Number($event)"
        />
        <div class="d-flex justify-space-between text-body-2 mb-3">
          <span class="text-medium-emphasis">{{ $t('pos.checkout.change') }}</span>
          <span class="font-weight-bold" :class="changeDue < 0 ? 'text-error' : 'text-success'">
            {{ formatMoney(changeDue) }}
          </span>
        </div>
      </template>

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
        <span class="flex-grow-1 text-start">{{ $t('pos.cart.checkout') }}</span>
        <span v-if="items.length" class="font-weight-black">{{ formatMoney(subtotal) }}</span>
      </v-btn>
    </div>
  </v-card>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useDisplay } from 'vuetify'
  import PosNumpad from './PosNumpad.vue'

  const props = defineProps({
    items: { type: Array, default: () => [] },
    subtotal: { type: Number, default: 0 },
    showNotes: { type: Boolean, default: false },
    loading: { type: Boolean, default: false }
  })

  const { mdAndDown: touch } = useDisplay()

  const emit = defineEmits(['update-qty', 'update-notes', 'remove', 'clear', 'checkout'])

  const paymentMethod = ref('cash')
  // null = "hasn't typed a custom amount yet" — the field then always shows
  // (and this stays in sync with) the live subtotal, i.e. exact change.
  const cashTendered = ref(null)

  // Reset for the next customer once the cart empties (order submitted or
  // manually cleared) — this component owns payment state locally, so it
  // has to clear it itself rather than relying on posStore.clear().
  watch(
    () => props.items.length,
    len => {
      if (len === 0) {
        paymentMethod.value = 'cash'
        cashTendered.value = null
        cashBuffer.value = ''
      }
    }
  )

  // Custom on-screen numpad for the cash-tendered field on touch devices —
  // avoids popping the native OS keyboard. `cashBuffer` is the raw string
  // being typed; empty means "not editing yet, show the default amount".
  const numpadOpen = ref(false)
  const cashBuffer = ref('')

  const numpadDisplay = computed(() =>
    cashBuffer.value !== '' ? `$${cashBuffer.value}` : formatMoney(cashTendered.value ?? props.subtotal)
  )

  function openNumpad() {
    cashBuffer.value = ''
  }

  function onNumpadDigit(digit) {
    if (cashBuffer.value.length >= 8) return
    cashBuffer.value += digit
    cashTendered.value = Number(cashBuffer.value)
  }

  function onNumpadDecimal() {
    if (cashBuffer.value.includes('.')) return
    cashBuffer.value = `${cashBuffer.value || '0'}.`
    cashTendered.value = Number(cashBuffer.value)
  }

  function onNumpadBackspace() {
    cashBuffer.value = cashBuffer.value.slice(0, -1)
    cashTendered.value = cashBuffer.value === '' ? null : Number(cashBuffer.value)
  }

  function clearNumpad() {
    cashBuffer.value = ''
    cashTendered.value = null
  }

  const changeDue = computed(() => (cashTendered.value ?? props.subtotal) - props.subtotal)

  const canCheckout = computed(() => {
    if (!props.items.length) return false
    if (paymentMethod.value !== 'cash') return true
    const val = cashTendered.value ?? props.subtotal
    return val !== null && val !== '' && val >= 0
  })

  function onCheckout() {
    emit('checkout', {
      payment_method: paymentMethod.value,
      cash_tendered: paymentMethod.value === 'cash' ? (cashTendered.value ?? props.subtotal) : undefined
    })
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(value ?? 0)
  }

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
