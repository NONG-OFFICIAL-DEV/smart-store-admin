<template>
  <AppDialog
    :model-value="modelValue"
    :max-width="480"
    :title="isEdit ? $t('roles.dialog.edit') : $t('roles.dialog.new')"
    :loading="loading"
    @update:model-value="$emit('update:modelValue', $event)"
  >
        <v-form ref="formRef">
          <v-row dense>
            <v-col cols="12">
              <v-text-field
                v-model="form.name"
                :label="$t('roles.field.name')"
                variant="outlined"
                prepend-inner-icon="mdi-shield-account"
                :rules="[rules.required, rules.maxLen(80)]"
                :error-messages="serverErrors.name"
                counter="80"
                :hint="$t('roles.field.name_hint')"
                persistent-hint
                @update:model-value="serverErrors.name = null"
              />
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="form.description"
                :label="$t('form.description')"
                variant="outlined"
                prepend-inner-icon="mdi-text"
                rows="3"
                :hint="$t('roles.field.description_hint')"
                persistent-hint
                clearable
              />
            </v-col>
          </v-row>
        </v-form>

    <template #actions="{ loading }">
      <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="close">
        {{ $t('btn.cancel') }}
      </v-btn>
      <v-btn
        :color="isEdit ? 'primary' : 'success'"
        variant="flat"
        rounded="lg"
        :loading="loading"
        @click="handleSubmit"
      >
        {{ isEdit ? $t('btn.save_changes') : $t('roles.create') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { AppDialog } from '@nong-official-dev/core'

  // ── Props & Emits ─────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    editItem: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    serverErrorsProp: { type: Object, default: () => ({}) }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])
  const { t } = useI18n()

  // ── Refs ──────────────────────────────────────────────────────────────────────
  const formRef = ref(null)
  const serverErrors = reactive({})

  watch(
    () => props.serverErrorsProp,
    errs => {
      Object.keys(serverErrors).forEach(k => delete serverErrors[k])
      Object.assign(serverErrors, errs ?? {})
    }
  )

  const defaultForm = () => ({ id: null, name: '', description: '' })
  const form = ref(defaultForm())
  const isEdit = computed(() => !!props.editItem)

  // Preserve the old wrapper's @close side effect (reset form + clear
  // server errors) for the X-button/Esc/backdrop dismiss path, which no
  // longer emits a separate close event in the new AppDialog — only
  // update:modelValue.
  watch(
    () => props.modelValue,
    val => {
      if (!val) {
        formRef.value?.reset()
        form.value = defaultForm()
        Object.keys(serverErrors).forEach(k => delete serverErrors[k])
      }
    }
  )

  // ── Rules ─────────────────────────────────────────────────────────────────────
  const rules = {
    required: v => !!v || t('validation.required'),
    maxLen: n => v => !v || v.length <= n || t('validation.max_length', { n })
  }

  // ── Watch editItem ────────────────────────────────────────────────────────────
  watch(
    () => props.editItem,
    item => {
      Object.keys(serverErrors).forEach(k => delete serverErrors[k])
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
    Object.keys(serverErrors).forEach(k => delete serverErrors[k])
    emit('update:modelValue', false)
  }
</script>
