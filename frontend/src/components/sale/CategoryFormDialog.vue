<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="close"
    max-width="450"
  >
    <v-card class="rounded-xl">
      <v-card-title class="pa-4 font-weight-bold d-flex align-center">
        <v-icon start color="primary">
          {{ editMode ? 'mdi-pencil' : 'mdi-plus-circle' }}
        </v-icon>
        {{ editMode ? 'Edit Category' : 'Add New Category' }}
      </v-card-title>

      <v-divider />

      <v-form ref="formRef" @submit.prevent="save">
        <v-card-text class="pa-6">
          <v-label class="mb-2 font-weight-medium">Category Name</v-label>
          <v-text-field
            v-model="form.name"
            placeholder="e.g. Italian Coffee"
            variant="outlined"
            density="comfortable"
            :rules="[v => !!v || 'Name is required']"
            required
          />

          <v-list-item class="px-0 mt-2">
            <template #prepend>
              <v-icon icon="mdi-eye-outline" class="me-2" />
            </template>
            <v-list-item-title>Visibility Status</v-list-item-title>
            <v-list-item-subtitle>
              Show this category on the menu
            </v-list-item-subtitle>
            <template #append>
              <v-switch v-model="form.is_active" color="primary" hide-details />
            </template>
          </v-list-item>
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" class="text-none px-4" @click="close">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            class="text-none px-6 rounded-lg"
            variant="flat"
            type="submit"
            :loading="categoryStore.loading"
          >
            {{ editMode ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-form>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, watch } from 'vue'
  import { useCategoryMenuStore } from '@/stores/categoryMenu'

  const props = defineProps({
    modelValue: Boolean,
    editMode: Boolean,
    item: Object
  })

  const emit = defineEmits(['update:modelValue', 'save'])
  const categoryStore = useCategoryMenuStore()
  const formRef = ref(null)

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
      }
    }
  )

  function close() {
    emit('update:modelValue', false)
    formRef.value?.resetValidation()
  }

  async function save() {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    emit('save', { ...form.value })
  }
</script>
