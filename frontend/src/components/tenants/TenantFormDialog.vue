<template>
  <v-dialog v-model="model" max-width="620" persistent scrollable>
    <v-card rounded="xl" elevation="0" border>
      <!-- ── Header ──────────────────────────────────────────────────────────── -->
      <v-card-title class="pa-6 pb-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar
              :color="isEdit ? 'primary' : 'success'"
              size="44"
              rounded="lg"
            >
              <v-icon
                :icon="
                  isEdit
                    ? 'mdi-office-building-cog'
                    : 'mdi-office-building-plus'
                "
                size="22"
              />
            </v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">
                {{ isEdit ? 'Edit Tenant' : 'Create New Tenant' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  isEdit
                    ? 'Update business info and settings'
                    : 'Creates owner account + business in one step'
                }}
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-0">
        <v-form ref="formRef">
          <!-- ══ OWNER ACCOUNT (create only) ═══════════════════════════════════ -->
          <template v-if="!isEdit">
            <div class="section-block">
              <div class="section-label">
                <v-icon
                  icon="mdi-account-circle-outline"
                  size="16"
                  class="mr-2"
                />
                Owner Account
              </div>

              <v-row dense>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.owner_first_name"
                    label="First Name"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    :rules="[r.required]"
                    prepend-inner-icon="mdi-account-outline"
                    maxlength="80"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.owner_last_name"
                    label="Last Name"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    :rules="[r.required]"
                    maxlength="80"
                  />
                </v-col>
                <v-col cols="12" sm="7">
                  <v-text-field
                    v-model="form.owner_email"
                    label="Email"
                    type="email"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    :rules="[r.required, r.email]"
                    prepend-inner-icon="mdi-email-outline"
                    maxlength="255"
                  />
                </v-col>
                <v-col cols="12" sm="5">
                  <v-text-field
                    v-model="form.owner_phone"
                    label="Phone"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    prepend-inner-icon="mdi-phone-outline"
                    maxlength="30"
                  />
                </v-col>
                <v-col cols="12" sm="8">
                  <v-text-field
                    v-model="form.owner_password"
                    label="Password"
                    :type="showPassword ? 'text' : 'password'"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    :rules="[r.required, r.minPassword]"
                    prepend-inner-icon="mdi-lock-outline"
                    :append-inner-icon="
                      showPassword ? 'mdi-eye-off' : 'mdi-eye'
                    "
                    @click:append-inner="showPassword = !showPassword"
                  />
                </v-col>
                <v-col cols="12" sm="4" class="d-flex align-center pb-5">
                  <v-btn
                    block
                    variant="tonal"
                    rounded="lg"
                    size="small"
                    prepend-icon="mdi-refresh"
                    @click="generatePassword"
                  >
                    Generate
                  </v-btn>
                </v-col>
              </v-row>

              <!-- Credential preview — shown after generate -->
              <v-alert
                v-if="credentialGenerated"
                type="warning"
                variant="tonal"
                rounded="lg"
                density="compact"
                class="mt-1"
                icon="mdi-key-alert-outline"
              >
                Share these credentials with the owner. Password won't be
                visible again after saving.
              </v-alert>
            </div>

            <v-divider />
          </template>

          <!-- ══ EDIT MODE: show owner info readonly ════════════════════════════ -->
          <template v-else>
            <div class="section-block pb-3">
              <div class="section-label mb-3">
                <v-icon
                  icon="mdi-account-circle-outline"
                  size="16"
                  class="mr-2"
                />
                Owner
              </div>
              <v-card
                rounded="lg"
                border
                elevation="0"
                color="grey-lighten-5"
                class="pa-3"
              >
                <div class="d-flex align-center gap-3">
                  <v-avatar
                    color="primary"
                    variant="tonal"
                    size="40"
                    rounded="lg"
                  >
                    <span class="text-body-2 font-weight-bold">
                      {{ ownerInitials }}
                    </span>
                  </v-avatar>
                  <div class="flex-grow-1">
                    <div class="text-body-2 font-weight-bold">
                      {{ props.item?.owner?.first_name }}
                      {{ props.item?.owner?.last_name }}
                    </div>
                    <div class="text-caption text-grey">
                      {{ props.item?.owner?.email }}
                    </div>
                  </div>
                  <v-btn
                    size="x-small"
                    variant="tonal"
                    rounded="lg"
                    prepend-icon="mdi-swap-horizontal"
                    color="warning"
                    @click="transferDialog = true"
                  >
                    Transfer
                  </v-btn>
                </div>
              </v-card>
            </div>
            <v-divider />
          </template>

          <!-- ══ BUSINESS INFO ══════════════════════════════════════════════════ -->
          <div class="section-block">
            <div class="section-label">
              <v-icon icon="mdi-store-outline" size="16" class="mr-2" />
              Business Info
            </div>

            <v-row dense>
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.name"
                  label="Business Name"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  :rules="[r.required]"
                  prepend-inner-icon="mdi-domain"
                  maxlength="150"
                  @update:model-value="onNameChange"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.plan"
                  :items="planOptions"
                  item-title="label"
                  item-value="value"
                  label="Plan"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-crown-outline"
                >
                  <template #item="{ props: itemProps, item }">
                    <v-list-item v-bind="itemProps">
                      <template #prepend>
                        <v-icon
                          :icon="item.raw.icon"
                          :color="item.raw.color"
                          size="18"
                          class="mr-2"
                        />
                      </template>
                    </v-list-item>
                  </template>
                </v-select>
              </v-col>

              <!-- Slug — auto-generated, lockable -->
              <v-col cols="12">
                <v-text-field
                  v-model="form.slug"
                  label="Slug"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-link-variant"
                  :readonly="!editingSlug"
                  :bg-color="!editingSlug ? 'grey-lighten-4' : undefined"
                  hint="Auto-generated from business name"
                  persistent-hint
                  maxlength="100"
                >
                  <template #append-inner>
                    <v-tooltip
                      :text="editingSlug ? 'Lock slug' : 'Edit manually'"
                    >
                      <template #activator="{ props: ttProps }">
                        <v-icon
                          v-bind="ttProps"
                          :icon="
                            editingSlug
                              ? 'mdi-lock-open-outline'
                              : 'mdi-lock-outline'
                          "
                          size="18"
                          class="cursor-pointer text-medium-emphasis"
                          @click="editingSlug = !editingSlug"
                        />
                      </template>
                    </v-tooltip>
                  </template>
                </v-text-field>
              </v-col>

              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="form.logo_url"
                  label="Logo URL"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-image-outline"
                />
              </v-col>

              <!-- Color picker -->
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="form.primary_color"
                  label="Brand Color"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-palette-outline"
                  maxlength="7"
                  placeholder="#FF5722"
                >
                  <template #append-inner>
                    <input
                      type="color"
                      :value="form.primary_color || '#6366f1'"
                      class="color-swatch"
                      @input="form.primary_color = $event.target.value"
                    />
                  </template>
                </v-text-field>
              </v-col>

              <!-- Active toggle (edit only) -->
              <v-col v-if="isEdit" cols="12" sm="4" class="d-flex align-center">
                <v-switch
                  v-model="form.is_active"
                  label="Active"
                  color="success"
                  inset
                  hide-details
                />
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- ══ LOCALIZATION ═══════════════════════════════════════════════════ -->
          <div class="section-block">
            <div class="section-label">
              <v-icon icon="mdi-earth" size="16" class="mr-2" />
              Localization
            </div>

            <v-row dense>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.currency"
                  :items="currencyOptions"
                  item-title="label"
                  item-value="value"
                  label="Currency"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-currency-usd"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.locale"
                  :items="localeOptions"
                  item-title="label"
                  item-value="value"
                  label="Locale"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-translate"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.timezone"
                  :items="timezoneOptions"
                  item-title="label"
                  item-value="value"
                  label="Timezone"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-clock-outline"
                />
              </v-col>
            </v-row>
          </div>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ── Actions ──────────────────────────────────────────────────────────── -->
      <v-card-actions class="pa-6 pt-4 gap-3">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
          Cancel
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :loading="loading"
          :prepend-icon="
            isEdit ? 'mdi-content-save' : 'mdi-office-building-plus'
          "
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Create Tenant' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- ── Transfer Ownership Dialog ────────────────────────────────────────────── -->
  <v-dialog v-model="transferDialog" max-width="420" persistent>
    <v-card rounded="xl" elevation="0" border>
      <v-card-title class="pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar color="warning" variant="tonal" size="40" rounded="lg">
            <v-icon icon="mdi-swap-horizontal" size="20" />
          </v-avatar>
          <div>
            <div class="text-h6 font-weight-bold">Transfer Ownership</div>
            <div class="text-caption text-medium-emphasis">
              Assign a new owner to this tenant
            </div>
          </div>
        </div>
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-5">
        <v-text-field
          v-model="transferEmail"
          label="New Owner Email"
          type="email"
          variant="outlined"
          density="comfortable"
          rounded="lg"
          prepend-inner-icon="mdi-email-outline"
          hint="Must be an existing user in the system"
          persistent-hint
        />
      </v-card-text>
      <v-card-actions class="px-5 pb-5 pt-0 gap-3">
        <v-btn variant="tonal" rounded="lg" @click="transferDialog = false">
          Cancel
        </v-btn>
        <v-btn
          color="warning"
          variant="flat"
          rounded="lg"
          @click="emitTransfer"
        >
          Transfer
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { slugify } from '@/utils/slugify' // simple helper below

  const props = defineProps({
    modelValue: Boolean,
    item: Object, // existing tenant for edit
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save', 'transfer'])

  const formRef = ref(null)
  const showPassword = ref(false)
  const editingSlug = ref(false)
  const credentialGenerated = ref(false)
  const transferDialog = ref(false)
  const transferEmail = ref('')

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.item?.id)

  const ownerInitials = computed(() => {
    const f = props.item?.owner?.first_name?.[0] || ''
    const l = props.item?.owner?.last_name?.[0] || ''
    return (f + l).toUpperCase()
  })

  // ── Options ───────────────────────────────────────────────────────────────────
  const planOptions = [
    { value: 'free', label: 'Free', icon: 'mdi-star-outline', color: 'grey' },
    {
      value: 'starter',
      label: 'Starter',
      icon: 'mdi-star-half-full',
      color: 'blue'
    },
    { value: 'pro', label: 'Pro', icon: 'mdi-star', color: 'primary' },
    {
      value: 'enterprise',
      label: 'Enterprise',
      icon: 'mdi-crown',
      color: 'warning'
    }
  ]

  const currencyOptions = [
    { value: 'USD', label: 'USD — US Dollar' },
    { value: 'KHR', label: 'KHR — Cambodian Riel' },
    { value: 'EUR', label: 'EUR — Euro' },
    { value: 'GBP', label: 'GBP — British Pound' },
    { value: 'THB', label: 'THB — Thai Baht' },
    { value: 'SGD', label: 'SGD — Singapore Dollar' },
    { value: 'MYR', label: 'MYR — Malaysian Ringgit' }
  ]

  const localeOptions = [
    { value: 'en-US', label: 'English (US)' },
    { value: 'en-GB', label: 'English (UK)' },
    { value: 'km-KH', label: 'Khmer (KH)' },
    { value: 'zh-CN', label: 'Chinese (CN)' },
    { value: 'th-TH', label: 'Thai (TH)' }
  ]

  const timezoneOptions = [
    { value: 'UTC', label: 'UTC' },
    { value: 'Asia/Phnom_Penh', label: 'Asia/Phnom Penh (ICT)' },
    { value: 'Asia/Bangkok', label: 'Asia/Bangkok (ICT)' },
    { value: 'Asia/Singapore', label: 'Asia/Singapore (SGT)' },
    { value: 'Asia/Tokyo', label: 'Asia/Tokyo (JST)' },
    { value: 'America/New_York', label: 'America/New York (EST)' },
    { value: 'Europe/London', label: 'Europe/London (GMT)' }
  ]

  // ── Default form ──────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    // Owner fields (create only)
    owner_first_name: '',
    owner_last_name: '',
    owner_email: '',
    owner_phone: '',
    owner_password: '',
    // Business fields
    name: '',
    slug: '',
    plan: 'free',
    logo_url: '',
    primary_color: '#6366f1',
    is_active: true,
    // Localization
    currency: 'USD',
    locale: 'en-US',
    timezone: 'UTC'
  })

  const form = reactive(defaultForm())

  // Populate on edit
  watch(
    () => props.item,
    val => {
      Object.keys(form).forEach(k => delete form[k])
      editingSlug.value = false
      credentialGenerated.value = false

      if (val) {
        Object.assign(form, {
          name: val.name,
          slug: val.slug,
          plan: val.plan,
          logo_url: val.logo_url ?? '',
          primary_color: val.primary_color ?? '#6366f1',
          is_active: val.is_active,
          currency: val.currency ?? 'USD',
          locale: val.locale ?? 'en-US',
          timezone: val.timezone ?? 'UTC'
        })
      } else {
        Object.assign(form, defaultForm())
      }
    },
    { immediate: true }
  )

  // ── Auto slug from name ───────────────────────────────────────────────────────
  const onNameChange = val => {
    if (!editingSlug.value) {
      form.slug = slugify(val)
    }
  }

  // ── Generate password ─────────────────────────────────────────────────────────
  const generatePassword = () => {
    const chars =
      'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#!'
    form.owner_password = Array.from(
      { length: 12 },
      () => chars[Math.floor(Math.random() * chars.length)]
    ).join('')
    showPassword.value = true
    credentialGenerated.value = true
  }

  // ── Validation ────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || 'Required',
    email: v => /.+@.+\..+/.test(v) || 'Invalid email',
    minPassword: v => !v || v.length >= 6 || 'Minimum 6 characters'
  }

  // ── Submit ────────────────────────────────────────────────────────────────────
  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    if (isEdit.value) {
      // Edit — only send tenant fields, never owner fields
      emit('save', {
        id: props.item.id,
        name: form.name,
        slug: form.slug,
        plan: form.plan,
        logo_url: form.logo_url,
        primary_color: form.primary_color,
        is_active: form.is_active,
        currency: form.currency,
        locale: form.locale,
        timezone: form.timezone
      })
    } else {
      // Create — send everything including owner fields
      emit('save', { ...form })
    }
  }

  const emitTransfer = () => {
    if (!transferEmail.value) return
    emit('transfer', {
      tenant_id: props.item.id,
      new_owner_email: transferEmail.value
    })
    transferDialog.value = false
    transferEmail.value = ''
  }

  const close = () => {
    formRef.value?.reset()
    Object.assign(form, defaultForm())
    credentialGenerated.value = false
    editingSlug.value = false
    emit('update:modelValue', false)
  }
</script>

<style scoped>
  .section-block {
    padding: 20px 24px;
  }
  .section-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgb(var(--v-theme-primary));
    margin-bottom: 16px;
    display: flex;
    align-items: center;
  }
  .color-swatch {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    padding: 0;
    background: none;
  }
  .cursor-pointer {
    cursor: pointer;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
