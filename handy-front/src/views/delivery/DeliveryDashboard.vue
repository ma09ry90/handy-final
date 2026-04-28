<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'

const orders = ref([])
const loading = ref(true)
const showOtpModal = ref(false)
const otpForm = ref({ orderId: null, otp: '' })
const otpError = ref('')
const gpsError = ref('')

// ✅ NEW: State for copy-to-clipboard feedback
const copiedOrderId = ref(null)

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

// ✅ NEW: Copy phone number to clipboard
const copyPhone = async (phone, orderId) => {
  try {
    await navigator.clipboard.writeText(phone)
    copiedOrderId.value = orderId
    setTimeout(() => { copiedOrderId.value = null }, 2000) // Hide "Copied!" text after 2 seconds
  } catch (err) {
    console.error('Failed to copy: ', err)
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
  otpError.value = ''
  if (action === 'delivered') {
    otpForm.value = { orderId, otp: '' }
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
      const errorMsg = e.response?.data?.message || 'Failed to update status.'
      if (action === 'delivered') {
        otpError.value = errorMsg
      } else {
        gpsError.value = errorMsg
      }
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
  <router-link to="/delivery/wallet" 
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-150 group"
            :class="$route.path === '/delivery/wallet' ? 'bg-green-200 text-green-900 shadow-sm font-semibold' : 'text-green-700 hover:bg-green-100 hover:text-green-900'">
            <span class="text-lg">💰</span>
            <span>{{ $t('nav.wallet') }}</span>
</router-link>
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
        
        <!-- Header -->
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

        <!-- Addresses & Phone -->
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          
          <!-- Pickup Section -->
          <div class="border-r-0 md:border-r border-gray-200 md:pr-6">
            <h3 class="font-bold text-gray-700 flex items-center gap-2 mb-2">📦 Pickup</h3>
            <p class="text-gray-800 font-medium mb-1">{{ order.pickup_address?.street }}</p>
            <p v-if="order.seller?.name" class="text-sm text-gray-500">Seller: {{ order.seller.name }}</p>
          </div>
          
          <!-- Dropoff Section -->
          <div>
            <h3 class="font-bold text-gray-700 flex items-center gap-2 mb-2">📍 Dropoff</h3>
            <p class="text-gray-800 font-medium">{{ order.delivery_address?.street }}</p>
            
            <!-- ✅ NEW: Buyer Phone Number (Click to Call / Copy) -->
            <div v-if="order.buyer_phone" class="mt-4 bg-gray-100 p-3 rounded-lg flex items-center justify-between">
              <a 
                :href="`tel:${order.buyer_phone}`" 
                class="flex items-center gap-2 text-blue-600 hover:text-blue-800 font-semibold transition"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                {{ order.buyer_phone }}
              </a>
              
              <button 
                @click="copyPhone(order.buyer_phone, order.id)" 
                class="text-gray-500 hover:text-gray-700 transition p-1"
                title="Copy phone number"
              >
                <!-- Checkmark icon when copied -->
                <svg v-if="copiedOrderId === order.id" class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <!-- Copy icon normally -->
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
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