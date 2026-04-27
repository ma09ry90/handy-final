<script setup>
//import { ref, onMounted, watch, nextTick, onUnmounted } from 'vue'
import { ref, reactive, onMounted, watch, nextTick, onUnmounted } from 'vue'
import api from '@/plugins/axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import OrderReportModal from '../../components/OrderReportModal.vue'
// Fix default marker icons breaking in Vue/Webpack
import { useI18n } from 'vue-i18n'

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});
const { t } = useI18n()
const orders = ref([])
const isLoading = ref(true)
const meta = ref(null)

// Modal State
const isModalOpen = ref(false)
const selectedOrder = ref(null)
const orderDetails = ref(null)
const isLoadingDetails = ref(false)

// ==========================================
// ✅ NEW: REVIEW SYSTEM STATE
// ==========================================
const activeReviewItem = ref(null) // Stores the order_item_id being reviewed
const reviewRating = ref(0)
const reviewComment = ref('')
const isSubmittingReview = ref(false)

// Map State
let map = null
let driverMarker = null
let pickupMarker = null
let dropoffMarker = null
let trackingPoll = null

const formatPrice = (price) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'ETB' }).format(price || 0)

const getStatusStyle = (status) => {
  const styles = {
    'pending_payment': 'bg-yellow-100 text-yellow-700',
    'paid': 'bg-blue-100 text-blue-700',
    'ready_for_pickup': 'bg-indigo-100 text-indigo-700',
    'picked_up': 'bg-indigo-100 text-indigo-700',
    'in_transit': 'bg-orange-100 text-orange-700',
    'delivered': 'bg-emerald-100 text-emerald-700',
    'cancelled': 'bg-red-100 text-red-700'
  }
  return styles[status] || 'bg-gray-100 text-gray-700'
}

const formatStatus = (status) => status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())

const fetchOrders = async () => {
  try {
    const res = await api.get('/orders')
    orders.value = res.data.data
    meta.value = res.data.meta
  } catch (e) {
    console.error("Failed to fetch orders", e)
  } finally {
    isLoading.value = false
  }
}


// ... your existing order fetching logic ...

const showReportModal = ref(false)
const selectedProduct = reactive({
  id: null,
  name: '',
  seller_id: null
})

const openReportModal = (productId, productName, sellerId) => {
  selectedProduct.id = productId
  selectedProduct.name = productName
  selectedProduct.seller_id = sellerId
  showReportModal.value = true
}

const handleReportSuccess = () => {
  // Optional: Show a global toast notification here
  // Or refresh the order data if needed
  console.log('Report was submitted successfully!')
}

const openDetails = async (orderNumber) => {
  isModalOpen.value = true
  isLoadingDetails.value = true
  selectedOrder.value = orderNumber
  orderDetails.value = null
  
  // Reset review state when opening new order
  activeReviewItem.value = null
  
  try {
    const res = await api.get(`/orders/${orderNumber}/summary`)
    orderDetails.value = res.data
  } catch (e) {
    console.error("Failed to fetch details")
  } finally {
    isLoadingDetails.value = false
  }
}

const closeModal = () => {
  isModalOpen.value = false
  orderDetails.value = null
  activeReviewItem.value = null // Reset review
  
  // Cleanup map and polling
  if (map) { map.remove(); map = null; }
  if (trackingPoll) { clearInterval(trackingPoll); trackingPoll = null; }
}

// ==========================================
// ✅ NEW: REVIEW SYSTEM LOGIC
// ==========================================

const startReview = (item) => {
  activeReviewItem.value = item.id
  reviewRating.value = item.my_review?.rating || 5 // Default 5 stars
  reviewComment.value = item.my_review?.comment || ''
}

const cancelReview = () => {
  activeReviewItem.value = null
}

const setRating = (rating) => {
  reviewRating.value = rating
}

