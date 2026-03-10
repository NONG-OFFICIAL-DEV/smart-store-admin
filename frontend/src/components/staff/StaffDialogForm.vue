<template>
  <v-dialog v-model="model" max-width="620" persistent scrollable>
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
                  isEdit
                    ? 'mdi-account-edit-outline'
                    : 'mdi-account-plus-outline'
                "
                size="20"
              />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">
                {{ isEdit ? 'Edit Staff Member' : 'Add Staff Member' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{
                  isEdit
                    ? 'Update employment details'
                    : 'Creates login account + assigns to branch'
                }}
              </div>
            </div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
        </div>
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-0" style="max-height: 72vh">
        <v-form ref="formRef">
          <!-- ── Edit mode: no stepper, simple layout ───────────────── -->
          <template v-if="isEdit">
            <!-- User card -->
            <div class="form-section">
              <v-card
                rounded="lg"
                border
                elevation="0"
                class="pa-4 d-flex align-center gap-3"
              >
                <v-avatar
                  color="primary"
                  variant="tonal"
                  size="40"
                  rounded="lg"
                >
                  <span class="text-body-2 font-weight-bold">
                    {{ initials(props.item) }}
                  </span>
                </v-avatar>
                <div class="flex-grow-1">
                  <div class="text-body-2 font-weight-bold">
                    {{ props.item?.user?.first_name }}
                    {{ props.item?.user?.last_name }}
                  </div>
                  <div class="text-caption text-grey">
                    {{ props.item?.user?.email }}
                  </div>
                </div>
                <v-chip size="x-small" color="primary" variant="tonal" label>
                  {{ props.item?.employee_code }}
                </v-chip>
              </v-card>
            </div>
            <v-divider />

            <!-- Employment fields -->
            <div class="form-section">
              <div class="form-section-label">
                <v-icon icon="mdi-briefcase-outline" size="13" class="mr-1" />
                Employment
              </div>
              <v-row dense>
                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.branch_id"
                    :items="filteredBranches"
                    item-value="id"
                    item-title="name"
                    label="Branch *"
                    variant="outlined"
                    rounded="lg"
                    :rules="[r.required]"
                    prepend-inner-icon="mdi-store-outline"
                  >
                    <template #item="{ props: p, item }">
                      <v-list-item
                        v-bind="p"
                        :subtitle="item.raw?.city || ''"
                      />
                    </template>
                  </v-select>
                </v-col>
                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.role_id"
                    :items="roles"
                    item-value="id"
                    item-title="name"
                    label="Role *"
                    variant="outlined"
                    rounded="lg"
                    :rules="[r.required]"
                    prepend-inner-icon="mdi-shield-account-outline"
                  >
                    <template #item="{ props: p, item }">
                      <v-list-item
                        v-bind="p"
                        :subtitle="item.raw?.description || ''"
                      />
                    </template>
                  </v-select>
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.hire_date"
                    type="date"
                    label="Hire Date"
                    variant="outlined"
                    rounded="lg"
                    prepend-inner-icon="mdi-calendar-outline"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model.number="form.hourly_rate"
                    type="number"
                    label="Hourly Rate ($)"
                    variant="outlined"
                    rounded="lg"
                    prepend-inner-icon="mdi-currency-usd"
                    min="0"
                    step="0.5"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model.number="form.salary"
                    type="number"
                    label="Monthly Salary ($)"
                    variant="outlined"
                    rounded="lg"
                    prepend-inner-icon="mdi-cash-multiple"
                    min="0"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.pin_code"
                    label="POS PIN (4–6 digits)"
                    :type="showPin ? 'text' : 'password'"
                    variant="outlined"
                    rounded="lg"
                    prepend-inner-icon="mdi-numeric"
                    :append-inner-icon="showPin ? 'mdi-eye-off' : 'mdi-eye'"
                    :rules="[r.pin]"
                    maxlength="6"
                    @click:append-inner="showPin = !showPin"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.employee_code"
                    label="Employee Code"
                    variant="outlined"
                    rounded="lg"
                    prepend-inner-icon="mdi-identifier"
                    :readonly="!editingCode"
                    :bg-color="!editingCode ? 'grey-lighten-4' : undefined"
                  >
                    <template #append-inner>
                      <v-icon
                        :icon="
                          editingCode
                            ? 'mdi-lock-open-outline'
                            : 'mdi-lock-outline'
                        "
                        size="16"
                        class="cursor-pointer text-medium-emphasis"
                        @click="editingCode = !editingCode"
                      />
                    </template>
                  </v-text-field>
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
                      <div class="text-caption text-grey">Allow login</div>
                    </div>
                    <v-switch v-model="form.is_active" color="success" inset />
                  </v-card>
                </v-col>
              </v-row>
            </div>
          </template>

          <!-- ── Create mode: stepper ───────────────────────────────── -->
          <template v-else>
            <v-stepper
              v-model="step"
              flat
              :items="stepItems"
              hide-actions
              alt-labels
              class="staff-stepper"
            >
              <!-- Step indicators -->
              <template #[`item.1`]>
                <!-- Step 1: Tenant (super admin only) or Account -->
                <div class="form-section">
                  <template v-if="isSuperAdmin()">
                    <div class="form-section-label">
                      <v-icon icon="mdi-domain" size="13" class="mr-1" />
                      Select Tenant
                    </div>
                    <v-select
                      v-model="form.tenant_id"
                      :items="tenants"
                      item-value="id"
                      item-title="name"
                      label="Tenant *"
                      variant="outlined"
                      rounded="lg"
                      :rules="[r.required]"
                      prepend-inner-icon="mdi-domain"
                      class="mb-4"
                      @update:model-value="onTenantChange"
                    >
                      <template #item="{ props: p, item }">
                        <v-list-item
                          v-bind="p"
                          :subtitle="item.raw?.owner?.email || ''"
                        />
                      </template>
                    </v-select>
                    <v-divider class="mb-4" />
                  </template>

                  <div class="form-section-label">
                    <v-icon icon="mdi-account-outline" size="13" class="mr-1" />
                    Account Info
                  </div>
                  <v-row dense>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.first_name"
                        label="First Name *"
                        variant="outlined"
                        rounded="lg"
                        :rules="[r.required]"
                        prepend-inner-icon="mdi-account-outline"
                        maxlength="80"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.last_name"
                        label="Last Name *"
                        variant="outlined"
                        rounded="lg"
                        :rules="[r.required]"
                        maxlength="80"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.email"
                        label="Email *"
                        type="email"
                        variant="outlined"
                        rounded="lg"
                        :rules="[r.required, r.email]"
                        prepend-inner-icon="mdi-email-outline"
                        maxlength="255"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.phone"
                        label="Phone"
                        variant="outlined"
                        rounded="lg"
                        prepend-inner-icon="mdi-phone-outline"
                        maxlength="30"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.password"
                        label="Password *"
                        :type="showPassword ? 'text' : 'password'"
                        variant="outlined"
                        rounded="lg"
                        :rules="[r.required, r.minPassword]"
                        prepend-inner-icon="mdi-lock-outline"
                        :append-inner-icon="
                          showPassword ? 'mdi-eye-off' : 'mdi-eye'
                        "
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
                        Generate Password
                      </v-btn>
                    </v-col>
                  </v-row>
                </div>
              </template>

              <template #[`item.2`]>
                <!-- Step 2: Employment -->
                <div class="form-section">
                  <div class="form-section-label">
                    <v-icon
                      icon="mdi-briefcase-outline"
                      size="13"
                      class="mr-1"
                    />
                    Employment Details
                  </div>
                  <v-row dense>
                    <v-col cols="12" sm="6">
                      <v-select
                        v-model="form.branch_id"
                        :items="filteredBranches"
                        item-value="id"
                        item-title="name"
                        label="Branch *"
                        variant="outlined"
                        rounded="lg"
                        :rules="[r.required]"
                        prepend-inner-icon="mdi-store-outline"
                      >
                        <template #item="{ props: p, item }">
                          <v-list-item
                            v-bind="p"
                            :subtitle="item.raw?.city || ''"
                          />
                        </template>
                      </v-select>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-select
                        v-model="form.role_id"
                        :items="roles"
                        item-value="id"
                        item-title="name"
                        label="Role *"
                        variant="outlined"
                        rounded="lg"
                        :rules="[r.required]"
                        prepend-inner-icon="mdi-shield-account-outline"
                      >
                      </v-select>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.hire_date"
                        type="date"
                        label="Hire Date"
                        variant="outlined"
                        rounded="lg"
                        prepend-inner-icon="mdi-calendar-outline"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model.number="form.hourly_rate"
                        type="number"
                        label="Hourly Rate ($)"
                        variant="outlined"
                        rounded="lg"
                        prepend-inner-icon="mdi-currency-usd"
                        min="0"
                        step="0.5"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model.number="form.salary"
                        type="number"
                        label="Monthly Salary ($)"
                        variant="outlined"
                        rounded="lg"
                        prepend-inner-icon="mdi-cash-multiple"
                        min="0"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="form.pin_code"
                        label="POS PIN (4–6 digits)"
                        :type="showPin ? 'text' : 'password'"
                        variant="outlined"
                        rounded="lg"
                        prepend-inner-icon="mdi-numeric"
                        :append-inner-icon="showPin ? 'mdi-eye-off' : 'mdi-eye'"
                        :rules="[r.pin]"
                        maxlength="6"
                        @click:append-inner="showPin = !showPin"
                      />
                    </v-col>
                  </v-row>
                </div>
              </template>

              <template #[`item.3`]>
                <!-- Step 3: Review -->
                <div class="form-section">
                  <div class="form-section-label">
                    <v-icon
                      icon="mdi-check-circle-outline"
                      size="13"
                      class="mr-1"
                    />
                    Review & Confirm
                  </div>

                  <!-- Account summary -->
                  <v-card rounded="lg" border elevation="0" class="pa-4 mb-3">
                    <div class="d-flex align-center gap-3 mb-3">
                      <v-avatar
                        color="primary"
                        variant="tonal"
                        size="40"
                        rounded="lg"
                      >
                        <span class="text-body-2 font-weight-bold">
                          {{
                            (form.first_name?.[0] ?? '') +
                            (form.last_name?.[0] ?? '')
                          }}
                        </span>
                      </v-avatar>
                      <div>
                        <div class="text-body-2 font-weight-bold">
                          {{ form.first_name }} {{ form.last_name }}
                        </div>
                        <div class="text-caption text-grey">
                          {{ form.email }}
                        </div>
                      </div>
                    </div>
                    <v-divider class="mb-3" />
                    <v-row dense>
                      <v-col cols="6">
                        <div class="text-caption text-grey">Branch</div>
                        <div class="text-body-2 font-weight-medium">
                          {{
                            filteredBranches.find(b => b.id === form.branch_id)
                              ?.name ?? '—'
                          }}
                        </div>
                      </v-col>
                      <v-col cols="6">
                        <div class="text-caption text-grey">Role</div>
                        <div class="text-body-2 font-weight-medium">
                          {{
                            roles.find(r => r.id === form.role_id)?.name ?? '—'
                          }}
                        </div>
                      </v-col>
                      <v-col cols="6">
                        <div class="text-caption text-grey">Hire Date</div>
                        <div class="text-body-2 font-weight-medium">
                          {{ form.hire_date || '—' }}
                        </div>
                      </v-col>
                      <v-col cols="6">
                        <div class="text-caption text-grey">Hourly Rate</div>
                        <div class="text-body-2 font-weight-medium">
                          {{ form.hourly_rate ? `$${form.hourly_rate}` : '—' }}
                        </div>
                      </v-col>
                      <v-col cols="6">
                        <div class="text-caption text-grey">Monthly Salary</div>
                        <div class="text-body-2 font-weight-medium">
                          {{ form.salary ? `$${form.salary}` : '—' }}
                        </div>
                      </v-col>
                      <v-col cols="6">
                        <div class="text-caption text-grey">Phone</div>
                        <div class="text-body-2 font-weight-medium">
                          {{ form.phone || '—' }}
                        </div>
                      </v-col>
                    </v-row>
                  </v-card>

                  <v-alert
                    type="info"
                    variant="tonal"
                    rounded="lg"
                    class="text-caption"
                  >
                    A login account will be created and the staff member will be
                    assigned to the selected branch.
                  </v-alert>
                </div>
              </template>
            </v-stepper>
          </template>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ── Actions ──────────────────────────────────────────────────── -->
      <v-card-actions class="pa-4 gap-2">
        <!-- Edit mode actions -->
        <template v-if="isEdit">
          <v-spacer />
          <v-btn
            variant="tonal"
            rounded="lg"
            :disabled="loading"
            @click="close"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            rounded="lg"
            :loading="loading"
            prepend-icon="mdi-content-save-outline"
            @click="submit"
          >
            Save Changes
          </v-btn>
        </template>

        <!-- Create mode stepper actions -->
        <template v-else>
          <v-btn
            variant="tonal"
            rounded="lg"
            :disabled="loading"
            @click="close"
          >
            Cancel
          </v-btn>
          <v-spacer />
          <!-- Step indicator text -->
          <span class="text-caption text-grey mr-2">
            Step {{ step }} of {{ stepItems.length }}
          </span>
          <v-btn
            v-if="step > 1"
            variant="tonal"
            rounded="lg"
            prepend-icon="mdi-arrow-left"
            @click="step--"
          >
            Back
          </v-btn>
          <v-btn
            v-if="step < stepItems.length"
            color="primary"
            variant="flat"
            rounded="lg"
            append-icon="mdi-arrow-right"
            @click="nextStep"
          >
            Next
          </v-btn>
          <v-btn
            v-if="step === stepItems.length"
            color="success"
            variant="flat"
            rounded="lg"
            :loading="loading"
            prepend-icon="mdi-account-plus-outline"
            @click="submit"
          >
            Add Staff
          </v-btn>
        </template>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useAuthStore } from '@/stores/authStore'
  import { useRoleStore } from '@/stores/roleStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useTenantStore } from '@/stores/tenantStore'
  import { usePermission } from '@/composables/usePermission'

  const props = defineProps({
    modelValue: Boolean,
    item: Object,
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const authStore = useAuthStore()
  const roleStore = useRoleStore()
  const branchStore = useBranchStore()
  const tenantStore = useTenantStore()
  const { isSuperAdmin } = usePermission()

  const { tenants } = storeToRefs(tenantStore)
  const { branches } = storeToRefs(branchStore)
  const { roles } = storeToRefs(roleStore)

  const formRef = ref(null)
  const showPassword = ref(false)
  const showPin = ref(false)
  const editingCode = ref(false)
  const step = ref(1)

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.item?.id)

  const stepItems = [
    { title: 'Account', value: 1 },
    { title: 'Employment', value: 2 },
    { title: 'Review', value: 3 }
  ]

  // ── Branches filtered by tenant ────────────────────────────────────────────────
  const filteredBranches = computed(() => {
    const list = branches.value?.data ?? branches.value ?? []
    if (isSuperAdmin.value && form.tenant_id) {
      return list.filter(b => b.tenant_id === form.tenant_id)
    }
    return list
  })

  const onTenantChange = () => {
    form.branch_id = null
  }

  // ── Default form ───────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    tenant_id: isSuperAdmin.value ? null : (authStore.tenant_id ?? null),
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    branch_id: null,
    role_id: null,
    hire_date: new Date().toISOString().split('T')[0],
    hourly_rate: null,
    salary: null,
    pin_code: '',
    is_active: true,
    employee_code: ''
  })

  const form = reactive(defaultForm())

  watch(
    () => props.item,
    val => {
      editingCode.value = false
      step.value = 1
      Object.assign(
        form,
        val
          ? {
              tenant_id: val.tenant_id,
              branch_id: val.branch_id,
              role_id: val.role_id,
              hire_date: val.hire_date,
              hourly_rate: val.hourly_rate,
              salary: val.salary,
              pin_code: '',
              is_active: val.is_active,
              employee_code: val.employee_code
            }
          : defaultForm()
      )
    },
    { immediate: true }
  )

  // ── Validation ─────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || 'Required',
    email: v => /.+@.+\..+/.test(v) || 'Invalid email',
    minPassword: v => !v || v.length >= 6 || 'At least 6 characters',
    pin: v => !v || /^\d{4,6}$/.test(v) || 'PIN must be 4–6 digits'
  }

  // ── Step navigation with validation ───────────────────────────────────────────
  const nextStep = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    step.value++
  }

  // ── Helpers ────────────────────────────────────────────────────────────────────
  const initials = item => {
    const f = item?.user?.first_name?.[0] ?? ''
    const l = item?.user?.last_name?.[0] ?? ''
    return (f + l).toUpperCase()
  }

  const generatePassword = () => {
    const chars =
      'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$'
    form.password = Array.from(
      { length: 10 },
      () => chars[Math.floor(Math.random() * chars.length)]
    ).join('')
    showPassword.value = true
  }

  // ── Submit ─────────────────────────────────────────────────────────────────────
  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('save', { ...form })
  }

  const close = () => {
    formRef.value?.reset()
    Object.assign(form, defaultForm())
    step.value = 1
    emit('update:modelValue', false)
  }

  // ── Init ───────────────────────────────────────────────────────────────────────
  onMounted(() => {
    if (isSuperAdmin.value) tenantStore.fetchTenants()
    branchStore.fetchBranches()
    roleStore.fetchRoles()
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
  .cursor-pointer {
    cursor: pointer;
  }
  .w-100 {
    width: 100%;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }

  /* Clean up stepper header */
  .staff-stepper :deep(.v-stepper-header) {
    box-shadow: none;
    padding: 12px 20px 0;
  }
  .staff-stepper :deep(.v-stepper-window) {
    margin: 0;
    padding: 0;
  }
</style>
