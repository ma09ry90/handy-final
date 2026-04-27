<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import api from '@/plugins/axios'
import MapPicker from '@/components/MapPicker.vue'

const router = useRouter()
const cartStore = useCartStore()
const authStore = useAuthStore()

// State
const addresses = ref([])
const selectedAddressId = ref(null)
const isProcessing = ref(false)
const error = ref(null)
const warning = ref(null)
const orderConfirmation = ref(null)

// ✅ NEW: Phone Number State
const buyerPhone = ref('')

// Administrative areas
const cities = ref([])
const subcities = ref([])
const woredas = ref([])
const loadingAreas = ref(false)

// New address form
const showNewAddress = ref(false)
const newAddress = ref({
  city_id: '',
  subcity_id: '',
  woreda_id: '',
  street: '',
  landmark: '',
  latitude: null,
  longitude: null
})

// Map state
const mapCoords = ref(null)

// Area coordinates for map centering
const areaCoordinates = {
  cities: {
    1: { lat: 9.0250, lng: 38.7469, name: 'Addis Ababa' },
    5: { lat: 6.5200, lng: 39.1900, name: 'Dire Dawa' },
    6: { lat: 7.6500, lng: 39.1100, name: 'Hawassa' },
    7: { lat: 8.5500, lng: 39.2700, name: 'Adama' },
    8: { lat: 11.1300, lng: 39.6300, name: 'Bahir Dar' },
  },
  subcities: {
    1: { lat: 9.0050, lng: 38.7450, name: 'Arada' },
    2: { lat: 9.0200, lng: 38.7700, name: 'Bole' },
    3: { lat: 9.0350, lng: 38.7300, name: 'Kirkos' },
    4: { lat: 8.9900, lng: 38.7500, name: 'Lideta' },
    5: { lat: 9.0400, lng: 38.7600, name: 'Yeka' },
  }
}

// Computed
const subtotal = computed(() => 
  cartStore.items.reduce((sum, item) => sum + (item.price * item.quantity), 0)
)

// ✅ BULLETPROOF: Finds the selected city object from the list
const selectedCity = computed(() => {
  if (!newAddress.value.city_id) return null
  return cities.value.find(c => c.id === parseInt(newAddress.value.city_id))
})

// ✅ BULLETPROOF: Checks the name "Jimma" or "ጂማ" regardless of Database ID
const isJimma = computed(() => {
  if (!selectedCity.value) return false
  const nameEn = (selectedCity.value.name_en || selectedCity.value.name || '').toLowerCase()
  const nameAm = (selectedCity.value.name_am || '').toLowerCase()
  return nameEn.includes('jimma') || nameAm.includes('ጂማ')
})

// ✅ BULLETPROOF: Skips subcity for Jimma
const skipSubcity = computed(() => {
  return isJimma.value || selectedCity.value?.skip_subcity === true
})

// ✅ BULLETPROOF: Centers map on Jimma if selected
const mapCenter = computed(() => {
  const woredaId = parseInt(newAddress.value.woreda_id)
  const subcityId = parseInt(newAddress.value.subcity_id)
  const cityId = parseInt(newAddress.value.city_id)
  
  if (woredaId && areaCoordinates.subcities[subcityId]) {
    const base = areaCoordinates.subcities[subcityId]
    return { lat: base.lat + (woredaId * 0.003), lng: base.lng + (woredaId * 0.002), name: base.name }
  }
  if (subcityId && areaCoordinates.subcities[subcityId]) {
    return areaCoordinates.subcities[subcityId]
  }
  
  // FORCE JIMMA COORDINATES IF SELECTED
  if (isJimma.value) {
    return { lat: 7.6733, lng: 36.8325, name: 'Jimma' }
  }
  
  if (cityId && areaCoordinates.cities[cityId]) {
    return areaCoordinates.cities[cityId]
  }
  return { lat: 9.0250, lng: 38.7469, name: 'Addis Ababa' }
})

