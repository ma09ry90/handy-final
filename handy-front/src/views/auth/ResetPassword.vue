<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="w-full max-w-md p-8 bg-white shadow rounded-lg">
      <h2 class="text-2xl font-bold text-center mb-6">Reset Password</h2>

      <div v-if="success" class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm">
        {{ success }} <br>
        <RouterLink to="/login" class="underline font-bold">Login here</RouterLink>
      </div>

      <div v-if="error" class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm">
        {{ error }}
      </div>

      <form v-if="!success" @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
          <input 
            v-model="form.email"
            type="email" 
            class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100"
            readonly
          >
        </div>

        <div class="mb-4">
          <label class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
          <input 
            v-model="form.password"
            type="password" 
            class="w-full px-3 py-2 border border-gray-300 rounded"
            required
          >
        </div>

        <div class="mb-6">
          <label class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
          <input 
            v-model="form.password_confirmation"
            type="password" 
            class="w-full px-3 py-2 border border-gray-300 rounded"
            required
          >
        </div>

        <button 
          type="submit" 
          :disabled="loading"
          class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 disabled:opacity-50"
        >
          <span v-if="!loading">Reset Password</span>
          <span v-else>Resetting...</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();

const form = ref({
  token: '',
  email: '',
  password: '',
  password_confirmation: ''
});

const loading = ref(false);
const success = ref('');
const error = ref('');

onMounted(() => {
  // Get token and email from URL query parameters
  form.value.token = route.params.token;
  form.value.email = route.query.email || '';
});

const submit = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await axios.post('/api/reset-password', form.value);
    success.value = response.data.message; // "Your password has been reset!"
  } catch (err) {
    if (err.response?.data?.message) {
      error.value = err.response.data.message;
    } else {
      error.value = "Failed to reset password. Invalid token or email.";
    }
  } finally {
    loading.value = false;
  }
};
</script>