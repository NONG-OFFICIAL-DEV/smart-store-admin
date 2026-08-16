<template>
  <AppDialog
    v-model="model"
    :max-width="560"
    :title="isEdit ? $t('users.dialog.edit_title') : $t('users.dialog.add_title')"
    :subtitle="
      isEdit
        ? $t('users.dialog.edit_subtitle')
        : $t('users.dialog.add_subtitle')
    "
    icon="mdi-account-outline"
    :color="isEdit ? 'primary' : 'success'"
    :loading="loading"
    body-class="pa-6"
    :submit-text="isEdit ? $t('btn.save_changes') : $t('users.dialog.create_title')"
    @close="close"
    @submit="handleSubmit"
  >
        <v-form ref="formRef">
          <!-- ── Basic Information ─────────────────────────────────────── -->
          <div class="form-section-label mb-3">
            <v-icon icon="mdi-account-outline" size="13" class="mr-1" />
            {{ $t('users.dialog.section_basic') }}
          </div>
          <v-row dense class="mb-1">
            <!-- Avatar preview -->
            <v-col cols="3">
              <v-avatar
                size="70"
                rounded="xl"
                :color="avatarColor(form.first_name)"
              >
                <v-img v-if="form.avatar_url" :src="form.avatar_url" cover />
                <span v-else class="text-h6 font-weight-bold text-white">
                  {{ form.first_name ? form.first_name[0].toUpperCase() : '?' }}
                </span>
              </v-avatar>
            </v-col>
            <v-col cols="9">
              <v-text-field
                v-model="form.avatar_url"
                :label="$t('users.dialog.avatar_url')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-link"
                :hint="$t('users.dialog.avatar_url_hint')"
                persistent-hint
                clearable
              />
            </v-col>
          </v-row>
          <v-row dense>
            <!-- First Name -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.first_name"
                :label="$t('form.first_name')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-account"
                :rules="[rules.required, rules.maxLen(80)]"
                :hint="$t('users.dialog.first_name_hint')"
                persistent-hint
              />
            </v-col>

            <!-- Last Name -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.last_name"
                :label="$t('form.last_name')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-account"
                :rules="[rules.required, rules.maxLen(80)]"
                :hint="$t('users.dialog.last_name_hint')"
                persistent-hint
              />
            </v-col>
          </v-row>

          <v-divider class="my-5" />

          <!-- ── Contact & Preferences ─────────────────────────────────── -->
          <div class="form-section-label mb-3">
            <v-icon icon="mdi-card-account-details-outline" size="13" class="mr-1" />
            {{ $t('users.dialog.section_contact') }}
          </div>
          <v-row dense>
            <!-- Email -->
            <v-col cols="12">
              <p v-if="isEdit" class="text-caption text-medium-emphasis mb-1">
                {{ $t('users.dialog.current_email_label') }}:
                <span class="font-weight-medium">{{ originalEmail }}</span>
              </p>
              <v-text-field
                v-model="form.email"
                :label="$t('form.email')"
                variant="outlined"
                rounded="lg"
                type="email"
                prepend-inner-icon="mdi-email-outline"
                :rules="[rules.required, rules.email]"
                :error-messages="serverErrors.email"
                :hint="$t('users.dialog.email_hint')"
                persistent-hint
                @update:model-value="serverErrors.email = null"
              />
              <v-alert
                v-if="emailChanged"
                type="warning"
                variant="tonal"
                density="comfortable"
                rounded="lg"
                class="mt-3"
                icon="mdi-alert-outline"
              >
                <span class="text-body-2">
                  {{ $t('users.dialog.email_change_warning', { name: fullName }) }}
                </span>
              </v-alert>
            </v-col>

            <!-- Phone -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.phone"
                :label="$t('form.phone')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-phone"
                :rules="[rules.maxLen(30)]"
                :hint="$t('users.dialog.phone_hint')"
                persistent-hint
                clearable
              />
            </v-col>

            <!-- Language -->
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.preferred_language"
                :items="languages"
                item-title="label"
                item-value="value"
                :label="$t('users.dialog.preferred_language')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-translate"
                :hint="$t('users.dialog.preferred_language_hint')"
                persistent-hint
                clearable
              />
            </v-col>
          </v-row>

          <!-- ── Security (create only — editing a user never touches the password) ── -->
          <template v-if="!isEdit">
            <v-divider class="my-5" />
            <div class="form-section-label mb-3">
              <v-icon icon="mdi-lock-outline" size="13" class="mr-1" />
              {{ $t('users.dialog.section_security') }}
            </div>
            <v-row dense>
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.password"
                  :label="$t('form.password')"
                  variant="outlined"
                  rounded="lg"
                  :type="showPassword ? 'text' : 'password'"
                  prepend-inner-icon="mdi-lock-outline"
                  :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                  :rules="passwordRules"
                  :hint="$t('users.dialog.password_hint')"
                  persistent-hint
                  @click:append-inner="showPassword = !showPassword"
                />
              </v-col>
              <v-col cols="12" sm="4" class="d-flex align-center pb-5">
                <v-btn
                  variant="tonal"
                  rounded="lg"
                  size="small"
                  prepend-icon="mdi-refresh"
                  @click="generatePassword"
                >
                  {{ $t('users.dialog.generate_password') }}
                </v-btn>
              </v-col>
            </v-row>
          </template>

          <v-divider class="my-5" />

          <!-- Active toggle -->
          <div class="d-flex gap-6 flex-wrap">
            <v-switch
              v-model="form.is_active"
              color="success"
              density="compact"
              hide-details
              inset
              :label="$t('users.dialog.account_active')"
            />
          </div>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'
  import { useAvatar } from '@/composables/useAvatar'
  import { usePasswordPolicy } from '@/composables/usePasswordPolicy'
  import { useAppUtils } from '@nong-official-dev/core'

  const { t } = useI18n()
  const { getAvatarColor } = useAvatar()
  const { generate, rules: passwordRules } = usePasswordPolicy()
  const { confirm } = useAppUtils()

  // ── Props & Emits ─────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editItem: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    serverErrorsProp: { type: Object, default: () => ({}) }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  // ── Model ──────────────────────────────────────────────────────────────────
  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  // ── Refs ──────────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const showPassword = ref(false)
  const serverErrors = reactive({})

  watch(
    () => props.serverErrorsProp,
    errs => {
      Object.keys(serverErrors).forEach(k => delete serverErrors[k])
      Object.assign(serverErrors, errs ?? {})
    }
  )

  // ── Constants ─────────────────────────────────────────────────────────────────
  const languages = [
    { value: 'en-US', label: 'English (US)' },
    { value: 'en-GB', label: 'English (UK)' },
    { value: 'km-KH', label: 'Khmer (KH)' },
    { value: 'zh-CN', label: 'Chinese (CN)' }
  ]

  // ── Form ──────────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    id: null,
    first_name: '',
    last_name: '',
    email: '',
    phone: null,
    avatar_url: null,
    preferred_language: 'km-KH',
    password: '',
    is_active: true
  })

  const form = ref(defaultForm())
  const isEdit = computed(() => !!props.editItem)
  const originalEmail = ref('')
  const emailChanged = computed(() => isEdit.value && form.value.email !== originalEmail.value)
  const fullName = computed(() => `${form.value.first_name} ${form.value.last_name}`.trim())

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || t('validation.required'),
    email: v => /.+@.+\..+/.test(v) || t('validation.email'),
    maxLen: n => v => !v || v.length <= n || t('validation.max_length', { n }),
    minLen: n => v => !v || v.length >= n || t('validation.min_length', { n })
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const colors = [
    'primary',
    'success',
    'warning',
    'error',
    'secondary',
    'teal',
    'purple',
    'orange'
  ]
  const avatarColor = name => getAvatarColor(name, { palette: colors })

  // ── Watch editItem ────────────────────────────────────────────────────────────
  watch(
    () => props.editItem,
    item => {
      showPassword.value = false
      Object.keys(serverErrors).forEach(k => delete serverErrors[k])
      originalEmail.value = item?.email ?? ''
      form.value = item
        ? {
            id: item.id,
            first_name: item.first_name,
            last_name: item.last_name,
            email: item.email,
            phone: item.phone ?? null,
            avatar_url: item.avatar_url ?? null,
            preferred_language: item.preferred_language ?? 'en',
            is_active: item.is_active
          }
        : defaultForm()
    },
    { immediate: true }
  )

  // ── Actions ───────────────────────────────────────────────────────────────────
  const generatePassword = () => {
    form.value.password = generate()
    showPassword.value = true
  }

  const handleSubmit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    if (emailChanged.value) {
      confirm({
        title: t('users.dialog.confirm_email_change_title'),
        message: t('users.dialog.confirm_email_change_message', {
          name: fullName.value,
          oldEmail: originalEmail.value,
          newEmail: form.value.email
        }),
        options: { type: 'warning', width: 520 },
        agree: () => emit('saved', { ...form.value }),
        cancel: () => {}
      })
      return
    }

    emit('saved', { ...form.value })
  }

  const close = () => {
    formRef.value?.reset()
    form.value = defaultForm()
    showPassword.value = false
    Object.keys(serverErrors).forEach(k => delete serverErrors[k])
  }
</script>

<style scoped>
  .form-section-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgb(var(--v-theme-primary));
    display: flex;
    align-items: center;
  }
</style>
