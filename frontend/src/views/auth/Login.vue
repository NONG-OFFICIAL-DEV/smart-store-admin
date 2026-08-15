<template>
  <AuthLayout>
    <template #left-content>
      <div class="eyebrow mb-4">{{ t('login.tagline') }}</div>
      <h2 class="left-title mb-5">
        {{ t('login.title1') }}
        <br />
        {{ t('login.title2') }}
      </h2>
      <p class="left-sub mb-10">
        {{ t('login.description') }}
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
    </template>

    <!-- ── 2FA verification step ────────────────────────────────────────── -->
    <template v-if="twoFactorToken">
      <div class="text-start mb-8 fade-in">
        <div class="form-header">
          <div class="form-title">{{ t('login.two_factor.title') }}</div>
          <div class="form-sub">
            {{ useRecoveryCode ? t('login.two_factor.recovery_sub') : t('login.two_factor.sub') }}
          </div>
        </div>
      </div>

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

      <v-form class="fade-in" @submit.prevent="handleVerifyTwoFactor">
        <template v-if="!useRecoveryCode">
          <label class="text-caption ml-1">{{ t('login.two_factor.code') }}</label>
          <v-otp-input
            v-model="code"
            length="6"
            variant="outlined"
            class="mt-1 mb-2"
            :disabled="loading"
            :error="!!errors.code"
            @finish="handleVerifyTwoFactor"
          />
        </template>
        <template v-else>
          <label class="text-caption ml-1">{{ t('login.two_factor.recovery_code') }}</label>
          <v-text-field
            v-model="code"
            :placeholder="t('login.two_factor.recovery_code_placeholder')"
            variant="outlined"
            rounded="lg"
            prepend-inner-icon="mdi-key-outline"
            class="mt-1"
            color="primary"
            :error-messages="errors.code"
            :disabled="loading"
          />
        </template>

        <v-btn
          type="submit"
          color="primary"
          block
          class="mt-6 py-7 text-none submit-btn"
          rounded="lg"
          elevation="0"
          :loading="loading"
        >
          {{ t('login.two_factor.verify') }}
        </v-btn>

        <div class="d-flex justify-space-between mt-4">
          <a class="text-caption text-primary cursor-pointer" @click="backToLogin">
            {{ t('login.two_factor.back_to_login') }}
          </a>
          <a class="text-caption text-primary cursor-pointer" @click="toggleRecoveryCode">
            {{ useRecoveryCode ? t('login.two_factor.use_code') : t('login.two_factor.use_recovery_code') }}
          </a>
        </div>
      </v-form>
    </template>

    <!-- ── Password login ──────────────────────────────────────────────── -->
    <template v-else>
      <div class="text-start mb-8 fade-in">
        <div class="form-header">
          <div class="form-title">{{ t('login.signIn') }}</div>
          <div class="form-sub">
            {{ t('login.sub') }}
          </div>
        </div>
      </div>

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

      <v-form
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
          @click:append-inner="visible = !visible"
          @update:model-value="errors.password = ''"
        />

        <div class="text-end mt-2">
          <router-link :to="{ name: 'ForgotPassword' }" class="text-caption text-primary">
            {{ t('login.forgot_password') }}
          </router-link>
        </div>

        <v-btn
          type="submit"
          color="primary"
          block
          class="mt-6 py-7 text-none submit-btn"
          rounded="lg"
          elevation="0"
          :loading="loading"
        >
         {{ t('login.signIn') }}
        </v-btn>

        <div class="text-center mt-6 text-caption">
          {{ t('login.no_account') }}
          <router-link :to="{ name: 'Register' }" class="text-primary font-weight-medium">
            {{ t('login.register_link') }}
          </router-link>
        </div>
      </v-form>
    </template>
  </AuthLayout>
</template>

