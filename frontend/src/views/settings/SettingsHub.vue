<template>
  <v-container fluid class="pa-0">
    <custom-title
      icon="mdi-cog-outline"
      :title="t('settings_hub.title')"
      :subtitle="t('settings_hub.subtitle')"
    />

    <v-tabs v-model="activeTab" color="primary" class="mb-4" show-arrows>
      <v-tab value="company">{{ t('settings_hub.tabs.company') }}</v-tab>
      <v-tab value="branch">{{ t('settings_hub.tabs.branch') }}</v-tab>
      <v-tab value="pos">{{ t('settings_hub.tabs.pos') }}</v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <!-- ── Company Info ────────────────────────────────────────────────── -->
      <v-window-item value="company">
        <v-card rounded="lg" elevation="0" border class="pa-6">
          <v-alert
            v-if="!canEdit"
            type="info"
            variant="tonal"
            density="compact"
            rounded="lg"
            class="mb-5"
            :text="t('settings_hub.owner_only')"
          />

          <v-form ref="formRef" :disabled="!canEdit || saving">
            <v-row dense>
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.name"
                  :label="t('tenant_create.field.business_name')"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-domain"
                  :rules="[v => !!v || t('validation.required')]"
                  maxlength="150"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="form.logo_url"
                  :label="t('tenant_create.field.logo_url')"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-image-outline"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-color-input
                  v-model="form.primary_color"
                  color-pip
                  :label="t('tenant_create.field.brand_color')"
                  variant="outlined"
                  density="comfortable"
                  pip-location="prepend-inner"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.currency"
                  :items="currencyOptions"
                  item-title="label"
                  item-value="value"
                  :label="t('tenant_create.field.currency')"
                  variant="outlined"
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
                  :label="t('tenant_create.field.locale')"
                  variant="outlined"
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
                  :label="t('tenant_create.field.timezone')"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-clock-outline"
                />
              </v-col>
            </v-row>
          </v-form>

          <div v-if="canEdit" class="d-flex justify-end mt-2">
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              :loading="saving"
              prepend-icon="mdi-content-save-outline"
              @click="save"
            >
              {{ t('btn.save') }}
            </v-btn>
          </div>
        </v-card>
      </v-window-item>

      <!-- ── Branch ───────────────────────────────────────────────────────── -->
      <v-window-item value="branch">
        <Branch />
      </v-window-item>

      <!-- ── POS ──────────────────────────────────────────────────────────── -->
      <v-window-item value="pos">
        <v-card rounded="lg" elevation="0" border class="pa-6">
          <v-alert
            v-if="!canEdit"
            type="info"
            variant="tonal"
            density="compact"
            rounded="lg"
            class="mb-5"
            :text="t('settings_hub.owner_only')"
          />

          <p class="text-caption text-medium-emphasis mb-4">
            {{ t('settings_hub.pos_settings_helper') }}
          </p>

          <div class="mb-5">
            <div class="text-body-2 font-weight-bold mb-2">
              {{ t('settings_hub.pos_order_types') }}
            </div>
            <v-switch
              v-for="ot in orderTypeOptions"
              :key="ot.value"
              v-model="form.pos_settings.order_types"
              :value="ot.value"
              :label="ot.label"
              :disabled="!canEdit || saving"
              color="primary"
              density="compact"
              hide-details
              class="mb-1"
            />
          </div>

          <v-divider class="mb-5" />

          <v-switch
            v-model="form.pos_settings.customer_selection"
            :label="t('settings_hub.pos_customer_selection')"
            :disabled="!canEdit || saving"
            color="primary"
            density="compact"
            hide-details
            class="mb-3"
          />
          <v-switch
            v-model="form.pos_settings.order_notes"
            :label="t('settings_hub.pos_order_notes')"
            :disabled="!canEdit || saving"
            color="primary"
            density="compact"
            hide-details
          />

          <div v-if="canEdit" class="d-flex justify-end mt-5">
            <v-btn
              color="primary"
              variant="flat"
              rounded="lg"
              :loading="saving"
              prepend-icon="mdi-content-save-outline"
              @click="save"
            >
              {{ t('btn.save') }}
            </v-btn>
          </div>
        </v-card>
      </v-window-item>
    </v-window>
  </v-container>
</template>

<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import { useAppUtils } from '@/composables/useAppUtils'
  import { getTenantByIdApi, updateTenantProfileApi } from '@/api/tenantService'
  import Branch from '@/views/branches/Branch.vue'

  const { t } = useI18n()
  const route = useRoute()
  const authStore = useAuthStore()
  const { notif } = useAppUtils()

  const requestedTab = typeof route.query.tab === 'string' ? route.query.tab : null
  const activeTab = ref(requestedTab === 'branch' ? 'branch' : 'company')

  const canEdit = computed(() => authStore.isOwner || authStore.isSuperAdmin)

  const formRef = ref(null)
  const saving = ref(false)
  const form = reactive({
    name: '',
    logo_url: '',
    primary_color: '#6366f1',
    currency: 'USD',
    locale: 'en-US',
    timezone: 'Asia/Phnom_Penh',
    pos_settings: {
      order_types: ['dine_in', 'takeaway', 'delivery'],
      customer_selection: true,
      order_notes: true
    }
  })

  const orderTypeOptions = [
    { value: 'dine_in', label: t('pos.cart.order_type.dine_in') },
    { value: 'takeaway', label: t('pos.cart.order_type.takeaway') },
    { value: 'delivery', label: t('pos.cart.order_type.delivery') }
  ]

  onMounted(async () => {
    if (!authStore.tenant_id) return
    const res = await getTenantByIdApi(authStore.tenant_id).catch(() => null)
    const tenant = res?.data?.data?.tenant
    if (!tenant) return
    form.name = tenant.name ?? ''
    form.logo_url = tenant.logo_url ?? ''
    form.primary_color = tenant.primary_color ?? '#6366f1'
    form.currency = tenant.currency ?? 'USD'
    form.locale = tenant.locale ?? 'en-US'
    form.timezone = tenant.timezone ?? 'Asia/Phnom_Penh'
    if (tenant.pos_settings) {
      form.pos_settings.order_types = tenant.pos_settings.order_types ?? form.pos_settings.order_types
      form.pos_settings.customer_selection = tenant.pos_settings.customer_selection ?? true
      form.pos_settings.order_notes = tenant.pos_settings.order_notes ?? true
    }
  })

  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    saving.value = true
    try {
      await updateTenantProfileApi(authStore.tenant_id, { ...form })
      await authStore.fetchMe()
      notif(t('settings_hub.saved'), { type: 'success' })
    } catch (err) {
      notif(err?.response?.data?.message || t('settings_hub.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

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
</script>
