<template>
  <AuthLayout>
    <template #left-content>
      <div class="eyebrow mb-4">{{ t('register.tagline') }}</div>
      <h2 class="left-title mb-5">
        {{ t('register.title1') }}
        <br />
        {{ t('register.title2') }}
      </h2>
      <p class="left-sub mb-10">
        {{ t('register.description') }}
      </p>
    </template>

    <div class="text-start mb-8 fade-in">
      <div class="form-header">
        <div class="form-title">{{ t('register.signUp') }}</div>
        <div class="form-sub">{{ t('register.sub') }}</div>
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

    <v-form ref="formRef" class="fade-in" @submit.prevent="handleRegister">
      <v-row dense>
        <v-col cols="6">
          <label class="text-caption ml-1">{{ t('register.owner_first_name') }}</label>
          <v-text-field
            v-model="form.owner_first_name"
            variant="outlined"
            rounded="lg"
            class="mt-1"
            :rules="[required]"
            :error-messages="errors.owner_first_name"
            :disabled="loading"
          />
        </v-col>
        <v-col cols="6">
          <label class="text-caption ml-1">{{ t('register.owner_last_name') }}</label>
          <v-text-field
            v-model="form.owner_last_name"
            variant="outlined"
            rounded="lg"
            class="mt-1"
            :rules="[required]"
            :error-messages="errors.owner_last_name"
            :disabled="loading"
          />
        </v-col>
      </v-row>

      <label class="text-caption ml-1">{{ t('register.business_name') }}</label>
      <v-text-field
        v-model="form.name"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-storefront-outline"
        class="mt-1"
        :rules="[required]"
        :error-messages="errors.name"
        :disabled="loading"
      />

      <label class="text-caption ml-1">{{ t('register.business_type') }}</label>
      <v-select
        v-model="form.business_type_id"
        :items="businessTypes"
        item-title="name"
        item-value="id"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-shape-outline"
        class="mt-1"
        :rules="[required]"
        :error-messages="errors.business_type_id"
        :disabled="loading"
      />

      <label class="text-caption ml-1">{{ t('register.owner_email') }}</label>
      <v-text-field
        v-model="form.owner_email"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-email-outline"
        class="mt-1"
        :rules="emailRules"
        :error-messages="errors.owner_email"
        :disabled="loading"
      />

      <label class="text-caption ml-1">{{ t('register.owner_password') }}</label>
      <v-text-field
        v-model="form.owner_password"
        :append-inner-icon="visible ? 'mdi-eye-off' : 'mdi-eye'"
        :type="visible ? 'text' : 'password'"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-lock-outline"
        class="mt-1"
        :rules="passwordRules"
        :error-messages="errors.owner_password"
        :disabled="loading"
        @click:append-inner="visible = !visible"
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
        {{ t('register.create_account') }}
      </v-btn>

      <div class="text-center mt-6 text-caption">
        {{ t('register.have_account') }}
        <router-link :to="{ name: 'Login' }" class="text-primary font-weight-medium">
          {{ t('register.login_link') }}
        </router-link>
      </div>
    </v-form>
  </AuthLayout>
</template>

<script setup>
  import { ref, reactive, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { getPublicBusinessTypesApi } from '@/api/businessTypeService'
  import AuthLayout from '@/components/layout/AuthLayout.vue'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const router = useRouter()
  const store = useAuthStore()

  const formRef = ref(null)
  const loading = ref(false)
  const visible = ref(false)
  const businessTypes = ref([])

  const form = reactive({
    owner_first_name: '',
    owner_last_name: '',
    owner_email: '',
    owner_password: '',
    name: '',
    business_type_id: null
  })

  const errors = reactive({
    owner_first_name: '', owner_last_name: '', owner_email: '',
    owner_password: '', name: '', business_type_id: '', general: ''
  })

  const required = v => !!v || t('form.required')
  const emailRules = [
    required,
    v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || t('login.rules.email_invalid')
  ]
  const passwordRules = [
    required,
    v => v.length >= 8 || t('validation.min_length', { n: 8 })
  ]

  onMounted(async () => {
    try {
      const { data } = await getPublicBusinessTypesApi()
      businessTypes.value = data.data
    } catch {
      // Non-fatal — the select just renders empty, user can still retry.
    }
  })

  function clearErrors() {
    Object.keys(errors).forEach(k => { errors[k] = '' })
  }

  const handleRegister = async () => {
    clearErrors()
    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    try {
      const response = await store.register({ ...form })
      if (response?.data?.success) {
        notif(t('messages.created_success'), { type: 'success' })
        router.push('/dashboard')
      }
    } catch (err) {
      const res = err.response?.data
      if (res?.errors) {
        Object.entries(res.errors).forEach(([field, messages]) => {
          if (field in errors) errors[field] = messages[0]
        })
      } else if (err.response?.status === 429) {
        errors.general = t('login.errors.too_many_attempts_retry')
      } else if (!err.response) {
        errors.general = t('login.errors.cannot_connect')
      } else {
        errors.general = res?.message ?? t('login.errors.unexpected_retry')
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
