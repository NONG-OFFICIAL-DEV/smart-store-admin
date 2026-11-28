<template>
  <AppDialog
    v-model="model"
    :max-width="520"
    :title="isEdit ? $t('cash_registers.dialog.title_edit') : $t('cash_registers.dialog.title_add')"
    :loading="loading"
  >
    <v-form ref="formRef">
      <v-row density="compact">
        <v-col cols="12">
          <v-select
            v-model="form.branch_id"
            :items="branches"
            item-title="name"
            item-value="id"
            :label="$t('cash_registers.form.branch') + ' *'"
            variant="outlined"
            rounded="lg"
            :rules="[r.required]"
            :disabled="isEdit"
          />
        </v-col>
        <v-col cols="12">
          <v-text-field
            v-model="form.name"
            :label="$t('cash_registers.form.name') + ' *'"
            variant="outlined"
            rounded="lg"
            :rules="[r.required]"
            prepend-inner-icon="mdi-cash-register"
            maxlength="100"
          />
        </v-col>
        <v-col cols="12">
          <v-textarea
            v-model="form.description"
            :label="$t('cash_registers.form.description')"
            variant="outlined"
            rounded="lg"
            rows="2"
          />
        </v-col>
        <v-col cols="12">
          <v-card rounded="lg" border elevation="0" class="px-3 py-2 d-flex align-center justify-space-between w-100">
            <div>
              <div class="text-body-medium font-weight-medium">{{ $t('status.active') }}</div>
              <div class="text-body-small text-grey">{{ $t('cash_registers.form.active_hint') }}</div>
            </div>
            <v-switch v-model="form.is_active" color="success" inset hide-details density="compact" />
          </v-card>
        </v-col>
      </v-row>
    </v-form>

    <template #actions="{ loading: busy }">
      <v-btn variant="tonal" rounded="lg" :disabled="busy" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        :color="isEdit ? 'primary' : 'success'"
        variant="flat"
        rounded="lg"
        :prepend-icon="isEdit ? 'mdi-content-save-outline' : 'mdi-plus'"
        :loading="busy"
        @click="save"
      >
        {{ isEdit ? $t('btn.save_changes') : $t('btn.add') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()
  const props = defineProps({
    modelValue: Boolean,
    cashRegister: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    loading: Boolean
  })
  const emit = defineEmits(['update:modelValue', 'save'])

  const formRef = ref(null)
  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })
  const isEdit = computed(() => !!props.cashRegister?.id)

  const defaultForm = () => ({
    branch_id: null,
    name: '',
    description: '',
    is_active: true
  })

  const form = reactive(defaultForm())

  watch(
    () => props.cashRegister,
    val => {
      Object.assign(
        form,
        val
          ? {
              branch_id: val.branch_id ?? null,
              name: val.name ?? '',
              description: val.description ?? '',
              is_active: val.is_active ?? true
            }
          : defaultForm()
      )
    },
    { immediate: true }
  )

  const r = {
    required: v => !!v || t('form.required')
  }

  const save = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    const payload = {
      ...(isEdit.value ? { id: props.cashRegister.id } : {}),
      branch_id: form.branch_id,
      name: form.name,
      description: form.description,
      is_active: form.is_active
    }

    emit('save', payload)
  }

  const close = () => {
    model.value = false
  }

  watch(model, val => {
    if (!val) {
      formRef.value?.reset()
      Object.assign(form, defaultForm())
    }
  })
</script>

<style scoped>
  .w-100 {
    width: 100%;
  }
</style>
