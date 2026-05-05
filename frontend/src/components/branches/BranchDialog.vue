<template>
  <v-dialog v-model="model" max-width="660" persistent scrollable>
    <v-card rounded="xl" elevation="0" border>
      <!-- ───────── HEADER ───────── -->
      <v-card-title class="pa-0">
        <div class="d-flex align-center gap-3 pa-5 pb-4">
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
            />
          </v-avatar>

          <div>
            <div class="text-body-1 font-weight-bold">
              {{ isEdit ? 'Edit Branch' : 'Create Branch' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{
                isEdit
                  ? 'Update branch details'
                  : 'Add a new branch to your network'
              }}
            </div>
          </div>

          <v-spacer />
          <v-btn
            icon="mdi-close"
            variant="text"
            density="comfortable"
            @click="close"
          />
        </div>

        <!-- ───────── STEPPER ───────── -->
        <div class="stepper-bar px-5 pb-4">
          <div class="stepper-track">
            <template v-for="(step, i) in steps" :key="i">
              <div
                class="step-item"
                :class="{
                  'step-active': i === currentStep,
                  'step-done': i < currentStep
                }"
                @click="tryJumpTo(i)"
              >
                <div class="step-dot">
                  <v-icon v-if="i < currentStep" icon="mdi-check" size="13" />
                  <span v-else>{{ i + 1 }}</span>
                </div>
                <span class="step-label">{{ step.label }}</span>
              </div>

              <div
                v-if="i < steps.length - 1"
                class="step-connector"
                :class="{ 'connector-done': i < currentStep }"
              />
            </template>
          </div>
        </div>
      </v-card-title>

      <v-divider />

      <!-- ───────── BODY ───────── -->
      <v-card-text class="pa-0" style="max-height: 60vh; overflow-y: auto">
        <v-form ref="formRef" @submit.prevent>
          <v-window v-model="currentStep" :touch="false">
            <!-- STEP 0: Tenant -->
            <v-window-item :value="0">
              <div class="step-content pa-5">
                <div class="step-section-title mb-4">Tenant information</div>

                <v-row dense>
                  <v-col cols="12" md="6" v-if="isSuperAdmin()">
                    <v-select
                      v-model="form.tenant_id"
                      :items="tenants"
                      item-title="name"
                      item-value="id"
                      label="Tenant *"
                      variant="outlined"
                      rounded="lg"
                      :rules="[rules.required]"
                      prepend-inner-icon="mdi-domain"
                    />
                  </v-col>

                  <v-col cols="12" md="6">
                    <v-select
                      v-model="form.business_type_id"
                      :items="tenantStore.businessTypes"
                      item-title="name"
                      item-value="id"
                      label="Business type"
                      variant="outlined"
                      rounded="lg"
                      disabled
                      prepend-inner-icon="mdi-shape-outline"
                    />
                  </v-col>
                </v-row>

                <v-alert
                  type="info"
                  variant="tonal"
                  rounded="lg"
                  density="compact"
                  class="mt-2"
                  icon="mdi-information-outline"
                >
                  Business type is automatically assigned from the selected
                  tenant.
                </v-alert>
              </div>
            </v-window-item>

            <!-- STEP 1: Branch details -->
            <v-window-item :value="1">
              <div class="step-content pa-5">
                <div class="step-section-title mb-4">Branch details</div>

                <v-row dense>
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.name"
                      label="Branch name *"
                      variant="outlined"
                      rounded="lg"
                      :rules="[rules.required]"
                      prepend-inner-icon="mdi-storefront-outline"
                      placeholder="e.g. Phnom Penh Central"
                    />
                  </v-col>

                  <v-col cols="12" md="6">
                    <v-select
                      v-model="form.branch_type_id"
                      :items="tenantStore.branchTypes"
                      item-title="name"
                      item-value="id"
                      label="Branch type"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-store-outline"
                    />
                  </v-col>

                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="form.phone"
                      label="Phone"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-phone-outline"
                      placeholder="+855 ..."
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="form.email"
                      label="Email"
                      type="email"
                      variant="outlined"
                      rounded="lg"
                      :rules="[rules.email]"
                      prepend-inner-icon="mdi-email-outline"
                      placeholder="branch@example.com"
                    />
                  </v-col>
                </v-row>
              </div>
            </v-window-item>

            <!-- STEP 2: Address + Settings -->
            <v-window-item :value="2">
              <div class="step-content pa-5">
                <div class="step-section-title mb-4">Address</div>

                <v-row dense>
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.address_line1"
                      label="Address line 1"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-map-marker-outline"
                      placeholder="Street address"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="form.address_line2"
                      label="Address line 2"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-map-marker-outline"
                      placeholder="Suite, floor, unit..."
                      hint="Optional"
                      persistent-hint
                    />
                  </v-col>

                  <v-col cols="6">
                    <v-text-field
                      v-model="form.city"
                      label="City"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-city-variant-outline"
                    />
                  </v-col>

                  <v-col cols="6">
                    <v-text-field
                      v-model="form.country"
                      label="Country"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-flag-outline"
                    />
                  </v-col>
                </v-row>

                <v-divider class="my-4" />

                <div class="step-section-title mb-4">Status settings</div>

                <v-row dense>
                  <v-col cols="6">
                    <v-card rounded="lg" variant="outlined" class="pa-4">
                      <div class="d-flex align-center justify-space-between">
                        <div>
                          <div class="text-body-2 font-weight-medium">
                            Branch is open
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            Visible and accepting customers
                          </div>
                        </div>
                        <v-switch
                          v-model="form.is_open"
                          color="primary"
                          hide-details
                          density="compact"
                          inset
                        />
                      </div>
                    </v-card>
                  </v-col>

                  <v-col cols="6">
                    <v-card rounded="lg" variant="outlined" class="pa-4">
                      <div class="d-flex align-center justify-space-between">
                        <div>
                          <div class="text-body-2 font-weight-medium">
                            Branch is active
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            Enabled and operational
                          </div>
                        </div>
                        <v-switch
                          v-model="form.is_active"
                          color="primary"
                          hide-details
                          density="compact"
                          inset
                        />
                      </div>
                    </v-card>
                  </v-col>
                </v-row>
              </div>
            </v-window-item>

            <!-- STEP 3: Review -->
            <v-window-item :value="3">
              <div class="step-content pa-5">
                <div class="step-section-title mb-4">
                  Review before {{ isEdit ? 'updating' : 'creating' }}
                </div>

                <v-row dense>
                  <!-- Tenant block -->
                  <v-col cols="12">
                    <div class="review-block mb-3">
                      <div
                        class="review-block-header d-flex align-center justify-space-between"
                      >
                        <div class="d-flex align-center gap-2">
                          <v-icon icon="mdi-domain" size="16" color="primary" />
                          <span
                            class="text-caption font-weight-medium text-uppercase"
                            style="letter-spacing: 0.5px"
                          >
                            Tenant
                          </span>
                        </div>
                        <v-btn
                          variant="text"
                          size="x-small"
                          color="primary"
                          @click="currentStep = 0"
                        >
                          Edit
                        </v-btn>
                      </div>
                      <v-row dense class="mt-1">
                        <v-col cols="6">
                          <div class="review-field">
                            <span class="review-label">Tenant</span>
                            <span class="review-value">
                              {{ tenantName || '—' }}
                            </span>
                          </div>
                        </v-col>
                        <v-col cols="6">
                          <div class="review-field">
                            <span class="review-label">Business type</span>
                            <span class="review-value">
                              {{ businessTypeName || '—' }}
                            </span>
                          </div>
                        </v-col>
                      </v-row>
                    </div>
                  </v-col>

                  <!-- Branch block -->
                  <v-col cols="12">
                    <div class="review-block mb-3">
                      <div
                        class="review-block-header d-flex align-center justify-space-between"
                      >
                        <div class="d-flex align-center gap-2">
                          <v-icon
                            icon="mdi-storefront-outline"
                            size="16"
                            color="primary"
                          />
                          <span
                            class="text-caption font-weight-medium text-uppercase"
                            style="letter-spacing: 0.5px"
                          >
                            Branch
                          </span>
                        </div>
                        <v-btn
                          variant="text"
                          size="x-small"
                          color="primary"
                          @click="currentStep = 1"
                        >
                          Edit
                        </v-btn>
                      </div>
                      <v-row dense class="mt-1">
                        <v-col cols="6">
                          <div class="review-field">
                            <span class="review-label">Name</span>
                            <span class="review-value">
                              {{ form.name || '—' }}
                            </span>
                          </div>
                        </v-col>
                        <v-col cols="6">
                          <div class="review-field">
                            <span class="review-label">Type</span>
                            <span class="review-value">
                              {{ branchTypeName || '—' }}
                            </span>
                          </div>
                        </v-col>
                        <v-col cols="6">
                          <div class="review-field">
                            <span class="review-label">Phone</span>
                            <span class="review-value">
                              {{ form.phone || '—' }}
                            </span>
                          </div>
                        </v-col>
                        <v-col cols="6">
                          <div class="review-field">
                            <span class="review-label">Email</span>
                            <span class="review-value">
                              {{ form.email || '—' }}
                            </span>
                          </div>
                        </v-col>
                      </v-row>
                    </div>
                  </v-col>

                  <!-- Address block -->
                  <v-col cols="12">
                    <div class="review-block mb-3">
                      <div
                        class="review-block-header d-flex align-center justify-space-between"
                      >
                        <div class="d-flex align-center gap-2">
                          <v-icon
                            icon="mdi-map-marker-outline"
                            size="16"
                            color="primary"
                          />
                          <span
                            class="text-caption font-weight-medium text-uppercase"
                            style="letter-spacing: 0.5px"
                          >
                            Address
                          </span>
                        </div>
                        <v-btn
                          variant="text"
                          size="x-small"
                          color="primary"
                          @click="currentStep = 2"
                        >
                          Edit
                        </v-btn>
                      </div>
                      <v-row dense class="mt-1">
                        <v-col cols="12">
                          <div class="review-field">
                            <span class="review-label">Full address</span>
                            <span class="review-value">
                              {{ fullAddress || '—' }}
                            </span>
                          </div>
                        </v-col>
                      </v-row>
                    </div>
                  </v-col>

                  <!-- Status block -->
                  <v-col cols="12">
                    <div class="review-block">
                      <div
                        class="review-block-header d-flex align-center justify-space-between"
                      >
                        <div class="d-flex align-center gap-2">
                          <v-icon
                            icon="mdi-toggle-switch-outline"
                            size="16"
                            color="primary"
                          />
                          <span
                            class="text-caption font-weight-medium text-uppercase"
                            style="letter-spacing: 0.5px"
                          >
                            Status
                          </span>
                        </div>
                        <v-btn
                          variant="text"
                          size="x-small"
                          color="primary"
                          @click="currentStep = 2"
                        >
                          Edit
                        </v-btn>
                      </div>
                      <div class="d-flex gap-3 mt-2">
                        <v-chip
                          :color="form.is_open ? 'success' : 'default'"
                          :variant="form.is_open ? 'tonal' : 'outlined'"
                          size="small"
                          :prepend-icon="
                            form.is_open
                              ? 'mdi-check-circle-outline'
                              : 'mdi-close-circle-outline'
                          "
                        >
                          {{ form.is_open ? 'Open' : 'Closed' }}
                        </v-chip>
                        <v-chip
                          :color="form.is_active ? 'success' : 'default'"
                          :variant="form.is_active ? 'tonal' : 'outlined'"
                          size="small"
                          :prepend-icon="
                            form.is_active
                              ? 'mdi-check-circle-outline'
                              : 'mdi-close-circle-outline'
                          "
                        >
                          {{ form.is_active ? 'Active' : 'Inactive' }}
                        </v-chip>
                      </div>
                    </div>
                  </v-col>
                </v-row>
              </div>
            </v-window-item>
          </v-window>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ───────── FOOTER ───────── -->
      <v-card-actions class="pa-4">
        <div class="text-caption text-medium-emphasis">
          Step {{ currentStep + 1 }} of {{ steps.length }}
        </div>

        <v-spacer />

        <v-btn
          v-if="currentStep > 0"
          variant="tonal"
          prepend-icon="mdi-arrow-left"
          @click="prevStep"
        >
          Back
        </v-btn>

        <v-btn variant="tonal" @click="close">Cancel</v-btn>

        <v-btn
          v-if="currentStep < steps.length - 1"
          color="primary"
          append-icon="mdi-arrow-right"
          @click="nextStep"
        >
          Continue
        </v-btn>

        <v-btn
          v-else
          color="primary"
          :loading="loading"
          prepend-icon="mdi-check"
          @click="submit"
        >
          {{ isEdit ? 'Update Branch' : 'Create Branch' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { useTenantStore } from '@/stores/tenantStore'
  import { usePermission } from '@/composables/usePermission'

  const { isSuperAdmin } = usePermission()
  const tenantStore = useTenantStore()

  // ─── Props / Emits ────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: Boolean,
    branch: Object
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  // ─── Dialog model ─────────────────────────────────────────────────────────────
  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const isEdit = computed(() => !!props.branch?.id)

  // ─── Stepper ──────────────────────────────────────────────────────────────────
  const steps = [
    { label: 'Tenant' },
    { label: 'Branch' },
    { label: 'Location' },
    { label: 'Review' }
  ]

  const currentStep = ref(0)

  // ─── Form ─────────────────────────────────────────────────────────────────────
  const loading = ref(false)
  const formRef = ref(null)
  const tenants = ref([])

  const defaultForm = () => ({
    id: null,
    tenant_id: null,
    business_type_id: null,
    branch_type_id: null,
    name: '',
    address_line1: '',
    address_line2: '',
    city: '',
    country: 'Cambodia',
    phone: '',
    email: '',
    is_open: true,
    is_active: true
  })

  const form = reactive(defaultForm())

  // ─── Validation rules ─────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || 'This field is required',
    email: v => !v || /.+@.+\..+/.test(v) || 'Invalid email address'
  }

  // Fields that belong to each step (used for per-step validation)
  const stepFields = {
    0: ['tenant_id'],
    1: ['name', 'email'],
    2: [],
    3: []
  }

  // ─── Computed display values ───────────────────────────────────────────────────
  const tenantName = computed(
    () => tenants.value.find(t => t.id === form.tenant_id)?.name
  )

  const businessTypeName = computed(
    () =>
      tenantStore.businessTypes?.find(b => b.id === form.business_type_id)?.name
  )

  const branchTypeName = computed(
    () => tenantStore.branchTypes?.find(b => b.id === form.branch_type_id)?.name
  )

  const fullAddress = computed(() =>
    [form.address_line1, form.address_line2, form.city, form.country]
      .filter(Boolean)
      .join(', ')
  )

  const handleTenantChange = async tenantId => {
    if (!tenantId) return

    const tenant = tenants.value.find(t => t.id === tenantId) || form.tenant // fallback from API

    form.business_type_id = tenant?.business_type_id ?? null

    if (form.business_type_id) {
      await tenantStore.fetchBranchTypeByBusinessType(form.business_type_id)
    }
  }
  // ─── Watch: populate form when editing ────────────────────────────────────────

  watch(
    () => props.modelValue,
    async open => {
      if (open && form.tenant_id) {
        await handleTenantChange(form.tenant_id)
      }
    }
  )

  watch(
    () => props.branch,
    async val => {
      Object.assign(form, val ? { ...defaultForm(), ...val } : defaultForm())

      // ✅ FIX: fallback from tenant
      if (val?.tenant?.business_type_id) {
        form.business_type_id = val.tenant.business_type_id
      }

      currentStep.value = 0

      // ✅ trigger loading
      if (form.tenant_id) {
        await handleTenantChange(form.tenant_id)
      }
    },
    { immediate: true }
  )

  // ─── Watch: auto-fill business type + branch types when tenant changes ─────────
  watch(
    () => form.tenant_id,
    async tenantId => {
      await handleTenantChange(tenantId)
    }
  )

  // ─── Load data ────────────────────────────────────────────────────────────────
  onMounted(async () => {
    await tenantStore.fetchTenants()
    await tenantStore.fetchBusinessTypes()
    tenants.value = tenantStore.tenants
  })

  // ─── Stepper navigation ───────────────────────────────────────────────────────
  const validateCurrentStep = async () => {
    if (!formRef.value) return true
    const fields = stepFields[currentStep.value]
    if (!fields?.length) return true
    const { valid } = await formRef.value.validate()
    return valid
  }

  const nextStep = async () => {
    const valid = await validateCurrentStep()
    if (!valid) return
    if (currentStep.value < steps.length - 1) currentStep.value++
  }

  const prevStep = () => {
    if (currentStep.value > 0) currentStep.value--
  }

  // Only allow jumping back to already-completed steps
  const tryJumpTo = i => {
    if (i < currentStep.value) currentStep.value = i
  }

  // ─── Submit ───────────────────────────────────────────────────────────────────

  const submit = async () => {
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
    Object.assign(form, defaultForm())
    currentStep.value = 0
    formRef.value?.reset()
  }
</script>

<style scoped>
  /* ── Stepper ── */
  .stepper-track {
    display: flex;
    align-items: center;
  }

  .step-item {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: default;
  }

  .step-item.step-done {
    cursor: pointer;
  }

  .step-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid rgba(var(--v-border-color), 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
    color: rgba(var(--v-theme-on-surface), 0.4);
    background: transparent;
    transition: all 0.2s ease;
  }

  .step-active .step-dot {
    background: rgb(var(--v-theme-primary));
    border-color: rgb(var(--v-theme-primary));
    color: #fff;
  }

  .step-done .step-dot {
    background: rgb(var(--v-theme-success));
    border-color: rgb(var(--v-theme-success));
    color: #fff;
  }

  .step-label {
    font-size: 12px;
    color: rgba(var(--v-theme-on-surface), 0.4);
    white-space: nowrap;
    transition: color 0.2s;
  }

  .step-active .step-label {
    color: rgba(var(--v-theme-on-surface), 0.87);
    font-weight: 500;
  }

  .step-done .step-label {
    color: rgba(var(--v-theme-on-surface), 0.6);
  }

  .step-connector {
    flex: 1;
    height: 1px;
    background: rgba(var(--v-border-color), 0.3);
    margin: 0 8px;
    transition: background 0.3s;
    min-width: 16px;
  }

  .connector-done {
    background: rgb(var(--v-theme-success));
  }

  /* ── Step content ── */
  .step-content {
    min-height: 240px;
  }

  .step-section-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: rgb(var(--v-theme-primary));
  }

  /* ── Review blocks ── */
  .review-block {
    border: 1px solid rgba(var(--v-border-color), 0.2);
    border-radius: 12px;
    padding: 12px 14px;
    background: rgba(var(--v-theme-surface-variant), 0.1);
  }

  .review-block-header {
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(var(--v-border-color), 0.15);
    margin-bottom: 4px;
  }

  .review-field {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 4px 0;
  }

  .review-label {
    font-size: 11px;
    color: rgba(var(--v-theme-on-surface), 0.5);
    text-transform: uppercase;
    letter-spacing: 0.4px;
  }

  .review-value {
    font-size: 13px;
    font-weight: 500;
    color: rgba(var(--v-theme-on-surface), 0.87);
  }

  /* ── Utilities ── */
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
