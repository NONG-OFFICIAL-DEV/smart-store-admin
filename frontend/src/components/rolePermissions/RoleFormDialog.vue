<template>
  <v-dialog
    :model-value="modelValue"
    max-width="480"
    persistent
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card rounded="xl" elevation="0" border>
      <v-card-title class="d-flex align-center justify-space-between">
        <div>
          {{ isEdit ? 'Edit Role' : 'New Role' }}
        </div>
        <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-6">
        <v-form ref="formRef">
          <v-row dense>
            <v-col cols="12">
              <v-text-field
                v-model="form.name"
                label="Role Name"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-shield-account"
                :rules="[rules.required, rules.maxLen(80)]"
                counter="80"
                hint="e.g. Branch Manager, Cashier, Inventory Staff"
                persistent-hint
              />
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="form.description"
                label="Description"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-text"
                rows="3"
                hint="What can users with this role do?"
                persistent-hint
                clearable
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-6 pt-4 gap-3">
        <v-btn variant="tonal" rounded="lg" @click="close">Cancel</v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          @click="handleSubmit"
        >
          {{ isEdit ? 'Save Changes' : 'Create Role' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'

  // ── Props & Emits ─────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editItem: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  // ── Refs ──────────────────────────────────────────────────────────────────────
  const formRef = ref(null)

  const defaultForm = () => ({ id: null, name: '', description: '' })
  const form = ref(defaultForm())
  const isEdit = computed(() => !!props.editItem)

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || 'Required',
    maxLen: n => v => !v || v.length <= n || `Max ${n} chars`
  }

  // ── Watch editItem ────────────────────────────────────────────────────────────
  watch(
    () => props.editItem,
    item => {
      form.value = item
        ? { id: item.id, name: item.name, description: item.description || '' }
        : defaultForm()
    },
    { immediate: true }
  )

  // ── Actions ───────────────────────────────────────────────────────────────────
  const handleSubmit = async () => {
    const { valid } = await formRef.value.validate()
    if (!valid) return
    emit('saved', { ...form.value })
  }

  const close = () => {
    formRef.value?.reset()
    form.value = defaultForm()
    emit('update:modelValue', false)
  }
</script>
