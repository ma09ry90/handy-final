<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const orders = ref([])
const loading = ref(true)

const fetchOrders = async () => {
  try {
    // Ensure this endpoint matches the route we updated in the controller
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

    <div v-else class="space-y-6">
      <div v-for="order in orders" :key="order.id" class="bg-white p-6 rounded-xl border shadow-sm">
        
        <!-- Top Row: Order Info & Action -->
        <div class="flex flex-col sm:flex-row justify-between gap-4 border-b pb-4 mb-4">
          <div>
            <p class="font-bold text-lg text-gray-900">{{ order.order_number }}</p>
            <p class="text-xs text-gray-400 mt-1">Created: {{ order.created_at }}</p>
            <span class="inline-block mt-2 px-3 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-700 uppercase">
              {{ order.status.replace(/_/g, ' ') }}
            </span>
          </div>
          
          <div class="flex items-center">
            <button 
              v-if="order.status === 'paid'" 
              @click="markReady(order.id)"
              class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition shadow-sm">
              📦 Ready for Pickup
            </button>
          </div>
        </div>

        <!-- 👉 NEW: Earnings Breakdown Section -->
        <div class="bg-gray-50 rounded-lg p-4 border border-dashed border-gray-200">
            <h3 class="text-sm font-semibold text-gray-500 mb-3 uppercase tracking-wide">Earnings Breakdown</h3>
            
            <div class="flex justify-between items-center mb-2 text-gray-700">
                <span>Product Total:</span>
                <span class="font-medium">{{ order.product_total }} ETB</span>
            </div>

            <div class="flex justify-between items-center mb-2 text-red-500">
                <span>Platform Fee (10%):</span>
                <span class="font-medium">- {{ order.platform_fee }} ETB</span>
            </div>

            <div class="border-t border-gray-200 my-2"></div>

            <div class="flex justify-between items-center text-green-600">
                <span class="font-bold text-base">You Earn:</span>
                <span class="font-extrabold text-xl">{{ order.artisan_earning }} ETB</span>
            </div>
        </div>

      </div>
    </div>
  </div>
</template>