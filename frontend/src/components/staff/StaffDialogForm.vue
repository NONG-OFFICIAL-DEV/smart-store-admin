<template>
<div>
  <AppDialog
    v-model="model"
    :max-width="620"
    :title="isEdit ? $t('staff.dialog.edit') : $t('staff.dialog.add')"
    :subtitle="isEdit ? $t('staff.dialog.edit_subtitle') : $t('staff.dialog.add_subtitle')"
    :icon="isEdit ? 'mdi-account-edit-outline' : 'mdi-account-plus-outline'"
    :color="isEdit ? 'primary' : 'success'"
    :loading="loading"
    :body-class="'pa-0'"
    :body-style="{ maxHeight: '72vh' }"
    @close="close"
  >
        <v-form ref="formRef">
          <!-- ── EDIT MODE ──────────────────────────────────────────── -->
          <template v-if="isEdit">
            <v-form ref="formStep1">
              <div class="form-section">
                <v-card
                  rounded="lg"
                  border
                  elevation="0"
                  class="pa-4 d-flex align-center gap-3"
                >
                  <v-avatar
                    size="56"
                    rounded="lg"
                    :color="avatarColor(props.item?.full_name)"
                  >
                    <span class="text-white font-weight-bold">
                      {{ initials(props.item?.full_name) }}
                    </span>
                  </v-avatar>
                  <div class="flex-grow-1">
                    <div class="text-body-2 font-weight-bold">
                      {{ props.item?.full_name }}
                    </div>
                    <div class="text-caption text-grey">
                      {{ props.item?.email }}
                    </div>
                  </div>
                  <v-chip size="x-small" color="primary" variant="tonal" label>
                    {{ props.item?.employee_code }}
                  </v-chip>
                </v-card>
              </div>
            </v-form>
            <v-divider />
            <v-form ref="formStep2">
              <div class="form-section">
                <div class="form-section-label">
                  <v-icon icon="mdi-briefcase-outline" size="13" class="mr-1" />
                  {{ $t('staff.section.employment') }}
                </div>
                <v-row dense>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="form.branch_id"
                      :items="filteredBranches"
                      item-value="id"
                      item-title="name"
                      :label="$t('po.field.branch')"
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
                      :items="assignableRoles"
                      item-value="id"
                      item-title="name"
                      :label="$t('staff.field.role_required')"
                      variant="outlined"
                      rounded="lg"
                      :rules="[r.required]"
                      prepend-inner-icon="mdi-shield-account-outline"
                    ></v-select>
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-date-input
                      v-model="form.hire_date"
                      :label="$t('staff.field.hire_date')"
                      rounded="lg"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model.number="form.hourly_rate"
                      type="number"
                      :label="$t('staff.field.hourly_rate')"
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
                      :label="$t('staff.field.salary')"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-cash-multiple"
                      min="0"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="form.pin_code"
                      :label="$t('staff.field.pin')"
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
                      :label="$t('staff.field.employee_code')"
                      variant="outlined"
                      rounded="lg"
                      prepend-inner-icon="mdi-identifier"
                      hide-details
                      :readonly="!editingCode"
                      :class="{ 'readonly-field': !editingCode }"
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
                        <div class="text-body-2 font-weight-medium">{{ $t('status.active') }}</div>
                        <div class="text-caption text-grey">{{ $t('staff.field.allow_login') }}</div>
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
                  <v-col cols="12" sm="6" class="d-flex align-center">
                    <v-btn
                      variant="tonal"
                      color="warning"
                      rounded="lg"
                      size="small"
                      prepend-icon="mdi-lock-reset"
                      @click="confirmResetPassword"
                    >
                      {{ $t('staff.reset_password') }}
                    </v-btn>
                  </v-col>
                </v-row>
              </div>
            </v-form>
          </template>

          <!-- ── CREATE MODE: Stepper ───────────────────────────────── -->
          <template v-else>
            <v-stepper
              v-model="step"
              flat
              :items="stepItems"
              hide-actions
              class="staff-stepper"
            >
              <template #[`item.1`]>
                <v-form ref="formStep1">
                  <div class="form-section">
                    <template v-if="isSuperAdmin()">
                      <div class="form-section-label">
                        <v-icon icon="mdi-domain" size="13" class="mr-1" />
                        {{ $t('staff.field.select_tenant') }}
                      </div>
                      <v-select
                        v-model="form.tenant_id"
                        :items="tenants"
                        item-value="id"
                        item-title="name"
                        :label="$t('products.field.tenant')"
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
                      <v-icon
                        icon="mdi-account-outline"
                        size="13"
                        class="mr-1"
                      />
                      {{ $t('staff.section.account_info') }}
                    </div>
                    <v-row dense>
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model="form.first_name"
                          :label="$t('staff.field.first_name_required')"
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
                          :label="$t('staff.field.last_name_required')"
                          variant="outlined"
                          rounded="lg"
                          :rules="[r.required]"
                          maxlength="80"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model="form.email"
                          :label="$t('staff.field.email_required')"
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
                          :label="$t('staff.form.phone')"
                          variant="outlined"
                          rounded="lg"
                          prepend-inner-icon="mdi-phone-outline"
                          maxlength="30"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model="form.password"
                          :label="$t('staff.field.password_required')"
                          :type="showPassword ? 'text' : 'password'"
                          variant="outlined"
                          rounded="lg"
                          :rules="passwordRules"
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
                          {{ $t('staff.generate_password') }}
                        </v-btn>
                      </v-col>
                    </v-row>
                  </div>
                </v-form>
              </template>

              <template #[`item.2`]>
                <v-form ref="formStep2">
                  <div class="form-section">
                    <div class="form-section-label">
                      <v-icon
                        icon="mdi-briefcase-outline"
                        size="13"
                        class="mr-1"
                      />
                      {{ $t('staff.section.employment_details') }}
                    </div>
                    <v-row dense>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.branch_id"
                          :items="filteredBranches"
                          item-value="id"
                          item-title="name"
                          :label="$t('po.field.branch')"
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
                          :items="assignableRoles"
                          item-value="id"
                          item-title="name"
                          :label="$t('staff.field.role_required')"
                          variant="outlined"
                          rounded="lg"
                          :rules="[r.required]"
                          prepend-inner-icon="mdi-shield-account-outline"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-date-input
                          v-model="form.hire_date"
                          :label="$t('staff.field.hire_date')"
                          rounded="lg"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model.number="form.hourly_rate"
                          type="number"
                          :label="$t('staff.field.hourly_rate')"
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
                          :label="$t('staff.field.salary')"
                          variant="outlined"
                          rounded="lg"
                          prepend-inner-icon="mdi-cash-multiple"
                          min="0"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-text-field
                          v-model="form.pin_code"
                          :label="$t('staff.field.pin')"
                          :type="showPin ? 'text' : 'password'"
                          variant="outlined"
                          rounded="lg"
                          prepend-inner-icon="mdi-numeric"
                          :append-inner-icon="
                            showPin ? 'mdi-eye-off' : 'mdi-eye'
                          "
                          :rules="[r.pin]"
                          maxlength="6"
                          @click:append-inner="showPin = !showPin"
                        />
                      </v-col>
                    </v-row>
                  </div>
                </v-form>
              </template>

              <template #[`item.3`]>
                <v-form ref="formStep3">
                  <div class="form-section">
                    <div class="form-section-label">
                      <v-icon
                        icon="mdi-check-circle-outline"
                        size="13"
                        class="mr-1"
                      />
                      {{ $t('staff.section.review_confirm') }}
                    </div>
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
                          <div class="text-caption text-grey">{{ $t('form.branch') }}</div>
                          <div class="text-body-2 font-weight-medium">
                            {{
                              filteredBranches.find(
                                b => b.id === form.branch_id
                              )?.name ?? '—'
                            }}
                          </div>
                        </v-col>
                        <v-col cols="6">
                          <div class="text-caption text-grey">{{ $t('form.role') }}</div>
                          <div class="text-body-2 font-weight-medium">
                            {{
                              assignableRoles.find(ro => ro.id === form.role_id)?.name ??
                              '—'
                            }}
                          </div>
                        </v-col>
                        <v-col cols="6">
                          <div class="text-caption text-grey">{{ $t('staff.field.hire_date') }}</div>
                          <div class="text-body-2 font-weight-medium">
                            {{ form.hire_date ? formatDate(form.hire_date) : '—' }}
                          </div>
                        </v-col>
                        <v-col cols="6">
                          <div class="text-caption text-grey">{{ $t('staff.review.hourly_rate') }}</div>
                          <div class="text-body-2 font-weight-medium">
                            {{
                              form.hourly_rate ? `$${form.hourly_rate}` : '—'
                            }}
                          </div>
                        </v-col>
                        <v-col cols="6">
                          <div class="text-caption text-grey">
                            {{ $t('staff.review.salary') }}
                          </div>
                          <div class="text-body-2 font-weight-medium">
                            {{ form.salary ? `$${form.salary}` : '—' }}
                          </div>
                        </v-col>
                        <v-col cols="6">
                          <div class="text-caption text-grey">{{ $t('staff.form.phone') }}</div>
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
                      {{ $t('staff.login_account_notice') }}
                    </v-alert>
                  </div>
                </v-form>
              </template>
            </v-stepper>
          </template>
        </v-form>

    <template #actions>
      <template v-if="isEdit">
        <v-spacer />
        <v-btn
          variant="tonal"
          rounded="lg"
          :disabled="loading"
          @click="close"
        >
          {{ $t('btn.cancel') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          :loading="loading"
          prepend-icon="mdi-content-save-outline"
          @click="submit"
        >
          {{ $t('btn.save_changes') }}
        </v-btn>
      </template>

      <template v-else>
        <v-btn
          variant="tonal"
          rounded="lg"
          :disabled="loading"
          @click="close"
        >
          {{ $t('btn.cancel') }}
        </v-btn>
        <v-spacer />
        <span class="text-caption text-grey mr-2">
          {{ $t('staff.step_of', { step, total: stepItems.length }) }}
        </span>
        <v-btn
          v-if="step > 1"
          variant="tonal"
          rounded="lg"
          prepend-icon="mdi-arrow-left"
          @click="prevStep"
        >
          {{ $t('btn.back') }}
        </v-btn>
        <v-btn
          v-if="step < stepItems.length"
          color="primary"
          variant="flat"
          rounded="lg"
          append-icon="mdi-arrow-right"
          @click="nextStep"
        >
          {{ $t('btn.next') }}
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
          {{ $t('btn.add_staff') }}
        </v-btn>
      </template>
    </template>
  </AppDialog>

  <TemporaryPasswordDialog
    v-model="tempPasswordDialog"
    :password="temporaryPassword"
  />
</div>
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useRoleStore } from '@/stores/roleStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useTenantStore } from '@/stores/tenantStore'
  import { useStaffStore } from '@/stores/staffStore'
  import { usePermission } from '@/composables/usePermission'
  import { useDate } from '@/composables/useDate'
  import { useAvatar } from '@/composables/useAvatar'
  import { usePasswordPolicy } from '@/composables/usePasswordPolicy'
  import { useAppUtils } from '@/composables/useAppUtils'
  import AppDialog from '@/components/common/AppDialog.vue'
  import TemporaryPasswordDialog from '@/components/common/TemporaryPasswordDialog.vue'

  const props = defineProps({
    modelValue: Boolean,
    item: { type: Object, default: null },
    loading: Boolean
  })

  const emit = defineEmits(['update:modelValue', 'save'])
  const { t } = useI18n()

  const authStore = useAuthStore()
  const roleStore = useRoleStore()
  const branchStore = useBranchStore()
  const tenantStore = useTenantStore()
  const staffStore = useStaffStore()
  const { isSuperAdmin } = usePermission()
  const { formatDate, formatLocalDate } = useDate()
  const { getInitials, getAvatarColor } = useAvatar()
  const { generate, rules: passwordRules } = usePasswordPolicy()
  const { confirm, notif } = useAppUtils()

  const { tenants } = storeToRefs(tenantStore)
  const { branches } = storeToRefs(branchStore)
  const { assignableRoles } = storeToRefs(roleStore)

  const formStep1 = ref(null)
  const formStep2 = ref(null)
  const formStep3 = ref(null)

  const showPassword = ref(false)
  const showPin = ref(false)
  const editingCode = ref(false)
  const step = ref(1)
  const tempPasswordDialog = ref(false)
  const temporaryPassword = ref('')

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const isEdit = computed(() => !!props.item?.id)

  const stepItems = [
    { title: t('staff.step.account'), value: 1 },
    { title: t('staff.step.employment'), value: 2 },
    { title: t('staff.step.review'), value: 3 }
  ]

  const filteredBranches = computed(() => {
    const list = branches.value?.data ?? branches.value ?? []

    if (isSuperAdmin() && form.tenant_id) {
      return list.filter(b => b.tenant_id === form.tenant_id)
    }

    return list
  })

  const onTenantChange = () => {
    form.branch_id = null
  }

  const defaultForm = () => ({
    tenant_id: isSuperAdmin() ? null : (authStore.tenant_id ?? null),
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    branch_id: null,
    role_id: null,
    hire_date: new Date(),
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

      if (val) {
        Object.assign(form, {
          id: val.id,
          tenant_id: val.tenant_id,
          branch_id: val.branch_id,
          role_id: val.role_id,
          hire_date: val.hire_date,
          hourly_rate: val.hourly_rate,
          salary: val.salary,
          pin_code: '',
          is_active: val.is_active,
          employee_code: val.employee_code
        })
      } else {
        Object.assign(form, defaultForm())
      }
    },
    { immediate: true }
  )

  const r = {
    required: v => !!v || t('validation.required'),
    email: v => /.+@.+\..+/.test(v) || t('validation.email'),
    pin: v => !v || /^\d{4,6}$/.test(v) || t('staff.validation.pin')
  }

  const nextStep = async () => {
    let valid = true

    if (step.value === 1) {
      const res = await formStep1.value.validate()
      valid = res.valid
    }

    if (step.value === 2) {
      const res = await formStep2.value.validate()
      valid = res.valid
    }

    if (!valid) return

    step.value++
  }

  const prevStep = () => {
    if (step.value === 2) formStep2.value?.resetValidation()
    if (step.value === 3) formStep3.value?.resetValidation()

    step.value--
  }

  const initials = n => getInitials(n)

  const avatarColor = n =>
    getAvatarColor(n, {
      palette: ['#3b5bdb', '#2f9e44', '#e67700', '#c92a2a'],
      fallback: '#808080'
    })

  const generatePassword = () => {
    form.password = generate()
    showPassword.value = true
  }

  const confirmResetPassword = () => {
    confirm({
      title: t('staff.reset_password'),
      message: t('staff.reset_password_confirm'),
      options: { type: 'warning', width: 500 },
      agree: async () => {
        try {
          temporaryPassword.value = await staffStore.resetPassword(props.item.id)
          tempPasswordDialog.value = true
        } catch {
          notif(t('staff.reset_password_failed'), { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }

  const submit = async () => {
    const res = await formStep2.value.validate()

    if (!res.valid) return

    emit('save', {
      ...form,
      hire_date: form.hire_date instanceof Date
        ? formatLocalDate(form.hire_date)
        : form.hire_date
    })
  }

  const close = () => {
    formStep1.value?.resetValidation()
    formStep2.value?.resetValidation()
    formStep3.value?.resetValidation()

    Object.assign(form, defaultForm())

    step.value = 1

    emit('update:modelValue', false)
  }

  onMounted(() => {
    if (isSuperAdmin()) tenantStore.fetchTenants()

    branchStore.fetchBranches()
    roleStore.fetchRoles()
  })
</script>
<style scoped>
  .readonly-field :deep(.v-field) {
    background-color: rgba(var(--v-theme-on-surface), 0.06) !important;
  }
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
  .staff-stepper :deep(.v-stepper-header) {
    box-shadow: none;
    padding: 12px 20px 0;
  }
  .staff-stepper :deep(.v-stepper-window) {
    margin: 0;
    padding: 0;
  }
</style>
