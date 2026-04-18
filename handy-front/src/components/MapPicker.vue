<!-- src/components/MapPicker.vue -->

<template>
  <div class="map-picker">
    <div v-if="!showMap" class="map-placeholder" @click="openMap">
      <div class="placeholder-content">
        <span class="map-icon">🗺️</span>
        <p class="placeholder-text">Click to select your exact delivery location on the map</p>
        <p v-if="centerLabel" class="center-label">Map will center on: {{ centerLabel }}</p>
      </div>
    </div>

    <div v-else class="map-active">
      <div ref="mapRef" class="leaflet-map"></div>
      
      <div class="map-toolbar">
        <div class="coords-display" v-if="selectedCoords">
          <span class="coord-icon">📍</span>
          <span class="coord-text">{{ selectedCoords.lat.toFixed(6) }}, {{ selectedCoords.lng.toFixed(6) }}</span>
        </div>
        <div v-else class="map-hint">
          <span>👆 Click anywhere on the map to place the pin</span>
        </div>
        
        <div class="map-actions">
          <button @click="useMyLocation" :disabled="locating" type="button" class="btn-gps">
            <span v-if="locating" class="spinner-small"></span>
            <span v-else>📍 Use My Location</span>
          </button>
          <button @click="resetView" type="button" class="btn-reset">🔄 Reset View</button>
          <button @click="closeMap" type="button" class="btn-close-map">✕ Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => null
  },
  centerLat: {
    type: Number,
    default: 9.025
  },
  centerLng: {
    type: Number,
    default: 38.747
  },
  zoom: {
    type: Number,
    default: 14
  },
  centerLabel: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

const mapRef = ref(null)
const showMap = ref(false)
const selectedCoords = ref(null)
const locating = ref(false)

let map = null
let marker = null

// Fix Leaflet default marker icons
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png'
})

function openMap() {
  showMap.value = true
  nextTick(() => {
    setTimeout(() => initMap(), 100)
  })
}

function closeMap() {
  showMap.value = false
  destroyMap()
}

function destroyMap() {
  if (map) {
    map.remove()
    map = null
    marker = null
  }
}

function initMap() {
  const center = selectedCoords.value 
    ? [selectedCoords.value.lat, selectedCoords.value.lng]
    : [props.centerLat, props.centerLng]

  map = L.map(mapRef.value, {
    center: center,
    zoom: props.zoom,
    zoomControl: true
  })

  // OpenStreetMap tiles (FREE)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map)

  // Click handler
  map.on('click', handleMapClick)

  // Add existing marker if coords exist
  if (selectedCoords.value) {
    addMarker(selectedCoords.value.lat, selectedCoords.value.lng)
  }

  // Force map to recalculate size
  setTimeout(() => map.invalidateSize(), 200)
}

function handleMapClick(e) {
  const { lat, lng } = e.latlng
  addMarker(lat, lng)
  selectedCoords.value = { lat, lng }
  emit('update:modelValue', { lat, lng })
}

function addMarker(lat, lng) {
  if (marker) {
    marker.setLatLng([lat, lng])
  } else {
    // Custom icon for better visibility
    const customIcon = L.divIcon({
      html: '<div style="background:#10b981; width:24px; height:24px; border-radius:50%; border:3px solid white; box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
      className: 'custom-marker',
      iconSize: [24, 24],
      iconAnchor: [12, 12]
    })
    
    marker = L.marker([lat, lng], { icon: customIcon }).addTo(map)
  }
}

function setView(lat, lng, zoom) {
  if (map) {
    map.setView([lat, lng], zoom || props.zoom)
    // Remove marker if view changed significantly
    // if (marker) {
    //   map.removeLayer(marker)
    //   marker = null
    //   selectedCoords.value = null
    //   emit('update:modelValue', null)
    // }
  }
}

async function useMyLocation() {
  if (!navigator.geolocation) {
    alert('Geolocation is not supported by your browser.')
    return
  }

  locating.value = true
  try {
    const position = await new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0
      })
    })

    const { latitude, longitude } = position.coords
    map.setView([latitude, longitude], 16)
    addMarker(latitude, longitude)
    selectedCoords.value = { lat: latitude, lng: longitude }
    emit('update:modelValue', { lat: latitude, lng: longitude })
  } catch (error) {
    let message = 'Could not get your location.'
    if (error.code === 1) message = 'Location permission denied. Please enable it in browser settings.'
    else if (error.code === 2) message = 'Location unavailable.'
    else if (error.code === 3) message = 'Location request timed out.'
    alert(message)
  } finally {
    locating.value = false
  }
}

function resetView() {
  if (map) {
    map.setView([props.centerLat, props.centerLng], props.zoom)
  }
}

// Watch for external center changes (when user selects different area)
watch(() => [props.centerLat, props.centerLng], ([newLat, newLng]) => {
  if (showMap.value && map) {
    setView(newLat, newLng, props.zoom)
  }
})

// Watch for external value changes
watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    selectedCoords.value = newVal
    if (showMap.value && map) {
      addMarker(newVal.lat, newVal.lng)
    }
  } else {
    selectedCoords.value = null
    if (marker && map) {
      map.removeLayer(marker)
      marker = null
    }
  }
})

onBeforeUnmount(() => {
  destroyMap()
})

defineExpose({ setView, openMap, closeMap })
</script>

<style scoped>
.map-picker {
  width: 100%;
}

.map-placeholder {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 32px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
  background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
}

.map-placeholder:hover {
  border-color: #10b981;
  background: linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%);
  transform: translateY(-1px);
}

.placeholder-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.map-icon {
  font-size: 2.5rem;
}

.placeholder-text {
  color: #374151;
  font-weight: 500;
  font-size: 0.95rem;
}

.center-label {
  color: #059669;
  font-size: 0.85rem;
  font-weight: 600;
  background: #d1fae5;
  padding: 4px 12px;
  border-radius: 20px;
}

.map-active {
  border: 2px solid #10b981;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
}

.leaflet-map {
  height: 300px;
  width: 100%;
  z-index: 1;
}

.map-toolbar {
  padding: 12px 16px;
  background: white;
  border-top: 1px solid #e5e7eb;
}

.coords-display {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
}

.coord-icon {
  font-size: 1.1rem;
}

.coord-text {
  font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
  font-size: 0.85rem;
  color: #059669;
  font-weight: 600;
  background: #d1fae5;
  padding: 4px 10px;
  border-radius: 6px;
}

.map-hint {
  text-align: center;
  color: #6b7280;
  font-size: 0.85rem;
  margin-bottom: 10px;
  padding: 6px;
  background: #f9fafb;
  border-radius: 6px;
}

.map-actions {
  display: flex;
  gap: 8px;
}

.btn-gps {
  flex: 1;
  padding: 8px 12px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.btn-gps:hover:not(:disabled) {
  background: #2563eb;
}

.btn-gps:disabled {
  background: #93c5fd;
  cursor: not-allowed;
}

.btn-reset {
  padding: 8px 12px;
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.8rem;
  cursor: pointer;
}

.btn-reset:hover {
  background: #e5e7eb;
}

.btn-close-map {
  padding: 8px 12px;
  background: #fee2e2;
  color: #dc2626;
  border: none;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-close-map:hover {
  background: #fecaca;
}

.spinner-small {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>