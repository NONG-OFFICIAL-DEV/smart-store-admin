<script setup>
  import { ref, watch, onMounted } from 'vue'
  import { useTenantStore } from '@/stores/tenantStore'

  /* ================= PROPS / EMITS ================= */
  const props = defineProps({
    modelValue: Boolean,
    editMode: Boolean,
    item: Object
  })

  const emit = defineEmits(['update:modelValue', 'save'])

  /* ================= TENANT ================= */
  const tenantStore = useTenantStore()

  /* ================= DEFAULT FORM ================= */
  const getDefaultForm = () => ({
    id: null,
    tenant_id: null,
    name: '',
    description: '',
    is_default: false,
    is_active: true
  })

  const form = ref(getDefaultForm())

  /* ================= WATCH ================= */
  watch(
    () => props.modelValue,
    open => {
      if (!open) return

      if (props.editMode && props.item) {
        form.value = { ...props.item }
      } else {
        form.value = getDefaultForm()
      }
    }
  )

  /* ================= METHODS ================= */
  function close() {
    emit('update:modelValue', false)
  }

  function save() {
    const payload = {
      id: form.value.id,
      tenant_id: form.value.tenant_id,
      name: form.value.name,
      description: form.value.description,
      is_default: form.value.is_default,
      is_active: form.value.is_active
    }

    emit('save', payload)
    close()
  }

  onMounted(async () => {
    await tenantStore.fetchTenants()
  })
</script>
<template>
  <v-dialog :model-value="modelValue" max-width="600" persistent>
    <v-card rounded="lg">
      <!-- HEADER -->
      <v-card-title>
        <div class="d-flex align-center justify-space-between">
          <div>
            {{ editMode ? 'Edit Menu' : 'Create Menu' }}
          </div>
          <v-btn icon="mdi-close" variant="text" size="small" @click="close" />
        </div>
      </v-card-title>
      <v-divider />
      <!-- BODY -->
      <v-card-text>
        <v-select
          v-model="form.tenant_id"
          :items="tenantStore.tenants"
          item-title="name"
          item-value="id"
          label="Tenant *"
          variant="outlined"
        />

        <v-text-field
          v-model="form.name"
          label="Menu Name"
          variant="outlined"
          required
        />

        <v-textarea
          v-model="form.description"
          label="Description"
          rows="3"
          variant="outlined"
        />

        <v-row>
          <v-col cols="6">
            <v-switch
              v-model="form.is_default"
              label="Default Menu"
              color="primary"
              inset
            />
          </v-col>

          <v-col cols="6">
            <v-switch
              v-model="form.is_active"
              label="Active"
              color="success"
              inset
            />
          </v-col>
        </v-row>
      </v-card-text>

      <!-- ACTIONS -->
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="close">Cancel</v-btn>
        <v-btn color="primary" @click="save">
          {{ editMode ? 'Update' : 'Save' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
