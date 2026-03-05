<template>
  <v-dialog v-model="model" max-width="580" persistent scrollable>
    <v-card rounded="xl">
      <v-card-title class="pa-6 pb-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar
              :color="isEdit ? 'primary' : 'success'"
              size="40"
              rounded="lg"
            >
              <v-icon
                :icon="isEdit ? 'mdi-pencil' : 'mdi-account-plus'"
                size="20"
              />
            </v-avatar>
            <div>
              <div class="text-h6 font-weight-bold">
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

      <v-card-text class="pa-6">
        <v-form ref="formRef">
          <!-- ══ ACCOUNT INFO (only shown when creating new) ══════════════════ -->
          <v-col cols="12">
            <!-- show if supper admin -->
            <v-select
              v-model="form.tenant_id"
              :items="tenants"
              item-value="id"
              item-title="name"
              label="Tenant (Business)"
              variant="outlined"
              density="comfortable"
              rounded="lg"
              :rules="[r.required]"
              :disabled="isEdit"
              prepend-inner-icon="mdi-domain"
              hint="This shift will be available to all branches under this tenant"
              persistent-hint
            />
          </v-col>

          <template v-if="!isEdit">
            <p class="text-overline text-primary mb-3">
              <v-icon icon="mdi-account-outline" size="14" class="mr-1" />
              Account Info
            </p>

            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.first_name"
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
                  v-model="form.last_name"
                  label="Last Name"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  :rules="[r.required]"
                  maxlength="80"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.email"
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
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.phone"
                  label="Phone"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  prepend-inner-icon="mdi-phone-outline"
                  maxlength="30"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.password"
                  label="Password"
                  :type="showPassword ? 'text' : 'password'"
                  variant="outlined"
                  density="comfortable"
                  rounded="lg"
                  :rules="[r.required, r.minPassword]"
                  prepend-inner-icon="mdi-lock-outline"
                  :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
                  @click:append-inner="showPassword = !showPassword"
                />
              </v-col>
              <v-col cols="12" sm="6" class="d-flex align-center pb-4">
                <v-btn
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

            <v-divider class="my-4" />
          </template>

          <!-- ══ EDIT MODE: show user info readonly ════════════════════════════ -->
          <template v-else>
            <v-card
              rounded="lg"
              border
              elevation="0"
              color="grey-lighten-5"
              class="pa-4 mb-4"
            >
              <div class="d-flex align-center gap-3">
                <v-avatar
                  color="primary"
                  variant="tonal"
                  size="44"
                  rounded="lg"
                >
                  <span class="text-body-1 font-weight-bold">
                    {{ initials(props.item) }}
                  </span>
                </v-avatar>
                <div>
                  <div class="text-body-1 font-weight-bold">
                    {{ props.item?.user?.first_name }}
                    {{ props.item?.user?.last_name }}
                  </div>
                  <div class="text-caption text-grey">
                    {{ props.item?.user?.email }}
                  </div>
                </div>
                <v-spacer />
                <v-chip
                  size="small"
                  color="primary"
                  variant="tonal"
                  prepend-icon="mdi-identifier"
                >
                  {{ props.item?.employee_code }}
                </v-chip>
              </div>
            </v-card>
          </template>

          <!-- ══ EMPLOYMENT ════════════════════════════════════════════════════ -->
          <p class="text-overline text-primary mb-3">
            <v-icon icon="mdi-briefcase-outline" size="14" class="mr-1" />
            Employment
          </p>

          <v-row dense>
            <!-- Branch — filtered to tenant's own branches only -->
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.branch_id"
                :items="branches.data"
                item-value="id"
                item-title="name"
                label="Branch"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-store-outline"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item
                    v-bind="itemProps"
                    :subtitle="item.raw?.city || ''"
                  />
                </template>
              </v-select>
            </v-col>

            <!-- Role — filtered to tenant's roles only -->
            <v-col cols="12" sm="6">
              <v-select
                v-model="form.role_id"
                :items="roles"
                item-value="id"
                item-title="name"
                label="Role"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[r.required]"
                prepend-inner-icon="mdi-shield-account-outline"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item
                    v-bind="itemProps"
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
                density="comfortable"
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
                density="comfortable"
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
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-cash-multiple"
                min="0"
              />
            </v-col>

            <!-- PIN -->
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="form.pin_code"
                label="POS PIN (4–6 digits)"
                :type="showPin ? 'text' : 'password'"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-numeric"
                :append-inner-icon="showPin ? 'mdi-eye-off' : 'mdi-eye'"
                :rules="[r.pin]"
                maxlength="6"
                hint="Optional — used for POS login"
                persistent-hint
                @click:append-inner="showPin = !showPin"
              />
            </v-col>

            <!-- Employee code — shown in edit mode only -->
            <v-col v-if="isEdit" cols="12" sm="6">
              <v-text-field
                v-model="form.employee_code"
                label="Employee Code"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                prepend-inner-icon="mdi-identifier"
                :readonly="!editingCode"
                :bg-color="!editingCode ? 'grey-lighten-4' : undefined"
                hint="Auto-generated — unlock to edit"
                persistent-hint
              >
                <template #append-inner>
                  <v-tooltip :text="editingCode ? 'Lock' : 'Edit manually'">
                    <template #activator="{ props: ttProps }">
                      <v-icon
                        v-bind="ttProps"
                        :icon="
                          editingCode
                            ? 'mdi-lock-open-outline'
                            : 'mdi-lock-outline'
                        "
                        size="18"
                        class="cursor-pointer text-medium-emphasis"
                        @click="editingCode = !editingCode"
                      />
                    </template>
                  </v-tooltip>
                </template>
              </v-text-field>
            </v-col>

            <v-col cols="12" sm="6" class="d-flex align-center">
              <v-switch
                v-model="form.is_active"
                label="Active"
                color="success"
                inset
                hide-details
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-6 pt-4 gap-3">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
          Cancel
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          :loading="loading"
          :prepend-icon="isEdit ? 'mdi-content-save' : 'mdi-account-plus'"
          @click="submit"
        >
          {{ isEdit ? 'Save Changes' : 'Add Staff' }}
        </v-btn>
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
  const { tenants } = storeToRefs(tenantStore)

  // Branches + roles already scoped to this tenant by the API
  // (backend filters by auth()->user() tenant automatically)
  const { branches } = storeToRefs(branchStore)
  const { roles } = storeToRefs(roleStore)

  const formRef = ref(null)
  const showPassword = ref(false)
  const showPin = ref(false)
  const editingCode = ref(false)

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.item?.id)

  // ── Default form ──────────────────────────────────────────────────────────────
  const defaultForm = () => ({
    // User fields (create only)
    tenant_id:null,
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    // Staff fields
    branch_id: null,
    role_id: null,
    hire_date: new Date().toISOString().split('T')[0],
    hourly_rate: null,
    salary: null,
    pin_code: '',
    is_active: true
  })

  const form = reactive(defaultForm())

  watch(
    () => props.item,
    val => {
      Object.keys(form).forEach(k => delete form[k])
      editingCode.value = false
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
              pin_code: '', // never prefill PIN
              is_active: val.is_active,
              employee_code: val.employee_code
            }
          : defaultForm()
      )
    },
    { immediate: true }
  )

  // ── Validation ────────────────────────────────────────────────────────────────
  const r = {
    required: v => !!v || 'Required',
    email: v => /.+@.+\..+/.test(v) || 'Invalid email',
    minPassword: v => !v || v.length >= 6 || 'At least 6 characters',
    pin: v => !v || /^\d{4,6}$/.test(v) || 'PIN must be 4–6 digits'
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  const initials = item => {
    const f = item?.user?.first_name?.[0] || ''
    const l = item?.user?.last_name?.[0] || ''
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

  // ── Submit ────────────────────────────────────────────────────────────────────
  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    // tenant_id comes from authStore on the backend — no need to send it
    emit('save', { ...form })
  }

  const close = () => {
    formRef.value?.reset()
    Object.assign(form, defaultForm())
    emit('update:modelValue', false)
  }

  // Branches and roles are already tenant-scoped by backend
  // GET /api/v1/branches    → returns only this tenant's branches
  // GET /api/v1/roles       → returns only this tenant's roles
  onMounted(() => {
    tenantStore.fetchTenants()
    branchStore.fetchBranches()
    roleStore.fetchRoles()
  })
</script>
