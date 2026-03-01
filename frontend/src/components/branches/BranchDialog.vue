<template>
  <v-dialog v-model="model" max-width="750" persistent>
    <v-card rounded="lg">
      <!-- Header -->
      <v-card-title class="d-flex justify-space-between align-center">
        <span>{{ isEdit ? 'Edit Branch' : 'Create Branch' }}</span>
        <v-btn icon="mdi-close" variant="text" @click="close" />
      </v-card-title>

      <v-divider />

      <!-- Form -->
      <v-card-text>
        <v-form ref="formRef" @submit.prevent="submit">
          <v-row dense>
            <v-col cols="12" md="6">
              <v-select
                v-model="form.tenant_id"
                :items="tenants"
                item-title="name"
                item-value="id"
                label="Tenant *"
                variant="outlined"
                :rules="rules.tenant_id"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.name"
                label="Branch Name *"
                variant="outlined"
                :rules="rules.name"
                maxlength="150"
                counter
              />
            </v-col>
          </v-row>

          <v-row class="mt-4" dense>
            <v-col cols="12" md="6">
              <v-select
                v-model="form.type"
                :items="types"
                label="Type *"
                variant="outlined"
                :rules="rules.type"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.address_line1"
                label="Address Line 1 *"
                variant="outlined"
                :rules="rules.address_line1"
                maxlength="255"
              />
            </v-col>
          </v-row>
          <v-row dense>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.city"
                label="City *"
                variant="outlined"
                :rules="rules.city"
                maxlength="100"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.country"
                label="Country"
                variant="outlined"
                maxlength="100"
              />
            </v-col>
          </v-row>

          <v-row dense>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.phone"
                label="Phone"
                variant="outlined"
                :rules="rules.phone"
                maxlength="30"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.email"
                label="Email"
                variant="outlined"
                :rules="rules.email"
                maxlength="255"
              />
            </v-col>
          </v-row>

          <v-row dense>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.tax_rate"
                label="Tax Rate (0 – 9.9999)"
                type="number"
                variant="outlined"
                :rules="rules.tax_rate"
                hint="Decimal value e.g. 0.1500 = 15%"
                persistent-hint
                step="0.0001"
                min="0"
                max="9.9999"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.service_charge_rate"
                label="Service Charge Rate (0 – 9.9999)"
                type="number"
                variant="outlined"
                :rules="rules.service_charge_rate"
                hint="Decimal value e.g. 0.1000 = 10%"
                persistent-hint
                step="0.0001"
                min="0"
                max="9.9999"
              />
            </v-col>
          </v-row>
          <v-row class="mt-4">
            <v-col cols="6">
              <v-switch
                v-model="form.is_open"
                label="Open"
                inset
                color="success"
                hide-details
              />
            </v-col>
            <v-col cols="6">
              <v-switch
                inset
                v-model="form.is_active"
                label="Active"
                color="success"
                hide-details
              />
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-textarea
                v-model="form.receipt_footer"
                label="Receipt Footer"
                rows="2"
                variant="outlined"
                hide-details
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- Actions -->
      <v-card-actions>
        <v-spacer />
        <v-btn variant="outlined" @click="close">Cancel</v-btn>
        <v-btn color="primary" :loading="loading" @click="submit">
          {{ isEdit ? 'Update' : 'Create' }}
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

  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  const isEdit = computed(() => !!props.branch?.id)
  const types = ['restaurant', 'cafe', 'kiosk', 'food_truck']
  const tenants = ref([])

  // ─── Validation Rules ────────────────────────────────────────────────────────

  const isDecimal54 = v => {
    const num = parseFloat(v)
    // decimal(5,4): absolute value must be < 10  (i.e. 0.0000 – 9.9999)
    if (isNaN(num)) return 'Must be a number'
    if (num < 0) return 'Must be 0 or greater'
    if (num >= 10) return 'Must be less than 10 (e.g. 0.1500 for 15%)'
    // max 4 decimal places
    if (!/^\d(\.\d{1,4})?$/.test(String(num)) && !/^\d{1}(\.\d{0,4})?$/.test(v))
      return 'Max 4 decimal places allowed'
    return true
  }

  const rules = {
    tenant_id: [v => !!v || 'Tenant is required'],
    name: [
      v => !!v || 'Branch name is required',
      v => (v && v.length <= 150) || 'Max 150 characters'
    ],
    type: [
      v => !!v || 'Type is required',
      v =>
        ['restaurant', 'cafe', 'kiosk', 'food_truck'].includes(v) ||
        'Invalid type'
    ],
    address_line1: [
      v => !!v || 'Address Line 1 is required',
      v => (v && v.length <= 255) || 'Max 255 characters'
    ],
    city: [
      v => !!v || 'City is required',
      v => (v && v.length <= 100) || 'Max 100 characters'
    ],
    postal_code: [v => !v || v.length <= 20 || 'Max 20 characters'],
    phone: [
      v => !v || v.length <= 30 || 'Max 30 characters',
      v => !v || /^[+\d\s().-]+$/.test(v) || 'Invalid phone format'
    ],
    email: [
      v => !v || v.length <= 255 || 'Max 255 characters',
      v => !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Invalid email format'
    ],
    // ⚠️ Key fix: decimal(5,4) → must be 0.0000 – 9.9999
    tax_rate: [
      v => (v !== '' && v !== null) || 'Tax rate is required',
      v => isDecimal54(v)
    ],
    service_charge_rate: [
      v => v === '' || v === null || isDecimal54(v) === true || isDecimal54(v)
    ]
  }

  // ─────────────────────────────────────────────────────────────────────────────

  const fetchTenants = async () => {
    await tenantStore.fetchTenants()
    tenants.value = tenantStore.tenants
  }

  onMounted(fetchTenants)

  watch(
    () => props.branch,
    val => {
      if (val) Object.assign(form, val)
      else Object.assign(form, defaultForm())
    },
    { immediate: true }
  )

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
