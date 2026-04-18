<script setup>
import { onMounted } from 'vue'; // ✅ FIX: Added this import
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

// Check if user session is valid on app load
onMounted(async () => {
    // If we have user data in storage, verify it with the server
    // This keeps the user logged in if they refresh the page
    if (authStore.isAuthenticated) {
        try {
            await authStore.fetchUser();
        } catch (e) {
            console.log("Session invalid or expired");
        }
    }
});
</script>

<template>
  <router-view />
</template>

<style>
/* Your global styles here if needed */
</style>