<template>
  <v-btn
    v-bind="$attrs"
    :color="config.color"
    :loading="loading"
    :disabled="loading"
    :style="{ '--glow': `${config.color}38` }"
    variant="flat"
    rounded="lg"
    size="large"
    class="payment-btn text-none px-5"
    @click="$emit('click')"
  >
    <template #prepend>
      <v-avatar size="24" rounded="md" color="white">
        <v-img
          v-if="!imgFailed"
          :src="config.logo"
          :alt="config.short"
          cover
          @error="imgFailed = true"
        />
        <span
          v-else
          class="text-caption font-weight-black"
          :style="{ color: config.color }"
        >
          {{ config.short }}
        </span>
      </v-avatar>
    </template>

    <span class="font-weight-semibold" style="letter-spacing: 0.2px">
      {{ label || $t(config.defaultLabelKey) }}
    </span>

    <template #append>
      <v-icon icon="mdi-arrow-right" size="15" class="arrow-icon" />
    </template>
  </v-btn>
</template>

<!-- Separate <script> keeps PAYMENT_CONFIGS at module scope so the
     defineProps() validator can reference it without the hoisting error. -->
<script>
export const PAYMENT_CONFIGS = {
  aba: {
    color: '#005b82',
    short: 'ABA',
    defaultLabelKey: 'tenants.payment_method.pay_with_aba',
    logo: 'https://cdn.brandfetch.io/domain/ababank.com/fallback/lettermark/theme/dark/h/400/w/400/icon?c=1bfwsmEH20zzEfSNTed',
  },
  bakong: {
    color: '#E11931',
    short: 'BK',
    defaultLabelKey: 'tenants.payment_method.pay_with_bakong',
    logo: 'https://api.nuget.org/v3-flatcontainer/kh.gov.nbc.bakongkhqr/1.0.0.16/icon',
  },
}

export const PAYMENT_METHODS = Object.keys(PAYMENT_CONFIGS)
</script>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  method: {
    type: String,
    required: true,
    validator: v => PAYMENT_METHODS.includes(v),
  },
  label:   { type: String,  default: '' },
  loading: { type: Boolean, default: false },
})

defineEmits(['click'])

const imgFailed = ref(false)
watch(() => props.method, () => { imgFailed.value = false })

const config = computed(() => PAYMENT_CONFIGS[props.method] ?? PAYMENT_CONFIGS.aba)
</script>

<style scoped>
.payment-btn {
  transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
}
.payment-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px var(--glow);
  filter: brightness(1.07);
}
.payment-btn:active {
  transform: translateY(0);
  filter: brightness(0.96);
}
.arrow-icon {
  opacity: 0.7;
  transition: transform 0.18s ease, opacity 0.18s ease;
}
.payment-btn:hover .arrow-icon {
  transform: translateX(3px);
  opacity: 1;
}
:deep(.v-btn__loader) { color: #fff; }
</style>