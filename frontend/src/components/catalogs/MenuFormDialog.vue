<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="480"
    :title="$t('menus.list.new')"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-form ref="formRef" @submit.prevent="handleSubmit">
      <v-text-field
        v-model="form.name"
        :label="$t('menus.form.name')"
        :placeholder="$t('menus.form.name_placeholder')"
        variant="outlined"
        rounded="lg"
        :rules="[rules.required]"
        maxlength="80"
        autofocus
        class="mb-4"
      />
      <div class="d-flex align-center justify-space-between mb-3">
        <span class="text-body-2">{{ $t('status.active') }}</span>
        <v-switch v-model="form.is_active" color="success" hide-details density="compact" inset />
      </div>
      <div class="d-flex align-center justify-space-between">
        <span class="text-body-2">{{ $t('menus.list.default_badge') }}</span>
        <v-switch v-model="form.is_default" color="primary" hide-details density="compact" inset />
      </div>
    </v-form>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        color="success"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-plus"
        :loading="loading"
        @click="handleSubmit"
      >
        {{ $t('menus.list.new') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'

  const { t } = useI18n()

  const props = defineProps({
    modelValue: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue', 'saved'])

  const formRef = ref(null)

  const defaultForm = () => ({ name: '', is_active: true, is_default: false })
  const form = ref(defaultForm())

  const rules = {
    required: v => !!v?.trim() || t('validation.required')
  }

  watch(
    () => props.modelValue,
    open => {
      if (!open) {
        formRef.value?.reset()
        form.value = defaultForm()
      }
    }
  )

  const handleSubmit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    emit('saved', {
      name: form.value.name.trim(),
      is_active: form.value.is_active,
      is_default: form.value.is_default
    })
  }

  const close = () => emit('update:modelValue', false)
</script>
