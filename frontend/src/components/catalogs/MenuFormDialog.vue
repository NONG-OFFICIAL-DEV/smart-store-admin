<script setup>
  import { ref, watch, onMounted } from 'vue'
  import { useTenantStore } from '@/stores/tenantStore'
  import { usePermission } from '@/composables/usePermission'
  import AppDialog from '@/components/common/AppDialog.vue'
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
  const formRef = ref(null)

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
    formRef.value?.validate().then(({ valid }) => {
      if (!valid) return

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
    })
  }

  // /v1/tenants is superadmin-only — tenant-logged-in users would get
  // Forbidden here, so skip the fetch for them.
  onMounted(async () => {
    if (isSuperAdmin()) await tenantStore.fetchTenants()
  })
</script>
<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="560"
    :scrollable="false"
    :title="
      editMode ? $t('menus.dialog.titleEdit') : $t('menus.dialog.titleCreate')
    "
    :subtitle="
      editMode
        ? $t('menus.dialog.subtitleEdit')
        : $t('menus.dialog.subtitleCreate')
    "
    :icon="editMode ? 'mdi-pencil-outline' : 'mdi-book-plus-outline'"
    :color="editMode ? 'primary' : 'success'"
    :submit-text="
      editMode ? $t('btn.save_changes') : $t('menus.dialog.create_menu')
    "
    :submit-icon="editMode ? 'mdi-content-save-outline' : 'mdi-plus'"
    @update:model-value="emit('update:modelValue', $event)"
    @close="close"
    @submit="save"
  >
    <!-- BODY -->
    <v-form ref="formRef">
      <!-- Tenant (super admin only) -->
      <template v-if="isSuperAdmin()">
        <p
          class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block mb-1"
        >
          {{ $t('menus.form.tenant') }}
        </p>
        <v-select
          v-model="form.tenant_id"
          :items="tenantStore.tenants"
          item-title="name"
          item-value="id"
          :placeholder="$t('menus.form.tenant_placeholder')"
          variant="outlined"
          rounded="lg"
          density="comfortable"
          prepend-inner-icon="mdi-domain"
          :rules="[v => !!v || $t('menus.validation.tenant_required')]"
          class="mb-4"
        />
      </template>

      <!-- Menu Name -->
      <p
        class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block mb-1"
      >
        {{ $t('menus.form.name') }}
        <span class="text-error">*</span>
      </p>
      <v-text-field
        v-model="form.name"
        :placeholder="$t('menus.form.name_placeholder')"
        variant="outlined"
        rounded="lg"
        density="comfortable"
        prepend-inner-icon="mdi-silverware-fork-knife"
        :rules="[
          v => !!v || $t('menus.validation.name_required'),
          v => v?.length >= 2 || $t('menus.validation.min_2_chars')
        ]"
        class="mb-4"
        counter="80"
        maxlength="80"
      />

      <!-- Description -->
      <p
        class="text-body-2 font-weight-medium text-grey-darken-2 mb-1 d-block mb-1"
      >
        {{ $t('form.description') }}
        <span class="text-medium-emphasis">({{ $t('form.optional') }})</span>
      </p>
      <v-textarea
        v-model="form.description"
        :placeholder="$t('menus.dialog.description_placeholder')"
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
            class="px-3 py-2 d-flex align-center justify-space-between w-100"
          >
            <div>
              <div class="d-flex align-center gap-2">
                <v-icon
                  icon="mdi-star-outline"
                  size="18"
                  :color="form.is_default ? 'primary' : 'medium-emphasis'"
                />
                <span class="text-body-2 font-weight-medium">
                  {{ $t('products.variant.default') }}
                </span>
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                {{ $t('menus.dialog.show_first_hint') }}
              </p>
            </div>

            <v-switch
              v-model="form.is_default"
              color="primary"
              inset
              density="compact"
              hide-details
              @click.stop
            />
          </v-card>
        </v-col>

        <v-col cols="6">
          <v-card
            rounded="lg"
            border
            elevation="0"
            :class="[
              'toggle-card pa-3',
              form.is_active ? 'toggle-card--active-success' : ''
            ]"
            flat
            @click="form.is_active = !form.is_active"
            class="px-3 py-2 d-flex align-center justify-space-between w-100"
          >
            <div>
              <div class="d-flex align-center gap-2">
                <v-icon
                  icon="mdi-check-circle-outline"
                  size="18"
                  :color="form.is_active ? 'success' : 'medium-emphasis'"
                />
                <span class="text-body-2 font-weight-medium">
                  {{ $t('status.active') }}
                </span>
              </div>
              <p class="text-caption text-medium-emphasis mb-0">
                {{ $t('menus.dialog.visible_to_customers_hint') }}
              </p>
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
      </v-row>
    </v-form>
  </AppDialog>
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