const mapZoom = computed(() => {
  if (newAddress.value.woreda_id) return 16
  if (newAddress.value.subcity_id) return 14
  if (newAddress.value.city_id) return 12
  return 11
})

const canSubmitAddress = computed(() => {
  return newAddress.value.city_id && 
         newAddress.value.street.trim() && 
         mapCoords.value
})

const canPlaceOrder = computed(() => {
  return selectedAddressId.value && !isProcessing.value
})

// Lifecycle
onMounted(async () => {
  if (!authStore.isAuthenticated) return router.push('/login')
  await cartStore.fetchCart()
  if (cartStore.items.length === 0) return router.push('/cart')
  
  await fetchCities()
  await fetchAddresses()
})

// Methods
async function fetchCities() {
  try {
    const res = await api.get('/cities')
    cities.value = res.data.data || res.data || []
  } catch (e) {
    cities.value = [
      { id: 1, name_en: 'Addis Ababa', name_am: 'አዲስ አበባ' },
      { id: 99, name_en: 'Jimma', name_am: 'ጂማ' } // Fallback ID doesn't matter anymore
    ]
  }
}

async function fetchSubcities(cityId) {
  if (!cityId) { subcities.value = []; woredas.value = []; return }
  loadingAreas.value = true
  try {
    const res = await api.get(`/cities/${cityId}/subcities`)
    subcities.value = res.data.data || res.data || []
  } catch (e) {
    if (parseInt(cityId) === 1) {
      subcities.value = [
        { id: 1, name_en: 'Arada', name_am: 'አራዳ' },
        { id: 2, name_en: 'Bole', name_am: 'ቦሌ' }
      ]
    } else {
      subcities.value = []
    }
  } finally {
    loadingAreas.value = false
  }
}

async function fetchWoredasForCity(cityId) {
  loadingAreas.value = true
  try {
    const res = await api.get(`/cities/${cityId}/woredas`)
    woredas.value = res.data.data || res.data || []
  } catch (e) {
    woredas.value = Array.from({ length: 18 }, (_, i) => ({
      id: i + 1, name: `Woreda ${i + 1}`, name_am: `ወረዳ ${i + 1}`
    }))
  } finally {
    loadingAreas.value = false
  }
}

async function fetchWoredas(subcityId) {
  if (!subcityId) { woredas.value = []; return }
  loadingAreas.value = true
  try {
    const res = await api.get(`/subcities/${subcityId}/woredas`)
    woredas.value = res.data.data || res.data || []
  } catch (e) {
    woredas.value = Array.from({ length: 8 }, (_, i) => ({
      id: i + 1, name: `Woreda ${i + 1}`, name_am: `ወረዳ ${i + 1}`
    }))
  } finally {
    loadingAreas.value = false
  }
}

async function fetchAddresses() {
  try {
    const res = await api.get('/addresses')
    addresses.value = res.data.data || res.data || []
    if (addresses.value.length > 0) selectedAddressId.value = addresses.value[0].id
  } catch (e) { console.error("Failed to fetch addresses", e) }
}

// Watchers
watch(() => newAddress.value.city_id, (newVal) => {
  newAddress.value.subcity_id = ''
  newAddress.value.woreda_id = ''
  subcities.value = []
  woredas.value = []
  
  if (newVal) {
    if (skipSubcity.value) {
      fetchWoredasForCity(newVal) // JIMMA PATH
    } else {
      fetchSubcities(newVal)       // ADDIS ABABA PATH
    }
  }
})

watch(() => newAddress.value.subcity_id, (newVal) => {
  newAddress.value.woreda_id = ''
  if (newVal) fetchWoredas(newVal)
})

watch(mapCoords, (newVal) => {
  if (newVal) {
    newAddress.value.latitude = newVal.lat
    newAddress.value.longitude = newVal.lng
  } else {
    newAddress.value.latitude = null
    newAddress.value.longitude = null
  }
})

