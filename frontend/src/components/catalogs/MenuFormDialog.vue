<script setup>
  import { ref, watch, onMounted } from 'vue'
  import { useTenantStore } from '@/stores/tenantStore'
  import { usePermission } from '@/composables/usePermission'
  const { isSuperAdmin } = usePermission()
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
  <v-dialog :model-value="modelValue" max-width="560" persistent>
    <v-card rounded="xl" elevation="0" class="menu-dialog">
      <!-- HEADER -->
      <div class="dialog-header pa-6 pb-4">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar
              :color="editMode ? 'primary' : 'success'"
              variant="tonal"
              size="44"
              rounded="lg"
              class="me-3"
            >
              <v-icon
                :icon="
                  editMode ? 'mdi-pencil-outline' : 'mdi-book-plus-outline'
                "
                size="22"
              />
            </v-avatar>
            <div>
              <p
                class="text-body-1 font-weight-bold mb-0"
                style="color: #1a1a2e"
              >
                {{ editMode ? 'Edit Menu' : 'Create New Menu' }}
              </p>
              <p class="text-caption text-medium-emphasis mb-0">
                {{
                  editMode
                    ? 'Update menu details and settings'
                    : 'Define a new menu for your store'
                }}
              </p>
            </div>
          </div>
          <v-btn
            icon="mdi-close"
            variant="text"
            size="small"
            density="comfortable"
            @click="close"
          />
        </div>
      </div>

      <v-divider />

      <!-- BODY -->
      <v-card-text class="pa-6">
        <v-form ref="formRef">
          <!-- Tenant (super admin only) -->
          <template v-if="isSuperAdmin()">
            <p
              class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block mb-1"
            >
              Tenant
            </p>
            <v-select
              v-model="form.tenant_id"
              :items="tenantStore.tenants"
              item-title="name"
              item-value="id"
              placeholder="Select tenant"
              variant="outlined"
              rounded="lg"
              density="comfortable"
              prepend-inner-icon="mdi-domain"
              :rules="[v => !!v || 'Tenant is required']"
              class="mb-4"
            />
          </template>

          <!-- Menu Name -->
          <p
            class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block mb-1"
          >
            Menu Name
            <span class="text-error">*</span>
          </p>
          <v-text-field
            v-model="form.name"
            placeholder="e.g. Breakfast Menu, Drinks, Lunch Special"
            variant="outlined"
            rounded="lg"
            density="comfortable"
            prepend-inner-icon="mdi-silverware-fork-knife"
            :rules="[
              v => !!v || 'Menu name is required',
              v => v?.length >= 2 || 'At least 2 characters'
            ]"
            class="mb-4"
            counter="80"
            maxlength="80"
          />

          <!-- Description -->
          <p
            class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block mb-1"
          >
            Description
            <span class="text-medium-emphasis">(optional)</span>
          </p>
          <v-textarea
            v-model="form.description"
            placeholder="Brief description of this menu..."
            variant="outlined"
            rounded="lg"
            rows="3"
            no-resize
            counter="300"
            maxlength="300"
            class="mb-2"
          />

          <!-- Toggles -->
          <v-row class="mt-2" dense>
            <v-col cols="6">
              <v-card
                :class="[
                  'toggle-card pa-3',
                  form.is_default ? 'toggle-card--active-primary' : ''
                ]"
                rounded="lg"
                flat
                @click="form.is_default = !form.is_default"
              >
                <div class="d-flex align-center justify-space-between">
                  <div class="d-flex align-center gap-2">
                    <v-icon
                      icon="mdi-star-outline"
                      size="18"
                      :color="form.is_default ? 'primary' : 'medium-emphasis'"
                    />
                    <span class="text-body-2 font-weight-medium">Default</span>
                  </div>
                  <v-switch
                    v-model="form.is_default"
                    color="primary"
                    inset
                    density="compact"
                    hide-details
                    @click.stop
                  />
                </div>
                <p class="text-caption text-medium-emphasis mt-1 mb-0">
                  Show this menu first
                </p>
              </v-card>
            </v-col>

            <v-col cols="6">
              <v-card
                :class="[
                  'toggle-card pa-3',
                  form.is_active ? 'toggle-card--active-success' : ''
                ]"
                rounded="lg"
                flat
                @click="form.is_active = !form.is_active"
              >
                <div class="d-flex align-center justify-space-between">
                  <div class="d-flex align-center gap-2">
                    <v-icon
                      icon="mdi-check-circle-outline"
                      size="18"
                      :color="form.is_active ? 'success' : 'medium-emphasis'"
                    />
                    <span class="text-body-2 font-weight-medium">Active</span>
                  </div>
                  <v-switch
                    v-model="form.is_active"
                    color="success"
                    inset
                    density="compact"
                    hide-details
                    @click.stop
                  />
                </div>
                <p class="text-caption text-medium-emphasis mt-1 mb-0">
                  Visible to customers
                </p>
              </v-card>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />

      <!-- ACTIONS -->
      <v-card-actions class="pa-4 gap-2">
        <v-spacer />
        <v-btn
          variant="text"
          rounded="lg"
          class="text-none px-5"
          @click="close"
        >
          Cancel
        </v-btn>
        <v-btn
          :color="editMode ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          class="text-none px-6"
          :prepend-icon="editMode ? 'mdi-content-save-outline' : 'mdi-plus'"
          @click="save"
        >
          {{ editMode ? 'Save Changes' : 'Create Menu' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
  .menu-dialog {
    border: 1px solid rgba(0, 0, 0, 0.06);
  }

  .dialog-header {
    background: rgba(0, 0, 0, 0.01);
  }

  .toggle-card {
    border: 1.5px solid rgba(0, 0, 0, 0.08);
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
    background: rgba(0, 0, 0, 0.01);
  }

  .toggle-card:hover {
    border-color: rgba(0, 0, 0, 0.15);
  }

  .toggle-card--active-primary {
    border-color: rgb(var(--v-theme-primary), 0.4) !important;
    background: rgb(var(--v-theme-primary), 0.04) !important;
  }

  .toggle-card--active-success {
    border-color: rgb(var(--v-theme-success), 0.4) !important;
    background: rgb(var(--v-theme-success), 0.04) !important;
  }
</style>
