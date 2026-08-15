<template>
  <v-container fluid class="pa-0">
    <AppPageHeader
      :title="isEdit ? $t('tenant_create.title_edit') : $t('tenant_create.title_create')"
      show-back
      :breadcrumbs="[
        { title: $t('menu.tenant'), to: '/tenants' },
        {
          title: isEdit ? form.name : $t('tenant_create.title_create')
        }
      ]"
    />

    <v-card rounded="xl" elevation="0" border>
      <v-stepper
        v-model="currentStep"
        :items="stepperItems"
        hide-actions
        flat
        rounded="xl"
        color="primary"
        class="tenant-stepper"
      >
        <v-divider />

        <!-- ── Tip banner ── -->
        <v-alert
          :icon="steps[currentStep - 1]?.tip?.icon ?? 'mdi-lightbulb-outline'"
          color="primary"
          variant="tonal"
          density="compact"
          rounded="0"
          class="px-6"
        >
          <span class="font-weight-medium">
            {{ steps[currentStep - 1]?.tip?.title }}:
          </span>
          <span class="text-medium-emphasis ms-1">
            {{ steps[currentStep - 1]?.tip?.body }}
          </span>
        </v-alert>

        <!-- ── Form body ── -->
        <v-form ref="formRef" @submit.prevent class="px-6 py-5">
          <v-window v-model="currentStep" :touch="false">
            <!-- ════ Step 1 — Owner Account ════ -->
            <v-window-item :value="1">
              <step-section-label
                icon="mdi-account-circle-outline"
                :label="$t('tenant_create.steps.owner_account')"
              />

              <v-row dense>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.owner_first_name"
                    :label="$t('tenant_create.field.first_name')"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    prepend-inner-icon="mdi-account-outline"
                    :rules="[r.required]"
                    maxlength="80"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.owner_last_name"
                    :label="$t('tenant_create.field.last_name')"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    :rules="[r.required]"
                    maxlength="80"
                  />
                </v-col>

                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.owner_email"
                    :label="$t('tenant_create.field.email')"
                    type="email"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    prepend-inner-icon="mdi-email-outline"
                    :rules="[r.required, r.email]"
                    :error-messages="serverErrors.owner_email"
                    :disabled="isEdit"
                    maxlength="255"
                    @update:model-value="clearServerError('owner_email')"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.owner_phone"
                    :label="$t('form.phone')"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    prepend-inner-icon="mdi-phone-outline"
                    maxlength="30"
                  />
                </v-col>

                <!-- Password — hidden in edit mode -->
                <template v-if="!isEdit">
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.owner_password"
                      :label="$t('tenant_create.field.password')"
                      :type="showPassword ? 'text' : 'password'"
                      variant="outlined"
                      density="comfortable"
                      rounded="lg"
                      prepend-inner-icon="mdi-lock-outline"
                      :append-inner-icon="
                        showPassword ? 'mdi-eye-off' : 'mdi-eye'
                      "
                      :rules="passwordRules"
                      @click:append-inner="showPassword = !showPassword"
                    />
                  </v-col>
                  <v-col cols="12" sm="6" class="d-flex align-center pb-5">
                    <v-btn
                      variant="tonal"
                      rounded="lg"
                      size="small"
                      prepend-icon="mdi-refresh"
                      @click="generatePassword"
                    >
                      {{ $t('tenant_create.generate_password') }}
                    </v-btn>
                  </v-col>

                  <v-col v-if="credentialGenerated" cols="12">
                    <v-alert
                      type="warning"
                      variant="tonal"
                      rounded="lg"
                      density="compact"
                      icon="mdi-key-alert-outline"
                    >
                      {{ $t('tenant_create.password_alert') }}
                    </v-alert>
                  </v-col>
                </template>

                <!-- Edit-mode info + reset password -->
                <v-col v-else cols="12">
                  <v-alert
                    type="info"
                    variant="tonal"
                    rounded="lg"
                    density="compact"
                    icon="mdi-information-outline"
                    class="mb-3"
                  >
                    {{ $t('tenant_create.email_locked_before') }}
                    <strong>{{ $t('tenant_create.reset_password_label') }}</strong>
                    {{ $t('tenant_create.email_locked_after') }}
                  </v-alert>
                  <v-btn
                    variant="tonal"
                    color="warning"
                    rounded="lg"
                    size="small"
                    prepend-icon="mdi-lock-reset"
                    @click="confirmResetOwnerPassword"
                  >
                    {{ $t('tenant_create.reset_password_label') }}
                  </v-btn>
                </v-col>
              </v-row>
            </v-window-item>

            <!-- ════ Step 2 — Business Info ════ -->
            <v-window-item :value="2">
              <step-section-label
                icon="mdi-store-outline"
                :label="$t('tenant_create.steps.business_info')"
              />

              <v-row dense>
                <v-col cols="12" sm="8">
                  <v-text-field
                    v-model="form.name"
                    :label="$t('tenant_create.field.business_name')"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    prepend-inner-icon="mdi-domain"
                    :rules="[r.required]"
                    maxlength="150"
                    @update:model-value="onNameChange"
                  />
                </v-col>
                <v-col cols="12" sm="4">
                  <v-text-field
                    v-model="form.slug"
                    :label="$t('tenant_create.field.slug')"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    prepend-inner-icon="mdi-link-variant"
                    :readonly="!editingSlug"
                    :bg-color="!editingSlug ? 'grey-lighten-2' : undefined"
                    :hint="$t('tenant_create.slug_hint')"
                    persistent-hint
                    maxlength="100"
                  >
                    <template #append-inner>
                      <v-tooltip
                        :text="editingSlug ? $t('tenant_create.lock_slug') : $t('tenant_create.edit_slug')"
                      >
                        <template #activator="{ props: tp }">
                          <v-icon
                            v-bind="tp"
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

                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.business_type_id"
                    :items="businessTypes"
                    :loading="buTypesLoading"
                    item-title="name"
                    item-value="id"
                    :label="$t('tenant_create.field.business_type')"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    prepend-inner-icon="mdi-store-outline"
                    :rules="[r.required]"
                  >
                    <template #item="{ props: p, item }">
                      <v-list-item v-bind="p">
                        <template #prepend>
                          <v-avatar
                            :color="item.raw.color"
                            variant="tonal"
                            size="28"
                            rounded="md"
                            class="mr-2"
                          >
                            <v-icon :icon="item.raw.icon" size="15" />
                          </v-avatar>
                        </template>
                        <template #append>
                      <span class="text-caption text-medium-emphasis">
                        {{ item.raw.code }}
                      </span>
                    </template>
                      </v-list-item>
                    </template>
                    <template #selection="{ item }">
                      <div class="d-flex align-center ga-2">
                        <v-avatar
                          :color="item.raw.color"
                          variant="tonal"
                          size="22"
                          rounded="sm"
                        >
                          <v-icon :icon="item.raw.icon" size="13" />
                        </v-avatar>
                        <span class="text-body-2">{{ item.raw.name }}</span>
                      </div>
                    </template>
                  </v-select>
                </v-col>

                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.logo_url"
                    :label="$t('tenant_create.field.logo_url')"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    prepend-inner-icon="mdi-image-outline"
                  />
                </v-col>

                <v-col cols="12" sm="6">
                  <v-color-input
                    v-model="form.primary_color"
                    color-pip
                    :label="$t('tenant_create.field.brand_color')"
                    variant="outlined"
                    density="comfortable"
                    pip-location="prepend-inner"
                  />
                </v-col>
              </v-row>
            </v-window-item>

            <!-- ════ Step 3 — Localization + Review ════ -->
            <v-window-item :value="3">
              <step-section-label icon="mdi-earth" :label="$t('tenant_create.steps.localization')" />

              <v-row dense class="mb-2">
                <v-col cols="12" sm="4">
                  <v-select
                    v-model="form.currency"
                    :items="currencyOptions"
                    item-title="label"
                    item-value="value"
                    :label="$t('tenant_create.field.currency')"
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
                    :label="$t('tenant_create.field.locale')"
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
                    :label="$t('tenant_create.field.timezone')"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    prepend-inner-icon="mdi-clock-outline"
                  />
                </v-col>
              </v-row>

              <v-divider class="my-5" />

              <step-section-label
                icon="mdi-check-circle-outline"
                :label="$t('tenant_create.steps.review')"
              />

              <!-- Review tiles -->
              <v-row dense>
                <v-col
                  v-for="tile in reviewItems"
                  :key="tile.label"
                  cols="6"
                  sm="4"
                  md="3"
                >
                  <v-sheet
                    rounded="lg"
                    border
                    class="pa-3 review-tile"
                  >
                    <div class="review-tile-label">{{ tile.label }}</div>
                    <div class="review-tile-value">{{ tile.value || '—' }}</div>
                  </v-sheet>
                </v-col>
              </v-row>
            </v-window-item>
          </v-window>
        </v-form>

        <v-divider />

        <!-- ── Footer actions ── -->
        <div class="d-flex align-center ga-3 pa-4 px-6">
          <!-- Step indicator dots -->
          <div class="d-flex ga-1 align-center">
            <div
              v-for="(_, i) in steps"
              :key="i"
              class="step-dot"
              :class="{
                'step-dot--active': i + 1 === currentStep,
                'step-dot--done': i + 1 < currentStep
              }"
            />
          </div>

          <span class="text-caption text-medium-emphasis ms-1">
            {{ currentStep }} / {{ steps.length }}
          </span>
          <v-spacer />

          <v-btn
            v-if="currentStep > 1"
            variant="tonal"
            rounded="lg"
            prepend-icon="mdi-arrow-left"
            @click="prevStep"
          >
            {{ $t('btn.back') }}
          </v-btn>

          <v-btn variant="outlined" rounded="lg" :to="{ name: 'tenants' }">
            {{ $t('btn.cancel') }}
          </v-btn>

          <v-btn
            v-if="currentStep < steps.length"
            color="primary"
            variant="flat"
            rounded="lg"
            append-icon="mdi-arrow-right"
            @click="nextStep"
          >
            {{ $t('tenant_create.continue') }}
          </v-btn>

          <v-btn
            v-else
            :color="isEdit ? 'primary' : 'success'"
            variant="flat"
            rounded="lg"
            :loading="loading"
            :prepend-icon="
              isEdit ? 'mdi-content-save-outline' : 'mdi-office-building-plus'
            "
            @click="submit"
          >
            {{ isEdit ? $t('btn.save_changes') : $t('tenant_create.create_tenant') }}
          </v-btn>
        </div>
      </v-stepper>
    </v-card>

    <TemporaryPasswordDialog
      v-model="tempPasswordDialog"
      :password="temporaryPassword"
    />
  </v-container>
