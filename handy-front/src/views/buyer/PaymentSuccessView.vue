<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/plugins/axios'

const orders = ref([])
const isLoading = ref(true)
const isProcessing = ref(true)
const error = ref(null)
let pollInterval = null

const formatPrice = (price) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'ETB' }).format(price || 0)

const fetchOrders = async () => {
  try {
    // Read order numbers from local storage
    const orderNumbers = JSON.parse(localStorage.getItem('pending_order_numbers') || '[]')
    
    if (orderNumbers.length === 0) {
      throw new Error("No order information found.")
    }

    // ✅ USE YOUR EXISTING API ROUTE: /api/orders/{orderNumber}/summary
    const promises = orderNumbers.map(number => api.get(`/orders/${number}/summary`))
    const responses = await Promise.all(promises)
    
    orders.value = responses.map(res => res.data)

    // Check if ALL orders are finally marked as 'paid'
    const allPaid = orders.value.every(o => o.status === 'paid')
    
    if (allPaid) {
      isProcessing.value = false
      localStorage.removeItem('pending_order_numbers')
      stopPolling()
    } else {
      isProcessing.value = true // Keep showing "Processing..." spinner
    }

  } catch (e) {
    error.value = e.response?.data?.message || e.message || "Failed to load order details."
    isProcessing.value = false
    stopPolling()
  } finally {
    isLoading.value = false
  }
}

const stopPolling = () => {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

onMounted(async () => {
  await fetchOrders()
  if (isProcessing.value && !error.value) {
    pollInterval = setInterval(fetchOrders, 3000)
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 py-16 min-h-screen flex flex-col items-center justify-center">
    
    <!-- Loading State -->
    <div v-if="isLoading" class="text-center">
      <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-emerald-500 mx-auto mb-6"></div>
      <p class="text-gray-500 text-lg">Loading order details...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="text-center bg-red-50 p-8 rounded-xl border border-red-200 max-w-md">
      <span class="text-4xl block mb-4">⚠️</span>
      <p class="text-red-700 font-semibold mb-4">{{ error }}</p>
      <RouterLink to="/orders" class="text-emerald-600 font-bold hover:underline">View My Orders</RouterLink>
    </div>

    <!-- Processing State (Waiting for Webhook to update DB) -->
    <div v-else-if="isProcessing" class="text-center bg-white p-10 rounded-2xl shadow-lg border border-gray-100 max-w-md w-full">
      <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-b-4 border-emerald-500 mx-auto mb-6"></div>
      <h2 class="text-2xl font-bold text-gray-800 mb-2">Payment Received!</h2>
      <p class="text-gray-500 mb-6">We are confirming your payment with the bank...</p>
      
      <div class="space-y-3 text-left bg-gray-50 p-4 rounded-lg">
        <div v-for="order in orders" :key="order.order_number" class="flex justify-between text-sm">
          <span class="text-gray-600">Order {{ order.order_number }}</span>
          <span class="font-semibold text-gray-800">{{ formatPrice(order.financials.total_amount) }}</span>
        </div>
      </div>
      <p class="text-xs text-gray-400 mt-4">Please do not close this page.</p>
    </div>

    <!-- Final Success State (Reads data from your summary endpoint) -->
    <div v-else class="text-center bg-white p-10 rounded-2xl shadow-lg border border-gray-100 max-w-lg w-full">
      <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6">
        <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      </div>
      <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Payment Successful!</h2>
      <p class="text-gray-500 mb-8">Thank you for your purchase.</p>
      
      <div class="space-y-4 text-left mb-8">
        <div v-for="order in orders" :key="order.order_number" class="border border-gray-200 rounded-xl p-4">
          <div class="flex justify-between items-center mb-2">
            <span class="font-mono text-xs text-gray-400">{{ order.order_number }}</span>
            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-1 rounded">PAID</span>
          </div>
          
          <!-- Shows items from your summary endpoint -->
          <div class="text-sm text-gray-600 mb-2 space-y-1">
             <div v-for="item in order.items" :key="item.version_id" class="flex justify-between">
                <span>{{ item.name }} x{{ item.quantity }}</span>
                <span>{{ formatPrice(item.line_total) }}</span>
             </div>
          </div>

          <div class="border-t pt-2 mt-2 flex justify-between font-bold text-gray-900">
            <span>Total</span>
            <span>{{ formatPrice(order.financials.total_amount) }}</span>
          </div>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row gap-4">
        <RouterLink to="/orders" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3 px-6 rounded-xl transition">
          View My Orders
        </RouterLink>
        <RouterLink to="/" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-6 rounded-xl transition">
          Continue Shopping
        </RouterLink>
      </div>
    </div>

  </div>
</template>