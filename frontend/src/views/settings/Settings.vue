<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-shield-lock-outline"
      :title="$t('settings.title')"
      :subtitle="$t('settings.subtitle')"
      class="custom-title-modern"
    />

    <v-row>
      <v-col cols="12" lg="9" xl="8">
        <!-- Login & Security — self-service change password + email, side by
             side (available to every authenticated user) -->
        <v-card class="pa-6" rounded="lg" elevation="0" border>
          <div class="mb-5">
            <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis">
              {{ $t('profile.security_title') }}
            </h3>
            <p class="text-caption text-medium-emphasis mb-0">
              {{ $t('profile.security_subtitle') }}
            </p>
          </div>

          <v-row class="ga-y-6">
            <v-col cols="12" md="6">
              <div class="d-flex align-center ga-2 mb-4">
                <v-icon size="18" color="primary">mdi-lock-outline</v-icon>
                <span class="text-body-2 font-weight-bold text-high-emphasis">
                  {{ $t('profile.change_password') }}
                </span>
              </div>

              <v-form ref="passwordFormRef">
                <v-row dense>
                  <v-col cols="12">
                    <v-text-field
                      v-model="passwordForm.current_password"
                      :label="$t('profile.current_password')"
                      :type="showCurrent ? 'text' : 'password'"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-lock-outline"
                      :append-inner-icon="showCurrent ? 'mdi-eye-off' : 'mdi-eye'"
                      :rules="[v => !!v || $t('validation.required')]"
                      @click:append-inner="showCurrent = !showCurrent"
                    />
                  </v-col>
                  <v-col cols="12">
                    <v-text-field
                      v-model="passwordForm.new_password"
                      :label="$t('profile.new_password')"
                      :type="showNew ? 'text' : 'password'"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-lock-plus-outline"
                      :append-inner-icon="showNew ? 'mdi-eye-off' : 'mdi-eye'"
                      :rules="passwordRules"
                      @click:append-inner="showNew = !showNew"
                    />
                  </v-col>
                  <v-col cols="12">
                    <v-text-field
                      v-model="passwordForm.new_password_confirmation"
                      :label="$t('profile.confirm_password')"
                      :type="showNew ? 'text' : 'password'"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-lock-check-outline"
                      :rules="[v => v === passwordForm.new_password || $t('validation.password_mismatch')]"
                    />
                  </v-col>
                </v-row>
              </v-form>

              <div class="d-flex justify-end mt-2">
                <v-btn
                  color="primary"
                  variant="flat"
                  rounded="lg"
                  :loading="changingPassword"
                  prepend-icon="mdi-content-save-outline"
                  @click="submitChangePassword"
                >
                  {{ $t('profile.change_password') }}
                </v-btn>
              </div>
            </v-col>

            <v-divider vertical class="d-none d-md-block" />
            <v-divider class="d-md-none" />

            <v-col cols="12" md="6">
              <div class="d-flex align-center ga-2 mb-4">
                <v-icon size="18" color="primary">mdi-email-outline</v-icon>
                <span class="text-body-2 font-weight-bold text-high-emphasis">
                  {{ $t('profile.change_email') }}
                </span>
              </div>

              <v-form ref="emailFormRef">
                <v-row dense>
                  <v-col cols="12">
                    <v-text-field
                      v-model="emailForm.email"
                      :label="$t('profile.new_email')"
                      type="email"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-email-outline"
                      :rules="[v => !!v || $t('validation.required'), v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || $t('login.rules.email_invalid')]"
                    />
                  </v-col>
                  <v-col cols="12">
                    <v-text-field
                      v-model="emailForm.current_password"
                      :label="$t('profile.current_password')"
                      :type="showEmailPassword ? 'text' : 'password'"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-lock-outline"
                      :append-inner-icon="showEmailPassword ? 'mdi-eye-off' : 'mdi-eye'"
                      :rules="[v => !!v || $t('validation.required')]"
                      @click:append-inner="showEmailPassword = !showEmailPassword"
                    />
                  </v-col>
                </v-row>
              </v-form>

              <div class="d-flex justify-end mt-2">
                <v-btn
                  color="primary"
                  variant="flat"
                  rounded="lg"
                  :loading="changingEmail"
                  prepend-icon="mdi-content-save-outline"
                  @click="submitChangeEmail"
                >
                  {{ $t('profile.change_email') }}
                </v-btn>
              </div>
            </v-col>
          </v-row>
        </v-card>

        <!-- Two-factor auth — "administrators" here means super admins
             and tenant owners, matching the backend's own authorization. -->
        <v-row v-if="authStore.isSuperAdmin || authStore.isOwner" class="mt-2">
          <v-col cols="12" md="12">
            <TwoFactorAuthSection />
          </v-col>
        </v-row>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { ref, reactive } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { usePasswordPolicy } from '@/composables/usePasswordPolicy'
  import { useAppUtils } from '@/composables/useAppUtils'
  import authService from '@/api/auth'
  import TwoFactorAuthSection from '@/components/common/TwoFactorAuthSection.vue'

  const { t } = useI18n()
  const authStore = useAuthStore()
  const { rules: passwordRules } = usePasswordPolicy()
  const { notif } = useAppUtils()

  // ── Self-service change password ─────────────────────────────────────────────
  const passwordFormRef = ref(null)
  const showCurrent = ref(false)
  const showNew = ref(false)
  const changingPassword = ref(false)
  const passwordForm = reactive({
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
  })

  const submitChangePassword = async () => {
    const { valid } = await passwordFormRef.value.validate()
    if (!valid) return

    changingPassword.value = true
    try {
      await authService.changePassword(
        passwordForm.current_password,
        passwordForm.new_password,
        passwordForm.new_password_confirmation
      )
      notif(t('profile.password_changed'), { type: 'success' })
      passwordForm.current_password = ''
      passwordForm.new_password = ''
      passwordForm.new_password_confirmation = ''
      passwordFormRef.value.resetValidation()
    } catch (err) {
      notif(err?.response?.data?.message || t('profile.password_change_failed'), { type: 'error' })
    } finally {
      changingPassword.value = false
    }
  }

  // ── Self-service change email ────────────────────────────────────────────────
  const emailFormRef = ref(null)
  const showEmailPassword = ref(false)
  const changingEmail = ref(false)
  const emailForm = reactive({
    email: '',
    current_password: ''
  })

  const submitChangeEmail = async () => {
    const { valid } = await emailFormRef.value.validate()
    if (!valid) return

    changingEmail.value = true
    try {
      await authService.updateEmail(emailForm.email, emailForm.current_password)
      notif(t('profile.email_changed'), { type: 'success' })
      emailForm.email = ''
      emailForm.current_password = ''
      emailFormRef.value.resetValidation()
      await authStore.fetchMe()
    } catch (err) {
      notif(err?.response?.data?.message || t('profile.email_change_failed'), { type: 'error' })
    } finally {
      changingEmail.value = false
    }
  }
</script>
