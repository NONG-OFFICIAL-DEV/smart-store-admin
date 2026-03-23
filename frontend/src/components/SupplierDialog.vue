<template>
  <v-dialog v-model="model" max-width="580" persistent scrollable>
    <v-card rounded="xl" elevation="0" border>
      <!-- ── Header ──────────────────────────────────────────────────── -->
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
                  isEdit ? 'mdi-truck-edit-outline' : 'mdi-truck-plus-outline'
                "
                size="20"
              />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">
                {{ isEdit ? 'Edit Supplier' : 'Add Supplier' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  isEdit
                    ? 'Update supplier details'
                    : 'Add a new supplier to your business'
                }}
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <!-- ── Form ────────────────────────────────────────────────────── -->
      <v-card-text class="pa-0" style="max-height: 65vh">
        <v-form ref="formRef">
          <!-- Basic Info -->
          <div class="form-section">
            <v-select
              v-if="isSuperAdmin()"
              v-model="form.tenant_id"
              :items="tenants"
              item-title="name"
              item-value="id"
              label="Tenant"
              :rules="[r.required]"
            />
            <div class="form-section-label">
              <v-icon icon="mdi-information-outline" size="13" class="mr-1" />
              Basic Info
            </div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.name"
                  label="Supplier Name *"
                  variant="outlined"
                  rounded="lg"
                  :rules="[r.required]"
                  prepend-inner-icon="mdi-domain"
                  maxlength="150"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.contact_person"
                  label="Contact Person"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-account-outline"
                  maxlength="100"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.phone"
                  label="Phone"
                  variant="outlined"
                  rounded="lg"
                  :rules="[r.phone]"
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
                  :rules="[r.email]"
                  prepend-inner-icon="mdi-email-outline"
                  maxlength="255"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.address"
                  label="Address"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-map-marker-outline"
                />
              </v-col>
            </v-row>
          </div>

          <v-divider />

          <!-- Payment & Settings -->
          <div class="form-section">
            <div class="form-section-label">
              <v-icon icon="mdi-cash-outline" size="13" class="mr-1" />
              Payment & Settings
            </div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-combobox
                  v-model="form.payment_terms"
                  :items="paymentTermOptions"
                  label="Payment Terms"
                  variant="outlined"
                  rounded="lg"
                  prepend-inner-icon="mdi-file-sign"
                  placeholder="e.g. Net 30, COD"
                  hide-details
                />
              </v-col>
              <v-col cols="12" sm="6" class="d-flex align-center">
                <v-card
                  rounded="lg"
                  border
                  elevation="0"
                  class="px-3 py-2 d-flex align-center justify-space-between w-100"
                >
                  <div>
                    <div class="text-body-2 font-weight-medium">Active</div>
                    <div class="text-caption text-grey">
                      Available for orders
                    </div>
                  </div>
                  <v-switch
                    v-model="form.is_active"
                    color="success"
                    inset
                    hide-details
                    density="compact"
                  />
                </v-card>
              </v-col>
            </v-row>
          </div>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ── Actions ──────────────────────────────────────────────────── -->
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
          @click="save"
        >
          {{ isEdit ? 'Save Changes' : 'Add Supplier' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useTenantStore } from '@/stores/tenantStore'
  import { usePermission } from '@/composables/usePermission'
  const { isSuperAdmin } = usePermission()
  const props = defineProps({
    modelValue: Boolean,
    supplier: { type: Object, default: null },
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const formRef = ref(null)
  const tenantStore = useTenantStore()
  const { tenants } = storeToRefs(tenantStore)
  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.supplier?.id)

  const paymentTermOptions = ['Net 30', 'Net 60', 'Net 90', 'COD', 'Prepaid']

  // ── Default form — matches DB schema exactly ───────────────────────────────────
  const defaultForm = () => ({
    tenant_id: null,
    name: '',
    contact_person: '', // ← schema: contact_person (not contact_name)
    phone: '',
    email: '',
    address: '',
    payment_terms: null,
    is_active: true // ← schema: is_active boolean (not status int)
  })

  const form = reactive(defaultForm())

  watch(
    () => props.supplier,
    val => {
      Object.assign(
        form,
        val
          ? {
              tenant_id: val.tenant_id ?? null,
              name: val.name ?? '',
              contact_person: val.contact_person ?? '',
              phone: val.phone ?? '',
              email: val.email ?? '',
              address: val.address ?? '',
              payment_terms: val.payment_terms ?? null,
              is_active: val.is_active ?? true
            }
          : defaultForm()
      )
    },
    { immediate: true }
  )

  // ── Rules ──────────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || 'Required',
    email: v => !v || /.+@.+\..+/.test(v) || 'Invalid email',
    phone: v => !v || /^[+\d\s().-]+$/.test(v) || 'Invalid phone'
  }

  // ── Submit ─────────────────────────────────────────────────────────────────────
  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    const payload = {
      ...(isEdit.value ? { id: props.supplier.id } : {}),
      name: form.name,
      contact_person: form.contact_person,
      phone: form.phone,
      email: form.email,
      address: form.address,
      payment_terms: form.payment_terms,
      is_active: form.is_active
    }

    // ✅ Only include tenant_id if super admin and has value
    if (isSuperAdmin() && form.tenant_id) {
      payload.tenant_id = form.tenant_id
    }

    emit('save', payload)
  }

  const close = () => {
    model.value = false
    formRef.value?.reset()
    Object.assign(form, defaultForm())
  }
  onMounted(async () => {
    if (isSuperAdmin()) tenantStore.fetchTenants()
  })
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
  .w-100 {
    width: 100%;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
