<template>
  <v-container fluid class="pa-0 login-container">
    <v-row no-gutters class="fill-height">
      <!-- ── Left panel ─────────────────────────────────────────────────── -->
      <v-col
        cols="12"
        md="6"
        class="left-panel d-none d-md-flex flex-column pa-12 text-white"
      >
        <div class="orb orb-1" />
        <div class="orb orb-2" />
        <div class="orb orb-3" />

        <div class="brand-mark d-flex align-center mb-auto">
          <div class="brand-icon-wrapper mr-3">
            <v-icon icon="mdi-store-outline" size="22" color="white" />
          </div>
          <span
            class="text-h6 font-weight-black"
            style="letter-spacing: -0.3px"
          >
            {{ t('app.name') }}
          </span>
        </div>

        <div class="left-content fade-in">
          <div class="eyebrow mb-4">RETAIL & RESTAURANT OS</div>
          <h2 class="left-title mb-5">
            One platform.
            <br />
            Every branch.
          </h2>
          <p class="left-sub mb-10">
            Manage your POS, inventory, staff and reports across all locations
            from a single dashboard.
          </p>
          <div class="feature-list">
            <div
              v-for="f in features"
              :key="f"
              class="feature-item d-flex align-center mb-4"
            >
              <div class="feature-check mr-3">
                <v-icon icon="mdi-check" size="13" color="white" />
              </div>
              <span class="text-body-2 font-weight-medium feature-text">
                {{ f }}
              </span>
            </div>
          </div>
        </div>

        <div class="text-caption mt-auto left-footer-text">
          © {{ new Date().getFullYear() }} {{ t('app.footer') }}
        </div>
      </v-col>

      <!-- ── Right panel ────────────────────────────────────────────────── -->
      <v-col
        cols="12"
        md="6"
        class="d-flex align-center justify-center right-panel"
      >
        <v-card
          flat
          class="login-card px-4 px-sm-10"
          width="100%"
          max-width="500"
        >
          <!-- ══ STEP: Setup PIN ════════════════════════════════════════════ -->
          <template v-if="step === 'setup-pin'">
            <div class="text-start mb-6 fade-in">
              <div class="form-header">
                <div class="form-title">Set Your PIN</div>
                <div class="form-sub">
                  Create a 4-digit PIN for quick login on this device.
                </div>
              </div>
            </div>

            <!-- ── Success alert ── -->
            <v-slide-y-transition>
              <v-alert
                v-if="pinSuccess"
                type="success"
                variant="tonal"
                density="comfortable"
                rounded="lg"
                border="start"
                class="mb-5"
                icon="mdi-shield-check"
              >
                <div class="text-body-2 font-weight-medium">
                  PIN set successfully!
                </div>
                <div class="text-caption mt-1">
                  Redirecting you to the dashboard…
                </div>
              </v-alert>
            </v-slide-y-transition>

            <!-- ── Error alert ── -->
            <v-slide-y-transition>
              <v-alert
                v-if="errors.general"
                type="error"
                variant="tonal"
                density="comfortable"
                rounded="lg"
                border="start"
                class="mb-5"
              >
                <span class="text-body-2">{{ errors.general }}</span>
              </v-alert>
            </v-slide-y-transition>

            <!-- ── Live PIN rules ── -->
            <div
              class="pin-rules mb-5 pa-4 rounded-lg"
              style="
                background: rgba(0, 0, 0, 0.03);
                border: 1px solid rgba(0, 0, 0, 0.06);
              "
            >
              <div
                class="text-caption font-weight-semibold text-medium-emphasis mb-3 text-uppercase"
                style="letter-spacing: 0.5px"
              >
                PIN Requirements
              </div>
              <div
                v-for="rule in pinRules"
                :key="rule.key"
                class="d-flex align-center mb-2"
              >
                <v-icon
                  :icon="
                    rule.passed ? 'mdi-check-circle' : 'mdi-circle-outline'
                  "
                  :color="rule.passed ? 'success' : 'grey-lighten-1'"
                  size="15"
                  class="mr-2 flex-shrink-0"
                />
                <span
                  class="text-caption"
                  :class="
                    rule.passed
                      ? 'text-success font-weight-medium'
                      : 'text-medium-emphasis'
                  "
                >
                  {{ rule.label }}
                </span>
              </div>
            </div>

            <!-- ── Enter PIN ── -->
            <label class="text-caption font-weight-medium ml-1">
              Enter PIN
              <v-chip
                v-if="activeField === 'new'"
                size="x-small"
                color="primary"
                variant="tonal"
                class="ml-2"
              >
                typing here
              </v-chip>
            </label>
            <v-otp-input
              v-model="newPin"
              length="4"
              type="password"
              variant="outlined"
              class="mt-1 mb-1"
              :disabled="pinSetupLoading || pinSuccess"
              :error="!!errors.newPin"
            />
            <div v-if="errors.newPin" class="text-caption text-error ml-1 mb-3">
              <v-icon icon="mdi-alert-circle-outline" size="13" class="mr-1" />
              {{ errors.newPin }}
            </div>

            <!-- ── Confirm PIN ── -->
            <label class="text-caption font-weight-medium ml-1 mt-2">
              Confirm PIN
              <v-chip
                v-if="activeField === 'confirm'"
                size="x-small"
                color="primary"
                variant="tonal"
                class="ml-2"
              >
                typing here
              </v-chip>
            </label>
            <v-otp-input
              v-model="confirmPin"
              length="4"
              type="password"
              variant="outlined"
              class="mt-1 mb-1"
              :disabled="pinSetupLoading || pinSuccess"
              :error="!!errors.confirmPin"
            />
            <div
              v-if="errors.confirmPin"
              class="text-caption text-error ml-1 mb-2"
            >
              <v-icon icon="mdi-alert-circle-outline" size="13" class="mr-1" />
              {{ errors.confirmPin }}
            </div>

            <!-- ── Numpad ── -->
            <div class="pin-pad mt-3">
              <v-row no-gutters justify="center">
                <v-col
                  v-for="key in [
                    '1',
                    '2',
                    '3',
                    '4',
                    '5',
                    '6',
                    '7',
                    '8',
                    '9',
                    '',
                    '0',
                    '⌫'
                  ]"
                  :key="key"
                  cols="4"
                  class="pa-1"
                >
                  <v-btn
                    v-if="key !== ''"
                    block
                    variant="tonal"
                    rounded="lg"
                    size="large"
                    class="text-h6 font-weight-medium"
                    :disabled="pinSetupLoading || pinSuccess"
                    @click="handleSetupNumpad(key)"
                  >
                    {{ key }}
                  </v-btn>
                </v-col>
              </v-row>
            </div>

            <v-btn
              color="primary"
              block
              class="mt-4 py-7 text-none submit-btn"
              rounded="lg"
              elevation="0"
              :loading="pinSetupLoading"
              :disabled="!allPinRulesPassed || pinSuccess"
              @click="handleSetPin"
            >
              Set PIN & Continue
            </v-btn>

            <v-btn
              variant="text"
              block
              class="mt-2 text-none text-caption text-medium-emphasis"
              :disabled="pinSetupLoading || pinSuccess"
              @click="goToDashboard"
            >
              Skip for now
            </v-btn>
          </template>

          <!-- ══ STEP: Login ════════════════════════════════════════════════ -->
          <template v-else>
            <!-- Header -->
            <div class="text-start mb-8 fade-in">
              <div class="form-header">
                <div class="form-title">{{ t('login.signIn') }}</div>
                <div class="form-sub">{{ t('login.sub') }}</div>
              </div>
            </div>

            <!-- Mode toggle -->
            <v-btn-toggle
              v-model="loginMode"
              mandatory
              density="compact"
              rounded="lg"
              variant="outlined"
              class="mb-6 w-100"
            >
              <v-btn
                value="pin"
                size="small"
                class="text-none flex-grow-1"
                prepend-icon="mdi-dialpad"
                :disabled="!hasBranchId"
              >
                {{ t('login.pin') }}
              </v-btn>
              <v-btn
                value="password"
                size="small"
                class="text-none flex-grow-1"
                prepend-icon="mdi-lock-outline"
              >
                {{ t('login.password') }}
              </v-btn>
            </v-btn-toggle>

            <!-- First time hint -->
            <v-alert
              v-if="!hasBranchId"
              type="info"
              variant="tonal"
              density="compact"
              rounded="lg"
              class="mb-6 text-caption"
              icon="mdi-information-outline"
            >
              Login with your password once to enable PIN login on this device.
            </v-alert>

            <!-- General error -->
            <v-slide-y-transition>
              <v-alert
                v-if="errors.general"
                type="error"
                variant="tonal"
                density="comfortable"
                class="mb-6 rounded-lg"
                border="start"
              >
                <span class="text-body-2">{{ errors.general }}</span>
              </v-alert>
            </v-slide-y-transition>

            <!-- ── Password form ── -->
            <v-form
              v-if="loginMode === 'password'"
              ref="formRef"
              class="fade-in"
              @submit.prevent="handleLogin"
            >
              <label class="text-caption ml-1">{{ t('login.email') }}</label>
              <v-text-field
                v-model="email"
                :placeholder="t('login.emailPlaceholder')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-email-outline"
                class="mt-1 mb-2"
                color="primary"
                :rules="emailRules"
                :error-messages="errors.email"
                :disabled="loading"
                validate-on="blur"
                @update:model-value="errors.email = ''"
              />

              <label class="text-caption ml-1">{{ t('login.password') }}</label>
              <v-text-field
                v-model="password"
                :placeholder="t('login.passwordPlaceholder')"
                variant="outlined"
                rounded="lg"
                :append-inner-icon="visible ? 'mdi-eye-off' : 'mdi-eye'"
                :type="visible ? 'text' : 'password'"
                prepend-inner-icon="mdi-lock-outline"
                class="mt-1"
                color="primary"
                :rules="passwordRules"
                :error-messages="errors.password"
                :disabled="loading"
                validate-on="blur"
                persistent-hint
                :hint="t('login.passwordHint')"
                @click:append-inner="visible = !visible"
                @update:model-value="errors.password = ''"
              />

              <v-btn
                type="submit"
                color="primary"
                block
                class="mt-8 py-7 text-none submit-btn"
                rounded="lg"
                elevation="0"
                :loading="loading"
              >
                {{ t('login.signIn') }}
              </v-btn>
            </v-form>

            <!-- ── PIN form ── -->
            <v-form
              v-else
              ref="pinFormRef"
              class="fade-in"
              @submit.prevent="handlePinLogin"
            >
              <label class="text-caption ml-1">{{ t('login.pin') }}</label>
              <v-otp-input
                v-model="pin"
                length="4"
                type="password"
                variant="outlined"
                class="mt-1 mb-2"
                :disabled="loading"
                :error="!!errors.pin"
                @finish="handlePinLogin"
              />
              <div v-if="errors.pin" class="text-caption text-error ml-1 mb-2">
                <v-icon
                  icon="mdi-alert-circle-outline"
                  size="13"
                  class="mr-1"
                />
                {{ errors.pin }}
              </div>

              <div class="pin-pad mt-4">
                <v-row no-gutters justify="center">
                  <v-col
                    v-for="key in [
                      '1',
                      '2',
                      '3',
                      '4',
                      '5',
                      '6',
                      '7',
                      '8',
                      '9',
                      '',
                      '0',
                      '⌫'
                    ]"
                    :key="key"
                    cols="4"
                    class="pa-1"
                  >
                    <v-btn
                      v-if="key !== ''"
                      block
                      variant="tonal"
                      rounded="lg"
                      size="large"
                      class="text-h6 font-weight-medium"
                      :disabled="loading || (key !== '⌫' && pin.length >= 4)"
                      @click="
                        key === '⌫' ? (pin = pin.slice(0, -1)) : (pin += key)
                      "
                    >
                      {{ key }}
                    </v-btn>
                  </v-col>
                </v-row>
              </div>

              <v-btn
                type="submit"
                color="primary"
                block
                class="mt-4 py-7 text-none submit-btn"
                rounded="lg"
                elevation="0"
                :loading="loading"
                :disabled="pin.length < 4"
              >
                {{ t('login.signInPin') }}
              </v-btn>
            </v-form>

            <!-- Language switcher -->
            <div
              class="d-flex align-center justify-center gap-2 mt-6 pt-4 border-top"
            >
              <span class="text-caption text-medium-emphasis">
                {{ t('common.language') }}
              </span>
              <v-btn-toggle
                v-model="locale"
                mandatory
                density="compact"
                rounded="pill"
                variant="outlined"
                @update:model-value="switchLocale"
              >
                <v-btn value="en" size="x-small" class="text-none px-3">
                  EN
                </v-btn>
                <v-btn value="km" size="x-small" class="text-none px-3">
                  KH
                </v-btn>
              </v-btn-toggle>
            </div>
          </template>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { useI18n } from 'vue-i18n'
  import authService from '@/api/auth'

  const { t, locale } = useI18n()
  const { notif } = useAppUtils()
  const router = useRouter()
  const store = useAuthStore()

  // ── Refs ───────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const email = ref('')
  const password = ref('')
  const loading = ref(false)
  const visible = ref(false)
  const hasBranchId = ref(false)
  const loginMode = ref('password')
  const pin = ref('')

  // ── PIN setup ──────────────────────────────────────────────────────────────
  const step = ref('login') // 'login' | 'setup-pin'
  const newPin = ref('')
  const confirmPin = ref('')
  const pinSetupLoading = ref(false)
  const pinSuccess = ref(false) // shows success alert
  const activeField = ref('new') // 'new' | 'confirm'

  const errors = reactive({
    email: '',
    password: '',
    pin: '',
    newPin: '',
    confirmPin: '',
    general: ''
  })

  const features = [
    'Multi-branch POS & order management',
    'Real-time inventory tracking',
    'Staff roles & permissions',
    'Sales analytics & reports'
  ]

  // ── On mount ───────────────────────────────────────────────────────────────
  onMounted(() => {
    const branchId = localStorage.getItem('branch_id')
    hasBranchId.value = !!branchId
    if (hasBranchId.value) loginMode.value = 'pin'
  })

  // ── Validation rules ───────────────────────────────────────────────────────
  const emailRules = [
    v => !!v || 'Email is required',
    v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Enter a valid email address'
  ]
  const passwordRules = [
    v => !!v || 'Password is required',
    v => v.length >= 6 || 'Password must be at least 6 characters'
  ]

  // ── Live PIN rules (mirrors backend) ──────────────────────────────────────
  const pinRules = computed(() => [
    {
      key: 'length',
      label: 'Exactly 4 digits',
      passed: newPin.value.length === 4
    },
    {
      key: 'not_repeat',
      label: 'Not all the same digit (e.g. 1111)',
      passed: newPin.value.length === 4 && !/^(\d)\1+$/.test(newPin.value)
    },
    {
      key: 'not_sequential',
      label: 'Not a simple sequence (e.g. 1234, 4321)',
      passed: newPin.value.length === 4 && !isSequentialPin(newPin.value)
    },
    {
      key: 'match',
      label: 'Both PINs match',
      passed:
        newPin.value.length === 4 &&
        confirmPin.value.length === 4 &&
        newPin.value === confirmPin.value
    }
  ])

  const allPinRulesPassed = computed(() => pinRules.value.every(r => r.passed))

  // ── Sequential PIN check ───────────────────────────────────────────────────
  function isSequentialPin(pin) {
    const ascending = Array.from(
      { length: pin.length },
      (_, i) => (parseInt(pin[0]) + i) % 10
    ).join('')
    const descending = Array.from(
      { length: pin.length },
      (_, i) => (parseInt(pin[0]) - i + 10) % 10
    ).join('')
    return pin === ascending || pin === descending
  }

  function switchLocale(lang) {
    locale.value = lang
    localStorage.setItem('lang', lang)
  }

  // ── Setup numpad ───────────────────────────────────────────────────────────
  function handleSetupNumpad(key) {
    errors.newPin = ''
    errors.confirmPin = ''

    activeField.value = newPin.value.length < 4 ? 'new' : 'confirm'

    if (key === '⌫') {
      if (activeField.value === 'confirm' && confirmPin.value.length > 0) {
        confirmPin.value = confirmPin.value.slice(0, -1)
      } else if (newPin.value.length > 0) {
        newPin.value = newPin.value.slice(0, -1)
        activeField.value = 'new'
      }
    } else {
      if (newPin.value.length < 4) {
        newPin.value += key
      } else if (confirmPin.value.length < 4) {
        confirmPin.value += key
      }
    }
  }

  // ── Set PIN ────────────────────────────────────────────────────────────────
  async function handleSetPin() {
    errors.newPin = ''
    errors.confirmPin = ''
    errors.general = ''

    if (newPin.value.length < 4) {
      errors.newPin = 'PIN must be 4 digits.'
      return
    }
    if (/^(\d)\1+$/.test(newPin.value)) {
      errors.newPin = 'PIN cannot be all the same digit (e.g. 1111).'
      newPin.value = ''
      confirmPin.value = ''
      return
    }
    if (isSequentialPin(newPin.value)) {
      errors.newPin = 'PIN cannot be a simple sequence (e.g. 1234, 4321).'
      newPin.value = ''
      confirmPin.value = ''
      return
    }
    if (newPin.value !== confirmPin.value) {
      errors.confirmPin = 'PINs do not match. Please try again.'
      confirmPin.value = ''
      return
    }

    pinSetupLoading.value = true
    try {
      await authService.setPin(newPin.value)

      // Show success alert then redirect after 1.5s
      pinSuccess.value = true
      setTimeout(() => goToDashboard(), 1500)
    } catch (err) {
      const res = err.response?.data
      errors.general = res?.message ?? 'Failed to set PIN. Please try again.'
      newPin.value = ''
      confirmPin.value = ''
    } finally {
      pinSetupLoading.value = false
    }
  }

  // ── Go to dashboard ────────────────────────────────────────────────────────
  function goToDashboard() {
    const route = store.isSuperAdmin ? '/admin-dashboard' : '/dashboard'
    router.push(route)
    notif(t('messages.login_success'), { type: 'success' })
  }

  // ── Password login ─────────────────────────────────────────────────────────
  async function handleLogin() {
    errors.email = errors.password = errors.general = ''

    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    try {
      const response = await store.login({
        email: email.value,
        password: password.value
      })
      if (response) {
        hasBranchId.value = !!localStorage.getItem('branch_id')
        const hasPin = response.data.has_pin ?? false

        if (!hasPin) {
          step.value = 'setup-pin'
        } else {
          goToDashboard()
        }
      }
    } catch (err) {
      const res = err.response?.data
      const code = res?.status
      if (code === 'validation_error') {
        errors.email = res.errors?.email?.[0] ?? ''
        errors.password = res.errors?.password?.[0] ?? ''
      } else if (code === 'invalid_credentials') {
        errors.general =
          res.message ?? 'The email or password you entered is incorrect.'
      } else if (err.response?.status === 429) {
        errors.general =
          'Too many attempts. Please wait a moment and try again.'
      } else if (!err.response) {
        errors.general = 'Cannot connect to server. Check your connection.'
      } else {
        errors.general =
          res?.message ?? 'An unexpected error occurred. Please try again.'
      }
    } finally {
      loading.value = false
    }
  }

  // ── PIN login ──────────────────────────────────────────────────────────────
  async function handlePinLogin() {
    if (pin.value.length < 4) return

    const branchId = localStorage.getItem('branch_id')
    if (!branchId) {
      errors.general =
        'Please login with your password first to register this terminal.'
      loginMode.value = 'password'
      return
    }

    errors.pin = ''
    errors.general = ''
    loading.value = true

    try {
      await store.loginByPin(pin.value)
      goToDashboard()
    } catch (err) {
      const res = err.response?.data
      const code = res?.status
      if (code === 'invalid_pin') {
        errors.pin = res.message ?? 'Incorrect PIN. Please try again.'
      } else if (code === 'locked') {
        errors.general = res.message ?? 'Terminal locked. Try again later.'
      } else if (code === 'terminal_not_trusted') {
        localStorage.removeItem('branch_id')
        hasBranchId.value = false
        loginMode.value = 'password'
        errors.general =
          'Terminal session expired. Please login with your password again.'
      } else if (!err.response) {
        errors.general = 'Cannot connect to server. Check your connection.'
      } else {
        errors.general = res?.message ?? 'An unexpected error occurred.'
      }
    } finally {
      loading.value = false
      pin.value = ''
    }
  }
