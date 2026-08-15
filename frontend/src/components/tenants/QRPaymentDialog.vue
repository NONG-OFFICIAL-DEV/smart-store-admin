<template>
  <AppDialog
    v-model="model"
    :max-width="400"
    :title="method === 'aba' ? $t('tenants.qr_payment.aba_title') : $t('tenants.qr_payment.bakong_title')"
    :subtitle="invoiceRef ? $t('tenants.qr_payment.invoice_label', { ref: invoiceRef }) : planName"
    icon="mdi-qrcode"
    :color="method === 'aba' ? 'blue-darken-3' : 'red-darken-3'"
    :loading="loading"
    body-class="pa-0"
    @close="$emit('close')"
  >
    <template #header-extra>
      <div class="qr-band" :class="`qr-band--${method}`">
        <div class="qr-band__logo-wrap">
          <img
            v-if="method === 'aba'"
            src="https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/ABA_Bank_logo.svg/200px-ABA_Bank_logo.svg.png"
            alt="ABA Bank"
            class="qr-band__logo"
            @error="abaFailed = true"
          />
          <span v-if="method === 'aba' && abaFailed" class="qr-band__logo-fallback">ABA</span>

          <img
            v-if="method === 'bakong'"
            src="https://bakong.nbc.gov.kh/images/logo.png"
            alt="Bakong"
            class="qr-band__logo"
            @error="bakongFailed = true"
          />
          <span v-if="method === 'bakong' && bakongFailed" class="qr-band__logo-fallback">Bakong</span>
        </div>
      </div>
    </template>

      <!-- ── Amount row ──────────────────────────────────────────────────── -->
      <div class="qr-amount-row" :class="`qr-amount-row--${method}`">
        <div class="qr-amount-row__usd">{{ formattedAmount }}</div>
        <div class="qr-amount-row__khr" v-if="khrAmount">≈ {{ khrAmount }}</div>
      </div>

      <!-- ── QR area ─────────────────────────────────────────────────────── -->
      <div class="qr-body">
        <div class="qr-frame" :class="`qr-frame--${method}`">

          <!-- Loading spinner -->
          <div v-if="loading" class="qr-frame__state">
            <v-progress-circular indeterminate :color="method === 'aba' ? 'blue-darken-3' : 'red-darken-3'" size="44" width="3" />
            <span class="qr-frame__state-text">{{ $t('tenants.qr_payment.generating') }}</span>
          </div>

          <!-- Real QR from API -->
          <img v-else-if="imageUrl" :src="imageUrl" class="qr-frame__img" :alt="$t('tenants.qr_payment.qr_alt')" />

          <!-- Dev placeholder -->
          <div v-else class="qr-frame__placeholder">
            <svg width="172" height="172" viewBox="0 0 172 172" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <!-- Finder pattern TL -->
              <rect x="8" y="8" width="46" height="46" rx="5" fill="none" :stroke="accentColor" stroke-width="4"/>
              <rect x="16" y="16" width="30" height="30" rx="2" :fill="accentColor"/>
              <!-- Finder pattern TR -->
              <rect x="118" y="8" width="46" height="46" rx="5" fill="none" :stroke="accentColor" stroke-width="4"/>
              <rect x="126" y="16" width="30" height="30" rx="2" :fill="accentColor"/>
              <!-- Finder pattern BL -->
              <rect x="8" y="118" width="46" height="46" rx="5" fill="none" :stroke="accentColor" stroke-width="4"/>
              <rect x="16" y="126" width="30" height="30" rx="2" :fill="accentColor"/>
              <!-- Data modules (random-looking grid) -->
              <rect x="66" y="8" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="78" y="8" width="14" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="96" y="8" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="66" y="22" width="14" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="84" y="22" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="96" y="22" width="16" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="66" y="36" width="8" height="14" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="78" y="36" width="16" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="98" y="36" width="14" height="14" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="8" y="66" width="8" height="14" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="20" y="66" width="16" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="40" y="66" width="12" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="56" y="66" width="6" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="8" y="82" width="14" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="26" y="82" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="38" y="82" width="18" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="8" y="94" width="8" height="16" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="20" y="94" width="12" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="36" y="94" width="22" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="8" y="114" width="16" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="28" y="114" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="40" y="114" width="18" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="118" y="66" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="130" y="66" width="12" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="146" y="66" width="18" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="118" y="78" width="16" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="138" y="78" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="150" y="78" width="14" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="118" y="90" width="8" height="14" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="130" y="90" width="18" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="152" y="90" width="12" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="118" y="108" width="12" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="134" y="108" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="146" y="108" width="18" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="118" y="120" width="18" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="140" y="120" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="152" y="120" width="12" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="118" y="132" width="8" height="12" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="130" y="132" width="14" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="148" y="132" width="16" height="12" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="118" y="148" width="22" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="144" y="148" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="156" y="148" width="8" height="8" rx="1" :fill="accentColor" opacity="0.7"/>
              <rect x="66" y="66" width="48" height="48" rx="6" fill="white" opacity="0.95"/>
              <!-- Center logo placeholder box -->
              <rect x="70" y="70" width="40" height="40" rx="4" :fill="accentColorLight"/>
              <text x="86" y="95" font-size="11" font-weight="700" :fill="accentColor" text-anchor="middle" font-family="sans-serif">{{ method === 'aba' ? 'ABA' : 'NBC' }}</text>
            </svg>
            <div class="qr-frame__dev-label">{{ $t('tenants.qr_payment.dev_sample') }}</div>
          </div>
        </div>

        <!-- Instruction alert -->
        <div class="qr-instruction" :class="`qr-instruction--${method}`">
          <v-icon size="16" class="mr-1">mdi-cellphone</v-icon>
          <span v-if="method === 'aba'" v-html="$t('tenants.qr_payment.instructions_aba')"></span>
          <span v-else v-html="$t('tenants.qr_payment.instructions_bakong')"></span>
        </div>

        <!-- Bakong compatible apps -->
        <div v-if="method === 'bakong'" class="qr-apps">
          <span v-for="app in bakongApps" :key="app" class="qr-apps__chip">{{ app }}</span>
        </div>

        <!-- Countdown -->
        <div class="qr-countdown">
          <v-icon size="14" :color="countdownColor" class="mr-1">mdi-clock-outline</v-icon>
          <span class="qr-countdown__label" :style="{ color: `var(--v-theme-${countdownColor})` }">
            {{ $t('tenants.qr_payment.expires_in', { time: formattedCountdown }) }}
          </span>
          <v-progress-linear
            :model-value="(countdown / QR_TTL) * 100"
            :color="countdownColor"
            bg-color="grey-lighten-3"
            rounded
            height="3"
            class="ml-2 flex-1-1"
          />
        </div>
      </div>

    <template #actions>
      <v-btn variant="text" size="small" prepend-icon="mdi-refresh" :loading="loading" @click="$emit('refresh')">
        {{ $t('btn.refresh') }}
      </v-btn>
      <v-spacer />
      <v-btn variant="flat" :color="method === 'aba' ? 'blue-darken-3' : 'red-darken-3'" size="small" prepend-icon="mdi-check-circle-outline" @click="$emit('confirm')">
        {{ $t('tenants.qr_payment.paid_confirm') }}
      </v-btn>
      <v-btn variant="text" size="small" @click="$emit('close')">{{ $t('btn.cancel') }}</v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import AppDialog from '@/components/common/AppDialog.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  method: { type: String, default: 'aba' }, // 'aba' | 'bakong'
  imageUrl: { type: String, default: null },
  loading: { type: Boolean, default: false },
  amount: { type: [Number, String], default: 0 },
  currency: { type: String, default: 'USD' },
  invoiceRef: { type: String, default: null },
  planName: { type: String, default: 'Subscription' },
})

