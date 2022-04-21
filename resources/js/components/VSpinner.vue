<template>
  <div class="spinner-container m-2	d-flex justify-content-center">
    <div class="spinner-border" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
  </div>
</template>

<script>
export default {
  name: "VSpinner"
}
</script>

<style scoped>

.spinner-container {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: 99;
}

</style>