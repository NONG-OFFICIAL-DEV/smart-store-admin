<template>
  <v-navigation-drawer v-model="model" location="right" width="560" temporary>
    <div class="d-flex flex-column fill-height">
      <div class="d-flex align-center justify-space-between pa-4">
        <span class="text-h6 font-weight-bold">
          {{ isEdit ? $t('staff.dialog.edit') : $t('staff.dialog.add') }}
        </span>
        <v-btn icon="mdi-close" variant="text" :disabled="loading" @click="close" />
      </div>
      <v-divider />

      <div class="pa-4 drawer-scroll">
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
                  <AppDatePicker
                    v-model="form.hire_date"
                    :label="$t('staff.field.hire_date')"
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

        <!-- ── CREATE MODE ────────────────────────────────────────── -->
        <template v-else>
          <v-form ref="formStep1">
            <div class="form-section">
              <div class="form-section-label">
                <v-icon icon="mdi-account-outline" size="13" class="mr-1" />
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
          <v-divider />
          <v-form ref="formStep2">
            <div class="form-section">
              <div class="form-section-label">
                <v-icon icon="mdi-briefcase-outline" size="13" class="mr-1" />
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
                  <AppDatePicker
                    v-model="form.hire_date"
                    :label="$t('staff.field.hire_date')"
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
              </v-row>
            </div>
          </v-form>
        </template>
      </v-form>
      </div>

      <v-divider />
      <div class="pa-4 d-flex align-center ga-2">
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
      </div>
    </div>
  </v-navigation-drawer>

  <TemporaryPasswordDialog
    v-model="tempPasswordDialog"
    :password="temporaryPassword"
  />
</template>

<script setup>
  import { ref, reactive, computed, watch, onMounted } from 'vue'
  import { storeToRefs } from 'pinia'
  import { useI18n } from 'vue-i18n'
  import { useAuthStore } from '@/stores/authStore'
  import { useRoleStore } from '@/stores/roleStore'
  import { useBranchStore } from '@/stores/branchStore'
  import { useStaffStore } from '@/stores/staffStore'
  import { useDate } from '@/composables/useDate'
  import { useAvatar } from '@/composables/useAvatar'
  import { usePasswordPolicy } from '@/composables/usePasswordPolicy'
  import { useAppUtils } from '@/composables/useAppUtils'
  import TemporaryPasswordDialog from '@/components/common/TemporaryPasswordDialog.vue'
  import { AppDatePicker } from '@nong-official-dev/core'

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
  const staffStore = useStaffStore()
  const { formatLocalDate } = useDate()
  const { getInitials, getAvatarColor } = useAvatar()
  const { generate, rules: passwordRules } = usePasswordPolicy()
  const { confirm, notif } = useAppUtils()

  const { branches } = storeToRefs(branchStore)
  const { assignableRoles } = storeToRefs(roleStore)

  const formStep1 = ref(null)
  const formStep2 = ref(null)

  const showPassword = ref(false)
  const editingCode = ref(false)
  const tempPasswordDialog = ref(false)
  const temporaryPassword = ref('')

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const isEdit = computed(() => !!props.item?.id)

  // Preserve the old wrapper's @close side effect (reset validation/form)
  // for the X-button/Esc/backdrop dismiss path, which no longer emits a
  // separate close event in the new AppDialog — only update:modelValue.
  watch(model, val => {
    if (!val) {
      formStep1.value?.resetValidation()
      formStep2.value?.resetValidation()
      Object.assign(form, defaultForm())
    }
  })

  const filteredBranches = computed(
    () => branches.value?.data ?? branches.value ?? []
  )

  const defaultForm = () => ({
    tenant_id: authStore.tenant_id ?? null,
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
    is_active: true,
    employee_code: ''
  })

  const form = reactive(defaultForm())

  watch(
    () => props.item,
    val => {
      editingCode.value = false

      if (val) {
        Object.assign(form, {
          id: val.id,
          tenant_id: val.tenant_id,
          branch_id: val.branch_id,
          role_id: val.role_id,
          hire_date: val.hire_date,
          hourly_rate: val.hourly_rate,
          salary: val.salary,
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
    email: v => /.+@.+\..+/.test(v) || t('validation.email')
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
          temporaryPassword.value = await staffStore.resetPassword(
            props.item.id
          )
          tempPasswordDialog.value = true
        } catch {
          notif(t('staff.reset_password_failed'), { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }

  const submit = async () => {
    if (isEdit.value) {
      const res = await formStep2.value.validate()
      if (!res.valid) return
    } else {
      const [res1, res2] = await Promise.all([
        formStep1.value.validate(),
        formStep2.value.validate()
      ])
      if (!res1.valid || !res2.valid) return
    }

    emit('save', {
      ...form,
      hire_date:
        form.hire_date instanceof Date
          ? formatLocalDate(form.hire_date)
          : form.hire_date
    })
  }

  const close = () => {
    formStep1.value?.resetValidation()
    formStep2.value?.resetValidation()

    Object.assign(form, defaultForm())

    emit('update:modelValue', false)
  }

  onMounted(() => {
    branchStore.fetchBranches()
    roleStore.fetchRoles()
  })
</script>
<style scoped>
  .drawer-scroll {
    flex: 1 1 auto;
    overflow-y: auto;
  }
  .readonly-field :deep(.v-field) {
    background-color: rgba(var(--v-theme-on-surface), 0.06) !important;
  }
  .form-section {
    padding: 16px 0px;
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
</style>
