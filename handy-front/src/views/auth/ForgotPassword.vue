<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="w-full max-w-md p-8 bg-white shadow rounded-lg">
      <h2 class="text-2xl font-bold text-center mb-6">Forgot Password</h2>
      
      <p class="text-gray-600 text-sm text-center mb-4">
        Enter your email address and we will send you a link to reset your password.
      </p>

      <div v-if="message" class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm">
        {{ message }}
      </div>
      <div v-if="error" class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm">
        {{ error }}
      </div>

      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
          <input 
            v-model="email"
            type="email" 
            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-indigo-500"
            required
          >
        </div>

        <button 
          type="submit" 
          :disabled="loading"
          class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 disabled:opacity-50"
        >
          <span v-if="!loading">Send Reset Link</span>
          <span v-else>Sending...</span>
        </button>
      </form>

      <div class="mt-4 text-center">
        <RouterLink to="/login" class="text-sm text-indigo-600 hover:underline">
          Back to Login
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const email = ref('');
const loading = ref(false);
const message = ref('');
const error = ref('');

const submit = async () => {
  loading.value = true;
  error.value = '';
  message.value = '';

  try {
    const response = await axios.post('/api/forgot-password', { email: email.value });
    message.value = response.data.message; // "We have emailed your password reset link!"
  } catch (err) {
    if (err.response?.data?.message) {
      error.value = err.response.data.message;
    } else {
      error.value = "Unable to send reset link.";
    }
  } finally {
    loading.value = false;
  }
};
</script>