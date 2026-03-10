<template>
  <v-dialog v-model="model" max-width="700" persistent scrollable>
    <v-card rounded="xl" elevation="0" border>
      <!-- ── Header ──────────────────────────────────────────────────────── -->
      <v-card-title class="pa-5 pb-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar
              :color="isEdit ? 'primary' : 'success'"
              size="42"
              rounded="lg"
              variant="tonal"
            >
              <v-icon
                :icon="
                  isEdit ? 'mdi-store-edit-outline' : 'mdi-store-plus-outline'
                "
                size="20"
              />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">
                {{ isEdit ? 'Edit Branch' : 'New Branch' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  isEdit
                    ? 'Update branch information'
                    : 'Add a new branch to your business'
                }}
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <!-- ── Body ────────────────────────────────────────────────────────── -->
      <v-card-text class="pa-0" style="max-height: 70vh">
        <v-form ref="formRef" @submit.prevent="submit">
          <!-- Tenant -->
          <div class="form-section">
            <div class="form-section-label">Tenant</div>
            <v-select
              v-model="form.tenant_id"
              :items="tenants"
              item-title="name"
              item-value="id"
              label="Select Tenant"
              variant="outlined"
              rounded="lg"
              :rules="rules.tenant_id"
              prepend-inner-icon="mdi-domain"
            />
          </div>

          <v-divider />

          <!-- Branch Info -->
          <div class="form-section">
            <div class="form-section-label">Branch Info</div>
            <v-row dense>
              <v-col cols="12" sm="8">
                <v-text-field
                  v-model="form.name"
                  label="Branch Name *"
                  variant="outlined"
                  rounded="lg"
                  :rules="rules.name"
                  prepend-inner-icon="mdi-storefront-outline"
                  maxlength="150"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="form.type"
                  :items="typeOptions"
                  item-title="label"
                  item-value="value"
                  label="Type *"
                  variant="outlined"
                  rounded="lg"
                  :rules="rules.type"
                >
                  <template #item="{ props: p, item }">
                    <v-list-item v-bind="p">
                      <template #prepend>
                        <v-icon :icon="item.raw.icon" size="16" class="mr-1" />
                      </template>
                    </v-list-item>
                  </template>
                  <template #selection="{ item }">
                    <div class="d-flex align-center gap-1">
                      <v-icon :icon="item.raw.icon" size="15" />
                      <span class="text-body-2">{{ item.raw.label }}</span>
                    </div>
                  </template>
                </v-select>
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- Address -->
          <div class="form-section">
            <div class="form-section-label">Address</div>
            <v-row dense>
              <v-col cols="12">
                <v-text-field
                  v-model="form.address_line1"
                  label="Address Line 1 *"
                  variant="outlined"
                  rounded="lg"
                  :rules="rules.address_line1"
                  prepend-inner-icon="mdi-map-marker-outline"
                  maxlength="255"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.address_line2"
                  label="Address Line 2"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-map-marker-outline"
                  maxlength="255"
                />
              </v-col>
              <v-col cols="5">
                <v-text-field
                  v-model="form.city"
                  label="City *"
                  variant="outlined"
                  rounded="lg"
                  :rules="rules.city"
                  maxlength="100"
                />
              </v-col>
              <v-col cols="4">
                <v-text-field
                  v-model="form.state"
                  label="State"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  maxlength="100"
                />
              </v-col>
              <v-col cols="3">
                <v-text-field
                  v-model="form.postal_code"
                  label="Postal"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  maxlength="20"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.country"
                  label="Country"
                  variant="outlined"
                  rounded="lg"
                  hide-details
                  maxlength="100"
                />
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- Contact -->
          <div class="form-section">
            <div class="form-section-label">Contact</div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.phone"
                  label="Phone"
                  variant="outlined"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.phone"
                  prepend-inner-icon="mdi-phone-outline"
                  maxlength="30"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.email"
                  label="Email"
                  type="email"
                  variant="outlined"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.email"
                  prepend-inner-icon="mdi-email-outline"
                  maxlength="255"
                />
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- Rates -->
          <div class="form-section">
            <div class="form-section-label">Rates & Charges</div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.tax_rate"
                  label="Tax Rate"
                  type="number"
                  variant="outlined"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.tax_rate"
                  prepend-inner-icon="mdi-percent"
                  step="0.0001"
                  min="0"
                  max="9.9999"
                  hint="e.g. 0.1500 = 15%"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.service_charge_rate"
                  label="Service Charge"
                  type="number"
                  variant="outlined"
                  rounded="lg"
                  hide-details="auto"
                  :rules="rules.service_charge_rate"
                  prepend-inner-icon="mdi-room-service-outline"
                  step="0.0001"
                  min="0"
                  max="9.9999"
                  hint="e.g. 0.1000 = 10%"
                  persistent-hint
                />
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- Settings -->
          <div class="form-section">
            <div class="form-section-label">Settings</div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-card
                  rounded="lg"
                  border
                  elevation="0"
                  class="px-4 py-3 d-flex align-center justify-space-between"
                >
                  <div>
                    <div class="text-body-2 font-weight-medium">Open</div>
                    <div class="text-caption text-grey">Accepting orders</div>
                  </div>
                  <v-switch
                    v-model="form.is_open"
                    color="success"
                    inset
                    hide-details
                  />
                </v-card>
              </v-col>
              <v-col cols="12" sm="6">
                <v-card
                  rounded="lg"
                  border
                  elevation="0"
                  class="px-4 py-3 d-flex align-center justify-space-between"
                >
                  <div>
                    <div class="text-body-2 font-weight-medium">Active</div>
                    <div class="text-caption text-grey">
                      Visible and operational
                    </div>
                  </div>
                  <v-switch
                    v-model="form.is_active"
                    color="primary"
                    inset
                    hide-details
                  />
                </v-card>
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="form.receipt_footer"
                  label="Receipt Footer"
                  variant="outlined"
                  rounded="lg"
                  rows="2"
                  hide-details
                  prepend-inner-icon="mdi-receipt-text-outline"
                  placeholder="e.g. Thank you for visiting!"
                  class="mt-2"
                />
              </v-col>
            </v-row>
          </div>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ── Actions ──────────────────────────────────────────────────────── -->
      <v-card-actions class="pa-4 gap-2">
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
          Cancel
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :loading="loading"
          :prepend-icon="isEdit ? 'mdi-content-save-outline' : 'mdi-plus'"
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Create Branch' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { useTenantStore } from '@/stores/tenantStore'

  const props = defineProps({
    modelValue: Boolean,
    branch: Object
  })
  const emit = defineEmits(['update:modelValue', 'saved'])

  const formRef = ref(null)
  const loading = ref(false)
  const tenantStore = useTenantStore()
  const tenants = ref([])

  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })
  const isEdit = computed(() => !!props.branch?.id)

  const typeOptions = [
    {
      value: 'restaurant',
      label: 'Restaurant',
      icon: 'mdi-silverware-fork-knife'
    },
    { value: 'cafe', label: 'Cafe', icon: 'mdi-coffee' },
    { value: 'kiosk', label: 'Kiosk', icon: 'mdi-store-outline' },
    { value: 'food_truck', label: 'Food Truck', icon: 'mdi-truck-outline' }
  ]

  const defaultForm = () => ({
    id: null,
    tenant_id: null,
    name: '',
    type: 'restaurant',
    address_line1: '',
    address_line2: '',
    city: '',
    state: '',
    country: 'Cambodia',
    postal_code: '',
    phone: '',
    email: '',
    tax_rate: 0,
    service_charge_rate: 0,
    receipt_footer: '',
    is_open: true,
    is_active: true
  })

  const form = reactive(defaultForm())

  watch(
    () => props.branch,
    val => {
      Object.assign(form, val ? val : defaultForm())
    },
    { immediate: true }
  )

  const isDecimal54 = v => {
    const num = parseFloat(v)
    if (isNaN(num)) return 'Must be a number'
    if (num < 0) return 'Must be 0 or greater'
    if (num >= 10) return 'Must be less than 10'
    return true
  }

  const rules = {
    tenant_id: [v => !!v || 'Tenant is required'],
    name: [
      v => !!v || 'Branch name is required',
      v => v?.length <= 150 || 'Max 150 chars'
    ],
    type: [v => !!v || 'Type is required'],
    address_line1: [v => !!v || 'Address is required'],
    city: [v => !!v || 'City is required'],
    phone: [v => !v || /^[+\d\s().-]+$/.test(v) || 'Invalid phone'],
    email: [v => !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Invalid email'],
    tax_rate: [
      v => (v !== '' && v !== null) || 'Required',
      v => isDecimal54(v)
    ],
    service_charge_rate: [v => !v || isDecimal54(v) === true || isDecimal54(v)]
  }

  onMounted(async () => {
    await tenantStore.fetchTenants()
    tenants.value = tenantStore.tenants
  })

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    loading.value = true
    try {
      emit('saved', { ...form })
      close()
    } finally {
      loading.value = false
    }
  }

  const close = () => {
    model.value = false
    formRef.value?.reset()
    Object.assign(form, defaultForm())
  }
</script>

<style scoped>
  .form-section {
    padding: 18px 20px;
  }
  .form-section-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgb(var(--v-theme-primary));
    margin-bottom: 12px;
  }
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
