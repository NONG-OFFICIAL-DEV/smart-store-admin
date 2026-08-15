<template>
  <AuthLayout>
    <template #left-content>
      <div class="eyebrow mb-4">{{ t('forgot_password.tagline') }}</div>
      <h2 class="left-title mb-5">{{ t('forgot_password.title') }}</h2>
      <p class="left-sub mb-10">{{ t('forgot_password.description') }}</p>
    </template>

    <div class="text-start mb-8 fade-in">
      <div class="form-header">
        <div class="form-title">{{ t('forgot_password.heading') }}</div>
        <div class="form-sub">{{ t('forgot_password.sub') }}</div>
      </div>
    </div>

    <template v-if="sent">
      <v-alert type="success" variant="tonal" density="comfortable" class="mb-6 rounded-lg" border="start">
        <span class="text-body-2">{{ t('forgot_password.sent_message') }}</span>
      </v-alert>
      <router-link :to="{ name: 'Login' }" class="text-primary font-weight-medium text-body-2">
        {{ t('forgot_password.back_to_login') }}
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

      <label class="text-caption ml-1">{{ t('login.email') }}</label>
      <v-text-field
        v-model="email"
        :placeholder="t('login.emailPlaceholder')"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-email-outline"
        class="mt-1"
        :rules="emailRules"
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
        {{ t('forgot_password.send_link') }}
      </v-btn>

      <div class="text-center mt-6 text-caption">
        <router-link :to="{ name: 'Login' }" class="text-primary font-weight-medium">
          {{ t('forgot_password.back_to_login') }}
        </router-link>
      </div>
    </v-form>
  </AuthLayout>
</template>

<script setup>
  import { ref, reactive } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import AuthLayout from '@/components/layout/AuthLayout.vue'

  const { t } = useI18n()
  const store = useAuthStore()

  const formRef = ref(null)
  const email = ref('')
  const loading = ref(false)
  const sent = ref(false)
  const errors = reactive({ general: '' })

  const emailRules = [
    v => !!v || t('login.rules.email_required'),
    v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || t('login.rules.email_invalid')
  ]

  const handleSubmit = async () => {
    errors.general = ''
    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    try {
      await store.forgotPassword(email.value)
      // Always shown regardless of whether the email exists — matches the
      // backend's own generic, non-leaking response.
      sent.value = true
    } catch (err) {
      if (err.response?.status === 429) {
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
