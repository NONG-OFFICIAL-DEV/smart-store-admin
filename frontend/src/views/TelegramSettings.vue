<template>
  <v-container fluid class="pa-0">
    <custom-title icon="mdi-send-outline">
      {{ $t('telegram_settings.title') }}
      <template #subtitle>
        {{ $t('telegram_settings.subtitle') }}
      </template>
    </custom-title>

    <v-row>
      <v-col cols="12" md="7">
        <v-card variant="flat" class="rounded-xl">
          <v-card-text class="pa-6">
            <v-form @submit.prevent="save">
              <v-text-field
                v-model="botUsername"
                :label="$t('telegram_settings.bot_username')"
                :hint="$t('telegram_settings.bot_username_hint')"
                persistent-hint
                prefix="@"
                class="mb-4"
              />

              <v-text-field
                v-model="botToken"
                :label="$t('telegram_settings.bot_token')"
                :placeholder="hasToken ? tokenPreview : ''"
                :hint="hasToken ? $t('telegram_settings.token_set_hint') : $t('telegram_settings.token_hint')"
                persistent-hint
                type="password"
                autocomplete="new-password"
              />

              <div class="d-flex ga-2 mt-6">
                <v-btn type="submit" color="primary" class="text-none" :loading="saving">
                  {{ $t('btn.save') }}
                </v-btn>
                <v-btn
                  variant="tonal"
                  class="text-none"
                  :loading="testing"
                  @click="testConnection"
                >
                  {{ $t('telegram_settings.test_connection') }}
                </v-btn>
              </div>

              <v-alert
                v-if="testResult"
                :type="testResult.ok ? 'success' : 'error'"
                variant="tonal"
                class="mt-4"
                density="comfortable"
              >
                {{ testResult.message }}
              </v-alert>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card variant="flat" class="rounded-xl">
          <v-card-text class="pa-6">
            <div class="text-subtitle-2 font-weight-bold mb-3">
              {{ $t('telegram_settings.how_to_title') }}
            </div>
            <ol class="text-body-2 text-medium-emphasis pl-4">
              <li class="mb-2">{{ $t('telegram_settings.how_to_step1') }}</li>
              <li class="mb-2">{{ $t('telegram_settings.how_to_step2') }}</li>
              <li class="mb-2">{{ $t('telegram_settings.how_to_step3') }}</li>
              <li>{{ $t('telegram_settings.how_to_step4') }}</li>
            </ol>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { onMounted, ref } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'
  import {
    getTelegramSettingsApi,
    updateTelegramSettingsApi,
    testTelegramConnectionApi
  } from '@/api/telegramSettingService'

  const { t } = useI18n()
  const { notif } = useAppUtils()

  const botUsername = ref('')
  const botToken = ref('')
  const hasToken = ref(false)
  const tokenPreview = ref('')
  const saving = ref(false)
  const testing = ref(false)
  const testResult = ref(null)

  onMounted(loadSettings)

  async function loadSettings() {
    const { data } = await getTelegramSettingsApi()
    botUsername.value = data.data.bot_username ?? ''
    hasToken.value = data.data.has_token
    tokenPreview.value = data.data.token_preview ?? ''
  }

  async function save() {
    saving.value = true
    try {
      const { data } = await updateTelegramSettingsApi({
        bot_username: botUsername.value,
        bot_token: botToken.value
      })
      hasToken.value = data.data.has_token
      tokenPreview.value = data.data.token_preview ?? ''
      botToken.value = ''
      testResult.value = null
      notif(t('messages.saved_success'), { type: 'success', color: 'primary' })
    } catch {
      notif(t('messages.error_occurred'), { type: 'error', color: 'error' })
    } finally {
      saving.value = false
    }
  }

  async function testConnection() {
    testing.value = true
    testResult.value = null
    try {
      const { data } = await testTelegramConnectionApi()
      testResult.value = { ok: true, message: t('telegram_settings.test_success', { username: data.data.bot_username }) }
    } catch (err) {
      testResult.value = { ok: false, message: err.response?.data?.message ?? t('telegram_settings.test_failed') }
    } finally {
      testing.value = false
    }
  }
</script>
