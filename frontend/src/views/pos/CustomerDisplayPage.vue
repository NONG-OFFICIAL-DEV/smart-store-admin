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

      <!-- Single persistent split layout — the poster carousel on the left
           NEVER unmounts/flickers when the order state changes; only
           OrderPanel's own inner content area swaps (items / QR / thanks),
           see OrderPanel.vue's own <transition>. -->
      <div class="cd-split">
        <section class="cd-split__poster">
          <PosterCarousel :posters="displayState.posters ?? []" />
        </section>
        <section class="cd-split__panel">
          <OrderPanel
            :branch-name="displayState.branchName"
            :wifi-text="displayState.wifiText"
            :state="displayState.state"
            :table-name="displayState.tableName"
            :order-number="displayState.orderNumber"
            :items="displayState.items ?? []"
            :subtotal="displayState.subtotal ?? 0"
            :discount="displayState.discount ?? 0"
            :total-usd="displayState.totalUsd ?? 0"
            :total-khr="displayState.totalKhr ?? 0"
            :currency="displayState.currency"
            :payment="displayState.payment"
          />
        </section>
      </div>
    </template>
  </div>
</template>

<script setup>
import { useCustomerDisplayReceiver } from '@/composables/useCustomerDisplay'
import { useCustomerDisplayDeviceBehavior } from '@/composables/useCustomerDisplayDeviceBehavior'
import PosterCarousel from '@/components/customer-display/PosterCarousel.vue'
import OrderPanel from '@/components/customer-display/OrderPanel.vue'

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

/* Persistent split layout — left poster carousel never unmounts; only
   OrderPanel's own inner content swaps (see OrderPanel.vue). */
.cd-split {
  display: flex;
  width: 100%;
  height: 100%;
}

.cd-split__poster {
  flex: 0 0 60%;
  min-width: 0;
}

.cd-split__panel {
  flex: 0 0 40%;
  min-width: 0;
  border-left: 1px solid rgba(226, 232, 240, 0.8);
  box-shadow: -10px 0 25px -5px rgba(15, 23, 42, 0.04);
}
</style>
