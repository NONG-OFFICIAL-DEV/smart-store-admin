<template>
    <div class="alert">
      <v-slide-x-reverse-transition group>
        <v-alert
          v-for="alert in alerts"
          :key="alert.id"
          :type="alert.type"
          :icon="alert.icon"
          :dense="alert.dense"
          :prominent="alert.prominent"
          :dismissible="alert.dismissible"
          @input="closeAlert(alert.id)"
          closable
          >
          <!-- variant="outlined" -->
          <strong class="capitalize-first-letter">{{
            alert.message
          }}</strong>
        </v-alert>
      </v-slide-x-reverse-transition>
    </div>
  </template>
  
  <script>
  export default {
    name: 'NotificationAlert',
    props: {
      // Type d'affichage
      outlined: { type: Boolean, required: false, default: true },
      text: { type: Boolean, required: false, default: false },
      dense: { type: Boolean, required: false, default: false },
      prominent: { type: Boolean, required: false, default: false },
      // Ajout de la possibilité de fermer l'alerte
      dismissible: { type: Boolean, required: false, default: false },
      // Temps d'affichage à l'écran en ms
      defaultTimeout: { type: Number, required: false, default: 2000 },
      // Maximum d'alerte afficher en même Temps
      defaultMaxAlert: { type: Number, required: false, default: 4 },
    },
    data() {
      return {
        alerts: [],
      }
    },
    methods: {
      newAlert(
        message,
        {
          type = 'success',
          icon = null,
          timeout = this.defaultTimeout,
          dense = this.dense,
          prominent = this.prominent,
          dismissible = this.dismissible,
        }
      ) {
        if (this.alerts.length === this.defaultMaxAlert) this.alerts.shift()
        // Création d'un id unique
        const id = new Date().valueOf() + Math.random()
        this.alerts.push({
          id,
          type,
          icon,
          message,
          dense,
          prominent,
          dismissible,
        })
        // Si timeout = 0 on laisse l'alerte à l'écran
        if (timeout) {
          setTimeout(() => {
            this.closeAlert(id)
          }, timeout)
        }
      },
      closeAlert(id) {
        this.alerts = this.alerts.filter((el) => el.id !== id)
      },
    },
  }
  </script>
  
  <style scoped>
  .alert {
    overflow: hidden;
    position: fixed;
    top: 80px;
    right: 0;
    margin-right: 16px;
    z-index: 9999;
  }
  </style>
  