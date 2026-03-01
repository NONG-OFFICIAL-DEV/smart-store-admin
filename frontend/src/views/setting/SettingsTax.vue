<template>
  <custom-title icon="mdi-shape-outline">Tax Settings</custom-title>
  
  <v-container fluid class="pa-0">
    <v-row>
      <v-col cols="12" lg="4">
        <v-card border elevation="0">
          <v-toolbar color="transparent" density="compact">
            <v-toolbar-title class="text-subtitle-1 font-weight-bold">
              <v-icon start :icon="editMode ? 'mdi-pencil' : 'mdi-plus'" size="small" />
              {{ editMode ? 'Edit Tax Rate' : 'Create New Tax' }}
            </v-toolbar-title>
          </v-toolbar>

          <v-divider />

          <v-card-text>
            <v-form ref="formRef" @submit.prevent="saveTax">
              <v-text-field
                v-model="form.name"
                label="Tax Name"
                placeholder="e.g. VAT, GST"
                variant="outlined"
                density="comfortable"
                :rules="[v => !!v || 'Name is required']"
                required
                class="mb-2"
              />
              
              <v-text-field
                v-model.number="form.rate"
                label="Tax Rate (%)"
                type="number"
                variant="outlined"
                density="comfortable"
                suffix="%"
                :rules="[v => v >= 0 || 'Rate must be 0 or higher']"
                required
                class="mb-2"
              />

              <v-switch 
                v-model="form.is_active" 
                label="Enable this tax" 
                color="primary"
                hide-details
                inset
              />

              <v-divider class="my-4" />

              <div class="d-flex gap-2">
                <v-btn 
                  type="submit" 
                  color="primary" 
                  elevation="0" 
                  :prepend-icon="editMode ? 'mdi-check' : 'mdi-plus'"
                >
                  {{ editMode ? 'Update' : 'Save' }}
                </v-btn>
                
                <v-btn
                  v-if="editMode"
                  variant="text"
                  @click="resetForm"
                >
                  Cancel
                </v-btn>
              </div>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="8">
        <v-card border elevation="0">
          <v-data-table
            :headers="headers"
            :items="taxes"
            hover
          >
            <template #item.rate="{ value }">
              <span class="font-weight-medium">{{ value }}%</span>
            </template>

            <template #item.is_active="{ value }">
              <v-chip
                :color="value ? 'success' : 'default'"
                size="small"
                variant="flat"
              >
                {{ value ? 'Active' : 'Inactive' }}
              </v-chip>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex justify-end">
                <v-btn
                  icon="mdi-pencil"
                  variant="text"
                  size="small"
                  color="primary"
                  @click="editTax(item)"
                />
                <v-btn
                  icon="mdi-trash-can"
                  variant="text"
                  size="small"
                  color="error"
                  @click="confirmDelete(item)"
                />
              </div>
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { reactive, ref } from 'vue'

const formRef = ref(null)
const editMode = ref(false)

const form = reactive({
  id: null,
  name: '',
  rate: 0,
  is_active: true
})

const headers = [
  { title: 'Tax Name', key: 'name', align: 'start' },
  { title: 'Rate', key: 'rate', align: 'end' },
  { title: 'Status', key: 'is_active', align: 'center' },
  { title: '', key: 'actions', align: 'end', sortable: false }
]

const taxes = ref([
  { id: 1, name: 'VAT', rate: 10, is_active: true },
  { id: 2, name: 'Service Tax', rate: 5, is_active: false }
])

async function saveTax() {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  if (editMode.value) {
    const index = taxes.value.findIndex(t => t.id === form.id)
    if (index !== -1) taxes.value[index] = { ...form }
  } else {
    taxes.value.push({ ...form, id: Date.now() })
  }

  resetForm()
}

function editTax(item) {
  Object.assign(form, item)
  editMode.value = true
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function confirmDelete(item) {
  if (confirm(`Are you sure you want to delete ${item.name}?`)) {
    taxes.value = taxes.value.filter(t => t.id !== item.id)
  }
}

function resetForm() {
  form.id = null
  form.name = ''
  form.rate = 0
  form.is_active = true
  editMode.value = false
  formRef.value?.reset()
}
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>