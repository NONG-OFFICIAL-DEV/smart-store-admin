<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="480"
    :title="isEdit ? $t('roles.dialog.edit') : $t('roles.dialog.new')"
    :subtitle="
      isEdit ? $t('roles.dialog.edit_subtitle') : $t('roles.dialog.new_subtitle')
    "
    icon="mdi-shield-account"
    :color="isEdit ? 'primary' : 'success'"
    :loading="loading"
    :submit-text="isEdit ? $t('btn.save_changes') : $t('roles.create')"
    @update:model-value="$emit('update:modelValue', $event)"
    @close="close"
    @submit="handleSubmit"
  >
        <v-form ref="formRef">
          <v-row dense>
            <v-col cols="12">
              <v-text-field
                v-model="form.name"
                :label="$t('roles.field.name')"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-shield-account"
                :rules="[rules.required, rules.maxLen(80)]"
                counter="80"
                :hint="$t('roles.field.name_hint')"
                persistent-hint
              />
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="form.description"
                :label="$t('form.description')"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-text"
                rows="3"
                :hint="$t('roles.field.description_hint')"
                persistent-hint
                clearable
              />
            </v-col>
          </v-row>
        </v-form>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'

  // ── Props & Emits ─────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editItem: { type: Object, default: null },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])
  const { t } = useI18n()

  // ── Refs ──────────────────────────────────────────────────────────────────────
  const formRef = ref(null)

  const defaultForm = () => ({ id: null, name: '', description: '' })
  const form = ref(defaultForm())
  const isEdit = computed(() => !!props.editItem)

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || t('validation.required'),
    maxLen: n => v => !v || v.length <= n || t('validation.max_length', { n })
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
