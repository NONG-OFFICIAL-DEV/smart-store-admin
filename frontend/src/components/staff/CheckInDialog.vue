<template>
  <v-dialog v-model="model" max-width="500">
    <v-card rounded="xl" class="pa-4">
      <!-- Header -->
      <v-card-title class="d-flex align-center">
        <v-icon icon="mdi-clock-outline" class="mr-2" color="primary" />
        <span class="font-weight-black">Check-in / Check-out</span>
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="closeDialog" />
      </v-card-title>

      <!-- Form -->
      <v-card-text>
        <v-form ref="formRef" v-model="valid">
          <!-- Employee -->
          <v-autocomplete
            v-model="form.employee_id"
            label="Select Employee"
            :items="employees"
            :item-title="
              employee => `${employee.first_name} ${employee.last_name}`
            "
            item-value="id"
            :rules="[rules.required]"
            variant="outlined"
            rounded="lg"
            class="mb-3"
          />

          <v-row>
            <v-col cols="6">
              <v-text-field
                v-model="form.check_in"
                label="Check-in Time"
                type="time"
                :rules="[rules.required]"
                variant="outlined"
                rounded="lg"
              />
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="form.check_out"
                label="Check-out Time"
                variant="outlined"
                rounded="lg"
                type="time"
              />
            </v-col>
          </v-row>

          <v-textarea
            v-model="form.note"
            label="Notes / Remarks"
            variant="outlined"
            rounded="lg"
            rows="3"
          />
        </v-form>
      </v-card-text>

      <!-- Actions -->
      <v-card-actions class="pa-4">
        <v-btn
          block
          color="primary"
          size="large"
          variant="flat"
          rounded="lg"
          class="text-none font-weight-bold"
          @click="submit"
        >
          Confirm Attendance
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed } from 'vue'

  /* Props */
  const props = defineProps({
    modelValue: Boolean,
    employees: {
      type: Array,
      required: true
    }
  })

  /* Emits */
  const emit = defineEmits(['update:modelValue', 'save'])

  /* Dialog model */
  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  /* Form */
  const formRef = ref(null)
  const valid = ref(true)

  const defaultForm = () => ({
    employee_id: null,
    check_in: '',
    check_out: '',
    note: ''
  })

  const form = ref(defaultForm())

  /* Rules */
  const rules = {
    required: v => !!v || 'This field is required'
  }

  /* Submit */
  function submit() {
    formRef.value?.validate().then(success => {
      if (!success) return
      emit('save', { ...form.value })
      closeDialog()
    })
  }

  /* Close & reset */
  function closeDialog() {
    form.value = defaultForm()
    emit('update:modelValue', false)
  }
</script>
