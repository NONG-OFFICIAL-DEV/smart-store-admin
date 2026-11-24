<template>
  <v-card variant="outlined" rounded="lg">
    <v-card-text class="pa-5">
      <div v-for="group in groups" :key="group.title" class="mb-6">
        <div class="section-label mb-2">{{ group.title }}</div>
        <v-table density="comfortable">
          <tbody>
            <tr v-for="row in group.rows" :key="row.keys">
              <td class="text-body-medium">{{ row.label }}</td>
              <td class="text-end">
                <kbd class="shortcut-key">{{ row.keys }}</kbd>
              </td>
            </tr>
          </tbody>
        </v-table>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()

  // These are the only shortcuts actually wired up in the app today (see
  // RetailPos.vue/FoodPos.vue's handlePosShortcuts()) — listed here, not
  // invented.
  const groups = computed(() => [
    {
      title: t('account.keyboard.general'),
      rows: [
        { keys: '⌘/Ctrl K · F2', label: t('shortcuts.search') },
        { keys: 'Esc', label: t('shortcuts.close') },
        { keys: '⌘/Ctrl B', label: t('shortcuts.toggle_sidebar') }
      ]
    },
    {
      title: t('account.keyboard.quick_sale'),
      rows: [{ keys: 'F10', label: t('shortcuts.checkout') }]
    }
  ])
</script>

<style scoped>
  .section-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(var(--v-theme-on-surface), 0.55);
  }
  .shortcut-key {
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(var(--v-theme-on-surface), 0.08);
    border: 1px solid rgba(var(--v-theme-on-surface), 0.14);
  }
</style>
