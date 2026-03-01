<template>
  <v-dialog v-model="model" max-width="640" persistent>
    <v-card rounded="lg">
      <!-- Header -->
      <v-card-title class="d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-3">
          <div>
            <p>
              {{ isEdit ? 'Edit Tenant' : 'Create Tenant' }}
            </p>
          </div>
        </div>
        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          :disabled="loading"
          @click="close"
        />
      </v-card-title>

      <v-divider />

      <!-- Form -->
      <v-card-text class="px-6 py-5">
        <v-form ref="formRef" @submit.prevent="submit">
          <div class="d-flex flex-column gap-4">
            <!-- Row 1: Name + Slug -->
            <v-row dense>
              <v-col cols="12" md="6">
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Name
                  <span class="text-error">*</span>
                </label>
                <v-text-field
                  v-model="form.name"
                  placeholder="e.g. Acme Restaurant Group"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.name"
                  :error-messages="serverErrors.name"
                  maxlength="150"
                  counter
                />
              </v-col>
              <v-col cols="12" md="6">
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Slug
                  <span class="text-error">*</span>
                </label>
                <v-text-field
                  v-model="form.slug"
                  placeholder="e.g. acme-restaurant"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.slug"
                  :error-messages="serverErrors.slug"
                  maxlength="100"
                >
                  <template #prepend-inner>
                    <v-icon
                      icon="mdi-link-variant"
                      size="16"
                      class="text-grey"
                    />
                  </template>
                </v-text-field>
              </v-col>
            </v-row>

            <!-- Row 2: Plan + Currency -->
            <v-row dense>
              <v-col cols="12" md="6">
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Plan
                  <span class="text-error">*</span>
                </label>
                <v-select
                  v-model="form.plan"
                  :items="planItems"
                  item-title="label"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.plan"
                  :error-messages="serverErrors.plan"
                >
                  <template #prepend-inner>
                    <v-icon
                      icon="mdi-tag-outline"
                      size="16"
                      class="text-grey"
                    />
                  </template>
                  <template #item="{ item, props: itemProps }">
                    <v-list-item v-bind="itemProps">
                      <template #append>
                        <v-chip
                          :color="planColor(item.value)"
                          size="x-small"
                          variant="tonal"
                        >
                          {{ item.value }}
                        </v-chip>
                      </template>
                    </v-list-item>
                  </template>
                </v-select>
              </v-col>
              <v-col cols="12" md="6">
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Currency
                  <span class="text-error">*</span>
                </label>
                <v-text-field
                  v-model="form.currency"
                  placeholder="USD"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.currency"
                  :error-messages="serverErrors.currency"
                  maxlength="3"
                >
                  <template #prepend-inner>
                    <v-icon
                      icon="mdi-currency-usd"
                      size="16"
                      class="text-grey"
                    />
                  </template>
                </v-text-field>
              </v-col>
            </v-row>

            <!-- Row 3: Timezone + Locale -->
            <v-row dense>
              <v-col cols="12" md="6">
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Timezone
                  <span class="text-error">*</span>
                </label>
                <v-combobox
                  v-model="form.timezone"
                  :items="timezones"
                  placeholder="UTC"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.timezone"
                  :error-messages="serverErrors.timezone"
                >
                  <template #prepend-inner>
                    <v-icon icon="mdi-earth" size="16" class="text-grey" />
                  </template>
                </v-combobox>
              </v-col>
              <v-col cols="12" md="6">
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Locale
                  <span class="text-error">*</span>
                </label>
                <v-combobox
                  v-model="form.locale"
                  :items="locales"
                  placeholder="en-US"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.locale"
                  :error-messages="serverErrors.locale"
                >
                  <template #prepend-inner>
                    <v-icon icon="mdi-translate" size="16" class="text-grey" />
                  </template>
                </v-combobox>
              </v-col>
            </v-row>

            <!-- Row 4: Logo URL + Primary Color -->
            <v-row dense>
              <v-col cols="12" md="8">
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Logo URL
                  <span class="text-caption text-grey ml-1">(optional)</span>
                </label>
                <v-text-field
                  v-model="form.logo_url"
                  placeholder="https://example.com/logo.png"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.logo_url"
                  :error-messages="serverErrors.logo_url"
                >
                  <template #prepend-inner>
                    <v-icon
                      icon="mdi-image-outline"
                      size="16"
                      class="text-grey"
                    />
                  </template>
                  <template v-if="logoPreviewValid" #append-inner>
                    <v-avatar size="24" rounded="sm">
                      <v-img :src="form.logo_url" cover />
                    </v-avatar>
                  </template>
                </v-text-field>
              </v-col>
              <v-col cols="12" md="4">
                <label
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block"
                >
                  Brand Color
                  <span class="text-caption text-grey ml-1">(hex)</span>
                </label>
                <v-text-field
                  v-model="form.primary_color"
                  placeholder="#FF5733"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.primary_color"
                  :error-messages="serverErrors.primary_color"
                  maxlength="7"
                >
                  <template #prepend-inner>
                    <div
                      class="color-swatch"
                      :style="{ background: colorPreview }"
                    />
                  </template>
                </v-text-field>
              </v-col>
            </v-row>

            <!-- Active Status -->
            <div
              class="d-flex align-center justify-space-between pa-4 rounded-lg bg-grey-lighten-5"
            >
              <div>
                <p
                  class="text-body-2 font-weight-medium text-grey-darken-2 mb-0"
                >
                  Active Status
                </p>
                <p class="text-caption text-grey mb-0">
                  Tenant will have access when active
                </p>
              </div>
              <v-switch
                v-model="form.is_active"
                color="success"
                hide-details
                inset
              />
            </div>
          </div>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- Actions -->
      <v-card-actions>
        <v-spacer />
        <v-btn
          variant="outlined"
          rounded="lg"
          :disabled="loading"
          @click="close"
        >
          Cancel
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :loading="loading"
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Create Tenant' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'

  const props = defineProps({
    modelValue: Boolean,
    tenant: Object
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const formRef = ref(null)
  const loading = ref(false)
  const serverErrors = reactive({})

  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  const isEdit = computed(() => !!props.tenant?.id)

  // ─── Static Data ──────────────────────────────────────────────────────────────
  const planItems = [
    { value: 'free', label: 'Free' },
    { value: 'starter', label: 'Starter' },
    { value: 'pro', label: 'Pro' },
    { value: 'enterprise', label: 'Enterprise' }
  ]

  const planColor = plan =>
    ({ free: 'grey', starter: 'blue', pro: 'purple', enterprise: 'amber' })[
      plan
    ] ?? 'grey'

  const timezones = [
    'UTC',
    'Asia/Phnom_Penh',
    'Asia/Bangkok',
    'Asia/Singapore',
    'Asia/Tokyo',
    'Asia/Shanghai',
    'Asia/Kolkata',
    'Europe/London',
    'Europe/Paris',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles'
  ]

  const locales = [
    'en-US',
    'en-GB',
    'km-KH',
    'zh-CN',
    'zh-TW',
    'ja-JP',
    'ko-KR',
    'fr-FR',
    'de-DE',
    'es-ES',
    'th-TH'
  ]

  // ─── Form ─────────────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    id: null,
    name: '',
    slug: '',
    plan: 'free',
    currency: 'USD',
    timezone: 'UTC',
    locale: 'en-US',
    logo_url: '',
    primary_color: '',
    is_active: true
  })

  const form = reactive(defaultForm())

  // ─── Previews ─────────────────────────────────────────────────────────────────
  const HEX_REGEX = /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/

  const colorPreview = computed(() =>
    HEX_REGEX.test(form.primary_color) ? form.primary_color : '#e0e0e0'
  )

  const logoPreviewValid = computed(() => {
    try {
      if (!form.logo_url) return false
      new URL(form.logo_url)
      return true
    } catch {
      return false
    }
  })

  // ─── Validation Rules ─────────────────────────────────────────────────────────
  const rules = {
    name: [
      v => !!v?.trim() || 'Name is required',
      v => v.trim().length >= 2 || 'At least 2 characters',
      v => v.trim().length <= 150 || 'Max 150 characters'
    ],
    slug: [
      v => !!v?.trim() || 'Slug is required',
      v => v.trim().length <= 100 || 'Max 100 characters',
      v =>
        /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(v) ||
        'Lowercase letters, numbers and hyphens only (e.g. my-tenant)'
    ],
    plan: [
      v => !!v || 'Plan is required',
      v =>
        ['free', 'starter', 'pro', 'enterprise'].includes(v) || 'Invalid plan'
    ],
    currency: [
      v => !!v?.trim() || 'Currency is required',
      v =>
        /^[A-Z]{3}$/.test(v?.trim()) || 'Must be a 3-letter ISO code (e.g. USD)'
    ],
    timezone: [
      v => !!v?.trim() || 'Timezone is required',
      v => v.trim().length <= 60 || 'Max 60 characters'
    ],
    locale: [
      v => !!v?.trim() || 'Locale is required',
      v =>
        /^[a-z]{2,3}(-[A-Z]{2,4})?$/.test(v?.trim()) ||
        'Must be a valid locale (e.g. en-US)',
      v => v.trim().length <= 10 || 'Max 10 characters'
    ],
    logo_url: [
      v => {
        if (!v) return true
        try {
          new URL(v)
          return true
        } catch {
          return 'Must be a valid URL'
        }
      }
    ],
    primary_color: [
      v => !v || HEX_REGEX.test(v) || 'Must be a valid hex color (e.g. #FF5733)'
    ]
  }

  // ─── Helpers — defined BEFORE watchers ───────────────────────────────────────
  const resetForm = () => {
    Object.assign(form, defaultForm())
    formRef.value?.resetValidation()
  }

  const clearServerErrors = () => {
    Object.keys(serverErrors).forEach(key => delete serverErrors[key])
  }

  const close = () => {
    if (loading.value) return
    model.value = false
    resetForm()
  }

  // ─── Watcher ──────────────────────────────────────────────────────────────────
  watch(
    () => props.tenant,
    val => {
      clearServerErrors()
      if (val) {
        Object.assign(form, {
          id: val.id ?? null,
          name: val.name ?? '',
          slug: val.slug ?? '',
          plan: val.plan ?? 'free',
          currency: val.currency ?? 'USD',
          timezone: val.timezone ?? 'UTC',
          locale: val.locale ?? 'en-US',
          logo_url: val.logo_url ?? '',
          primary_color: val.primary_color ?? '',
          is_active: val.is_active ?? true
        })
      } else {
        resetForm()
      }
    },
    { immediate: true }
  )

  // ─── Submit ───────────────────────────────────────────────────────────────────
  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    clearServerErrors()

    try {
      const payload = {
        ...form,
        name: form.name.trim(),
        slug: form.slug.trim().toLowerCase(),
        currency: form.currency.trim().toUpperCase(),
        logo_url: form.logo_url || null,
        primary_color: form.primary_color || null
      }

      emit('saved', payload)
      close()
    } catch (err) {
      if (err?.response?.status === 422) {
        const errors = err.response.data?.errors ?? {}
        Object.keys(errors).forEach(key => {
          serverErrors[key] = Array.isArray(errors[key])
            ? errors[key][0]
            : errors[key]
        })
      }
    } finally {
      loading.value = false
    }
  }
</script>

<style scoped>
  .color-swatch {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    border: 1px solid rgba(0, 0, 0, 0.15);
    flex-shrink: 0;
  }
</style>
