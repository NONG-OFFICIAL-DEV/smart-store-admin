<template>
  <AppDialog
    v-model="model"
    :max-width="450"
    :persistent="false"
    :title="editMode ? $t('categories.dialog.edit') : $t('categories.dialog.add')"
    :loading="categoryStore.loading"
  >
    <v-form ref="formRef" @submit.prevent="save">
      <v-label class="mb-2 font-weight-medium">{{ $t('categories.form.name') }}</v-label>
      <v-text-field
        v-model="form.name"
        :placeholder="$t('categories.form.name_placeholder')"
        variant="outlined"
        :rules="[v => !!v || $t('validation.required')]"
        required
      />

      <v-list-item class="px-0 mt-2">
        <template #prepend>
          <v-icon icon="mdi-eye-outline" class="me-2" />
        </template>
        <v-list-item-title>{{ $t('categories.form.visibility') }}</v-list-item-title>
        <v-list-item-subtitle>
          {{ $t('categories.form.visibility_hint') }}
        </v-list-item-subtitle>
        <template #append>
          <v-switch v-model="form.is_active" color="primary" hide-details />
        </template>
      </v-list-item>
    </v-form>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        :color="editMode ? 'primary' : 'success'"
        variant="flat"
        rounded="lg"
        :loading="loading"
        @click="save"
      >
        {{ editMode ? $t('btn.update') : $t('btn.create') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useCategoryMenuStore } from '@/stores/categoryMenu'
  import { AppDialog } from '@nong-official-dev/core'

  const props = defineProps({
    modelValue: Boolean,
    editMode: Boolean,
    item: Object
  })

  const emit = defineEmits(['update:modelValue', 'save'])
  const categoryStore = useCategoryMenuStore()
  const formRef = ref(null)

  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  const defaultForm = {
    id: null,
    name: '',
    is_active: true
  }

  const form = ref({ ...defaultForm })

  watch(
    () => props.modelValue,
    isOpen => {
      if (isOpen) {
        if (props.item) {
          form.value = { ...props.item }
        } else {
          form.value = { ...defaultForm }
        }
      } else {
        formRef.value?.resetValidation()
      }
    }
  )

  function close() {
    emit('update:modelValue', false)
  }

  async function save() {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    emit('save', { ...form.value })
  }
</script>
