<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('notification.preferences_title')"
    @update:model-value="v => emit('update:modelValue', v)"
  >
    <div class="d-flex align-center justify-space-between py-2">
      <div>
        <div class="font-weight-medium">{{ $t('notification.system_channel') }}</div>
        <div class="text-caption text-medium-emphasis">{{ $t('notification.system_channel_desc') }}</div>
      </div>
      <v-switch
        v-model="notifySystem"
        color="primary"
        hide-details
        inset
        :loading="saving.system"
        @update:model-value="v => onToggle('notify_system', v, 'system')"
      />
    </div>

    <v-divider class="my-2" />

    <div class="d-flex align-center justify-space-between py-2">
      <div>
        <div class="font-weight-medium">{{ $t('notification.email_channel') }}</div>
        <div class="text-caption text-medium-emphasis">{{ $t('notification.email_channel_desc') }}</div>
      </div>
      <v-switch
        v-model="notifyEmail"
        color="primary"
        hide-details
        inset
        :loading="saving.email"
        @update:model-value="v => onToggle('notify_email', v, 'email')"
      />
    </div>

    <v-divider class="my-2" />

    <div v-if="telegramLinked" class="d-flex align-center justify-space-between py-2">
      <div>
        <div class="font-weight-medium">
          {{ $t('notification.telegram_channel') }}
          <v-chip size="x-small" color="success" variant="tonal" class="ml-2">
            {{ $t('notification.telegram_linked') }}
          </v-chip>
        </div>
        <div class="text-caption text-medium-emphasis">{{ $t('notification.telegram_channel_desc') }}</div>
      </div>
      <div class="d-flex align-center ga-2">
        <v-switch
          v-model="notifyTelegram"
          color="primary"
          hide-details
          inset
          :loading="saving.telegram"
          @update:model-value="v => onToggle('notify_telegram', v, 'telegram')"
        />
        <v-btn variant="text" size="small" color="error" class="text-none" @click="onUnlinkTelegram">
          {{ $t('notification.unlink_telegram') }}
        </v-btn>
      </div>
    </div>

    <div v-else class="text-center pt-2">
      <div class="font-weight-medium mb-1">{{ $t('notification.telegram_channel') }}</div>
      <p class="text-center text-grey-darken-1 px-2 mb-4" v-html="$t('notification.telegram_link_desc')"></p>
      <v-btn
        color="blue-darken-1"
        class="text-none font-weight-bold rounded-lg"
        prepend-icon="mdi-send"
        :loading="linkingTelegram"
        @click="onLinkTelegram"
      >
        {{ $t('notification.connect_telegram') }}
      </v-btn>
    </div>

    <template #actions>
      <v-btn variant="tonal" rounded="lg" @click="emit('update:modelValue', false)">
        {{ $t('btn.close') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils, AppDialog } from '@nong-official-dev/core'
  import {
    getNotificationPreferencesApi,
    updateNotificationPreferencesApi,
    getTelegramLinkUrlApi,
    unlinkTelegramApi
  } from '@/api/notificationService'

  const props = defineProps({
    modelValue: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue', 'close', 'changed'])

  const { t } = useI18n()
  const { notif } = useAppUtils()

  const notifySystem = ref(true)
  const notifyEmail = ref(true)
  const notifyTelegram = ref(false)
  const telegramLinked = ref(false)
  const linkingTelegram = ref(false)
  const saving = ref({ system: false, email: false, telegram: false })

  watch(
    () => props.modelValue,
    open => {
      if (open) loadPreferences()
      else emit('close')
    }
  )

  const loadPreferences = async () => {
    const { data } = await getNotificationPreferencesApi()
    notifySystem.value = data.data.notify_system
    notifyEmail.value = data.data.notify_email
    notifyTelegram.value = data.data.notify_telegram
    telegramLinked.value = data.data.telegram_linked
  }

  const onToggle = async (field, value, key) => {
    saving.value[key] = true
    try {
      await updateNotificationPreferencesApi({ [field]: value })
      emit('changed')
    } catch {
      if (key === 'system') notifySystem.value = !value
      if (key === 'email') notifyEmail.value = !value
      if (key === 'telegram') notifyTelegram.value = !value
      notif(t('messages.error_occurred'), { type: 'error', color: 'error' })
    } finally {
      saving.value[key] = false
    }
  }

  const onLinkTelegram = async () => {
    linkingTelegram.value = true
    try {
      const { data } = await getTelegramLinkUrlApi()
      window.open(data.data.url, '_blank')
    } finally {
      linkingTelegram.value = false
    }
  }

  const onUnlinkTelegram = async () => {
    await unlinkTelegramApi()
    telegramLinked.value = false
    notifyTelegram.value = false
    emit('changed')
  }
</script>
