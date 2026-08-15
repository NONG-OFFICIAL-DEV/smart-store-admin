<template>
  <AuthLayout>
    <template #left-content>
      <div class="eyebrow mb-4">{{ t('reset_password.tagline') }}</div>
      <h2 class="left-title mb-5">{{ t('reset_password.title') }}</h2>
      <p class="left-sub mb-10">{{ t('reset_password.description') }}</p>
    </template>

    <div class="text-start mb-8 fade-in">
      <div class="form-header">
        <div class="form-title">{{ t('reset_password.heading') }}</div>
        <div class="form-sub">{{ t('reset_password.sub') }}</div>
      </div>
    </div>

    <template v-if="done">
      <v-alert type="success" variant="tonal" density="comfortable" class="mb-6 rounded-lg" border="start">
        <span class="text-body-2">{{ t('reset_password.success_message') }}</span>
      </v-alert>
      <router-link :to="{ name: 'Login' }" class="text-primary font-weight-medium text-body-2">
        {{ t('forgot_password.back_to_login') }}
      </router-link>
    </template>

    <template v-else-if="!token || !email">
      <v-alert type="error" variant="tonal" density="comfortable" class="mb-6 rounded-lg" border="start">
        <span class="text-body-2">{{ t('reset_password.errors.missing_token') }}</span>
      </v-alert>
      <router-link :to="{ name: 'ForgotPassword' }" class="text-primary font-weight-medium text-body-2">
        {{ t('reset_password.request_new_link') }}
      </router-link>
    </template>

    <v-form v-else ref="formRef" class="fade-in" @submit.prevent="handleSubmit">
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

      <label class="text-caption ml-1">{{ t('reset_password.new_password') }}</label>
      <v-text-field
        v-model="password"
        :append-inner-icon="visible ? 'mdi-eye-off' : 'mdi-eye'"
        :type="visible ? 'text' : 'password'"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-lock-outline"
        class="mt-1"
        :rules="passwordRules"
        :disabled="loading"
        @click:append-inner="visible = !visible"
      />

      <label class="text-caption ml-1">{{ t('reset_password.confirm_password') }}</label>
      <v-text-field
        v-model="passwordConfirmation"
        :type="visible ? 'text' : 'password'"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-lock-check-outline"
        class="mt-1"
        :rules="confirmRules"
        :disabled="loading"
      />

      <v-btn
        type="submit"
        color="primary"
        block
        class="mt-6 py-7 text-none submit-btn"
        rounded="lg"
        elevation="0"
        :loading="loading"
      >
        {{ t('reset_password.submit') }}
      </v-btn>
    </v-form>
  </AuthLayout>
</template>

<script setup>
  import { ref, reactive, computed } from 'vue'
  import { useRoute } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import AuthLayout from '@/components/layout/AuthLayout.vue'

  const { t } = useI18n()
  const route = useRoute()
  const store = useAuthStore()

  const token = computed(() => route.query.token ?? '')
  const email = computed(() => route.query.email ?? '')

  const formRef = ref(null)
  const password = ref('')
  const passwordConfirmation = ref('')
  const visible = ref(false)
  const loading = ref(false)
  const done = ref(false)
  const errors = reactive({ general: '' })

  const passwordRules = [
    v => !!v || t('form.required'),
    v => v.length >= 8 || t('validation.min_length', { n: 8 })
  ]
  const confirmRules = [
    v => !!v || t('form.required'),
    v => v === password.value || t('reset_password.errors.password_mismatch')
  ]

  const handleSubmit = async () => {
    errors.general = ''
    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    try {
      await store.resetPassword({
        token: token.value,
        email: email.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value
      })
      done.value = true
    } catch (err) {
      if (err.response?.status === 422) {
        errors.general = err.response.data?.message ?? t('reset_password.errors.invalid_token')
      } else if (err.response?.status === 429) {
        errors.general = t('forgot_password.errors.throttled')
      } else if (!err.response) {
        errors.general = t('login.errors.cannot_connect')
      } else {
        errors.general = t('login.errors.unexpected_retry')
      }
    } finally {
      loading.value = false
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
  .form-header {
    margin-bottom: 8px;
  }
  .form-title {
    font-size: 30px;
    font-weight: 800;
    color: rgb(var(--v-theme-on-surface));
    letter-spacing: -0.5px;
  }
  .form-sub {
    font-size: 14px;
    color: rgba(var(--v-theme-on-surface), 0.6);
    margin-top: 4px;
  }
  .submit-btn {
    font-weight: 700 !important;
    font-size: 15px !important;
    box-shadow: 0 4px 18px rgba(var(--v-theme-primary), 0.28) !important;
  }
  .fade-in {
    animation: fadeIn 0.7s cubic-bezier(0.16, 1, 0.3, 1);
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>