<script setup>
  import { ref, reactive, computed } from 'vue'
  import { useRouter } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { useI18n } from 'vue-i18n'
  import AuthLayout from '@/components/layout/AuthLayout.vue'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const router = useRouter()
  const store = useAuthStore()

  const formRef = ref(null)
  const email = ref('')
  const password = ref('')
  const loading = ref(false)
  const visible = ref(false)

  const twoFactorToken = ref('')
  const code = ref('')
  const useRecoveryCode = ref(false)

  const errors = reactive({ email: '', password: '', code: '', general: '' })
  const features = computed(() => [
    t('login.feature_list.pos'),
    t('login.feature_list.inventory'),
    t('login.feature_list.staff'),
    t('login.feature_list.reports')
  ])

  // ── Validation rules ───────────────────────────────────────────────────────
  const emailRules = [
    v => !!v || t('login.rules.email_required'),
    v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || t('login.rules.email_invalid')
  ]
  const passwordRules = [
    v => !!v || t('login.rules.password_required'),
    v => v.length >= 6 || t('validation.min_length', { n: 6 })
  ]

  function navigateAfterLogin(user) {
    const route = user.must_change_password
      ? '/force-password-change'
      : user.is_super_admin
        ? '/admin-dashboard'
        : '/dashboard'
    router.push(route)
    notif(t('messages.login_success'), { type: 'success' })
  }

  function backToLogin() {
    twoFactorToken.value = ''
    code.value = ''
    useRecoveryCode.value = false
    errors.general = ''
  }

  function toggleRecoveryCode() {
    useRecoveryCode.value = !useRecoveryCode.value
    code.value = ''
    errors.code = ''
  }

  // ── Submit — password step ───────────────────────────────────────────────
  const handleLogin = async () => {
    errors.email = errors.password = errors.general = ''

    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    try {
      const response = await store.login({
        email: email.value,
        password: password.value
      })

      if (response?.data?.requires_two_factor) {
        twoFactorToken.value = response.data.two_factor_token
        return
      }

      if (response) {
        navigateAfterLogin(response.data)
      }
    } catch (err) {
      const res = err.response?.data
      const code = res?.status

      if (code === 'validation_error') {
        errors.email = res.errors?.email?.[0] ?? ''
        errors.password = res.errors?.password?.[0] ?? ''
      } else if (code === 'invalid_credentials') {
        errors.general =
          res.message ?? t('login.errors.invalid_credentials')
      } else if (err.response?.status === 429) {
        errors.general = t('login.errors.too_many_attempts_retry')
      } else if (!err.response) {
        errors.general = t('login.errors.cannot_connect')
      } else {
        errors.general =
          res?.message ?? t('login.errors.unexpected_retry')
      }
    } finally {
      loading.value = false
    }
  }

  // ── Submit — 2FA step ────────────────────────────────────────────────────
  const handleVerifyTwoFactor = async () => {
    if (!code.value) return
    errors.code = errors.general = ''
    loading.value = true
    try {
      const response = await store.verifyTwoFactor(twoFactorToken.value, code.value)
      if (response) {
        navigateAfterLogin(response.data)
      }
    } catch (err) {
      const res = err.response?.data
      const errCode = res?.code

      if (errCode === 'TWO_FACTOR_CHALLENGE_EXPIRED') {
        errors.general = t('login.two_factor.errors.expired')
        backToLogin()
      } else if (errCode === 'INVALID_TWO_FACTOR_CODE') {
        errors.code = t('login.two_factor.errors.invalid_code')
      } else if (err.response?.status === 429) {
        errors.general = t('login.errors.too_many_attempts_retry')
      } else if (!err.response) {
        errors.general = t('login.errors.cannot_connect')
      } else {
        errors.general = res?.message ?? t('login.errors.unexpected_retry')
      }
    } finally {
      loading.value = false
      code.value = ''
    }
  }
</script>

<style scoped>
  .eyebrow {
    font-size: 10px;
    font-weight: 700;
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

  /* ── Form header ──────────────────────────────────────────────────────── */
  .form-header {
    margin-bottom: 8px;
  }
  .form-title {
    font-size: 30px;
    font-weight: 800;
    color: rgb(var(--v-theme-on-surface));
    letter-spacing: -0.5px;
    transition: color 0.25s ease;
  }
  .form-sub {
    font-size: 14px;
    color: rgba(var(--v-theme-on-surface), 0.6);
    margin-top: 4px;
    transition: color 0.25s ease;
  }

  /* ── Field borders ────────────────────────────────────────────────────── */
  :deep(.v-field__outline) {
    --v-field-border-opacity: 1;
    --v-field-border-color: rgba(var(--v-theme-on-surface), 0.15);
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

  /* ── Submit ───────────────────────────────────────────────────────────── */
  .submit-btn {
    font-weight: 700 !important;
    font-size: 15px !important;
    letter-spacing: 0.1px !important;
    box-shadow: 0 4px 18px rgba(var(--v-theme-primary), 0.28) !important;
    transition:
      box-shadow 0.2s,
      transform 0.1s !important;
  }
  .submit-btn:hover:not(:disabled) {
    box-shadow: 0 6px 24px rgba(var(--v-theme-primary), 0.4) !important;
    transform: translateY(-1px);
  }
  .submit-btn:active {
    transform: translateY(0) !important;
  }

  .cursor-pointer {
    cursor: pointer;
  }

  /* ── Animation ────────────────────────────────────────────────────────── */
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
