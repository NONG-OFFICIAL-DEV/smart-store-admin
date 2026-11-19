<template>
  <v-app-bar scroll-behavior="inverted" elevation="0" border flat>
    <!-- Nav toggle -->
    <v-app-bar-nav-icon
      @click="emit('toggle')"
      :color="rail ? 'primary' : undefined"
      icon="mdi-menu"
    ></v-app-bar-nav-icon>
    <v-divider class="mx-3" inset vertical />

    <!-- Platform brand — the SAME image for every tenant, never the
         tenant's own logo (that only ever shows on receipts/bills/
         customer display/e-menu). See ShopBranchSwitcher for the
         per-tenant branding instead. -->
    <v-app-bar-title class="d-none d-lg-block d-print-block">
      <button type="button" class="platform-logo-btn" @click="router.push('/')">
        <v-img :src="platformLogo" height="32" width="auto" contain />
      </button>
    </v-app-bar-title>
    <v-divider class="mx-3" inset vertical />

    <ShopBranchSwitcher />

    <template v-slot:append>
      <AppBarActions :user="user" :bu-name="buName" :branch-name="branchName" :role-name="roleName" />
    </template>
  </v-app-bar>
</template>

<script setup>
  import { computed } from 'vue'
  import { useTheme } from 'vuetify'
  import { useRouter } from 'vue-router'
  import ShopBranchSwitcher from './ShopBranchSwitcher.vue'
  import AppBarActions from './AppBarActions.vue'
  import logoWhite from '/logo_white.png'
  import logoDark from '/logo_dark.png'

  const router = useRouter()
  const theme = useTheme()

  const emit = defineEmits(['toggle'])

  defineProps({
    rail: Boolean,
    user: Object,
    buName: String,
    branchName: String,
    roleName: String
  })

  const isDark = computed(() => theme.global.current.value.dark)

  // The platform brand — identical for every tenant. Never the tenant's own
  // logo (ShopBranchSwitcher reads authStore.logo_url for that instead).
  const platformLogo = computed(() => (isDark.value ? logoWhite : logoDark))
</script>

<style scoped>
  .platform-logo-btn {
    border: none;
    background: transparent;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    padding: 0;
  }
</style>
