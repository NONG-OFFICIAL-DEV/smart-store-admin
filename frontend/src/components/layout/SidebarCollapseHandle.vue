<script setup>
  import { useI18n } from 'vue-i18n'

  defineProps({
    collapsed: Boolean,
    left: { type: Number, required: true }
  })
  const emit = defineEmits(['toggle'])

  const { t } = useI18n()
</script>

<template>
  <v-tooltip
    location="right"
    :text="collapsed ? t('sidebar.expand_menu') : t('sidebar.collapse_menu')"
  >
    <template #activator="{ props: tip }">
      <button
        type="button"
        v-bind="tip"
        class="sidebar-collapse-handle"
        :style="{ left: left + 'px' }"
        :aria-label="collapsed ? t('sidebar.expand_menu') : t('sidebar.collapse_menu')"
        @click="emit('toggle')"
      >
        <v-icon icon="mdi-chevron-left" size="20" :class="{ 'icon-collapsed': collapsed }" />
      </button>
    </template>
  </v-tooltip>
</template>

<style scoped>
  .sidebar-collapse-handle {
    position: fixed;
    top: 20px;
    width: 24px;
    height: 24px;
    border-radius: 999px;
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
    box-shadow:
      0 1px 2px rgba(0, 0, 0, 0.08),
      0 1px 3px rgba(0, 0, 0, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(var(--v-theme-on-surface), 0.7);
    cursor: pointer;
    z-index: 1010;
    transition: left 0.2s ease;
  }
  .sidebar-collapse-handle:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .sidebar-collapse-handle:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
  .sidebar-collapse-handle .v-icon {
    transition: transform 0.2s ease;
  }
  .sidebar-collapse-handle .icon-collapsed {
    transform: rotate(180deg);
  }
</style>
