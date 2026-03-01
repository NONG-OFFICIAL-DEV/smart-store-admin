<template>
  <v-dialog v-model="internalOpen" max-width="800" persistent>
    <v-card>
      <v-toolbar
        :title="form.id ? 'Edit Supplier' : 'Add Supplier'"
        class="bg-primary"
      >
        <v-spacer />
        <v-btn icon="mdi-close" @click="close"></v-btn>
      </v-toolbar>

      <v-form ref="supplierForm" @submit.prevent="save">
        <v-card-text>
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.name"
                :rules="[v => !!v || 'Name is required']"
                required
              >
                <template v-slot:label>
                  Supplier Name
                  <span class="required-asterisk">*</span>
                </template>
              </v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.contact_name"
                label="Contact Person"
              />
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field v-model="form.phone" label="Phone" />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.email"
                type="email"
                :rules="emailRules"
                required
              >
                <template v-slot:label>
                  Email
                  <span class="required-asterisk">*</span>
                </template>
              </v-text-field>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12" md="8">
              <v-text-field v-model="form.address" label="Address" />
            </v-col>
            <v-col cols="12" md="4">
              <v-switch
                v-model="form.status"
                :label="form.status ? 'Active' : 'Inactive'"
                :true-value="1"
                :false-value="0"
                color="success"
                inset
                hide-details
              ></v-switch>
            </v-col>
          </v-row>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="close">Cancel</v-btn>
          <v-btn color="primary" variant="elevated" @click="save">Save</v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, watch } from 'vue'

  const props = defineProps({
    modelValue: Boolean,
    supplier: { type: Object, default: null }
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const internalOpen = ref(false)
  const supplierForm = ref(null) // Ref for the form element

  const initialForm = {
    id: null,
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    status: 1
  }

  const form = ref({ ...initialForm })

  const statusOptions = ref([
    { id: 1, name: 'Active' },
    { id: 0, name: 'Inactive' }
  ])

  // Validation Rules
  const emailRules = [
    v => !!v || 'Email is required',
    v => /.+@.+\..+/.test(v) || 'E-mail must be valid'
  ]

  const resetForm = () => {
    form.value = { ...initialForm }
    if (supplierForm.value) {
      supplierForm.value.resetValidation()
    }
  }

  watch(
    () => props.modelValue,
    val => (internalOpen.value = val),
    { immediate: true }
  )
  watch(internalOpen, val => {
    emit('update:modelValue', val)
    if (!val) resetForm() // Reset when closing
  })

  watch(
    () => props.supplier,
    newVal => {
      if (newVal) {
        form.value = { ...newVal }
      } else {
        resetForm()
      }
    },
    { immediate: true }
  )

  const close = () => (internalOpen.value = false)

  const save = async () => {
    // Validate form before emitting save
    const { valid } = await supplierForm.value.validate()

    if (valid) {
      emit('save', { ...form.value })
      close()
    }
  }
</script>
<style scoped>
  /* Targets the asterisk specifically if you want it red globally in this form */
  :deep(.v-label.v-field-label--floating) {
    opacity: 1; /* Keeps the label clear */
  }

  /* Custom class or global selector to make the star red */
  .required-asterisk {
    color: rgb(var(--v-theme-error)); /* Uses Vuetify's error color variable */
    margin-left: 2px;
    font-weight: bold;
  }
</style>