const submitReview = async (item) => {
  if (reviewRating.value === 0) {
    alert('Please select a rating.')
    return
  }

  isSubmittingReview.value = true
  
  try {
    await api.post('/reviews', {
      order_item_id: item.id,
      rating: reviewRating.value,
      comment: reviewComment.value
    })

    // ✅ OPTIMISTIC UI UPDATE
    // Update the local item state so the UI changes immediately without reloading
    item.has_reviewed = true
    item.my_review = {
      rating: reviewRating.value,
      comment: reviewComment.value
    }
    
    // Close the form
    activeReviewItem.value = null
    alert('Thank you for your review!')
    } catch (e) {
    console.error(e)
    alert(e.response?.data?.message || 'Failed to submit review.')
  } finally {
    isSubmittingReview.value = false
  }
}

// ==========================================
// MAP LOGIC (Untouched)
// ==========================================
const initMap = (details) => {
  nextTick(() => {
    if (map) { map.remove(); } // Reset if already exists
    
    const mapContainer = document.getElementById('tracking-map')
    if (!mapContainer) return;

    let lat = 9.02, lng = 38.74
    const driverCoords = details.latest_location

    if (driverCoords && driverCoords.lat && driverCoords.lng) {
      lat = driverCoords.lat
      lng = driverCoords.lng
    }

    map = L.map('tracking-map').setView([lat, lng], 14)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map)

    if (details.delivery?.pickup?.latitude && details.delivery?.pickup?.longitude) {
      pickupMarker = L.marker([details.delivery.pickup.latitude, details.delivery.pickup.longitude])
        .addTo(map).bindPopup('📦 Pickup Location')
    }

    if (details.delivery?.dropoff?.latitude && details.delivery?.dropoff?.longitude) {
      dropoffMarker = L.marker([details.delivery.dropoff.latitude, details.delivery.dropoff.longitude])
        .addTo(map).bindPopup('📍 Drop-off Location')
    } 
    
    if (driverCoords && driverCoords.lat && driverCoords.lng) {
      const driverIcon = L.divIcon({
        className: 'custom-driver-icon',
        html: '<div style="background:#3b82f6; width:16px; height:16px; border-radius:50%; border:3px solid white; box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
        iconSize: [16, 16],
        iconAnchor: [8, 8]
      })
      
      driverMarker = L.marker([driverCoords.lat, driverCoords.lng], { icon: driverIcon })
        .addTo(map)
        .bindPopup('🛵 Driver')

      if (details.delivery?.dropoff?.latitude) {
        L.polyline(
          [[driverCoords.lat, driverCoords.lng], [details.delivery.dropoff.latitude, details.delivery.dropoff.longitude]],
          { color: '#3b82f6', weight: 3, dashArray: '10, 10' }
        ).addTo(map)
      }
    }
    
    setTimeout(() => map.invalidateSize(), 100)
  })
}

const startLiveTrackingPoll = (orderNumber) => {
  if (trackingPoll) clearInterval(trackingPoll)
  
  trackingPoll = setInterval(async () => {
    try {
      const res = await api.get(`/orders/${orderNumber}/tracking`)
      const loc = res.data.latest_location
      
      if (loc && loc.lat && driverMarker) {
        driverMarker.setLatLng([loc.lat, loc.lng])
        map.panTo([loc.lat, loc.lng], { animate: true })
      }
      
      if (res.data.current_status === 'delivered') {
        clearInterval(trackingPoll)
      }
    } catch (e) { console.error("Tracking poll failed", e) }
  }, 15000)
}

watch(orderDetails, (newVal) => {
  if (newVal && isModalOpen.value) {
    initMap(newVal)
    if (['picked_up', 'in_transit'].includes(newVal.status) && selectedOrder.value) {
      startLiveTrackingPoll(selectedOrder.value)
    }
  }
})

onUnmounted(() => {
  if (map) map.remove()
  if (trackingPoll) clearInterval(trackingPoll)
})