async function saveNewAddress() {
  error.value = null
  if (!newAddress.value.city_id) return error.value = "Please select a city"
  if (!newAddress.value.street.trim()) return error.value = "Please enter your street address"
  if (!mapCoords.value) return error.value = "Please select your exact location on the map"

  try {
    const payload = {
      city_id: newAddress.value.city_id,
      subcity_id: skipSubcity.value ? null : (newAddress.value.subcity_id || null),
      woreda_id: newAddress.value.woreda_id || null,
      street: newAddress.value.street,
      landmark: newAddress.value.landmark,
      latitude: mapCoords.value.lat,
      longitude: mapCoords.value.lng
    }

    const res = await api.post('/addresses', payload)
    const savedAddress = res.data.data || res.data
    addresses.value.push(savedAddress)
    selectedAddressId.value = savedAddress.id
    resetAddressForm()
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save address'
  }
}

function resetAddressForm() {
  newAddress.value = { city_id: '', subcity_id: '', woreda_id: '', street: '', landmark: '', latitude: null, longitude: null }
  mapCoords.value = null
  showNewAddress.value = false
  subcities.value = []
  woredas.value = []
}

async function placeOrder() {
  if (!selectedAddressId.value) return error.value = "Please select or add a delivery address."
// 2. ✅ NEW: Validate Phone Number
  const phoneRegex = /^(09|07)[0-9]{8}$/;
  if (!buyerPhone.value || !phoneRegex.test(buyerPhone.value)) {
    error.value = "Please enter a valid phone number (10 digits starting with 09 or 07).";
    return;
  }

  isProcessing.value = true
  error.value = null
  warning.value = null

  try {
    const res = await api.post('/orders/checkout', { delivery_address_id: selectedAddressId.value, buyer_phone: buyerPhone.value})
    const responseData = res.data
    
    if (responseData.orders && responseData.orders.length > 0) {
      // SUCCESS: Just show the modal. DO NOT touch the cart!
      orderConfirmation.value = responseData
    } else {
      // FIX: If no orders were returned for some reason, throw an error instead of clearing the cart
      throw new Error('Failed to create orders. No orders returned from server.')
    }
  } catch (e) {
    const errorData = e.response?.data
    error.value = errorData?.detail || errorData?.message || e.message || 'An error occurred during checkout.'
    
    // FIX: Only refresh the cart from the DB to sync UI. DO NOT clear it.
    if (errorData?.out_of_stock || errorData?.removed_count) {
      warning.value = errorData?.detail || 'Please review your cart.';
      await cartStore.fetchCart() 
    }
  } finally {
    isProcessing.value = false
  }
}

async function proceedToPayment() {
  try {
    const orderNumbers = orderConfirmation.value.orders.map(o => o.order_number);
    
    const res = await api.post('/payment/initiate', {
      order_ids: orderConfirmation.value.orders.map(o => o.order_id)
    });
    
    // Save the order numbers
    localStorage.setItem('pending_order_numbers', JSON.stringify(orderNumbers));
    
    window.location.href = res.data.checkout_url;
    
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to initiate payment.';
    orderConfirmation.value = null; 
  }
}

