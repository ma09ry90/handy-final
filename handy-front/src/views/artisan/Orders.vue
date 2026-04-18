<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const orders = ref([])
const loading = ref(true)

const fetchOrders = async () => {
  try {
    const res = await api.get('/artisan/products') // Reuse existing endpoint that returns products with order data, OR create a specific orders endpoint in backend if you prefer
    // Assuming you will add a dedicated route for this in ArtisanProductController:
    // Route::get('/artisan/orders', [ArtisanProductController::class, 'getMyOrders']);
    
    // For now, we mock the API call
    const ordersRes = await api.get('/artisan/orders'); 
    orders.value = ordersRes.data;
  } catch (e) {
    console.error("Failed to fetch artisan orders", e)
  } finally {
    loading.value = false
  }
}

const markReady = async (orderId) => {
  if (!confirm('Mark this order as ready for pickup?')) return
  try {
    await api.post(`/artisan/orders/${orderId}/ready`)
    alert('Order marked as ready!')
    fetchOrders()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to update.')
  }
}

onMounted(fetchOrders)
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-10 min-h-screen bg-gray-50">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">My Orders & Sales</h1>

    <div v-if="loading" class="text-center py-20 text-gray-500">Loading...</div>

    <div v-else-if="orders.length === 0" class="bg-white p-10 text-center rounded-xl border text-gray-500">
      You haven't sold anything yet!
    </div>

    <div v-else class="space-y-4">
      <div v-for="order in orders" :key="order.id" class="bg-white p-5 rounded-xl border shadow-sm flex flex-col sm:flex-row justify-between gap-4">
        <div>
          <p class="font-bold text-gray-900">{{ order.order_number }}</p>
          <p class="text-sm text-gray-500">Total: {{ order.total_amount }} ETB</p>
          <span class="inline-block mt-1 px-2 py-0.5 text-xs font-bold rounded bg-blue-100 text-blue-700">
            {{ order.status.replace(/_/g, ' ') }}
          </span>
        </div>
        
        <div class="flex items-end">
          <button 
            v-if="order.status === 'paid'" 
            @click="markReady(order.id)"
            class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition"
          >
            📦 Ready for Pickup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>