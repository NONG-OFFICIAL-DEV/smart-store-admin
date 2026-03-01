<template>
  <custom-title icon="mdi-bell-outline">
    Notifications
    <template #right v-if="authStore.me.telegram_chat_id">
      <v-btn
        variant="tonal"
        color="primary"
        class="text-none me-3"
        @click="markAllAsRead"
      >
        Mark all as read
      </v-btn>
      <v-btn variant="outlined" color="grey" prepend-icon="mdi-filter-variant">
        Filter
      </v-btn>
    </template>
  </custom-title>
  <v-container fluid class="pa-0">
    <!-- Show Telegram link if user hasn't linked -->
    <v-card
      v-if="!authStore.me.telegram_chat_id"
      class="mx-auto overflow-hidden rounded-xl"
      elevation="0"
    >
      <v-card-text class="pa-8 text-center">
        <v-row align="start" no-gutters>
          <v-col cols="12" class="text-center mb-4">
            <v-avatar color="blue-lighten-5" size="80" class="mb-2">
              <v-icon color="blue-darken-1" size="40">mdi-send</v-icon>
            </v-avatar>
            <h2 class="font-weight-bold grey-darken-3">Stay Connected</h2>
          </v-col>
          <v-col cols="12">
            <p class="text-center text-grey-darken-1 px-2">
              Link your Telegram to get
              <strong>instant alerts</strong>
              for purchases, stock updates, and approvals directly on your
              phone.
            </p>
          </v-col>
        </v-row>

        <v-btn
          size="large"
          color="blue-darken-1"
          class="mt-6 text-none font-weight-bold rounded-lg"
          prepend-icon="mdi-send"
          @click="linkTelegram"
        >
          Connect Telegram Bot
        </v-btn>
<!-- 
        <v-btn
          size="large"
          color="blue-darken-1"
          class="mt-6 text-none font-weight-bold rounded-lg"
          prepend-icon="mdi-send"
          @click="confirmTelegram"
        >
          confirm Telegram
        </v-btn> -->
      </v-card-text>
    </v-card>

    <!-- Show notifications list if user linked Telegram -->
    <template v-else>
      <v-card
        v-if="notifications.data && notifications.data.length > 0"
        variant="flat"
      >
        <v-list lines="two" class="py-0">
          <v-list-item
            v-for="item in notifications.data"
            :key="item.id"
            :value="item.id"
            :class="!item.read ? 'unread' : ''"
          >
            <template #prepend>
              <v-avatar size="44" :color="item.color" variant="tonal">
                <v-icon :icon="item.icon" />
              </v-avatar>
            </template>

            <v-list-item-title class="font-weight-medium">
              {{ item.title }}
            </v-list-item-title>
            <v-list-item-subtitle>
              {{ item.message }}
            </v-list-item-subtitle>

            <template #append>
              <div class="text-right">
                <div class="text-time text-medium-emphasis">
                  {{ item.time }}
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
                    <v-list-item @click="makeAsRead(item.id)">
                      <v-list-item-title>Mark as read</v-list-item-title>
                    </v-list-item>
                    <v-list-item @click="remove(item.id)">
                      <v-list-item-title class="text-error">
                        Delete
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
                You have no notifications at this time.
              </p>
            </v-col>
          </v-row>
        </div>
      </div>
    </template>
  </v-container>
</template>

<script setup>
  import { onMounted, computed } from 'vue'
  import { useAuthStore } from '@/stores/auth'
  import { useNotificationStore } from '@/stores/notificationStore'
  const authStore = useAuthStore()
  const notificationStore = useNotificationStore()

  onMounted(() => {
    notificationStore.fetchNotifications()
  })
  const notifications = computed(() => notificationStore.notifications)

  const linkTelegram = async () => {
    const botUsername = 'IVMAlertBot'
    const token = authStore.me.name
    window.open(`https://t.me/${botUsername}?start=${token}`, '_blank')
  }

  // async function confirmTelegram() {
  //   try {
  //     notificationStore.confirmTelegram()
  //   } catch (err) {
  //     console.error(err.response.data)
  //   }
  // }

  const makeAsRead = id => {
    notificationStore.makeAsRead(id)
    notificationStore.fetchNotifications()
    authStore.fetchMe()
  }

  const markAllAsRead = () => {
    notificationStore.makeAsReadAll()
    notificationStore.fetchNotifications()
    authStore.fetchMe()
  }

  const remove = id => {
    notificationStore.remove(id)
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
