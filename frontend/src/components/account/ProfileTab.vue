<template>
  <div class="d-flex flex-column gap-6">
    <!-- Profile info -->
    <v-card variant="outlined" rounded="lg">
      <v-card-text class="pa-5">
        <div class="d-flex align-center gap-2 mb-4">
          <v-icon size="18" color="primary">mdi-account-outline</v-icon>
          <span class="text-title-small font-weight-bold">{{ t('account.profile.info_title') }}</span>
        </div>

        <AvatarUpload
          :current-url="user?.avatar_url"
          :initials="initials"
          :avatar-color="avatarColor"
          class="mb-5"
          @update:file="onAvatarChange"
        />

        <v-form ref="formRef">
          <v-row>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.first_name"
                :label="t('account.profile.first_name')"
                variant="outlined"
                rounded="lg"
                :rules="[v => !!v || t('validation.required')]"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.last_name"
                :label="t('account.profile.last_name')"
                variant="outlined"
                rounded="lg"
                :rules="[v => !!v || t('validation.required')]"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.phone"
                type="tel"
                :label="t('account.profile.phone')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-phone-outline"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                :model-value="user?.email"
                :label="t('account.profile.email')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-email-outline"
                readonly
                :hint="t('account.profile.email_readonly_note')"
                persistent-hint
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-expand-transition>
        <div v-if="isDirty" class="save-bar px-5 py-3 d-flex justify-end gap-2">
          <v-btn variant="text" rounded="lg" :disabled="saving" @click="discard">{{ t('btn.cancel') }}</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">
            {{ t('btn.save') }}
          </v-btn>
        </div>
      </v-expand-transition>
    </v-card>

    <!-- Change password -->
    <v-card variant="outlined" rounded="lg" class="mt-4">
      <v-card-text class="pa-5">
        <div class="d-flex align-center gap-2 mb-4">
          <v-icon size="18" color="primary">mdi-lock-outline</v-icon>
          <span class="text-title-small font-weight-bold">{{ t('profile.change_password') }}</span>
        </div>

        <v-form ref="passwordFormRef">
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="passwordForm.current_password"
                :label="t('profile.current_password')"
                :type="showCurrent ? 'text' : 'password'"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showCurrent ? 'mdi-eye-off' : 'mdi-eye'"
                :rules="[v => !!v || t('validation.required')]"
                @click:append-inner="showCurrent = !showCurrent"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="passwordForm.new_password"
                :label="t('profile.new_password')"
                :type="showNew ? 'text' : 'password'"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-lock-plus-outline"
                :append-inner-icon="showNew ? 'mdi-eye-off' : 'mdi-eye'"
                :rules="passwordRules"
                :hint="t('account.profile.password_strength_hint')"
                persistent-hint
                @click:append-inner="showNew = !showNew"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="passwordForm.new_password_confirmation"
                :label="t('profile.confirm_password')"
                :type="showNew ? 'text' : 'password'"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-lock-check-outline"
                :rules="[v => v === passwordForm.new_password || t('validation.password_mismatch')]"
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
            {{ t('profile.change_password') }}
          </v-btn>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
  import { ref, reactive, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useAuthStore } from '@/stores/authStore'
  import { useAvatar } from '@/composables/useAvatar'
  import AvatarUpload from './AvatarUpload.vue'
  import authService from '@/api/auth'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const authStore = useAuthStore()
  const { getInitials, getAvatarColor } = useAvatar()

  const user = computed(() => authStore.me)
  const initials = computed(() => getInitials(user.value, '?'))
  const avatarColor = computed(() => getAvatarColor(user.value))

  // ── Profile info ─────────────────────────────────────────────────────────
  const formRef = ref(null)
  const saving = ref(false)
  // undefined = untouched, File = new photo picked, null = explicitly removed.
  const avatarFile = ref(undefined)

  function snapshot() {
    return {
      first_name: user.value?.first_name || '',
      last_name: user.value?.last_name || '',
      phone: user.value?.phone || ''
    }
  }
  const form = reactive(snapshot())
  const original = ref(snapshot())

  const isDirty = computed(
    () => avatarFile.value !== undefined || JSON.stringify(form) !== JSON.stringify(original.value)
  )

  function onAvatarChange(file) {
    avatarFile.value = file
  }

  function discard() {
    Object.assign(form, original.value)
    avatarFile.value = undefined
  }

  async function save() {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    saving.value = true
    try {
      const formData = new FormData()
      formData.append('first_name', form.first_name)
      formData.append('last_name', form.last_name)
      if (form.phone) formData.append('phone', form.phone)
      if (avatarFile.value) formData.append('avatar', avatarFile.value)

      const { data } = await authService.updateProfile(formData)
      authStore.me = { ...authStore.me, ...data.data.user }

      original.value = snapshot()
      avatarFile.value = undefined
      notif(t('account.profile.saved'), { type: 'success' })
    } catch (err) {
      notif(err?.response?.data?.message || t('account.profile.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  // ── Change password ──────────────────────────────────────────────────────
  const passwordFormRef = ref(null)
  const showCurrent = ref(false)
  const showNew = ref(false)
  const changingPassword = ref(false)
  const passwordForm = reactive({
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
  })
  const passwordRules = [
    v => !!v || t('validation.required'),
    v => (v && v.length >= 8) || t('validation.password_min_length')
  ]

  async function submitChangePassword() {
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
</script>

<style scoped>
  .save-bar {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgba(var(--v-theme-on-surface), 0.02);
  }
</style>