const emit = defineEmits(['update:modelValue', 'close', 'refresh', 'confirm'])

const model = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

// ─── Logo error states ────────────────────────────────────────────────────────
const abaFailed = ref(false)
const bakongFailed = ref(false)

// ─── Countdown ────────────────────────────────────────────────────────────────
const QR_TTL = 300
const countdown = ref(QR_TTL)
let timer = null

function startCountdown() {
  clearInterval(timer)
  countdown.value = QR_TTL
  timer = setInterval(() => {
    if (countdown.value > 0) countdown.value--
    else { clearInterval(timer); emit('refresh') }
  }, 1000)
}

watch(() => props.modelValue, val => {
  if (val) startCountdown()
  else clearInterval(timer)
})

watch(() => props.imageUrl, val => {
  if (val) startCountdown()
})

onUnmounted(() => clearInterval(timer))

// ─── Computed ─────────────────────────────────────────────────────────────────
const accentColor = computed(() => props.method === 'aba' ? '#1D4ED8' : '#B91C1C')
const accentColorLight = computed(() => props.method === 'aba' ? '#DBEAFE' : '#FEE2E2')

const bakongApps = ['ABA', 'ACLEDA', 'Canadia', 'Wing', 'Phillip', 'Prince']

const countdownColor = computed(() => {
  if (countdown.value > 120) return 'success'
  if (countdown.value > 60) return 'warning'
  return 'error'
})

