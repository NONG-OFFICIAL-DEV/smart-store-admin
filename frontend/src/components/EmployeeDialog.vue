<template>
  <v-dialog v-model="show" max-width="900">
    <v-card>
      <v-toolbar class="bg-primary">
        <v-toolbar-title>
          {{ editing ? 'Edit Staff' : 'Create Staff' }}
        </v-toolbar-title>
        <v-spacer />
        <v-btn icon="mdi-close" @click="close"></v-btn>
      </v-toolbar>

      <v-card-text>
        <v-form ref="formRef" lazy-validation>
          <!-- Personal Info -->
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                label="First Name"
                v-model="firstName"
                :rules="[rules.required]"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                label="Last Name"
                v-model="lastName"
                :rules="[rules.required]"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field label="Phone Number" v-model="phone" />
            </v-col>
            <v-col cols="12" md="6">
              <v-select
                label="Gender"
                v-model="gender"
                :items="['Male', 'Female', 'Other']"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-date-input v-model="dob" label="Date of Birth" />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field label="Job Title" v-model="jobTitle" />
            </v-col>
          </v-row>

          <!-- Job Info -->
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field label="Department" v-model="department" />
            </v-col>
            <v-col cols="12" md="6">
              <v-date-input v-model="joiningDate" label="Date of Joining" />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                label="Emergency Contact Phone"
                v-model="emergencyPhone"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-select
                label="Status"
                v-model="status"
                :items="['Active', 'Inactive', 'On Leave']"
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn text @click="close">Cancel</v-btn>
        <v-btn color="primary" @click="submit">
          {{ editing ? 'Update' : 'Create' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, watch } from 'vue'

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
    required: v => !!v || 'Required.'
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