</script>

<style scoped>
  .login-container {
    height: 100vh;
    overflow: hidden;
  }

  .left-panel {
    position: relative;
    overflow: hidden;
    background: linear-gradient(
      150deg,
      #0f172a 0%,
      #1e3a5f 35%,
      #1d4ed8 70%,
      #6366f1 100%
    );
  }
  .left-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
      radial-gradient(
        ellipse at 20% 50%,
        rgba(99, 102, 241, 0.35) 0%,
        transparent 60%
      ),
      radial-gradient(
        ellipse at 80% 10%,
        rgba(59, 130, 246, 0.25) 0%,
        transparent 55%
      ),
      radial-gradient(
        ellipse at 60% 90%,
        rgba(139, 92, 246, 0.2) 0%,
        transparent 50%
      );
    pointer-events: none;
  }

  .orb {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    filter: blur(60px);
  }
  .orb-1 {
    width: 340px;
    height: 340px;
    top: -100px;
    right: -80px;
    background: rgba(99, 102, 241, 0.3);
  }
  .orb-2 {
    width: 240px;
    height: 240px;
    bottom: 40px;
    left: -60px;
    background: rgba(59, 130, 246, 0.25);
  }
  .orb-3 {
    width: 180px;
    height: 180px;
    top: 45%;
    left: 55%;
    background: rgba(139, 92, 246, 0.2);
  }

  .brand-icon-wrapper {
    background: rgba(255, 255, 255, 0.15);
    padding: 9px;
    border-radius: 11px;
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    display: grid;
    place-items: center;
  }

  .left-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    z-index: 1;
  }
  .eyebrow {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 3px;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
  }
  .left-title {
    font-size: 40px;
    font-weight: 900;
    line-height: 1.08;
    letter-spacing: -1.2px;
    color: #fff;
  }
  .left-sub {
    font-size: 14px;
    line-height: 1.75;
    color: rgba(255, 255, 255, 0.5);
    max-width: 360px;
  }
  .feature-check {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    display: grid;
    place-items: center;
    backdrop-filter: blur(4px);
  }
  .feature-text {
    color: rgba(255, 255, 255, 0.75);
  }
  .left-footer-text {
    color: rgba(255, 255, 255, 0.25);
    position: relative;
    z-index: 1;
  }

  .right-panel {
    background: #ffffff;
  }
  .login-card {
    background: transparent !important;
  }

  .form-title {
    font-size: 30px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.5px;
  }
  .form-sub {
    font-size: 14px;
    color: #94a3b8;
    margin-top: 4px;
  }

  :deep(.v-field__outline) {
    --v-field-border-opacity: 1;
    --v-field-border-color: #e2e8f0;
  }
  :deep(.v-field--focused .v-field__outline) {
    --v-field-border-color: rgb(var(--v-theme-primary));
  }
  :deep(.v-field__prepend-inner .v-icon),
  :deep(.v-field__append-inner .v-icon) {
    opacity: 0.4;
  }
  :deep(.v-field--focused .v-field__prepend-inner .v-icon) {
    opacity: 0.9;
    color: rgb(var(--v-theme-primary)) !important;
  }

  .submit-btn {
    font-weight: 700 !important;
    font-size: 15px !important;
    letter-spacing: 0.1px !important;
    box-shadow: 0 4px 18px rgba(99, 102, 241, 0.28) !important;
    transition:
      box-shadow 0.2s,
      transform 0.1s !important;
  }
  .submit-btn:hover:not(:disabled) {
    box-shadow: 0 6px 24px rgba(99, 102, 241, 0.4) !important;
    transform: translateY(-1px);
  }
  .submit-btn:active {
    transform: translateY(0) !important;
  }

  .fade-in {
    animation: fadeIn 0.7s cubic-bezier(0.16, 1, 0.3, 1);
  }
  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>
