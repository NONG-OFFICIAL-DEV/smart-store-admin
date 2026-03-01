<template>
  <v-container fluid class="login-container">
    <v-row no-gutters class="fill-height">
      <!-- LEFT SIDE -->
      <v-col cols="12" md="6" class="left-side">
        <v-img
          src="https://www.kindpng.com/picc/m/567-5674919_inventory-management-inventory-management-system-png-transparent-png.png"
          alt="Login Illustration"
          cover
          class="h-100 w-100 login-image"
        />
      </v-col>

      <!-- RIGHT SIDE -->
      <v-col
        cols="12"
        md="6"
        class="right-side d-flex align-center justify-center pa-8"
      >
        <v-card class="login-card" elevation="0" rounded="xl">
          <div class="text-start mb-6 fade-in">
            <h1 class="title">Welcome Back 👋</h1>
            <p class="subtitle">Please sign in to continue</p>
          </div>

          <v-form @submit.prevent="login" class="fade-in">
            <v-text-field
              v-model="email"
              label="Email"
              variant="outlined"
              rounded="lg"
              prepend-inner-icon="mdi-email-outline"
              class="mb-4"
              density="comfortable"
              :error="!!errors.email"
              :error-messages="errors.email"
              required
            />

            <v-text-field
              v-model="password"
              label="Password"
              variant="outlined"
              rounded="lg"
              :append-inner-icon="visible ? 'mdi-eye-off' : 'mdi-eye'"
              :type="visible ? 'text' : 'password'"
              prepend-inner-icon="mdi-lock-outline"
              density="comfortable"
              :error="!!errors.password"
              :error-messages="errors.password"
              required
              @click:append-inner="visible = !visible"
            />
            <v-alert
              type="error"
              density="compact"
              v-if="errors.general"
              class="text-red mt-2"
            >
              {{ errors.general }}
            </v-alert>

            <v-btn
              type="submit"
              color="primary"
              block
              class="mt-6"
              size="large"
              rounded="lg"
            >
              Login
            </v-btn>

            <!-- <div class="text-center mt-4 fade-in">
              <v-btn
                variant="text"
                size="small"
                @click="onForgotPassword"
              >
                Forgot password?
              </v-btn>
            </div> -->
          </v-form>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, reactive } from 'vue'
  import { useRouter } from 'vue-router'
  import { useAuthStore } from '@/stores/auth'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { useI18n } from 'vue-i18n'
  const { t } = useI18n()
  const { notif } = useAppUtils()
  const email = ref('')
  const password = ref('')
  const store = useAuthStore()
  const router = useRouter()
  const errors = reactive({
    email: '',
    password: '',
    general: ''
  })
  const visible = ref(false)
  const login = async () => {
    // clear previous errors
    errors.email = ''
    errors.password = ''
    errors.general = ''

    try {
      const success = await store.login({
        email: email.value,
        password: password.value
      })

      if (success) {
        if (success.data.user.role_id === 5) {
          router.push('/pos/menu-list')
        } else if (success.data.user.role_id === 4) {
          router.push('/pos/dining-table-view')
        } else {
          router.push('/dashboard')
        }
        notif(t('messages.login_sucess'), {
          type: 'success',
          color: 'primary'
        })
      }
    } catch (err) {
      const res = err.response?.data
      if (res?.status === 'validation_error') {
        errors.email = res.errors.email?.join(', ')
        errors.password = res.errors.password?.join(', ')
      }
      if (res?.status == 'invalid_credentials') {
        // console.log(res.status == 'invalid_credentials')
        errors.general = res.message
      }
    }
  }
</script>

<style scoped>
  .login-container {
    /* background: linear-gradient(135deg, #e3f2fd, #fce4ec); */
    height: 100vh;
  }

  .left-side {
    display: none;
  }

  .login-card {
    max-width: 420px;
    width: 100%;
    padding: 40px;
    backdrop-filter: blur(14px);
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.3);
  }

  .title {
    font-size: 28px;
    font-weight: 700;
  }

  .subtitle {
    font-size: 15px;
    opacity: 0.7;
  }

  .fade-in {
    animation: fadeIn 0.7s ease-in-out;
  }

  /* Smooth fade animation */
  @keyframes fadeIn {
    0% {
      opacity: 0;
      transform: translateY(6px);
    }
    100% {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @media (min-width: 960px) {
    .left-side {
      display: block;
    }
  }
</style>