function formatAddress(addr) {
  if (!addr) return ''
  const parts = []
  if (addr.street) parts.push(addr.street)
  if (addr.city?.name_en) parts.push(addr.city.name_en)
  if (addr.subcity?.name_en) parts.push(addr.subcity.name_en)
  if (addr.woreda?.name) parts.push(`Woreda ${addr.woreda.name}`)
  if (addr.landmark) parts.push(`Near ${addr.landmark}`)
  return parts.join(', ')
}
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-10 min-h-screen bg-gray-50">
    
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Checkout</h1>
      <p class="text-gray-500 mt-1">{{ cartStore.items.length }} item(s) in your cart</p>
    </div>

    <!-- Error Display -->
    <div v-if="error" class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r flex items-start gap-3">
      <span class="text-red-500 text-lg">⚠️</span>
      <div>
        <p class="font-medium">{{ error }}</p>
        <!-- Display Out of Stock Details -->
        <ul v-if="warning && $route.query.stockError" class="mt-2 list-disc list-inside text-sm">
          <li v-for="item in $route.query.stockError" :key="item.cart_item_id">
            {{ item.product_name }} (Requested: {{ item.requested_qty }}, Available: {{ item.available_stock }})
          </li>
        </ul>
      </div>
      <button @click="error = null; warning = null" class="ml-auto text-red-400 hover:text-red-600">✕</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- LEFT COLUMN -->
      <div class="lg:col-span-2 space-y-6">

          <!-- New Address Form -->
          <div v-if="showNewAddress" class="bg-gray-50 p-5 rounded-lg border border-gray-200 space-y-4 mb-5">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- City -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">City / Region *</label>
                <select v-model="newAddress.city_id" class="w-full border border-gray-300 rounded-lg p-2.5 bg-white focus:ring-2 focus:ring-emerald-500">
                  <option value="">Select City</option>
                  <option v-for="city in cities" :key="city.id" :value="city.id">
                    {{ city.name_en || city.name }} {{ city.name_am ? `(${city.name_am})` : '' }}
                  </option>
                </select>
              </div>

              <!-- Subcity (HIDDEN FOR JIMMA) -->
              <div v-if="!skipSubcity">
                <label class="block text-sm font-medium text-gray-700 mb-1">Subcity / Zone</label>
                <select v-model="newAddress.subcity_id" :disabled="!newAddress.city_id || loadingAreas" class="w-full border border-gray-300 rounded-lg p-2.5 bg-white disabled:bg-gray-100 focus:ring-2 focus:ring-emerald-500">
                  <option value="">{{ loadingAreas ? 'Loading...' : 'Select Subcity' }}</option>
                  <option v-for="sub in subcities" :key="sub.id" :value="sub.id">
                    {{ sub.name_en || sub.name }} {{ sub.name_am ? `(${sub.name_am})` : '' }}
                  </option>
                </select>
              </div>

              <!-- Woreda -->
              <div :class="skipSubcity ? 'md:col-span-2' : ''">
                <label class="block text-sm font-medium text-gray-700 mb-1">Woreda</label>
                <select v-model="newAddress.woreda_id" :disabled="(!skipSubcity && !newAddress.subcity_id) || loadingAreas" class="w-full border border-gray-300 rounded-lg p-2.5 bg-white disabled:bg-gray-100 focus:ring-2 focus:ring-emerald-500">
                  <option value="">{{ loadingAreas ? 'Loading...' : 'Select Woreda' }}</option>
                  <option v-for="w in woredas" :key="w.id" :value="w.id">
                    {{ w.name || `Woreda ${w.id}` }} {{ w.name_am ? `(${w.name_am})` : '' }}
                  </option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Street Address *</label>
                <input v-model="newAddress.street" type="text" placeholder="e.g., Bole Road, Near Edna Mall" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-emerald-500">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nearby Landmark</label>
                <input v-model="newAddress.landmark" type="text" placeholder="e.g., Behind Shola Market" class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-emerald-500">
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Exact Delivery Location * <span class="text-gray-400 font-normal">(Click map)</span></label>
              <div v-if="mapCenter.name" class="mb-2 text-xs text-emerald-600 bg-emerald-50 inline-block px-2 py-1 rounded">🗺️ Centered on: {{ mapCenter.name }}</div>
              
              <MapPicker v-model="mapCoords" :center-lat="mapCenter.lat" :center-lng="mapCenter.lng" :zoom="mapZoom" />
              
              <p v-if="!mapCoords" class="mt-2 text-xs text-amber-600">⚠️ Please click on the map to mark your exact delivery point</p>
              <p v-else class="mt-2 text-xs text-emerald-600">✅ Location selected: {{ mapCoords.lat.toFixed(6) }}, {{ mapCoords.lng.toFixed(6) }}</p>
            </div>

            <div class="flex justify-end pt-2">
              <button @click="saveNewAddress" :disabled="!canSubmitAddress" class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white px-8 py-2.5 rounded-lg font-semibold transition">
                💾 Save Address
              </button>
            </div>
          </div>

          <!-- Address List -->
          <div v-if="addresses.length === 0 && !showNewAddress" class="text-center py-8 text-gray-400">
            <span class="text-4xl block mb-3">📍</span>
            <p class="font-medium">No saved addresses</p>
          </div>
          
          <div v-else class="space-y-3">
            <label v-for="addr in addresses" :key="addr.id" class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition-all"
              :class="selectedAddressId === addr.id ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:bg-gray-50'">
              <input type="radio" :value="addr.id" v-model="selectedAddressId" class="mt-1 w-4 h-4 text-emerald-600">
              <div class="flex-1">
                <p class="font-semibold text-gray-800">{{ addr.street }}</p>
                <p class="text-sm text-gray-500 mt-0.5">{{ formatAddress(addr) }}</p>
                <div v-if="addr.latitude && addr.longitude" class="mt-1 text-xs text-gray-400">📍 {{ addr.latitude.toFixed(4) }}, {{ addr.longitude.toFixed(4) }}</div>
              </div>
              <span v-if="selectedAddressId === addr.id" class="bg-emerald-100 text-emerald-700 text-xs font-bold px-2 py-1 rounded">SELECTED</span>
            </label>
          </div>
        </div>
        <!-- Phone Number Input Section -->
