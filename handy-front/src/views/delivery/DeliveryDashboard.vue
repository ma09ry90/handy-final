<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'

const orders = ref([])
const loading = ref(true)
const showOtpModal = ref(false)
const otpForm = ref({ orderId: null, otp: '' })
const otpError = ref('')
const gpsError = ref('')

// Tracking Variables
let trackingInterval = null
const isTracking = ref(false)

const fetchOrders = async () => {
  try {
    const res = await api.get('/delivery/my-orders')
    orders.value = res.data
    
    // Auto-resume live tracking if there's an existing "in_transit" order on page load
    const transitOrder = orders.value.find(o => o.status === 'in_transit')
    if (transitOrder) {
      startLiveTracking(transitOrder.id)
    }
  } catch (e) {
    console.error("Failed to fetch delivery orders", e)
  } finally {
    loading.value = false
  }
}

// 1. Helper to get current GPS coordinates
const getCurrentLocation = () => {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Geolocation is not supported by your browser.'))
      return
    }
    navigator.geolocation.getCurrentPosition(
      (position) => resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude }),
      (error) => reject(error),
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    )
  })
}

const triggerAction = (orderId, action) => {
  gpsError.value = ''
  if (action === 'delivered') {
    otpForm.value = { orderId, otp: '' }
    otpError.value = ''
    showOtpModal.value = true
  } else {
    updateTrackingAndStatus(orderId, action)
  }
}

// 2. Updated function to send Status + Location together
const updateTrackingAndStatus = async (orderId, newStatus, otpCode = null) => {
  try {
    // Get location before making the API call
    const location = await getCurrentLocation()
    
    // Payload matches the tracking controller we built
    const payload = {
      status: newStatus,
      latitude: location.latitude,
      longitude: location.longitude
    }
    
    if (otpCode) payload.otp = otpCode

    // Hit the tracking endpoint instead of the generic status endpoint
    await api.post(`/orders/${orderId}/track`, payload)
    
    showOtpModal.value = false
    gpsError.value = ''

    // Handle background tracking logic
    if (newStatus === 'in_transit') {
      startLiveTracking(orderId)
    } else if (newStatus === 'delivered') {
      stopLiveTracking()
    }

    fetchOrders()
  } catch (e) {
    if (e.code === 1) { // Geolocation permission denied
      gpsError.value = 'Location permission denied. Please enable it in your browser settings.'
    } else {
      otpError.value = e.response?.data?.message || 'Failed to update status.'
    }
  }
}

// 3. Background Live Tracking (Pings every 30 seconds)
const startLiveTracking = (orderId) => {
  stopLiveTracking() // Clear any existing intervals
  isTracking.value = true
  
  trackingInterval = setInterval(async () => {
    try {
      const location = await getCurrentLocation()
      await api.post(`/orders/${orderId}/track`, {
        status: 'in_transit', // Keep pinging as in_transit
        latitude: location.latitude,
        longitude: location.longitude
      })
    } catch (e) {
      console.error("Live tracking ping failed", e)
    }
  }, 30000) // 30,000 ms = 30 seconds
}

const stopLiveTracking = () => {
  if (trackingInterval) {
    clearInterval(trackingInterval)
    trackingInterval = null
    isTracking.value = false
  }
}

// Clean up interval when driver leaves the page
onUnmounted(() => {
  stopLiveTracking()
})

onMounted(fetchOrders)
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-10 min-h-screen bg-gray-50 font-sans">
    <div class="flex justify-between items-center mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Delivery Dashboard</h1>
      
      <!-- Live Tracking Indicator -->
      <div v-if="isTracking" class="flex items-center gap-2 bg-green-100 text-green-700 px-4 py-2 rounded-full text-sm font-bold animate-pulse">
        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
        Live GPS Active
      </div>
    </div>

    <!-- GPS Error Alert -->
    <div v-if="gpsError" class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
      <p class="text-red-700 text-sm">{{ gpsError }}</p>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-500">Loading...</div>

    <div v-else-if="orders.length === 0" class="bg-white p-10 rounded-xl text-center text-gray-500 border">
      <p class="text-lg font-semibold">No active deliveries</p>
    </div>

    <div v-else class="space-y-6">
      <div v-for="order in orders" :key="order.id" class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="bg-gray-800 text-white p-4 flex justify-between items-center">
          <span class="font-bold">{{ order.order_number }}</span>
          <span class="px-3 py-1 rounded-full text-xs font-bold uppercase"
                :class="{
                  'bg-yellow-500 text-yellow-900': order.status === 'assigned' || order.status === 'ready_for_pickup',
                  'bg-blue-500 text-blue-900': order.status === 'picked_up',
                  'bg-purple-500 text-purple-900': order.status === 'in_transit',
                  'bg-green-500 text-green-900': order.status === 'delivered'
                }">
            {{ order.status.replace(/_/g, ' ') }}
          </span>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="border-r-0 md:border-r border-gray-200 md:pr-6">
            <h3 class="font-bold text-gray-700 flex items-center gap-2 mb-2">📦 Pickup</h3>
            <p class="text-gray-800 font-medium">{{ order.pickup_address?.street }}</p>
          </div>
          <div>
            <h3 class="font-bold text-gray-700 flex items-center gap-2 mb-2">📍 Dropoff</h3>
            <p class="text-gray-800 font-medium">{{ order.delivery_address?.street }}</p>
          </div>
        </div>

        <div class="p-4 bg-gray-50 border-t flex flex-wrap gap-3">
          <button v-if="order.status === 'assigned' || order.status === 'ready_for_pickup'" 
                  @click="triggerAction(order.id, 'picked_up')" 
                  class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition">
            Mark Picked Up
          </button>
          
          <button v-if="order.status === 'picked_up'" 
                  @click="triggerAction(order.id, 'in_transit')" 
                  class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 rounded-lg transition">
            Start Transit
          </button>
          
          <button v-if="order.status === 'in_transit'" 
                  @click="triggerAction(order.id, 'delivered')" 
                  class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-lg transition">
            Confirm Delivery (Requires OTP)
          </button>
        </div>
      </div>
    </div>

    <!-- OTP Modal -->
    <div v-if="showOtpModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="bg-white rounded-2xl p-8 w-full max-w-sm mx-4 shadow-2xl">
        <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">Enter Delivery OTP</h3>
        <p class="text-sm text-gray-500 text-center mb-6">The buyer must provide this code to confirm handover.</p>
        
        <input 
          v-model="otpForm.otp" 
          type="text" 
          maxlength="4" 
          placeholder="e.g. 1234"
          class="w-full border-2 border-gray-200 focus:border-emerald-500 rounded-lg p-4 text-center text-2xl tracking-widest font-mono mb-2 outline-none"
        />
        
        <p v-if="otpError" class="text-red-500 text-sm text-center mb-4">{{ otpError }}</p>

        <div class="flex gap-3">
          <button @click="showOtpModal = false" class="flex-1 border border-gray-300 py-3 rounded-lg font-semibold text-gray-700">Cancel</button>
          <button 
            @click="updateTrackingAndStatus(otpForm.orderId, 'delivered', otpForm.otp)" 
            :disabled="otpForm.otp.length !== 4"
            class="flex-1 bg-emerald-600 disabled:bg-gray-300 text-white py-3 rounded-lg font-semibold transition"
          >
            Confirm
          </button>
        </div>
      </div>
    </div>
  </div>
</template>