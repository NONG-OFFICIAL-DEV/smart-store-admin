<template>
  <AppDialog
    v-model="model"
    :max-width="720"
    :title="isEdit ? $t('tenant_create.title_edit') : $t('tenant_create.title_create')"
    :loading="loading || loadingTenant"
  >
    <v-form ref="formRef">
      <div class="form-section">
        <div class="form-section-label">
          <v-icon icon="mdi-account-circle-outline" size="13" class="mr-1" />
          {{ $t('tenant_create.steps.owner_account') }}
        </div>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="form.owner_first_name"
              :label="$t('tenant_create.field.first_name')"
              variant="outlined"
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
              rounded="lg"
              :rules="[r.required]"
              maxlength="80"
            />
          </v-col>

          <!-- ── CREATE — editable email + password ─────────────────────── -->
          <template v-if="!isEdit">
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.owner_email"
                :label="$t('tenant_create.field.email')"
                type="email"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-email-outline"
                :rules="[r.required, r.email]"
                :error-messages="serverErrors.owner_email"
                maxlength="255"
                @update:model-value="clearServerError('owner_email')"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.owner_phone"
                :label="$t('form.phone')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-phone-outline"
                maxlength="30"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.owner_password"
                :label="$t('tenant_create.field.password')"
                :type="showPassword ? 'text' : 'password'"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-lock-outline"
                :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                :rules="passwordRules"
                @click:append-inner="showPassword = !showPassword"
              />
            </v-col>
            <v-col cols="12" sm="6" class="d-flex align-center">
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

          <!-- ── EDIT — readonly email + reset password ──────────────────── -->
          <template v-else>
            <v-col cols="12" sm="6">
              <v-text-field
                :model-value="form.owner_email"
                :label="$t('tenant_create.field.email')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-email-outline"
                readonly
                class="readonly-field"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.owner_phone"
                :label="$t('form.phone')"
                variant="outlined"
                rounded="lg"
                prepend-inner-icon="mdi-phone-outline"
                maxlength="30"
              />
            </v-col>
            <v-col cols="12">
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
          </template>
        </v-row>
      </div>

      <v-divider />

      <div class="form-section">
        <div class="form-section-label">
          <v-icon icon="mdi-store-outline" size="13" class="mr-1" />
          {{ $t('tenant_create.steps.business_info') }}
        </div>
        <BusinessInfoFields
          v-model:name="form.name"
          v-model:slug="form.slug"
          v-model:business-type-id="form.business_type_id"
          v-model:logo-url="form.logo_url"
          v-model:primary-color="form.primary_color"
          v-model:currency="form.currency"
          v-model:editing-slug="editingSlug"
          :business-types="businessTypes"
          :bu-types-loading="buTypesLoading"
          :currency-options="currencyOptions"
          :server-errors="serverErrors"
          :required-rule="r.required"
          @clear-error="clearServerError"
        />
      </div>
    </v-form>

    <template #actions>
      <v-spacer />
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        :color="isEdit ? 'primary' : 'success'"
        variant="flat"
        rounded="lg"
        :loading="loading"
        :prepend-icon="isEdit ? 'mdi-content-save-outline' : 'mdi-office-building-plus'"
        @click="submit"
      >
        {{ isEdit ? $t('btn.save_changes') : $t('tenant_create.create_tenant') }}
      </v-btn>
    </template>
  </AppDialog>

  <TemporaryPasswordDialog
    v-model="tempPasswordDialog"
    :password="temporaryPassword"
  />
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'
  import { storeToRefs } from 'pinia'
  import { useTenantStore } from '@/stores/tenantStore'
  import { useBusinessTypeStore } from '@/stores/businessTypeStore'
  import { usePasswordPolicy } from '@/composables/usePasswordPolicy'
  import { useAppUtils } from '@/composables/useAppUtils'
  import TemporaryPasswordDialog from '@/components/common/TemporaryPasswordDialog.vue'
  import BusinessInfoFields from './BusinessInfoFields.vue'

  const { t } = useI18n()
  const { generate, rules: passwordRules } = usePasswordPolicy()
  const { confirm, notif } = useAppUtils()

  const props = defineProps({
    modelValue: Boolean,
    // The tenant list row being edited — null for create. Only `id` is
    // relied on; the dialog fetches the full edit shape itself (owner
    // fields aren't on the list row).
    tenant: { type: Object, default: null }
  })
  const emit = defineEmits(['update:modelValue', 'saved'])

  const tenantStore = useTenantStore()
  const businessTypeStore = useBusinessTypeStore()
  const { businessTypes } = storeToRefs(businessTypeStore)

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const isEdit = computed(() => !!props.tenant?.id)

  // ── State ──────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const loading = ref(false)
  const loadingTenant = ref(false)
  const buTypesLoading = ref(false)
  const showPassword = ref(false)
  const editingSlug = ref(false)
  const credentialGenerated = ref(false)
  const serverErrors = reactive({})
  const tempPasswordDialog = ref(false)
  const temporaryPassword = ref('')

  const defaultForm = () => ({
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
    currency: 'USD'
  })

  const form = reactive(defaultForm())

  const currencyOptions = [
    { value: 'USD', label: t('tenant_create.currency.usd') },
    { value: 'KHR', label: t('tenant_create.currency.khr') },
    { value: 'EUR', label: t('tenant_create.currency.eur') },
    { value: 'GBP', label: t('tenant_create.currency.gbp') },
    { value: 'SGD', label: t('tenant_create.currency.sgd') },
    { value: 'MYR', label: t('tenant_create.currency.myr') }
  ]

  const r = {
    required: v => !!v || t('products.rule.required'),
    email: v => /.+@.+\..+/.test(v) || t('tenant_create.rule.email')
  }

  // ── Populate / reset ─────────────────────────────────────────────────────────
  const populateForm = tenant => {
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
  }

  const resetForm = () => {
    Object.assign(form, defaultForm())
    showPassword.value = false
    editingSlug.value = false
    credentialGenerated.value = false
    Object.keys(serverErrors).forEach(k => delete serverErrors[k])
    formRef.value?.resetValidation()
  }

  // Driven entirely off visibility, not off `props.tenant` changing — the
  // same tenant row can be re-opened for edit twice in a row (close
  // unsaved, reopen) with an unchanged object reference, which a watcher
  // on `() => props.tenant` would miss entirely.
  watch(model, async val => {
    if (!val) {
      resetForm()
      return
    }

    buTypesLoading.value = true
    try {
      await businessTypeStore.fetchBusinessTypes()
    } finally {
      buTypesLoading.value = false
    }

    if (props.tenant?.id) {
      loadingTenant.value = true
      try {
        const full = await tenantStore.fetchTenantForEdit(props.tenant.id)
        if (full) populateForm(full)
      } finally {
        loadingTenant.value = false
      }
    }
  })

  // ── Helpers ───────────────────────────────────────────────────────────────────
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
          temporaryPassword.value = await tenantStore.resetOwnerPassword(props.tenant.id)
          tempPasswordDialog.value = true
        } catch {
          notif(t('tenant_create.reset_password_failed'), { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }

  // ── Server errors ───────────────────────────────────────────────────────────────
  const clearServerError = field => {
    delete serverErrors[field]
  }

  const handleServerErrors = errors => {
    Object.keys(serverErrors).forEach(k => delete serverErrors[k])
    if (!errors) return
    Object.entries(errors).forEach(([field, messages]) => {
      serverErrors[field] = Array.isArray(messages) ? messages : [messages]
    })
  }

  // ── Submit ──────────────────────────────────────────────────────────────────────
  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    loading.value = true
    try {
      if (isEdit.value) {
        await tenantStore.updateTenant(props.tenant.id, { ...form })
      } else {
        await tenantStore.createTenant({ ...form })
      }
      emit('saved')
      model.value = false
    } catch (err) {
      if (err?.response?.data?.errors) handleServerErrors(err.response.data.errors)
    } finally {
      loading.value = false
    }
  }

  const close = () => {
    if (loading.value) return
    model.value = false
  }
</script>

<style scoped>
  .form-section {
    padding: 16px 20px;
  }
  .form-section-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgb(var(--v-theme-primary));
    margin-bottom: 12px;
    display: flex;
    align-items: center;
  }
  .readonly-field :deep(.v-field) {
    background-color: rgba(var(--v-theme-on-surface), 0.06) !important;
  }
</style>
