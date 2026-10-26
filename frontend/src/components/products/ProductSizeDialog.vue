<template>
  <v-dialog :model-value="modelValue" max-width="400" @update:model-value="$emit('update:modelValue', $event)">
    <v-card rounded="lg">
      <v-card-title>{{ isEdit ? t('products.detail.edit_size') : t('products.detail.add_size') }}</v-card-title>
      <v-card-text>
        <v-form ref="formRef">
          <v-text-field
            v-model="form.name"
            :label="t('products.variant.name')"
            placeholder="e.g. Small, Medium, Large"
            variant="outlined"
            rounded="lg"
            density="comfortable"
            class="mb-2"
            :rules="[r.required]"
            maxlength="80"
          />
          <v-text-field
            v-model.number="form.price"
            type="number"
            step="0.01"
            min="0"
            :label="t('products.detail.price')"
            variant="outlined"
            rounded="lg"
            density="comfortable"
            prefix="$"
            :rules="[r.required, r.nonNegative]"
          />
        </v-form>
      </v-card-text>
      <v-card-actions class="pa-4 pt-0">
        <v-spacer />
        <v-btn variant="tonal" rounded="lg" class="text-none" :disabled="loading" @click="close">
          {{ t('btn.cancel') }}
        </v-btn>
        <v-btn color="primary" variant="flat" rounded="lg" class="text-none" :loading="loading" @click="submit">
          {{ t('btn.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    size: { type: Object, default: null }, // existing variant for edit
    basePrice: { type: [String, Number], default: 0 },
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  const formRef = ref(null)
  const isEdit = computed(() => !!props.size?.id)

  const form = reactive({ name: '', price: 0 })

  // "Price" here is the absolute price shown to staff — the backend still
  // stores price_adjustment relative to the product's base_price, so this
  // is a pure UI-boundary conversion, not a new field.
  watch(
    () => props.modelValue,
    open => {
      if (!open) return
      if (props.size) {
        form.name = props.size.name ?? ''
        form.price = Number(props.basePrice ?? 0) + Number(props.size.price_adjustment ?? 0)
      } else {
        form.name = ''
        form.price = Number(props.basePrice ?? 0)
      }
      formRef.value?.resetValidation()
    },
    { immediate: true }
  )

  const r = {
    required: v => (v !== null && v !== '' && v !== undefined) || t('validation.required'),
    nonNegative: v => Number(v) >= 0 || t('products.rule.nonNegative')
  }

  function close() {
    if (props.loading) return
    emit('update:modelValue', false)
  }

  async function submit() {
    const { valid } = await formRef.value.validate()
    if (!valid) return

    emit('saved', {
      ...(isEdit.value ? { id: props.size.id } : {}),
      name: form.name.trim(),
      price_adjustment: Number(form.price) - Number(props.basePrice ?? 0),
      sort_order: props.size?.sort_order
    })
  }
</script>
