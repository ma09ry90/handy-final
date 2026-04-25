<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="text-center p-8 bg-white shadow rounded-lg max-w-md">
      <!-- Success Icon -->
      <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
        <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
      </div>

      <h1 class="text-2xl font-bold text-gray-900 mb-2">Email Verified!</h1>
      
      <p class="text-gray-600 mb-6">
        Your email has been successfully verified. You can now login to your account.
      </p>

      <router-link 
        to="/login" 
        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
      >
        Continue to Login
      </router-link>
    </div>
  </div>
</template>

<script setup>
// No logic needed, just a display page
</script>