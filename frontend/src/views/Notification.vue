<template>
  <v-container fluid class="pa-0">
    <custom-title>
      {{ $t('notification.title') }}
      <template #right>
        <v-btn
          variant="tonal"
          class="text-none me-3"
          prepend-icon="mdi-tune-variant"
          @click="preferencesOpen = true"
        >
          {{ $t('notification.preferences_title') }}
        </v-btn>
        <v-btn
          variant="tonal"
          color="primary"
          class="text-none me-3"
          @click="markAllAsRead"
        >
          {{ $t('notification.mark_all_read') }}
        </v-btn>
      </template>
    </custom-title>

    <NotificationPreferencesDialog
      v-model="preferencesOpen"
      @changed="authStore.fetchMe"
    />

    <v-card
      v-if="notifications.length > 0"
      variant="flat"
    >
      <v-list lines="two" class="py-0">
        <v-list-item
          v-for="item in notifications"
          :key="item.id"
          :value="item.id"
          :class="!item.read_at ? 'unread' : ''"
        >
          <template #prepend>
            <v-avatar size="44" :color="notificationColor(item.type)" variant="tonal">
              <v-icon :icon="notificationIcon(item.type)" />
            </v-avatar>
          </template>

          <v-list-item-title class="font-weight-medium">
            {{ item.title }}
          </v-list-item-title>
          <v-list-item-subtitle>
            {{ item.body }}
          </v-list-item-subtitle>

          <template #append>
            <div class="text-right">
              <div class="text-time text-medium-emphasis">
                {{ formatDateTime(item.created_at) }}
              </div>
              <v-menu>
                <template #activator="{ props }">
                  <v-btn
                    icon="mdi-dots-vertical"
                    variant="text"
                    size="small"
                    v-bind="props"
                  />
                </template>
                <v-list density="compact">
                  <v-list-item v-if="!item.read_at" @click="makeAsRead(item.id)">
                    <v-list-item-title>
                      {{ $t('notification.mark_as_read') }}
                    </v-list-item-title>
                  </v-list-item>
                  <v-list-item @click="remove(item.id)">
                    <v-list-item-title class="text-error">
                      {{ $t('btn.delete') }}
                    </v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-menu>
            </div>
          </template>
        </v-list-item>
      </v-list>
    </v-card>

    <!-- No notifications -->
    <div
      v-else
      class="mx-auto overflow-hidden rounded-xl"
      max-width="500"
      elevation="0"
    >
      <div class="pa-8">
        <v-row align="start" no-gutters>
          <v-col cols="12">
            <p class="text-center text-grey-darken-1 px-2">
              {{ $t('notification.no_notifications') }}
            </p>
          </v-col>
        </v-row>
      </div>
    </div>
  </v-container>
</template>

<script setup>
  import { onMounted, computed, ref } from 'vue'
  import { useAuthStore } from '@/stores/authStore'
  import { useNotificationStore } from '@/stores/notificationStore'
  import { useDate } from '@/composables/useDate'
  import NotificationPreferencesDialog from '@/components/notifications/NotificationPreferencesDialog.vue'

  const authStore = useAuthStore()
  const notificationStore = useNotificationStore()
  const { formatDateTime } = useDate()

  const preferencesOpen = ref(false)

  onMounted(() => {
    notificationStore.fetchNotifications()
  })
  const notifications = computed(() => notificationStore.notifications)

  // ── Notification type -> icon/color ─────────────────────────────────────────
  const TYPE_STYLES = {
    low_stock:      { icon: 'mdi-alert-outline',        color: 'warning' },
    new_order:      { icon: 'mdi-receipt-text-outline',  color: 'primary' },
    shift_reminder: { icon: 'mdi-clock-outline',         color: 'info' },
    payment_failed: { icon: 'mdi-credit-card-off-outline', color: 'error' },
  }
  const notificationIcon = type => TYPE_STYLES[type]?.icon ?? 'mdi-bell-outline'
  const notificationColor = type => TYPE_STYLES[type]?.color ?? 'primary'

  const makeAsRead = id => {
    notificationStore.markAsRead(id)
    notificationStore.fetchNotifications()
    authStore.fetchMe()
  }

  const markAllAsRead = () => {
    notificationStore.markAllAsRead()
    notificationStore.fetchNotifications()
    authStore.fetchMe()
  }

  const remove = id => {
    notificationStore.deleteNotification(id)
    notificationStore.fetchNotifications()
    authStore.fetchMe()
  }
</script>

<style scoped>
  .text-time {
    font-size: 12px;
  }
  .unread {
    background-color: rgba(33, 150, 243, 0.06);
  }
</style>
