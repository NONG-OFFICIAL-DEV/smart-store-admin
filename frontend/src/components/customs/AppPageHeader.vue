<template>
  <div class="page-header mb-4">
    <v-btn
      v-if="showBack"
      icon="mdi-arrow-left"
      variant="tonal"
      rounded="lg"
      size="small"
      color="primary"
      class="page-header__back"
      @click="onBack"
    />

    <!-- Single breadcrumb row: parents are links, the page title is the
         last (current) crumb. -->
    <v-breadcrumbs :items="crumbs" density="compact" class="page-header__crumbs">
      <template #divider>
        <v-icon icon="mdi-chevron-right" size="16" class="text-medium-emphasis" />
      </template>
      <template #item="{ item }">
        <v-breadcrumbs-item
          :to="item.to"
          :disabled="item.disabled || !item.to"
          class="page-header__crumb"
          :class="{ 'page-header__crumb--current': item.current }"
        >
          {{ item.title }}
        </v-breadcrumbs-item>
      </template>
    </v-breadcrumbs>

    <slot name="title-after" />

    <div v-if="$slots.right" class="page-header__actions">
      <slot name="right" />
    </div>
  </div>
</template>

<script setup>
  import { computed } from 'vue'
  import { useRouter } from 'vue-router'

  const router = useRouter()

  const props = defineProps({
    title: { type: String, required: true },
    breadcrumbs: { type: Array, default: () => [] },
    showBack: { type: Boolean, default: false },
    backTo: { type: String, default: null }
  })

  // Parents + the current page title as the final, non-clickable crumb.
  const crumbs = computed(() => [
    ...props.breadcrumbs.map(c => ({ ...c, current: false })),
    { title: props.title, disabled: true, current: true }
  ])

  const onBack = () => {
    if (props.backTo) router.push(props.backTo)
    else router.back()
  }
</script>

<style scoped>
  .page-header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 12px;
    width: 100%;
  }

  .page-header__back {
    flex: none;
  }

  .page-header__crumbs {
    flex: 1 1 auto;
    min-width: 0;
    flex-wrap: nowrap;
    padding: 0 !important;
    min-height: 0 !important;
  }

  .page-header__crumb {
    padding: 0 !important;
    min-height: 0;
    font-size: 0.875rem;
    line-height: 1.25rem;
    font-weight: 500;
  }

  /* Current page: stronger, never greyed out */
  .page-header__crumb--current {
    font-size: 1.125rem;
    line-height: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    opacity: 1 !important;
    color: rgb(var(--v-theme-on-surface)) !important;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .page-header__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-left: auto;
    flex: none;
  }

  /* Vuetify 4 buttons are taller by default; keep header actions compact */
  .page-header__actions :deep(.v-btn) {
    height: 36px;
  }

  /* Parent crumbs are links */
  :deep(.v-breadcrumbs-item:not(.v-breadcrumbs-item--disabled)) {
    color: rgb(var(--v-theme-primary));
    opacity: 0.8;
    transition: opacity 0.2s;
  }

  :deep(.v-breadcrumbs-item:not(.v-breadcrumbs-item--disabled):hover) {
    opacity: 1;
    text-decoration: underline;
  }
</style>