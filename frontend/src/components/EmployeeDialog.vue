<template>
  <AppDialog
    v-model="show"
    :max-width="900"
    :title="editing ? $t('staff.dialog.titleEdit') : $t('staff.dialog.titleCreate')"
    icon="mdi-account-outline"
    :color="editing ? 'primary' : 'success'"
    :submit-text="editing ? $t('btn.update') : $t('btn.create')"
    @close="close"
    @submit="submit"
  >
    <v-form ref="formRef" lazy-validation>
      <!-- Personal Info -->
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field
            :label="$t('form.first_name')"
            v-model="firstName"
            :rules="[rules.required]"
          />
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field
            :label="$t('form.last_name')"
            v-model="lastName"
            :rules="[rules.required]"
          />
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field :label="$t('staff.form.phone')" v-model="phone" />
        </v-col>
        <v-col cols="12" md="6">
          <v-select
            :label="$t('staff.form.gender')"
            v-model="gender"
            :items="genderOptions"
          />
        </v-col>
        <v-col cols="12" md="6">
          <AppDatePicker v-model="dob" :label="$t('staff.form.dob')" />
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field :label="$t('staff.form.job_title')" v-model="jobTitle" />
        </v-col>
      </v-row>

      <!-- Job Info -->
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field :label="$t('staff.form.department')" v-model="department" />
        </v-col>
        <v-col cols="12" md="6">
          <AppDatePicker v-model="joiningDate" :label="$t('staff.form.joining_date')" />
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field
            :label="$t('staff.form.emergency_phone')"
            v-model="emergencyPhone"
          />
        </v-col>
        <v-col cols="12" md="6">
          <v-select
            :label="$t('form.status')"
            v-model="status"
            :items="statusOptions"
          />
        </v-col>
      </v-row>
    </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, watch, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'
  import { AppDatePicker } from '@nong-official-dev/core'

  const { t } = useI18n()

  // Options
  const genderOptions = computed(() => [
    { title: t('staff.form.male'), value: 'Male' },
    { title: t('staff.form.female'), value: 'Female' },
    { title: t('staff.form.other'), value: 'Other' }
  ])

  const statusOptions = computed(() => [
    { title: t('status.active'), value: 'Active' },
    { title: t('status.inactive'), value: 'Inactive' },
    { title: t('status.on_leave'), value: 'On Leave' }
  ])

  // Props & emits
  const props = defineProps({
    modelValue: Boolean,
    employee: { type: Object, default: null }
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  // Dialog state
  const show = ref(props.modelValue)
  watch(
    () => props.modelValue,
    val => (show.value = val)
  )
  watch(show, val => emit('update:modelValue', val))

  // Form
  const formRef = ref(null)

  // Validation rules
  const rules = {
    required: v => !!v || t('validation.required')
  }

  // Fields
  const firstName = ref('')
  const lastName = ref('')
  const phone = ref('')
  const gender = ref('')
  const dob = ref(null)
  const jobTitle = ref('')
  const department = ref('')
  const joiningDate = ref(null)
  const status = ref('Active')
  const address = ref('')
  const emergencyName = ref('')
  const emergencyPhone = ref('')

  // Editing state
  const editing = ref(false)

  watch(
    () => props.employee,
    emp => {
      if (emp) {
        editing.value = true
        firstName.value = emp.first_name || ''
        lastName.value = emp.last_name || ''
        phone.value = emp.phone || ''
        gender.value = emp.gender || ''
        dob.value = emp.dob || null
        jobTitle.value = emp.job_title || ''
        department.value = emp.department || ''
        joiningDate.value = emp.joining_date || null
        status.value = emp.status || 'Active'
        address.value = emp.address || ''
        emergencyName.value = emp.emergency_name || ''
        emergencyPhone.value = emp.emergency_phone || ''
      } else {
        editing.value = false
        resetForm()
      }
    },
    { immediate: true }
  )

  // Methods
  function close() {
    show.value = false
  }

  function submit() {
    formRef.value?.validate().then(success => {
      if (success) {
        emit('save', {
          first_name: firstName.value,
          last_name: lastName.value,
          phone: phone.value,
          gender: gender.value,
          dob: dob.value,
          job_title: jobTitle.value,
          department: department.value,
          joining_date: joiningDate.value,
          status: status.value,
          address: address.value,
          emergency_name: emergencyName.value,
          emergency_phone: emergencyPhone.value
        })
        close()
      }
    })
  }

  function resetForm() {
    firstName.value = ''
    lastName.value = ''
    phone.value = ''
    gender.value = ''
    dob.value = null
    jobTitle.value = ''
    department.value = ''
    joiningDate.value = null
    status.value = 'Active'
    address.value = ''
    emergencyName.value = ''
    emergencyPhone.value = ''
    formRef.value?.resetValidation()
  }
</script>
