<template>
  <div class="cd-root" :class="{ 'cd-root--no-cursor': cursorHidden }">
    <template v-if="mode === 'loading' && !receivedViaBroadcast">
      <div class="cd-pairing">
        <div class="cd-pairing__title">Loading&hellip;</div>
      </div>
    </template>

    <template v-else-if="mode === 'pairing' && !receivedViaBroadcast">
      <div class="cd-pairing">
        <div class="cd-pairing__title">Pair this display</div>
        <p class="cd-pairing__hint">
          Enter this code in POS Settings &rarr; Customer display:
        </p>
        <div class="cd-pairing__code">{{ pairingCode ?? '······' }}</div>
      </div>
    </template>

    <template v-else>
      <div v-if="connectionStatus === 'reconnecting' && !receivedViaBroadcast" class="cd-badge">
        Reconnecting&hellip;
      </div>

      <transition name="cd-fade" mode="out-in">
        <IdleScreen
          v-if="displayState.state === 'idle'"
          key="idle"
          :logo-url="displayState.logoUrl"
          :wifi-text="displayState.wifiText"
          :promo="displayState.promo"
        />
        <OrderScreen
          v-else-if="displayState.state === 'order'"
          key="order"
          :logo-url="displayState.logoUrl"
          :table-name="displayState.tableName"
          :items="displayState.items"
          :item-count="displayState.itemCount"
          :subtotal="displayState.subtotal"
          :discount="displayState.discount"
          :total-usd="displayState.totalUsd"
          :total-khr="displayState.totalKhr"
          :currency="displayState.currency"
        />
        <PayQrScreen
          v-else-if="displayState.state === 'pay_qr'"
          key="pay_qr"
          :total-usd="displayState.totalUsd"
          :total-khr="displayState.totalKhr"
          :payment="displayState.payment"
        />
        <ThanksScreen
          v-else-if="displayState.state === 'thanks'"
          key="thanks"
          :order-number="displayState.orderNumber"
          :payment="displayState.payment"
        />
        <IdleScreen
          v-else
          key="fallback"
          :logo-url="displayState.logoUrl"
          :wifi-text="displayState.wifiText"
          :promo="displayState.promo"
        />
      </transition>
    </template>
  </div>
</template>

<script setup>
import { useCustomerDisplayReceiver } from '@/composables/useCustomerDisplay'
import { useCustomerDisplayDeviceBehavior } from '@/composables/useCustomerDisplayDeviceBehavior'
import IdleScreen from '@/components/customer-display/IdleScreen.vue'
import OrderScreen from '@/components/customer-display/OrderScreen.vue'
import PayQrScreen from '@/components/customer-display/PayQrScreen.vue'
import ThanksScreen from '@/components/customer-display/ThanksScreen.vue'

const { mode, pairingCode, connectionStatus, displayState, receivedViaBroadcast } =
  useCustomerDisplayReceiver()
const { cursorHidden } = useCustomerDisplayDeviceBehavior()
</script>

<style scoped>
.cd-root {
  position: relative;
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  background: #f7f5f0;
  font-family: 'Inter', sans-serif;
  --cd-teal: #0f6e75;
}

.cd-root--no-cursor,
.cd-root--no-cursor * {
  cursor: none;
}

.cd-badge {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 10;
  background: #b45309;
  color: #fff;
  font-size: clamp(12px, 1.1vw, 15px);
  font-weight: 600;
  padding: 6px 14px;
  border-radius: 999px;
}

.cd-pairing {
  width: 100%;
  height: 100%;
  background: var(--cd-teal);
  color: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: clamp(24px, 4vw, 64px);
  box-sizing: border-box;
}

.cd-pairing__title {
  font-weight: 800;
  font-size: clamp(28px, 3.5vw, 44px);
}

.cd-pairing__hint {
  margin-top: 12px;
  font-size: clamp(14px, 1.4vw, 19px);
  opacity: 0.85;
}

.cd-pairing__code {
  margin-top: clamp(24px, 3.5vw, 40px);
  font-weight: 800;
  font-size: clamp(48px, 7vw, 88px);
  letter-spacing: 0.15em;
}

.cd-fade-enter-active,
.cd-fade-leave-active {
  transition: opacity 0.2s ease;
}

.cd-fade-enter-from,
.cd-fade-leave-to {
  opacity: 0;
}
</style>