onMounted(fetchOrders)
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-10 min-h-screen bg-gray-50">
    
    <h1 class="text-3xl font-bold text-gray-900 mb-8">My Orders</h1>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-20 text-gray-400">
      <div class="animate-spin rounded-full h-10 w-10 border-t-4 border-b-4 border-emerald-500 mx-auto mb-4"></div>
      Loading orders...
    </div>
    <!-- Empty State -->
    <div v-else-if="orders.length === 0" class="text-center py-20 bg-white rounded-xl border">
      <svg class="w-20 h-20 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      <p class="text-gray-500 text-lg mb-4">You haven't placed any orders yet.</p>
      <router-link to="/" class="text-emerald-600 font-semibold hover:underline">Start Shopping</router-link>
    </div>

    <!-- Orders List -->
    <div v-else class="space-y-4">
      <div v-for="order in orders" :key="order.id" class="bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-2">
            <span class="font-mono text-sm text-gray-500">{{ order.order_number }}</span>
            <span :class="getStatusStyle(order.status)" class="text-xs font-bold px-2.5 py-1 rounded-full">
              {{ formatStatus(order.status) }}
            </span>
          </div>
          <div class="flex items-center gap-4 text-sm text-gray-600">
            <span>{{ order.item_count }} Item(s)</span>
            <span>•</span>
            <span>{{ order.created_at }}</span>
          </div>
        </div>

        <div class="flex items-center gap-5">
          <span class="text-xl font-extrabold text-gray-900">{{ formatPrice(order.total_amount) }}</span>
          <button @click="openDetails(order.order_number)" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold py-2 px-5 rounded-lg transition text-sm">
            View Details
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- ORDER DETAILS MODAL -->
    <!-- ========================================== -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 flex justify-end">
      <!-- Backdrop -->
      <div @click="closeModal" class="absolute inset-0 bg-black/50"></div>
      
      <!-- Slide-over Panel -->
      <div class="relative w-full max-w-lg bg-white h-full overflow-y-auto shadow-2xl flex flex-col">
        
        <!-- Modal Header -->
        <div class="sticky top-0 bg-white z-10 p-6 border-b flex justify-between items-center">
          <div>
            <h2 class="text-xl font-bold text-gray-900">Order Details</h2>
            <p v-if="selectedOrder" class="text-sm text-gray-400 font-mono">{{ selectedOrder }}</p>
          </div>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 p-6">
          
          <!-- Loading Details -->
          <div v-if="isLoadingDetails" class="py-20 flex justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-t-4 border-b-4 border-emerald-500"></div>
          </div>

          <!-- Details Content -->
          <div v-else-if="orderDetails" class="space-y-6">
            
            <!-- Status & Dates -->
            <div class="flex justify-between items-center">
              <span :class="getStatusStyle(orderDetails.status)" class="text-sm font-bold px-3 py-1 rounded-full">
                {{ formatStatus(orderDetails.status) }}
              </span>
              <span class="text-sm text-gray-400">{{ orderDetails.created_at }}</span>
            </div>
            <!-- ========================================== -->
            <!-- ✅ UPDATED: ITEMS LIST WITH REVIEW SYSTEM -->
            <!-- ========================================== -->
            <div class="border rounded-xl overflow-hidden">
              <div class="bg-gray-50 px-4 py-3 border-b">
                <h3 class="font-bold text-gray-700 text-sm">Items Ordered</h3>
              </div>
              <div class="divide-y">
                <div v-for="item in orderDetails.items" :key="item.id" class="p-4 bg-white">
                  
                  <!-- Item Row -->
                  <div class="flex justify-between items-center">
                    <div>
                      <p class="font-medium text-gray-800">{{ item.name }}</p>
                      <p class="text-xs text-gray-400">Qty: {{ item.quantity }} × {{ formatPrice(item.unit_price) }}</p>
                    </div>
                    <span class="font-semibold text-gray-900">{{ formatPrice(item.line_total) }}</span>
                  </div>

                  <button 
                    @click="openReportModal(item.product_id, item.product_name, item.seller_id)"
                    class="text-sm text-red-600 hover:text-red-800 font-medium flex items-center gap-1"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    {{ t('report.title') }}
                  </button>

                  <!-- ✅ NEW: REVIEW SECTION PER ITEM -->
                  <div v-if="orderDetails.status === 'delivered'" class="mt-3 pt-3 border-t border-dashed border-gray-100">
                    
                    <!-- Case A: User has already reviewed this item -->
                    <div v-if="item.has_reviewed" class="bg-emerald-50 p-3 rounded-lg">
                      <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold text-emerald-700">YOUR RATING</span>
                        <div class="flex">
                          <span v-for="n in 5" :key="n" class="text-lg" :class="n <= item.my_review.rating ? 'text-yellow-400' : 'text-gray-300'">★</span>
                        </div>
                      </div>
                      <p v-if="item.my_review.comment" class="text-sm text-gray-600 italic">"{{ item.my_review.comment }}"</p>
                      <p v-else class="text-sm text-gray-400 italic">No comment provided.</p>
                    </div>

                    <!-- Case B: Review Form Active -->
                    <div v-else-if="activeReviewItem === item.id" class="bg-gray-50 p-3 rounded-lg border border-gray-200 mt-2">
                      <p class="text-sm font-semibold text-gray-700 mb-2">Rate this product</p>
                      
                      <!-- Star Rating Input -->
                      <div class="flex gap-1 mb-3">
                        <button v-for="n in 5" :key="n" @click="setRating(n)" class="text-2xl focus:outline-none transition-transform" :class="n <= reviewRating ? 'text-yellow-400 scale-110' : 'text-gray-300 hover:text-yellow-300'">
                          ★
                        </button>
                      </div>
                      
                      <textarea v-model="reviewComment" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none" rows="2" placeholder="Share your thoughts (optional)..."></textarea>
                      
                      <div class="flex justify-end gap-2 mt-3">
                        <button @click="cancelReview" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-200 rounded">Cancel</button>
                        <button @click="submitReview(item)" :disabled="isSubmittingReview" class="px-4 py-1.5 text-sm bg-emerald-600 text-white rounded hover:bg-emerald-700 disabled:opacity-50">
                          {{ isSubmittingReview ? 'Saving...' : 'Submit' }}
                        </button>
                      </div>
                    </div>
                    <!-- Case C: "Write Review" Button -->
                    <button v-else @click="startReview(item)" class="mt-2 text-sm text-emerald-600 font-medium hover:text-emerald-800 flex items-center gap-1">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                      Write a Review
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Delivery Info -->
            <div class="border rounded-xl p-4 space-y-2" v-if="orderDetails.delivery">
              <h3 class="font-bold text-gray-700 text-sm mb-3">Delivery Details</h3>
              <div class="flex justify-between text-sm">
                <span class="text-gray-500">Drop-off:</span>
                <span class="text-gray-800">{{ orderDetails.delivery.dropoff.address }}</span>
              </div>
              <div class="flex justify-between text-sm" v-if="orderDetails.delivery.distance_km">
                <span class="text-gray-500">Distance:</span>
                <span class="text-gray-800">{{ orderDetails.delivery.distance_km }} km</span>
              </div>
            </div>

            <!-- OTP Section -->
            <div 
              v-if="orderDetails.otp && ['paid', 'ready_for_pickup', 'assigned', 'picked_up', 'in_transit'].includes(orderDetails.status)" 
              class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center"
            >
              <p class="text-xs font-bold text-amber-700 uppercase mb-2">Handover Verification Code</p>
              <p class="text-sm text-amber-600 mb-2">Give this 4-digit code to the delivery driver when you receive your item:</p>
              <div class="bg-white border-2 border-dashed border-amber-400 rounded-lg py-3 px-6 inline-block">
                <span class="text-3xl font-extrabold tracking-widest text-amber-800">{{ orderDetails.otp }}</span>
              </div>
            </div>
            
            <div 
              v-if="!orderDetails.otp && orderDetails.status === 'delivered'" 
              class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-center"
            >
              <p class="text-sm font-semibold text-emerald-700">✅ Item Successfully Delivered & Verified</p>
            </div>

            <!-- Live Map Container -->
            <div v-if="orderDetails && ['assigned', 'ready_for_pickup', 'picked_up', 'in_transit'].includes(orderDetails.status)" 
                 class="border rounded-xl overflow-hidden mb-6 shadow-inner">
              <div id="tracking-map" class="w-full h-64 md:h-80 bg-gray-200 z-0"></div>
              <div v-if="orderDetails.latest_location" class="bg-blue-50 px-4 py-2 text-center text-xs text-blue-700 font-medium border-t flex items-center justify-center gap-2">
                <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                Live location - Updated {{ orderDetails.latest_location.updated_at }}
              </div>
              <div v-else class="bg-gray-50 px-4 py-2 text-center text-xs text-gray-500 border-t">
                Waiting for driver GPS signal...
              </div>
            </div>
            <!-- Timeline Tracker -->
            <div class="border rounded-xl p-4 mb-6">
              <h3 class="font-bold text-gray-700 text-sm mb-4">Delivery Progress</h3>
              
              <div v-if="orderDetails.timeline && orderDetails.timeline.length > 0">
                <div v-if="orderDetails.latest_location && ['picked_up', 'in_transit'].includes(orderDetails.status)" 
                     class="mb-5 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
                  <div class="flex items-center gap-2 text-sm text-blue-700">
                    <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                    <span class="font-medium">Driver is on the way</span>
                  </div>
                  <span class="text-xs text-blue-500">Updated {{ orderDetails.latest_location.updated_at }}</span>
                </div> 
                
                <div class="relative ml-3 border-l-2 border-gray-200 space-y-6">
                  <div v-for="(log, index) in [...orderDetails.timeline].reverse()" :key="log.id" class="relative pl-8">
                    <div class="absolute -left-[21px] top-0 w-4 h-4 rounded-full border-2 flex items-center justify-center"
                         :class="index === orderDetails.timeline.length - 1 ? 'bg-emerald-500 border-emerald-500' : 'bg-white border-gray-300'">
                      <svg v-if="index === orderDetails.timeline.length - 1" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-gray-800">{{ formatStatus(log.status) }}</p>
                      <p class="text-xs text-gray-400 mt-0.5">{{ new Date(log.created_at).toLocaleString() }}</p>
                      <p v-if="log.notes" class="text-xs text-gray-500 mt-1 italic bg-gray-50 px-2 py-1 rounded inline-block">📝 {{ log.notes }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Static Fallback -->
              <div v-else class="flex items-center">
                 <!-- Simplified static timeline for brevity, original logic preserved -->
                 <div class="text-center text-gray-400 text-sm w-full py-4">No tracking history available yet.</div>
              </div>
            </div>

            <!-- Financials -->
            <div class="bg-gray-900 text-white rounded-xl p-4 space-y-2">
              <div class="flex justify-between text-sm text-gray-300">
                <span>Subtotal</span>
                <span>{{ formatPrice(orderDetails.financials.subtotal) }}</span>
              </div>
              <div class="flex justify-between text-sm text-gray-300">
                <span>Delivery Fee</span>
                <span>{{ formatPrice(orderDetails.financials.delivery_fee) }}</span>
              </div>
              <div class="border-t border-gray-700 pt-2 mt-2 flex justify-between text-lg font-extrabold">
                <span>Total</span>
                <span>{{ formatPrice(orderDetails.financials.total_amount) }}</span>
              </div>
            </div>

          </div> 
        </div>
      </div>
    </div>

    <!-- The Report Modal Component -->
    <OrderReportModal
      :is-visible="showReportModal"
      :product-id="selectedProduct.id"
      :user-id="selectedProduct.seller_id"
      :product-name="selectedProduct.name"
      @close="showReportModal = false"
      @submitted="handleReportSuccess"
    />

  </div>
</template>

<style scoped>
/* Custom styling for stars if needed */
</style>