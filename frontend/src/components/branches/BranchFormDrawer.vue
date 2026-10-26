<template>
  <AppDrawer
    :model-value="modelValue"
    width="420"
    :loading="loading"
    :persistent="loading"
    :confirm-close="() => confirmDiscard(isDirty)"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template #title>
      <v-icon :icon="isEdit ? 'mdi-store-edit-outline' : 'mdi-store-plus-outline'" class="me-2" />
      {{ isEdit ? $t('branches.dialog.titleEdit') : $t('branches.dialog.titleCreate') }}
    </template>

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
        v-model="form.email"
        :label="$t('form.email')"
        type="email"
        variant="outlined"
        density="comfortable"
        rounded="lg"
        :rules="[rules.email]"
        prepend-inner-icon="mdi-email-outline"
        :placeholder="$t('branches.dialog.email_placeholder')"
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
        class="mb-4"
      />

      <div class="text-body-2 font-weight-medium mb-2">{{ $t('branches.table.status') }}</div>
      <v-btn-toggle
        v-model="form.status"
        mandatory
        rounded="lg"
        divided
        density="comfortable"
        class="mb-2 flex-wrap"
      >
        <v-btn value="open" class="text-none">
          <v-icon icon="mdi-circle" size="10" color="success" class="me-2" />
          {{ $t('branches.status.open') }}
        </v-btn>
        <v-btn value="closed" class="text-none">
          <v-icon icon="mdi-circle" size="10" color="warning" class="me-2" />
          {{ $t('branches.status.closed') }}
        </v-btn>
        <v-btn value="disabled" class="text-none">
          <v-icon icon="mdi-circle" size="10" color="grey" class="me-2" />
          {{ $t('branches.status.disabled') }}
        </v-btn>
      </v-btn-toggle>
    </v-form>

    <template #footer>
      <v-btn
        variant="tonal"
        rounded="lg"
        class="flex-grow-1 text-none"
        :disabled="loading"
        @click="close"
      >
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        color="primary"
        variant="flat"
        rounded="lg"
        class="flex-grow-1 text-none"
        :loading="loading"
        @click="submit"
      >
        {{ isEdit ? $t('branches.dialog.submitEdit') : $t('branches.dialog.submitCreate') }}
      </v-btn>
    </template>
  </AppDrawer>
</template>

<script setup>
  import { ref, computed, watch, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useTenantStore } from '@/stores/tenantStore'
  import { useAuthStore } from '@/stores/authStore'
  import { useSettingsForm } from '@/composables/useSettingsForm.js'
  import { useConfirmDiscard } from '@/composables/useConfirmDiscard.js'
  import AppDrawer from '@/components/common/AppDrawer.vue'

  const { t } = useI18n()
  const tenantStore = useTenantStore()
  const authStore = useAuthStore()
  const { confirmDiscard } = useConfirmDiscard()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    branch: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const isEdit = computed(() => !!props.branch?.id)
  const formRef = ref(null)

  const defaultForm = () => ({
    id: null,
    branch_type_id: null,
    name: '',
    address: '',
    phone: '',
    email: '',
    status: 'open'
  })

  const { form, isDirty, load } = useSettingsForm(defaultForm())

  const rules = {
    required: v => !!v || t('validation.required'),
    email: v => !v || /.+@.+\..+/.test(v) || t('validation.email')
  }

  // Open=active+open, Closed=active+not-open, Disabled=not-active — one
  // toggle covers both real independent booleans on the Branch model.
  function toStatus(isActive, isOpen) {
    if (!isActive) return 'disabled'
    return isOpen ? 'open' : 'closed'
  }
  function fromStatus(status) {
    if (status === 'disabled') return { is_active: false, is_open: false }
    if (status === 'closed') return { is_active: true, is_open: false }
    return { is_active: true, is_open: true }
  }

  watch(
    () => props.modelValue,
    open => {
      if (!open) return
      load(
        props.branch
          ? {
              ...defaultForm(),
              id: props.branch.id,
              branch_type_id: props.branch.branch_type_id ?? props.branch.branch_type?.id ?? null,
              name: props.branch.name ?? '',
              address: props.branch.address_line1 ?? '',
              phone: props.branch.phone ?? '',
              email: props.branch.email ?? '',
              status: toStatus(props.branch.is_active ?? true, props.branch.is_open ?? true)
            }
          : defaultForm()
      )
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

  async function close() {
    if (props.loading) return
    if (!(await confirmDiscard(isDirty.value))) return
    emit('update:modelValue', false)
  }

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
      ...fromStatus(form.status)
    })
  }
</script>