</template>

<script setup>
  import {
    ref,
    reactive,
    computed,
    onMounted,
    h,
    resolveComponent
  } from 'vue'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import { useRouter, useRoute } from 'vue-router'
  import { useI18n } from 'vue-i18n'
  import { slugify } from '@/utils/slugify'
  import { useTenantStore } from '@/stores/tenantStore'
  import { useBusinessTypeStore } from '@/stores/businessTypeStore'
  import { usePasswordPolicy } from '@/composables/usePasswordPolicy'
  import { useAppUtils } from '@/composables/useAppUtils'
  import TemporaryPasswordDialog from '@/components/common/TemporaryPasswordDialog.vue'

  const { t } = useI18n()
  const { generate, rules: passwordRules } = usePasswordPolicy()
  const { confirm, notif } = useAppUtils()
  // ── Inline step label component ────────────────────────────────────────────────

  const StepSectionLabel = {
    props: ['icon', 'label'],
    setup(props) {
      return () =>
        h('div', { class: 'step-section-label mb-4' }, [
          h(resolveComponent('v-icon'), {
            icon: props.icon,
            size: '14',
            class: 'me-1'
          }),
          props.label
        ])
    }
  }

  const router = useRouter()
  const route = useRoute()
  const store = useTenantStore()
  const businessStore = useBusinessTypeStore()

  // ── Edit mode detection ─────────────────────────────────────────────────────────
  const businessTypes = computed(() => businessStore.businessTypes)
  const isEdit = computed(() => !!route.params.id)
  const tenantId = computed(() => route.params.id)

  // ── State ───────────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const currentStep = ref(1)
  const loading = ref(false)
  const showPassword = ref(false)
  const editingSlug = ref(false)
  const credentialGenerated = ref(false)
  const buTypesLoading = ref(false)
  const serverErrors = reactive({})
  const tempPasswordDialog = ref(false)
  const temporaryPassword = ref('')

  const populateForm = tenant => {
    // If owner is nested:
    form.owner_first_name = tenant.owner?.first_name ?? ''
    form.owner_last_name = tenant.owner?.last_name ?? ''
    form.owner_email = tenant.owner?.email ?? ''
    form.owner_phone = tenant.owner?.phone ?? ''

    form.name = tenant.name ?? ''
    form.slug = tenant.slug ?? ''
    form.business_type_id = tenant.business_type_id ?? null
    form.logo_url = tenant.logo_url ?? ''
    form.primary_color = tenant.primary_color ?? '#6366f1'
    form.currency = tenant.currency ?? 'USD'
    form.locale = tenant.locale ?? 'en-US'
    form.timezone = tenant.timezone ?? 'Asia/Phnom_Penh'
  }
  // ── Steps config ────────────────────────────────────────────────────────────────
  const steps = [
    {
      label: t('tenant_create.steps.owner_account'),
      icon: 'mdi-account-circle-outline',
      tip: {
        icon: 'mdi-account-key-outline',
        title: t('tenant_create.steps.owner_account'),
        body: t('tenant_create.tips.owner_account')
      }
    },
    {
      label: t('tenant_create.steps.business_info'),
      icon: 'mdi-store-outline',
      tip: {
        icon: 'mdi-link-variant',
        title: t('tenant_create.steps.business_info'),
        body: t('tenant_create.tips.business_info')
      }
    },
    {
      label: t('tenant_create.steps.localization'),
      icon: 'mdi-earth',
      tip: {
        icon: 'mdi-translate',
        title: t('tenant_create.steps.localization'),
        body: t('tenant_create.tips.localization')
      }
    }
  ]

  const stepperItems = computed(() =>
    steps.map((s, i) => ({ title: s.label, value: i + 1 }))
  )

  // ── Form ────────────────────────────────────────────────────────────────────────
  const form = reactive({
    owner_first_name: '',
    owner_last_name: '',
    owner_email: '',
    owner_phone: '',
    owner_password: '',
    name: '',
    slug: '',
    business_type_id: null,
    logo_url: '',
    primary_color: '#6366f1',
    currency: 'USD',
    locale: 'en-US',
    timezone: 'Asia/Phnom_Penh'
  })

  // ── Options ─────────────────────────────────────────────────────────────────────
  const currencyOptions = [
    { value: 'USD', label: t('tenant_create.currency.usd') },
    { value: 'KHR', label: t('tenant_create.currency.khr') },
    { value: 'EUR', label: t('tenant_create.currency.eur') },
    { value: 'GBP', label: t('tenant_create.currency.gbp') },
    { value: 'SGD', label: t('tenant_create.currency.sgd') },
    { value: 'MYR', label: t('tenant_create.currency.myr') }
  ]

  const localeOptions = [
    { value: 'en-US', label: t('tenant_create.locale_options.en_us') },
    { value: 'en-GB', label: t('tenant_create.locale_options.en_gb') },
    { value: 'km-KH', label: t('tenant_create.locale_options.km_kh') },
    { value: 'zh-CN', label: t('tenant_create.locale_options.zh_cn') }
  ]

  const timezoneOptions = [
    { value: 'Asia/Phnom_Penh', label: t('tenant_create.timezone_options.phnom_penh') },
    { value: 'Asia/Bangkok', label: t('tenant_create.timezone_options.bangkok') },
    { value: 'Asia/Singapore', label: t('tenant_create.timezone_options.singapore') },
    { value: 'Asia/Ho_Chi_Minh', label: t('tenant_create.timezone_options.ho_chi_minh') },
    { value: 'UTC', label: t('tenant_create.timezone_options.utc') }
  ]

  // ── Review summary ──────────────────────────────────────────────────────────────
  const reviewItems = computed(() => [
    {
      label: t('tenant_create.review.owner'),
      value: `${form.owner_first_name} ${form.owner_last_name}`.trim()
    },
    { label: t('form.email'), value: form.owner_email },
    { label: t('tenant_create.review.business'), value: form.name },
    { label: t('tenant_create.field.slug'), value: form.slug },
    { label: t('tenant_create.field.currency'), value: form.currency },
    { label: t('tenant_create.field.locale'), value: form.locale },
    { label: t('tenant_create.field.timezone'), value: form.timezone }
  ])

  // ── Validation rules ────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || t('products.rule.required'),
    email: v => /.+@.+\..+/.test(v) || t('tenant_create.rule.email')
  }

  // ── Helpers ─────────────────────────────────────────────────────────────────────
  const onNameChange = val => {
    if (!editingSlug.value) form.slug = slugify(val)
  }

  const generatePassword = () => {
    form.owner_password = generate()
    showPassword.value = true
    credentialGenerated.value = true
  }

  const confirmResetOwnerPassword = () => {
    confirm({
      title: t('tenant_create.reset_password_label'),
      message: t('tenant_create.reset_password_confirm'),
      options: { type: 'warning', width: 500 },
      agree: async () => {
        try {
          temporaryPassword.value = await store.resetOwnerPassword(tenantId.value)
          tempPasswordDialog.value = true
        } catch {
          notif(t('tenant_create.reset_password_failed'), { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }

  // ── Navigation ──────────────────────────────────────────────────────────────────
  const nextStep = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    if (currentStep.value < steps.length) currentStep.value++
  }

  const prevStep = () => {
    if (currentStep.value > 1) currentStep.value--
  }

  // ── Server errors ───────────────────────────────────────────────────────────────
  const fieldStepMap = {
    owner_first_name: 1,
    owner_last_name: 1,
    owner_email: 1,
    owner_phone: 1,
    owner_password: 1,
    name: 2,
    slug: 2,
    business_type_id: 2,
    currency: 3,
    locale: 3,
    timezone: 3
  }

  const clearServerError = field => {
    delete serverErrors[field]
  }

  const handleServerErrors = errors => {
    Object.keys(serverErrors).forEach(k => delete serverErrors[k])
    if (!errors) return
    Object.entries(errors).forEach(([field, messages]) => {
      serverErrors[field] = Array.isArray(messages) ? messages : [messages]
    })
    const firstField = Object.keys(errors)[0]
    if (firstField) currentStep.value = fieldStepMap[firstField] ?? 1
  }

  // ── Submit ──────────────────────────────────────────────────────────────────────
  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    try {
      if (isEdit.value) {
        await store.updateTenant(tenantId.value, { ...form })
      } else {
        await store.createTenant({ ...form })
      }
      router.push({ name: 'tenants' })
    } catch (err) {
      if (err?.response?.data?.errors)
        handleServerErrors(err.response.data.errors)
    } finally {
      loading.value = false
    }
  }

  // ── Mount ───────────────────────────────────────────────────────────────────────

  onMounted(async () => {
    buTypesLoading.value = true
    try {
      await Promise.all([store.fetchBusinessTypes(), businessStore.fetchBusinessTypes()])

      if (isEdit.value) {
        const tenant = await store.fetchTenantForEdit(tenantId.value)
        if (!tenant) {
          // Tenant not found — redirect gracefully
          router.replace({ name: 'tenants' })
          return
        }
        populateForm(tenant)
      }
    } catch {
      router.replace({ name: 'tenants' }) // or show an error toast
    } finally {
      buTypesLoading.value = false
    }
  })
</script>

<style scoped>
  /* ── Step section label ────────────────────────────────────── */
  .step-section-label {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: rgb(var(--v-theme-primary));
  }

  /* ── Plan cards ────────────────────────────────────────────── */
  .plan-card {
    transition:
      transform 0.15s ease,
      box-shadow 0.15s ease;
    height: 100%;
  }
  .plan-card:not(.plan-disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1) !important;
  }
  .plan-selected {
    box-shadow: 0 0 0 2px rgb(var(--v-theme-primary)) !important;
  }
  .plan-disabled {
    opacity: 0.5;
    cursor: not-allowed !important;
  }

  /* ── Step dot indicator ────────────────────────────────────── */
  .step-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: rgba(var(--v-border-color), 0.4);
    transition: all 0.2s ease;
  }
  .step-dot--done {
    background-color: rgb(var(--v-theme-primary));
    opacity: 0.4;
  }
  .step-dot--active {
    width: 18px;
    border-radius: 3px;
    background-color: rgb(var(--v-theme-primary));
  }

  /* ── Review tiles ──────────────────────────────────────────── */
  .review-tile {
    background: rgba(var(--v-theme-surface-variant), 0.1);
  }
  .review-tile-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: rgba(var(--v-theme-on-surface), 0.5);
  }
  .review-tile-value {
    font-size: 0.875rem;
    font-weight: 500;
    margin-top: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .cursor-pointer {
    cursor: pointer;
  }
  .min-w-0 {
    min-width: 0;
  }
</style>

<style>
  .tenant-stepper .v-stepper-window {
    margin: 0;
  }
</style>
