<template>
  <v-dialog v-model="model" location="right" width="420" persistent>
    <v-card height="100vh" class="rounded-0">
      <v-card-title class="d-flex align-center justify-space-between pa-5 pb-4">
        <div class="d-flex align-center gap-3">
          <v-avatar
            :color="isEdit ? 'primary' : 'success'"
            size="36"
            rounded="lg"
          >
            <v-icon
              :icon="isEdit ? 'mdi-pencil' : 'mdi-plus'"
              size="18"
              color="white"
            />
          </v-avatar>
          <div>
            <div class="text-subtitle-1 font-weight-bold">
              {{ isEdit ? 'Edit Variant' : 'Add Variant' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ productName }}
            </div>
          </div>
        </div>
        <v-btn icon="mdi-close" size="small" variant="text" @click="close" />
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-5">
        <v-form ref="formRef" @submit.prevent="submit">
          <v-row dense>
            <v-col cols="12">
              <v-text-field
                v-model="form.name"
                label="Variant Name"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[rules.required, rules.maxLen(80)]"
                :error-messages="serverErrors.name"
              />
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model.number="form.price_adjustment"
                label="Adjustment"
                variant="outlined"
                density="comfortable"
                type="number"
                rounded="lg"
                prefix="$"
                :rules="[rules.isNumber]"
                :error-messages="serverErrors.price_adjustment"
              />
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="form.sku_suffix"
                label="SKU Suffix"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                :rules="[rules.maxLen(20)]"
                :error-messages="serverErrors.sku_suffix"
              />
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model.number="form.sort_order"
                label="Sort Order"
                variant="outlined"
                density="comfortable"
                type="number"
                rounded="lg"
                :rules="[rules.nonNegative]"
              />
            </v-col>

            <v-col cols="6" class="d-flex align-center justify-center">
              <v-switch
                v-model="form.is_default"
                color="primary"
                inset
                hide-details
                label="Default"
              />
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-5">
        <v-btn variant="tonal" rounded="lg" @click="close" class="flex-grow-1">
          Cancel
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :loading="loading"
          @click="submit"
          class="flex-grow-1"
        >
          {{ isEdit ? 'Save' : 'Create' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'

  const props = defineProps({
    modelValue: Boolean,
    variant: Object,
    productName: String,
    productId: [String, Number],
    loading: Boolean,
    serverErrors: { type: Object, default: () => ({}) }
  })

  const emit = defineEmits(['update:modelValue', 'save'])

  const formRef = ref(null)
  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  const isEdit = computed(() => !!props.variant?.id)

  const defaultForm = () => ({
    id: null,
    name: '',
    price_adjustment: 0,
    sku_suffix: '',
    is_default: false,
    sort_order: 0
  })

  const form = reactive(defaultForm())

  const rules = {
    required: v => !!v || 'Required',
    maxLen: n => v => !v || v.length <= n || `Max ${n} chars`,
    isNumber: v => v === '' || v === null || !isNaN(v) || 'Invalid number',
    nonNegative: v => v >= 0 || 'Min 0'
  }

  watch(
    () => props.variant,
    val => {
      if (val) Object.assign(form, val)
      else Object.assign(form, defaultForm())
    },
    { immediate: true }
  )

  const close = () => {
    model.value = false
    formRef.value?.resetValidation()
  }

  const submit = async () => {
    const { valid } = await formRef.value.validate()
    if (valid) emit('save', { ...form, product_id: props.productId })
  }
</script>
