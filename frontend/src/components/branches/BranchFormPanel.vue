<template>
  <v-card rounded="lg" elevation="0" border class="pa-4">
    <div class="d-flex align-center mb-4">
      <v-icon :icon="isEdit ? 'mdi-store-edit-outline' : 'mdi-store-plus-outline'" class="me-2" />
      <span class="text-subtitle-1 font-weight-bold">
        {{ isEdit ? $t('branches.dialog.titleEdit') : $t('branches.dialog.titleCreate') }}
      </span>
    </div>

    <v-form ref="formRef" @submit.prevent="submit">
      <v-text-field
        v-model="form.name"
        :label="$t('branches.form.name') + ' *'"
        variant="outlined"
        density="comfortable"
        rounded="lg"
        :rules="[rules.required]"
        prepend-inner-icon="mdi-storefront-outline"
        :placeholder="$t('branches.form.name_placeholder')"
        class="mb-2"
      />

      <v-select
        v-model="form.branch_type_id"
        :items="tenantStore.branchTypes"
        item-title="name"
        item-value="id"
        :label="$t('branches.form.branch_type')"
        variant="outlined"
        density="comfortable"
        rounded="lg"
        prepend-inner-icon="mdi-store-outline"
        class="mb-2"
      />

      <v-text-field
        v-model="form.phone"
        :label="$t('branches.form.phone')"
        variant="outlined"
        density="comfortable"
        rounded="lg"
        prepend-inner-icon="mdi-phone-outline"
        :placeholder="$t('branches.dialog.phone_placeholder')"
        class="mb-2"
      />

      <v-text-field
        v-model="form.address"
        :label="$t('branches.form.address')"
        variant="outlined"
        density="comfortable"
        rounded="lg"
        prepend-inner-icon="mdi-map-marker-outline"
        :placeholder="$t('branches.dialog.address_line1_placeholder')"
        class="mb-2"
      />

      <v-text-field
        v-model="form.email"
        :label="$t('form.email')"
        type="email"
        variant="outlined"
        density="comfortable"
        rounded="lg"
        :rules="[rules.email]"
        prepend-inner-icon="mdi-email-outline"
        :placeholder="$t('branches.dialog.email_placeholder')"
        class="mb-3"
      />

      <div class="d-flex align-center justify-space-between mb-2">
        <span class="text-body-2">{{ $t('branches.dialog.is_open_label') }}</span>
        <v-switch v-model="form.is_open" color="primary" hide-details density="compact" inset />
      </div>
      <div class="d-flex align-center justify-space-between mb-4">
        <span class="text-body-2">{{ $t('branches.dialog.is_active_label') }}</span>
        <v-switch v-model="form.is_active" color="primary" hide-details density="compact" inset />
      </div>

      <div class="d-flex ga-2">
        <v-btn
          v-if="isEdit"
          variant="tonal"
          rounded="lg"
          class="flex-grow-1"
          :disabled="loading"
          @click="$emit('cancel')"
        >
          {{ $t('btn.cancel') }}
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          rounded="lg"
          class="flex-grow-1"
          :loading="loading"
          @click="submit"
        >
          {{ isEdit ? $t('branches.dialog.submitEdit') : $t('branches.dialog.submitCreate') }}
        </v-btn>
      </div>
    </v-form>
  </v-card>
</template>

<script setup>
  import { ref, reactive, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTenantStore } from '@/stores/tenantStore'
  import { useAuthStore } from '@/stores/authStore'

  const { t } = useI18n()
  const tenantStore = useTenantStore()
  const authStore = useAuthStore()

  const props = defineProps({
    branch: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['saved', 'cancel'])

  const isEdit = ref(false)
  const formRef = ref(null)

  const defaultForm = () => ({
    id: null,
    branch_type_id: null,
    name: '',
    address: '',
    phone: '',
    email: '',
    is_open: true,
    is_active: true
  })

  const form = reactive(defaultForm())

  const rules = {
    required: v => !!v || t('validation.required'),
    email: v => !v || /.+@.+\..+/.test(v) || t('validation.email')
  }

  watch(
    () => props.branch,
    val => {
      isEdit.value = !!val?.id
      Object.assign(form, defaultForm(), val
        ? {
            id: val.id,
            branch_type_id: val.branch_type_id ?? val.branch_type?.id ?? null,
            name: val.name ?? '',
            address: val.address_line1 ?? '',
            phone: val.phone ?? '',
            email: val.email ?? '',
            is_open: val.is_open ?? true,
            is_active: val.is_active ?? true
          }
        : {})
      formRef.value?.resetValidation()
    },
    { immediate: true }
  )

  // Branch type options are scoped to the tenant's own business type —
  // there's only ever one business type per tenant, so no picker for it.
  onMounted(() => {
    if (authStore.business_type_id) {
      tenantStore.fetchBranchTypeByBusinessType(authStore.business_type_id)
    }
  })

  async function submit() {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    emit('saved', {
      id: form.id,
      name: form.name,
      branch_type_id: form.branch_type_id || undefined,
      phone: form.phone || undefined,
      email: form.email || undefined,
      address_line1: form.address || undefined,
      is_open: form.is_open,
      is_active: form.is_active
    })
  }
</script>