<div class="card mb-3">
  <div class="card-body">
    <h5 class="card-title mb-3">Contact Details</h5>
    
    <div class="mb-0">
      <label for="buyer_phone" class="form-label fw-bold">
        Phone Number <span class="text-danger">*</span>
      </label>
      <input 
        type="tel" 
        class="form-control" 
        id="buyer_phone"
        v-model="buyerPhone"
        placeholder="09XXXXXXXX"
        maxlength="10"
        :disabled="isProcessing"
      >
      <div class="form-text text-muted small mt-1">
        The delivery person will call this number upon arrival.
      </div>
    </div>
  </div>
</div>

<!-- Existing Place Order Button Section -->
<div class="d-grid gap-2 mt-4">
  <button 
    class="btn btn-primary btn-lg" 
    @click="placeOrder" 
    :disabled="isProcessing"
  >
    <span v-if="isProcessing" class="spinner-border spinner-border-sm me-2"></span>
    {{ isProcessing ? 'Processing...' : 'Place Order' }}
  </button>
  
  <!-- Error Display -->
  <div v-if="error" class="alert alert-danger mt-3 mb-0">
    {{ error }}
  </div>

        <!-- Section 2: Order Items -->
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
          <div class="flex items-center gap-2 mb-5">
            <span class="w-7 h-7 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-sm font-bold">2</span>
            <h2 class="text-xl font-bold text-gray-900">Order Items</h2>
          </div>
          
          <div class="divide-y divide-gray-100">
            <div v-for="item in cartStore.items" :key="item.version_id || item.id" class="flex items-center justify-between py-4">
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                  <span class="text-gray-600 font-bold text-sm">{{ item.quantity }}×</span>
                </div>
                <div>
                  <p class="font-medium text-gray-800">{{ item.name }}</p>
                  <p v-if="item.seller_name" class="text-xs text-gray-400">by {{ item.seller_name }}</p>
                </div>
              </div>
              <span class="font-semibold text-gray-900 whitespace-nowrap">{{ (item.price * item.quantity).toLocaleString() }} ETB</span>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm sticky top-24">
          <h2 class="text-xl font-bold text-gray-900 mb-5">Order Summary</h2>
          
          <div class="space-y-3 mb-5">
            <div class="flex justify-between text-gray-600">
              <span>Subtotal ({{ cartStore.items.length }} items)</span>
              <span class="font-medium">{{ subtotal.toLocaleString() }} ETB</span>
            </div>
            <div class="flex justify-between text-gray-600">
              <span>Delivery Fee</span>
              <span class="text-sm text-gray-400">Calculated per seller</span>
            </div>
          </div>
          
          <div class="border-t border-gray-200 pt-4 mb-6">
            <p class="text-xs text-gray-400 mb-1">Estimated Total (excl. delivery)</p>
            <div class="flex justify-between text-lg font-bold text-gray-900">
              <span>Total</span>
              <span>{{ subtotal.toLocaleString() }} ETB</span>
            </div>
          </div>

          <div v-if="selectedAddressId" class="mb-5 p-3 bg-gray-50 rounded-lg">
            <p class="text-xs text-gray-500 mb-1">Delivering to:</p>
            <p class="text-sm font-medium text-gray-700">{{ formatAddress(addresses.find(a => a.id === selectedAddressId)) }}</p>
          </div>

          <button @click="placeOrder" :disabled="!canPlaceOrder" class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white font-bold py-4 rounded-lg shadow-md transition-all flex items-center justify-center gap-2">
            <svg v-if="isProcessing" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            <span v-if="isProcessing">Calculating Delivery...</span>
            <span v-else>Place Order</span>
          </button>
          
          <div class="mt-4 flex items-center justify-center gap-1 text-xs text-gray-400">
            <span>🔒</span><span>Secure checkout</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ================================================================== -->
    <!-- NEW: ORDER BREAKDOWN MODAL (Shows before redirecting to Payment) -->
    <!-- ================================================================== -->
    <div v-if="orderConfirmation" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-0 overflow-hidden">
        
        <!-- Header -->
        <div class="bg-emerald-600 text-white p-6 text-center">
          <div class="text-4xl mb-2">✅</div>
          <h3 class="text-2xl font-bold">Orders Created!</h3>
          <p class="text-emerald-100 text-sm mt-1">Review your order breakdown before payment</p>
        </div>

        <!-- Body: Breakdown -->
        <div class="p-6 max-h-[60vh] overflow-y-auto space-y-4">
          
          <!-- Loop through orders (1 per seller) -->
          <div v-for="order in orderConfirmation.orders" :key="order.order_id" class="border border-gray-200 rounded-xl p-4">
            <div class="flex justify-between items-start mb-3">
              <div>
                <p class="font-bold text-gray-800">{{ order.seller_name }}</p>
                <p class="text-xs text-gray-400 font-mono">{{ order.order_number }}</p>
              </div>
              <span class="text-xs bg-emerald-50 text-emerald-700 px-2 py-1 rounded-full font-semibold">{{ order.item_count }} Items</span>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-3 space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Subtotal</span>
                <span class="text-gray-800 font-medium">{{ order.subtotal.toLocaleString() }} ETB</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Delivery ({{ order.distance_km }} km)</span>
                <span class="text-gray-800 font-medium">{{ order.delivery_fee.toLocaleString() }} ETB</span>
              </div>
              <div class="border-t border-gray-200 pt-2 mt-2 flex justify-between">
                <span class="text-gray-700 font-bold">Order Total</span>
                <span class="text-emerald-700 font-bold">{{ order.total_amount.toLocaleString() }} ETB</span>
              </div>
            </div>
          </div>

          <!-- Grand Total -->
          <div class="bg-gray-900 text-white rounded-xl p-4 flex justify-between items-center">
            <span class="text-lg font-bold">Grand Total</span>
            <span class="text-2xl font-extrabold">{{ orderConfirmation.grand_total.toLocaleString() }} ETB</span>
          </div>

        </div>

        <!-- Footer Actions -->
        <div class="p-6 bg-gray-50 border-t border-gray-200 flex gap-3">
          <button @click="orderConfirmation = null" class="flex-1 border border-gray-300 text-gray-700 py-3 rounded-xl font-semibold hover:bg-white transition">
            Cancel
          </button>
          <button @click="proceedToPayment" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-xl font-bold shadow-lg transition flex items-center justify-center gap-2">
            <span>💳</span> Proceed to Payment
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.5rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
}
input[type="radio"] { accent-color: #059669; }
* { transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }
</style>