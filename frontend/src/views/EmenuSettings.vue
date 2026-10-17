<template>
  <v-container fluid class="pa-0">
    <custom-title icon="mdi-qrcode">
      {{ $t('emenu_settings.title') }}
      <template #subtitle>
        {{ $t('emenu_settings.subtitle') }}
      </template>
    </custom-title>

    <v-row>
      <v-col cols="12" md="7">
        <v-card rounded="lg" border elevation="0">
          <v-card-text class="pa-6">
            <v-form @submit.prevent="save">
              <v-text-field
                v-model="baseUrl"
                :label="$t('emenu_settings.base_url')"
                :hint="$t('emenu_settings.base_url_hint')"
                :placeholder="resolvedBaseUrl"
                persistent-hint
                class="mb-2"
              />

              <div class="text-caption text-medium-emphasis mb-4">
                {{ $t('emenu_settings.currently_resolves_to') }}
                <strong>{{ resolvedBaseUrl }}</strong>
              </div>

              <v-btn type="submit" color="primary" class="text-none" :loading="saving">
                {{ $t('btn.save') }}
              </v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card rounded="lg" border elevation="0">
          <v-card-text class="pa-6">
            <div class="text-subtitle-2 font-weight-bold mb-3">
              {{ $t('emenu_settings.what_is_this_title') }}
            </div>
            <p class="text-body-2 text-medium-emphasis">
              {{ $t('emenu_settings.what_is_this_body') }}
            </p>
            <v-alert type="warning" variant="tonal" density="comfortable" class="mt-3">
              {{ $t('emenu_settings.change_warning') }}
            </v-alert>
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
  import { getEmenuSettingsApi, updateEmenuSettingsApi } from '@/api/emenuSettingService'

  const { t } = useI18n()
  const { notif } = useAppUtils()

  const baseUrl = ref('')
  const resolvedBaseUrl = ref('')
  const saving = ref(false)

  onMounted(loadSettings)

  async function loadSettings() {
    const { data } = await getEmenuSettingsApi()
    baseUrl.value = data.data.base_url ?? ''
    resolvedBaseUrl.value = data.data.resolved_base_url ?? ''
  }

  async function save() {
    saving.value = true
    try {
      const { data } = await updateEmenuSettingsApi({ base_url: baseUrl.value || null })
      baseUrl.value = data.data.base_url ?? ''
      resolvedBaseUrl.value = data.data.resolved_base_url ?? ''
      notif(t('messages.saved_success'), { type: 'success', color: 'primary' })
    } catch {
      notif(t('messages.error_occurred'), { type: 'error', color: 'error' })
    } finally {
      saving.value = false
    }
  }
</script>
