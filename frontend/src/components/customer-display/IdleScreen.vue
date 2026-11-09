<template>
  <div class="cd-idle" :class="{ 'cd-idle--teal-only': !hasPromo }">
    <div class="cd-idle__left">
      <img v-if="logoUrl" :src="logoUrl" class="cd-idle__logo" alt="" />
      <div class="cd-idle__welcome">Welcome</div>
      <div class="cd-idle__welcome cd-idle__welcome--km">សូមស្វាគមន៍</div>
      <div class="cd-idle__hint">Your order will show here.</div>
      <div v-if="wifiText" class="cd-idle__wifi">
        <span class="cd-idle__wifi-icon">&#128246;</span>{{ wifiText }}
      </div>
    </div>

    <div v-if="hasPromo" class="cd-idle__right">
      <div class="cd-idle__special">TODAY'S SPECIAL</div>
      <img :src="promo.imageUrl" class="cd-idle__promo-image" alt="" />
      <div class="cd-idle__promo-title">{{ promo.title }}</div>
      <div v-if="promo.subtitle" class="cd-idle__promo-subtitle">{{ promo.subtitle }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  logoUrl: { type: String, default: null },
  wifiText: { type: String, default: null },
  promo: { type: Object, default: () => ({}) }
})

const hasPromo = computed(() => !!(props.promo?.imageUrl && props.promo?.title))
</script>

<style scoped>
.cd-idle {
  display: flex;
  width: 100%;
  height: 100%;
}

.cd-idle__left {
  flex: 0 0 45%;
  background: var(--cd-teal);
  color: #fff;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: clamp(24px, 4vw, 64px);
  position: relative;
  box-sizing: border-box;
}

.cd-idle--teal-only .cd-idle__left {
  flex: 1 1 100%;
  align-items: center;
  text-align: center;
}

.cd-idle__logo {
  max-height: clamp(40px, 6vw, 80px);
  max-width: 60%;
  margin-bottom: clamp(16px, 3vw, 32px);
  object-fit: contain;
}

.cd-idle__welcome {
  font-family: 'Inter', sans-serif;
  font-weight: 800;
  font-size: clamp(32px, 5vw, 64px);
  line-height: 1.1;
}

.cd-idle__welcome--km {
  font-family: 'Noto Sans Khmer', sans-serif;
  font-weight: 700;
  font-size: clamp(24px, 3.5vw, 44px);
  margin-top: 4px;
}

.cd-idle__hint {
  margin-top: clamp(16px, 2.5vw, 32px);
  font-size: clamp(16px, 1.6vw, 22px);
  opacity: 0.85;
}

.cd-idle__wifi {
  position: absolute;
  left: clamp(24px, 4vw, 64px);
  bottom: clamp(24px, 4vw, 48px);
  font-size: clamp(14px, 1.3vw, 18px);
  opacity: 0.85;
  display: flex;
  align-items: center;
  gap: 8px;
}

.cd-idle--teal-only .cd-idle__wifi {
  left: 50%;
  transform: translateX(-50%);
}

.cd-idle__right {
  flex: 1 1 55%;
  background: #f7f5f0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: clamp(24px, 4vw, 56px);
  text-align: center;
  box-sizing: border-box;
}

.cd-idle__special {
  font-family: 'Inter', sans-serif;
  font-size: clamp(14px, 1.3vw, 18px);
  font-weight: 700;
  letter-spacing: 0.12em;
  color: var(--cd-teal);
  margin-bottom: clamp(12px, 2vw, 20px);
}

.cd-idle__promo-image {
  width: 100%;
  max-width: 480px;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: 24px;
}

.cd-idle__promo-title {
  margin-top: clamp(16px, 2.5vw, 28px);
  font-family: 'Inter', sans-serif;
  font-size: clamp(22px, 2.4vw, 32px);
  font-weight: 800;
  color: #1a1a1a;
}

.cd-idle__promo-subtitle {
  margin-top: 6px;
  font-size: clamp(15px, 1.4vw, 19px);
  color: #555;
}
</style>
