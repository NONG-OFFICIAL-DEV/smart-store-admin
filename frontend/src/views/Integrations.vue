<template>
  <v-container fluid class="pa-0">
    <AppToolbar
      :title="t('integrations.title')"
      :subtitle="t('integrations.subtitle')"
    />

    <!-- ── Telegram notifications ─────────────────────────────────────── -->
    <v-card rounded="lg" class="mb-4">
      <v-card-item>
        <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis">
          {{ $t('integrations.telegram.title') }}
        </h3>
        <v-card-subtitle>
          {{ t('integrations.telegram.subtitle') }}
        </v-card-subtitle>
        <template #append>
          <v-chip :color="telegramStatusColor" size="small" variant="tonal">
            {{ t(`integrations.telegram.status.${telegramStatus}`) }}
          </v-chip>
        </template>
      </v-card-item>
      <v-divider />
      <v-card-text class="pa-6">
        <v-row>
          <v-col cols="12" md="7">
            <v-text-field
              v-model="botUsername"
              :label="t('integrations.telegram.bot_username')"
              :hint="t('integrations.telegram.bot_username_hint')"
              :rules="usernameRules"
              persistent-hint
              prefix="@"
              autocapitalize="off"
              spellcheck="false"
              :autofocus="!isTouch"
              class="mb-4"
            />

            <template v-if="hasToken && !replacingToken">
              <div class="d-flex align-center flex-wrap ga-3">
                <div class="d-flex align-center ga-2">
                  <v-icon icon="mdi-check-circle" color="success" size="18" />
                  <span class="text-body-2">
                    {{
                      t('integrations.telegram.token_saved', {
                        preview: tokenPreview
                      })
                    }}
                  </span>
                </div>
                <v-btn
                  variant="text"
                  size="small"
                  class="text-none"
                  @click="replacingToken = true"
                >
                  {{ t('integrations.telegram.replace') }}
                </v-btn>
              </div>
            </template>
            <v-text-field
              v-else
              v-model="botToken"
              :label="t('integrations.telegram.bot_token')"
              :hint="
                hasToken
                  ? t('integrations.telegram.replace_hint')
                  : t('integrations.telegram.token_hint')
              "
              persistent-hint
              type="password"
              autocomplete="off"
              autocapitalize="off"
              spellcheck="false"
            />

            <div class="d-flex align-center flex-wrap ga-3 mt-6">
              <v-btn
                variant="outlined"
                class="text-none"
                :loading="testing"
                @click="testConnection"
              >
                {{ t('integrations.telegram.test') }}
              </v-btn>
              <span
                v-if="testResult"
                class="text-body-2"
                :class="testResult.ok ? 'text-success' : 'text-error'"
              >
                {{ testResult.message }}
              </span>
            </div>
          </v-col>

          <v-col cols="12" md="5">
            <v-expansion-panels variant="accordion">
              <v-expansion-panel>
                <v-expansion-panel-title>
                  {{ t('integrations.telegram.how_to_title') }}
                </v-expansion-panel-title>
                <v-expansion-panel-text>
                  <ol class="text-body-2 text-medium-emphasis pl-4">
                    <li class="mb-2">
                      {{ t('integrations.telegram.how_to_step1') }}
                    </li>
                    <li class="mb-2">
                      {{ t('integrations.telegram.how_to_step2') }}
                    </li>
                    <li class="mb-2">
                      {{ t('integrations.telegram.how_to_step3') }}
                    </li>
                    <li>{{ t('integrations.telegram.how_to_step4') }}</li>
                  </ol>
                </v-expansion-panel-text>
              </v-expansion-panel>
            </v-expansion-panels>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- ── E-Menu QR links ────────────────────────────────────────────── -->
    <v-card rounded="lg" class="mb-4">
      <v-card-item>
        <h3 class="text-subtitle-1 font-weight-bold text-high-emphasis">
          {{ $t('integrations.emenu.title') }}
        </h3>
        <v-card-subtitle>
          {{ t('integrations.emenu.subtitle') }}
        </v-card-subtitle>
      </v-card-item>
      <v-divider />
      <v-card-text class="pa-6">
        <v-row>
          <v-col cols="12" md="7">
            <v-text-field
              v-model="baseUrl"
              type="url"
              :label="t('integrations.emenu.base_url')"
              :hint="t('integrations.emenu.base_url_hint')"
              :rules="baseUrlRules"
              persistent-hint
              autocapitalize="off"
              spellcheck="false"
              class="mb-4"
            />

            <div class="text-caption text-medium-emphasis mb-1">
              {{ t('integrations.emenu.example_label') }}
            </div>
            <v-sheet
              color="grey-lighten-4"
              rounded="lg"
              class="pa-3 text-body-2 font-weight-medium"
            >
              {{ exampleUrl }}
            </v-sheet>
          </v-col>

          <v-col cols="12" md="5">
            <v-alert type="warning" variant="tonal" density="comfortable">
              {{ t('integrations.emenu.change_warning') }}
            </v-alert>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- ── Save bar ───────────────────────────────────────────────────── -->
    <v-card
      rounded="lg"
      class="pa-4 d-flex justify-end ga-2"
    >
      <v-btn
        variant="text"
        class="text-none"
        :disabled="!dirty"
        @click="discard"
      >
        {{ t('integrations.discard') }}
      </v-btn>
      <v-btn
        color="primary"
        variant="flat"
        class="text-none"
        :disabled="!dirty"
        :loading="saving"
        @click="saveAll"
      >
        {{ t('integrations.save') }}
      </v-btn>
    </v-card>
  </v-container>
</template>