const formattedCountdown = computed(() => {
  const m = Math.floor(countdown.value / 60)
  const s = countdown.value % 60
  return `${m}:${s.toString().padStart(2, '0')}`
})

const formattedAmount = computed(() => {
  if (props.amount == null) return '—'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.currency ?? 'USD',
    minimumFractionDigits: 0,
  }).format(props.amount)
})

const khrAmount = computed(() => {
  if (!props.amount || props.currency === 'KHR') return null
  return new Intl.NumberFormat('km-KH', {
    style: 'currency',
    currency: 'KHR',
    minimumFractionDigits: 0,
  }).format(props.amount * 4100)
})
</script>

<style scoped>
.qr-card { overflow: hidden; }

/* ── Top color band ──────────────────────────────────────────────────────── */
.qr-band {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
}
.qr-band--aba { background: linear-gradient(135deg, #1e3a8a 0%, #1D4ED8 100%); }
.qr-band--bakong { background: linear-gradient(135deg, #7f1d1d 0%, #B91C1C 100%); }

.qr-band__logo-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.15);
  border-radius: 8px;
  padding: 6px 10px;
  min-width: 56px;
  height: 36px;
}
.qr-band__logo {
  max-height: 22px;
  max-width: 60px;
  object-fit: contain;
  filter: brightness(0) invert(1);
}
.qr-band__logo-fallback {
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.06em;
  color: white;
}
.qr-band__info { flex: 1; }
.qr-band__title { font-size: 14px; font-weight: 700; color: white; line-height: 1.3; }
.qr-band__sub { font-size: 11px; color: rgba(255,255,255,0.75); margin-top: 1px; }
.qr-band__close {
  background: rgba(255,255,255,0.15);
  border: none;
  border-radius: 6px;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: white;
  transition: background 0.15s;
}
.qr-band__close:hover { background: rgba(255,255,255,0.28); }

/* ── Amount row ──────────────────────────────────────────────────────────── */
.qr-amount-row {
  display: flex;
  align-items: baseline;
  gap: 10px;
  padding: 10px 20px;
}
.qr-amount-row--aba { background: #EFF6FF; border-bottom: 1px solid #DBEAFE; }
.qr-amount-row--bakong { background: #FFF5F5; border-bottom: 1px solid #FEE2E2; }
.qr-amount-row__usd { font-size: 26px; font-weight: 700; }
.qr-amount-row--aba .qr-amount-row__usd { color: #1D4ED8; }
.qr-amount-row--bakong .qr-amount-row__usd { color: #B91C1C; }
.qr-amount-row__khr { font-size: 12px; color: #6B7280; }

/* ── Body ────────────────────────────────────────────────────────────────── */
.qr-body { padding: 16px 20px 12px; }

/* ── QR frame ────────────────────────────────────────────────────────────── */
.qr-frame {
  width: 200px;
  height: 200px;
  margin: 0 auto 14px;
  border-radius: 14px;
  border: 2px solid transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  position: relative;
  overflow: hidden;
}
.qr-frame--aba { border-color: #BFDBFE; box-shadow: 0 0 0 4px #EFF6FF; }
.qr-frame--bakong { border-color: #FCA5A5; box-shadow: 0 0 0 4px #FFF5F5; }

.qr-frame__state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}
.qr-frame__state-text { font-size: 12px; color: #9CA3AF; }
.qr-frame__img { width: 180px; height: 180px; object-fit: contain; }
.qr-frame__placeholder { display: flex; flex-direction: column; align-items: center; }
.qr-frame__dev-label {
  font-size: 10px;
  color: #9CA3AF;
  margin-top: 4px;
  letter-spacing: 0.03em;
}

/* ── Instruction ─────────────────────────────────────────────────────────── */
.qr-instruction {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  font-size: 12px;
  padding: 8px 12px;
  border-radius: 8px;
  margin-bottom: 10px;
  line-height: 1.5;
}
.qr-instruction--aba { background: #EFF6FF; color: #1D4ED8; }
.qr-instruction--bakong { background: #FFF5F5; color: #B91C1C; }

/* ── Bakong apps ─────────────────────────────────────────────────────────── */
.qr-apps {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: center;
  margin-bottom: 10px;
}
.qr-apps__chip {
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 99px;
  border: 1px solid #FCA5A5;
  color: #B91C1C;
  background: #FFF5F5;
}

/* ── Countdown ───────────────────────────────────────────────────────────── */
.qr-countdown {
  display: flex;
  align-items: center;
  font-size: 11px;
}
.qr-countdown__label { white-space: nowrap; color: #6B7280; }

/* ── Footer ──────────────────────────────────────────────────────────────── */
.qr-footer { padding: 10px 16px; }
</style>