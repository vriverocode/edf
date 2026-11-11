<template>
  <div v-if="isLoading" class="loader-overlay">
    <div class="skyline-loader">
      <div class="building building-1"></div>
      <div class="building building-2"></div>
      <div class="building building-3"></div>
      <div class="building building-4"></div>
    </div>
    <div class="loader-text">easycondo</div>
  </div>
</template>

<script setup>
defineProps({
  isLoading: {
    type: Boolean,
    default: true
  }
})
</script>

<style scoped lang="scss">
.loader-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: $primary; /* Verde oscuro del fondo */
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.skyline-loader {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  height: 60px;
  margin-bottom: 20px;
}

.building {
  width: 20px;
  border-radius: 2px 2px 0 0;
  animation: bounce 1s infinite alternate;
}

/* Colores extraídos del logotipo */
.building-1 {
  height: 30px;
  background-color: #DED8C7; /* Crema */
  animation-delay: 0s;
}

.building-2 {
  height: 50px;
  background-color: #D0A070; /* Ocre */
  animation-delay: 0.2s;
}

.building-3 {
  height: 60px;
  background-color: #A8573F; /* Terracota */
  animation-delay: 0.4s;
}

.building-4 {
  height: 40px;
  background-color: #E6B849; /* Amarillo/Dorado de la llave */
  animation-delay: 0.6s;
}

.loader-text {
  color: #DED8C7;
  font-family: sans-serif;
  font-weight: bold;
  font-size: 1.2rem;
  letter-spacing: 1px;
  animation: pulse 1.5s infinite;
}

@keyframes bounce {
  0% {
    transform: scaleY(0.8);
    transform-origin: bottom;
  }
  100% {
    transform: scaleY(1.2);
    transform-origin: bottom;
  }
}

@keyframes pulse {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}
</style>