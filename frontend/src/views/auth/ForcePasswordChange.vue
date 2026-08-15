<template>
  <v-container fluid class="pa-0 fill-height justify-center align-center">
    <v-card max-width="460" width="100%" class="pa-8" rounded="xl" elevation="4">
      <div class="text-center mb-6">
        <v-avatar color="warning" variant="tonal" size="56" class="mb-3">
          <v-icon icon="mdi-key-alert-outline" size="28" />
        </v-avatar>
        <h2 class="text-h6 font-weight-bold">{{ t('force_password_change.title') }}</h2>
        <p class="text-caption text-medium-emphasis mt-1">
          {{ t('force_password_change.subtitle') }}
        </p>
      </div>

      <v-form ref="formRef" @submit.prevent="submit">
        <v-text-field
          v-model="form.current_password"
          :label="t('profile.current_password')"
          :type="showCurrent ? 'text' : 'password'"
          variant="outlined"
          rounded="lg"
          class="mb-2"
          prepend-inner-icon="mdi-lock-outline"
          :append-inner-icon="showCurrent ? 'mdi-eye-off' : 'mdi-eye'"
          :rules="[v => !!v || t('validation.required')]"
          :error-messages="errors.current_password"
          @click:append-inner="showCurrent = !showCurrent"
          @update:model-value="errors.current_password = ''"
        />
        <v-text-field
          v-model="form.new_password"
          :label="t('profile.new_password')"
          :type="showNew ? 'text' : 'password'"
          variant="outlined"
          rounded="lg"
          class="mb-2"
          prepend-inner-icon="mdi-lock-plus-outline"
          :append-inner-icon="showNew ? 'mdi-eye-off' : 'mdi-eye'"
          :rules="passwordRules"
          @click:append-inner="showNew = !showNew"
        />
        <v-text-field
          v-model="form.new_password_confirmation"
          :label="t('profile.confirm_password')"
          :type="showNew ? 'text' : 'password'"
          variant="outlined"
          rounded="lg"
          prepend-inner-icon="mdi-lock-check-outline"
          :rules="[v => v === form.new_password || t('validation.password_mismatch')]"
        />

        <v-btn
          type="submit"
          color="primary"
          block
          class="mt-4 py-6 text-none"
          rounded="lg"
          elevation="0"
          :loading="loading"
        >
          {{ t('force_password_change.submit') }}
        </v-btn>
      </v-form>
    </v-card>
  </v-container>
</template>

<script setup>
  import { ref, reactive } from 'vue'
  import { useRouter } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { usePasswordPolicy } from '@/composables/usePasswordPolicy'
  import authService from '@/api/auth'

  const { t } = useI18n()
  const router = useRouter()
  const authStore = useAuthStore()
  const { notif } = useAppUtils()
  const { rules: passwordRules } = usePasswordPolicy()

  const formRef = ref(null)
  const loading = ref(false)
  const showCurrent = ref(false)
  const showNew = ref(false)
  const errors = reactive({ current_password: '' })

  const form = reactive({
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
  })

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    try {
      await authService.changePassword(
        form.current_password,
        form.new_password,
        form.new_password_confirmation
      )
      authStore.mustChangePassword = false
      notif(t('force_password_change.success'), { type: 'success' })
      router.push(authStore.isOwner ? { name: 'Dashboard' } : { name: 'AdminDashboard' })
    } catch (err) {
      const status = err.response?.data?.status
      if (status === 'invalid_current_password') {
        errors.current_password = err.response.data.message
      } else {
        notif(err?.response?.data?.message || t('force_password_change.failed'), { type: 'error' })
      }
    } finally {
      loading.value = false
    }
  }
</script>
