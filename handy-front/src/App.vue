<script setup>
// Empty — auth initialization is in main.js, not here.
// The old fetchUser() call was breaking things because:
// 1. It doesn't exist in the auth store
// 2. It runs in onMounted, which is AFTER the router guard
</script>

<template>
  <router-view />
</template>

<style>
/* Your global styles here if needed */
</style>