<script setup>
  import { computed, onMounted, ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils, AppToolbar } from '@nong-official-dev/core'
  import { useTouchDevice } from '@/composables/useTouchDevice.js'
  import {
    getTelegramSettingsApi,
    updateTelegramSettingsApi,
    testTelegramConnectionApi
  } from '@/api/telegramSettingService'
  import {
    getEmenuSettingsApi,
    updateEmenuSettingsApi
  } from '@/api/emenuSettingService'

  const { t } = useI18n()
  const { notif } = useAppUtils()
  const { isTouch } = useTouchDevice()

  // ── Telegram ─────────────────────────────────────────────────────────────
  const botUsername = ref('')
  const botToken = ref('')
  const hasToken = ref(false)
  const tokenPreview = ref('')
  const replacingToken = ref(false)
  const testing = ref(false)
  const testResult = ref(null)
  // No persisted "connected" field on the backend — this is session-only UI
  // state, reset to "untested" on every page load even if it was tested
  // successfully before.
  const telegramStatus = ref('untested')
  const telegramStatusColor = computed(
    () =>
      ({ connected: 'success', failed: 'error', untested: undefined })[
        telegramStatus.value
      ]
  )

  const usernameRules = [
    v => !!v || t('integrations.telegram.username_required'),
    v =>
      /^[A-Za-z0-9_]+$/.test(v || '') ||
      t('integrations.telegram.username_format'),
    v =>
      /bot$/i.test(v || '') || t('integrations.telegram.username_must_end_bot')
  ]

  // Strip a leading "@" the moment it's typed/pasted — the field already has
  // its own "@" prefix, so a user typing "@my_bot" would otherwise end up
  // double-prefixed.
  watch(botUsername, val => {
    if (val?.startsWith('@')) botUsername.value = val.slice(1)
  })

  // Editing anything after a test makes that result stale.
  watch([botUsername, botToken], () => {
    if (testResult.value) {
      testResult.value = null
      telegramStatus.value = 'untested'
    }
  })

  // ── E-Menu ───────────────────────────────────────────────────────────────
  const baseUrl = ref('')
  const resolvedBaseUrl = ref('')

  const baseUrlRules = [
    v =>
      !v || v.startsWith('https://') || t('integrations.emenu.url_rule_https'),
    v => !v || isValidUrl(v) || t('integrations.emenu.url_rule_invalid')
  ]

  function isValidUrl(v) {
    try {
      new URL(v)
      return true
    } catch {
      return false
    }
  }

  // Trim spaces + a trailing slash — saved and shown normalized, never with
  // a literal "//" once /menu/... is appended.
  function normalizeUrl(v) {
    return (v ?? '').trim().replace(/\/+$/, '')
  }

  const exampleUrl = computed(() => {
    const base = normalizeUrl(baseUrl.value) || resolvedBaseUrl.value
    return `${base}/menu/sihanoukville/table/12`
  })

  // ── Snapshot + dirty tracking (one Save bar covers both sections) ────────
  const original = ref({ botUsername: '', baseUrl: '' })

  const dirty = computed(
    () =>
      botUsername.value !== original.value.botUsername ||
      botToken.value !== '' ||
      normalizeUrl(baseUrl.value) !== original.value.baseUrl
  )

  async function loadSettings() {
    const [telegramRes, emenuRes] = await Promise.all([
      getTelegramSettingsApi(),
      getEmenuSettingsApi()
    ])

    botUsername.value = telegramRes.data.data.bot_username ?? ''
    hasToken.value = telegramRes.data.data.has_token
    tokenPreview.value = telegramRes.data.data.token_preview ?? ''
    botToken.value = ''
    replacingToken.value = false
    testResult.value = null
    telegramStatus.value = 'untested'

    baseUrl.value = emenuRes.data.data.base_url ?? ''
    resolvedBaseUrl.value = emenuRes.data.data.resolved_base_url ?? ''

    original.value = {
      botUsername: botUsername.value,
      baseUrl: normalizeUrl(baseUrl.value)
    }
  }

  function discard() {
    loadSettings()
  }

  const saving = ref(false)
  async function saveAll() {
    saving.value = true
    try {
      const telegramPayload = { bot_username: botUsername.value }
      // Blank/omitted bot_token means "keep the current one" server-side —
      // only send it when the user actually typed a replacement.
      if (botToken.value) telegramPayload.bot_token = botToken.value

      const [telegramRes, emenuRes] = await Promise.all([
        updateTelegramSettingsApi(telegramPayload),
        updateEmenuSettingsApi({
          base_url: normalizeUrl(baseUrl.value) || null
        })
      ])

      hasToken.value = telegramRes.data.data.has_token
      tokenPreview.value = telegramRes.data.data.token_preview ?? ''
      botToken.value = ''
      replacingToken.value = false

      baseUrl.value = emenuRes.data.data.base_url ?? ''
      resolvedBaseUrl.value = emenuRes.data.data.resolved_base_url ?? ''

      original.value = {
        botUsername: botUsername.value,
        baseUrl: normalizeUrl(baseUrl.value)
      }

      notif(t('integrations.saved_success'), {
        type: 'success',
        color: 'primary'
      })
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
      await testTelegramConnectionApi()
      testResult.value = {
        ok: true,
        message: t('integrations.telegram.test_success')
      }
      telegramStatus.value = 'connected'
    } catch (err) {
      testResult.value = {
        ok: false,
        message:
          err.response?.data?.message ?? t('integrations.telegram.test_failed')
      }
      telegramStatus.value = 'failed'
    } finally {
      testing.value = false
    }
  }

  onMounted(loadSettings)
</script>
