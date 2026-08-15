<template>
  <v-dialog v-model="model" max-width="460" persistent>
    <v-card rounded="xl">
      <v-card-item class="pa-6 pb-0">
        <template #prepend>
          <v-avatar color="warning" variant="tonal" rounded="lg">
            <v-icon icon="mdi-key-alert-outline" />
          </v-avatar>
        </template>
        <v-card-title class="font-weight-bold">
          {{ t('common.temporary_password.title') }}
        </v-card-title>
        <v-card-subtitle>
          {{ t('common.temporary_password.subtitle') }}
        </v-card-subtitle>
      </v-card-item>

      <v-card-text class="pa-6">
        <v-text-field
          :model-value="password"
          readonly
          variant="outlined"
          rounded="lg"
          :type="visible ? 'text' : 'password'"
          prepend-inner-icon="mdi-lock-outline"
          :append-inner-icon="visible ? 'mdi-eye-off' : 'mdi-eye'"
          @click:append-inner="visible = !visible"
        >
          <template #append>
            <v-btn
              icon="mdi-content-copy"
              variant="text"
              size="small"
              @click="copy"
            />
          </template>
        </v-text-field>

        <v-alert
          type="warning"
          variant="tonal"
          rounded="lg"
          density="compact"
          icon="mdi-alert-outline"
        >
          {{ t('common.temporary_password.warning') }}
        </v-alert>
      </v-card-text>

      <v-card-actions class="pa-4 pt-0">
        <v-spacer />
        <v-btn color="primary" variant="flat" rounded="lg" @click="close">
          {{ t('common.temporary_password.done') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@/composables/useAppUtils'

  const { t } = useI18n()
  const { notif } = useAppUtils()

  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    password: { type: String, default: '' }
  })

  const emit = defineEmits(['update:modelValue'])

  const visible = ref(false)

  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  watch(
    () => props.modelValue,
    val => {
      if (val) visible.value = true
    }
  )

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(props.password)
      notif(t('common.temporary_password.copied'), { type: 'success' })
    } catch {
      /* clipboard unavailable — no-op */
    }
  }

  const close = () => emit('update:modelValue', false)
</script>
