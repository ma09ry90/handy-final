<template>
  <div class="p-6">
    
    <!-- DEBUG BOX -->
    <div class="bg-gray-100 border-2 border-dashed border-gray-400 p-4 rounded mb-6 font-mono text-xs">
      <p><strong>1. Base URL Used:</strong> {{ actualUrl }}</p>
      <p><strong>2. Status:</strong> {{ status }}</p>
      <p v-if="error" class="text-red-600"><strong>3. Error:</strong> {{ error }}</p>
      <p v-if="responseData"><strong>4. Response:</strong> {{ responseData }}</p>
    </div>

    <!-- Simple Stats -->
    <div class="bg-white p-4 rounded shadow">
        <h3 class="text-gray-500">Products</h3>
        <p class="text-2xl font-bold">{{ stats.total_products }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios'; // USING RAW AXIOS

// HARD CODED VARIABLES FOR TESTING
const PC2_IP = '192.168.105.135'; // <--- CHECK THIS IP!
const actualUrl = 'http://' + PC2_IP + ':8000/api/artisan/dashboard';

const status = ref('Initializing...');
const error = ref(null);
const responseData = ref(null);

const stats = ref({ total_products: 0 });

onMounted(async () => {
  status.value = 'Attempting to connect to ' + actualUrl;
  
  try {
    // DIRECT AXIOS CALL WITH FULL URL
    const res = await axios.get(actualUrl, {
        headers: { 
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + localStorage.getItem('token') 
        }
    });
    
    status.value = 'SUCCESS: Connected!';
    responseData.value = JSON.stringify(res.data);
    stats.value = res.data;
    
  } catch (e) {
    status.value = 'FAILED';
    error.value = e.message;
    console.error(e);
  }
});
</script>