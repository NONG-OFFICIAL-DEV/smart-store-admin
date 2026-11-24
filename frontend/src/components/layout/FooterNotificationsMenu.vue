<!--
  The packaged <AppNotificationBell> (@nong-official-dev/core) owns its own
  v-menu + activator internally with no slot to swap the activator out and
  no exposed unread count — see useNotificationBell.js's own comment. This
  mirrors its exact panel markup (same props, same fetch/mark functions —
  true behavioral reuse, not a rebuild) behind a plain <button> activator so
  the sidebar footer card can render its own custom trigger.
-->
<template>
  <v-menu
    v-model="menuOpen"
    location="bottom end"
    transition="scale-transition"
    :close-on-content-click="false"
    min-width="340"
    max-width="380"
    @update:model-value="val => val && loadRecent()"
  >
    <template #activator="{ props: menuProps }">
      <v-tooltip v-if="rail" location="end" :text="title">
        <template #activator="{ props: tip }">
          <button type="button" v-bind="{ ...tip, ...menuProps }" class="rail-btn" :aria-label="title">
            <span class="icon-wrap">
              <v-icon icon="mdi-bell-outline" size="18" />
              <span v-if="unreadCount > 0" class="badge badge--dot" />
            </span>
          </button>
        </template>
      </v-tooltip>

      <button v-else-if="bar" type="button" v-bind="menuProps" class="bar-notif-btn" :aria-label="title">
        <v-icon icon="mdi-bell-outline" size="20" />
        <span v-if="unreadCount > 0" class="bar-notif-badge">{{ badgeText }}</span>
      </button>

      <button v-else type="button" v-bind="menuProps" class="bottom-btn" :aria-label="title">
        <v-icon icon="mdi-bell-outline" size="18" />
        <span class="label">{{ label }}</span>
        <span v-if="unreadCount > 0" class="badge">{{ unreadCount }}</span>
      </button>
    </template>

    <v-card elevation="0" rounded="lg" class="overflow-hidden border">
      <div class="d-flex align-center justify-space-between pa-3 border-b">
        <span class="text-title-small font-weight-bold">{{ title }}</span>
        <v-btn
          v-if="unreadCount > 0"
          variant="text"
          size="small"
          density="compact"
          class="text-none"
          @click="handleMarkAllRead"
        >
          {{ markAllReadText }}
        </v-btn>
      </div>

      <v-progress-linear v-if="loading" indeterminate color="primary" height="2" />

      <v-list density="comfortable" class="py-0" style="max-height: 360px; overflow-y: auto">
        <v-list-item
          v-for="n in notifications"
          :key="n.id"
          rounded="md"
          class="mx-1 my-1"
          :class="{ 'bg-primary-lighten-5': !n.read_at }"
          @click="handleItemClick(n)"
        >
          <template #prepend>
            <v-icon :icon="icon(n)" :color="color(n)" size="20" class="mr-2" />
          </template>
          <v-list-item-title class="text-body-medium text-wrap" :class="{ 'font-weight-bold': !n.read_at }">
            {{ message(n) }}
          </v-list-item-title>
          <v-list-item-subtitle class="text-body-small">
            {{ formatDate(n.created_at) }}
          </v-list-item-subtitle>
        </v-list-item>

        <v-list-item v-if="!loading && notifications.length === 0">
          <v-list-item-title class="text-body-medium text-medium-emphasis text-center py-2">
            {{ emptyText }}
          </v-list-item-title>
        </v-list-item>
      </v-list>

      <v-divider />
      <v-list-item rounded="md" class="ma-1 text-center" density="compact" @click="handleViewAll">
        <v-list-item-title class="text-body-medium text-primary font-weight-medium">
          {{ viewAllText }}
        </v-list-item-title>
      </v-list-item>
    </v-card>
  </v-menu>
</template>

<script setup>
  import { ref, computed } from 'vue'

  const props = defineProps({
    // Same shape/semantics as AppNotificationBell's own props.
    fetchRecent: { type: Function, required: true },
    fetchUnreadCount: { type: Function, required: true },
    markRead: { type: Function, required: true },
    markAllRead: { type: Function, required: true },
    icon: { type: Function, default: n => (n.read_at ? 'mdi-bell-outline' : 'mdi-bell-ring-outline') },
    color: { type: Function, default: () => 'primary' },
    message: { type: Function, required: true },
    formatDate: { type: Function, default: d => new Date(d).toLocaleString() },
    perPage: { type: Number, default: 10 },
    title: { type: String, default: 'Notifications' },
    emptyText: { type: String, default: 'No notifications' },
    markAllReadText: { type: String, default: 'Mark all read' },
    viewAllText: { type: String, default: 'View all' },
    // This composable-tracked count (see useNotificationBell.js) drives both
    // the custom activator's own badge and this panel's unread-aware bits —
    // kept in sync instead of re-fetching a second independent counter.
    unreadCount: { type: Number, default: 0 },
    label: { type: String, default: 'Alerts' },
    rail: Boolean,
    // AppBarActions.vue's compact square trigger — badge caps at "9+"
    // instead of showing the raw count (rail/bottom-btn keep their own
    // existing uncapped behavior, unchanged).
    bar: Boolean
  })

  const emit = defineEmits(['item-click', 'view-all', 'read'])

  const menuOpen = ref(false)
  const loading = ref(false)
  const notifications = ref([])

  const badgeText = computed(() => (props.unreadCount > 9 ? '9+' : props.unreadCount))

  async function loadRecent() {
    loading.value = true
    try {
      const { items } = await props.fetchRecent({ perPage: props.perPage })
      notifications.value = items
    } finally {
      loading.value = false
    }
  }

  async function handleItemClick(n) {
    menuOpen.value = false

    if (!n.read_at) {
      props.markRead(n.id).catch(() => {})
      emit('read')
    }

    emit('item-click', n)
  }

  async function handleMarkAllRead() {
    await props.markAllRead()
    notifications.value = notifications.value.map(n => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
    emit('read')
  }

  function handleViewAll() {
    menuOpen.value = false
    emit('view-all')
  }

  defineExpose({ refresh: loadRecent })
</script>

<style scoped>
  .bottom-btn {
    min-height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font: 600 13px inherit;
    border: 0;
    background: transparent;
    width: 100%;
    min-width: 0;
    padding-inline: 6px;
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
  }
  .bottom-btn .label {
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .bottom-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .bottom-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }

  .rail-btn {
    width: 100%;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0;
    border: 0;
    background: transparent;
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
  }
  .rail-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.05);
  }
  .rail-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
  .icon-wrap {
    position: relative;
    display: inline-flex;
  }

  .badge {
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #d92d20;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
  }
  .badge--dot {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 8px;
    width: 8px;
    height: 8px;
    padding: 0;
  }

  .bar-notif-btn {
    position: relative;
    width: 40px;
    height: 40px;
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: rgb(var(--v-theme-on-surface));
    cursor: pointer;
  }
  .bar-notif-btn:hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  .bar-notif-btn:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
  .bar-notif-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border-radius: 999px;
    background: #d92d20;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    border: 2px solid rgb(var(--v-theme-surface));
  }
</style>
