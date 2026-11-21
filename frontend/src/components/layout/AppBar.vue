<template>
  <v-app-bar scroll-behavior="inverted" elevation="0" border flat>
    <!-- Classic/Icon-rail: mobile-only, since the floating collapse handle
         replaces this at lgAndUp (the sidebar is a permanent drawer there).
         POS-focus: always shown — its sidebar is never permanent. -->
    <template v-if="mdAndDown || forceHamburger">
      <v-app-bar-nav-icon
        @click="emit('toggle')"
        icon="mdi-menu"
      ></v-app-bar-nav-icon>
    </template>

    <!-- The platform logo lives in the Sidebar header for every layout (see
         Sidebar.vue) — never duplicated here. -->
    <div class="ms-4">
      <ShopBranchSwitcher />
    </div>

    <template v-slot:append>
      <AppBarActions
        :user="user"
        :bu-name="buName"
        :branch-name="branchName"
        :role-name="roleName"
      />
    </template>
  </v-app-bar>
</template>

<script setup>
  import { useDisplay } from 'vuetify'
  import ShopBranchSwitcher from './ShopBranchSwitcher.vue'
  import AppBarActions from './AppBarActions.vue'

  const emit = defineEmits(['toggle'])

  defineProps({
    user: Object,
    buName: String,
    branchName: String,
    roleName: String,
    // POS-focus reuses this same AppBar (see Layout.vue) but its sidebar is
    // always temporary, never permanent — so the hamburger belongs here
    // regardless of viewport width, not just on mdAndDown.
    forceHamburger: Boolean
  })

  const { mdAndDown } = useDisplay()
</script>
