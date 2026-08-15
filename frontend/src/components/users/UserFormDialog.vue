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
          <v-row dense>
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
            <v-col col="7">
              <v-text-field
                v-model="form.avatar_url"
                :label="$t('users.dialog.avatar_url')"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-link"
                :hint="$t('users.dialog.avatar_url_hint')"
                persistent-hint
                clearable
              />
            </v-col>
          </v-row>
          <v-row>
            <!-- First Name -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.first_name"
                :label="$t('form.first_name')"
                variant="outlined"
                density="comfortable"
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
                density="comfortable"
                prepend-inner-icon="mdi-account"
                :rules="[rules.required, rules.maxLen(80)]"
                :hint="$t('users.dialog.last_name_hint')"
                persistent-hint
              />
            </v-col>

            <!-- Email -->
            <v-col cols="12">
              <v-text-field
                v-model="form.email"
                :label="$t('form.email')"
                variant="outlined"
                density="comfortable"
                type="email"
                prepend-inner-icon="mdi-email-outline"
                :rules="[rules.required, rules.email]"
                :hint="$t('users.dialog.email_hint')"
                persistent-hint
              />
              <!-- :disabled="isEdit" -->
            </v-col>

            <!-- Phone -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.phone"
                :label="$t('form.phone')"
                variant="outlined"
                density="comfortable"
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
                density="comfortable"
                prepend-inner-icon="mdi-translate"
                :hint="$t('users.dialog.preferred_language_hint')"
                persistent-hint
                clearable
              />
            </v-col>

            <!-- Password (create only — editing a user never touches the password) -->
            <template v-if="!isEdit">
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.password"
                  :label="$t('form.password')"
                  variant="outlined"
                  density="comfortable"
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
            </template>

            <!-- Active toggle -->
            <v-col cols="12">
              <div class="d-flex gap-6 flex-wrap mt-1">
                <v-switch
                  v-model="form.is_active"
                  color="success"
                  density="compact"
                  hide-details
                  inset
                  :label="$t('users.dialog.account_active')"
                />
              </div>
            </v-col>
          </v-row>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'
  import { useAvatar } from '@/composables/useAvatar'
  import { usePasswordPolicy } from '@/composables/usePasswordPolicy'

  const { t } = useI18n()
  const { getAvatarColor } = useAvatar()
  const { generate, rules: passwordRules } = usePasswordPolicy()

  // ── Props & Emits ─────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editItem: { type: Object, default: null },
    loading: { type: Boolean, default: false }
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

    emit('saved', { ...form.value })
  }

  const close = () => {
    formRef.value?.reset()
    form.value = defaultForm()
    showPassword.value = false
  }
</script>
