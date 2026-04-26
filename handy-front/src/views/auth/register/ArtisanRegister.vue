<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import api from '@/plugins/axios';

const router = useRouter();
const authStore = useAuthStore();
const { t, locale } = useI18n();

const form = ref({
    first_name: '', last_name: '', gender: '', email: '', phone_number: '', birthdate: '',
    password: '', password_confirmation: '',
    
    // Shop Info
    shop_name_en: '', shop_name_am: '', shop_name_or: '',
    description_en: '', description_am: '', description_or: '',
    slang: '', shop_logo: null,
    business_license_number: '', tax_id: '',
    bank_name: '', bank_account_name: '', bank_account_number: '',
    
    // Documents
    identity_document: null, business_license_document: null, tax_registration_document: null,

    // Address Fields
    city_id: '',
    subcity_id: '',
    woreda_id: '',
    street: '',
    landmark: '',

    // Map Coordinates
    latitude: null,
    longitude: null,

    // Terms
    agree_terms: false,
});

// Address Logic
const cities = ref([]);
const subcities = ref([]);
const woredas = ref([]);

// Map refs
const mapContainer = ref(null);
let map = null;
let marker = null;
const geoLocateMsg = ref('');

// Default center (Addis Ababa)
const defaultLat = 9.0250;
const defaultLng = 38.7469;

// ============================================
// PREDEFINED COORDINATES FOR ETHIOPIAN CITIES
// ============================================
const cityCoords = {
    'addis ababa': { lat: 9.0250, lng: 38.7469, zoomWithSubcities: 12, zoomWithoutSubcities: 13 },
    'adir dar': { lat: 11.5760, lng: 37.3908, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'bahir dar': { lat: 11.5760, lng: 37.3908, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'hawassa': { lat: 7.0603, lng: 38.4776, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'jimma': { lat: 7.6694, lng: 36.8350, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'mekelle': { lat: 13.4967, lng: 39.4753, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    //'mek'ele': { lat: 13.4967, lng: 39.4753, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'dire dawa': { lat: 9.5937, lng: 41.8661, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'adama': { lat: 8.5414, lng: 39.2703, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'gondar': { lat: 12.6000, lng: 37.4500, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'dessie': { lat: 11.1361, lng: 39.6394, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'debre markos': { lat: 10.3380, lng: 37.7244, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'debre birhan': { lat: 9.6810, lng: 39.5140, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'shashamane': { lat: 7.2040, lng: 38.5950, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'arbaminch': { lat: 6.0333, lng: 37.5500, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'harar': { lat: 9.3138, lng: 42.1181, zoomWithSubcities: 12, zoomWithoutSubcities: 15 },
    'jijiga': { lat: 9.3500, lng: 42.8000, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
    'nekemte': { lat: 9.0819, lng: 36.5561, zoomWithSubcities: 12, zoomWithoutSubcities: 14 },
};

// PREDEFINED COORDINATES FOR ADDIS ABABA SUBCITIES
const subcityCoords = {
    'addis ketema': { lat: 9.0200, lng: 38.7420 },
    'lideta': { lat: 9.0150, lng: 38.7350 },
    'kirkos': { lat: 9.0150, lng: 38.7550 },
    'arada': { lat: 9.0300, lng: 38.7500 },
    'yeka': { lat: 9.0400, lng: 38.7900 },
    'bole': { lat: 8.9800, lng: 38.7900 },
    'nifas silk-lafto': { lat: 8.9800, lng: 38.7300 },
    'kolfe keranio': { lat: 8.9500, lng: 38.7100 },
    'akaki kality': { lat: 8.9000, lng: 38.7800 },
    'gulele': { lat: 9.0500, lng: 38.7200 },
    'chafe': { lat: 8.9600, lng: 38.7600 },
};

// Helper: Get coordinates for a city
const getCityCoords = (city, hasSubcities) => {
    // 1. Try API-provided coordinates first
    if (city.latitude && city.longitude) {
        return {
            lat: parseFloat(city.latitude),
            lng: parseFloat(city.longitude),
            zoom: hasSubcities ? 12 : 14
        };
    }

    // 2. Try matching by name (English, Amharic, Oromo)
    const nameEn = (city.name_en || city.name || '').toLowerCase().trim();
    const nameAm = (city.name_am || '').toLowerCase().trim();
    const nameOr = (city.name_or || '').toLowerCase().trim();

    for (const [key, coords] of Object.entries(cityCoords)) {
        if (nameEn.includes(key) || nameAm === key || nameOr === key || key.includes(nameEn)) {
            return {
                lat: coords.lat,
                lng: coords.lng,
                zoom: hasSubcities ? coords.zoomWithSubcities : coords.zoomWithoutSubcities
            };
        }
    }

    // 3. Fallback to Addis Ababa
    return { lat: defaultLat, lng: defaultLng, zoom: hasSubcities ? 12 : 13 };
};

// Helper: Get coordinates for a subcity
const getSubcityCoords = (subcity) => {
    // 1. Try API-provided coordinates first
    if (subcity.latitude && subcity.longitude) {
        return {
            lat: parseFloat(subcity.latitude),
            lng: parseFloat(subcity.longitude),
            zoom: 15
        };
    }

    // 2. Try matching by name
    const nameEn = (subcity.name_en || subcity.name || '').toLowerCase().trim();
    const nameAm = (subcity.name_am || '').toLowerCase().trim();
    const nameOr = (subcity.name_or || '').toLowerCase().trim();

    for (const [key, coords] of Object.entries(subcityCoords)) {
        if (nameEn.includes(key) || nameAm === key || nameOr === key || key.includes(nameEn)) {
            return { lat: coords.lat, lng: coords.lng, zoom: 15 };
        }
    }

    // 3. Fallback: slightly offset from city center
    return { lat: defaultLat + 0.01, lng: defaultLng + 0.01, zoom: 14 };
};

// Fetch Cities on Load
onMounted(async () => {
    try {
        const res = await api.get('/cities');
        cities.value = res.data;
    } catch (e) { console.error(e); }

    // Initialize map after DOM is ready
    await nextTick();
    initMap();
});

const initMap = () => {
    if (!mapContainer.value) return;

    // Initialize Leaflet map
    map = L.map(mapContainer.value).setView([defaultLat, defaultLng], 13);

    // Add tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    // Add draggable marker
    const defaultIcon = L.icon({
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    marker = L.marker([defaultLat, defaultLng], { 
        icon: defaultIcon,
        draggable: true 
    }).addTo(map);

    // Set initial coordinates
    form.value.latitude = defaultLat;
    form.value.longitude = defaultLng;

    // Click on map to place marker
    map.on('click', function(e) {
        const { lat, lng } = e.latlng;
        marker.setLatLng([lat, lng]);
        form.value.latitude = lat;
        form.value.longitude = lng;
        updateCoordinateDisplay();
    });

    // Drag marker to update coordinates
    marker.on('dragend', function(e) {
        const { lat, lng } = e.target.getLatLng();
        form.value.latitude = lat;
        form.value.longitude = lng;
        updateCoordinateDisplay();
    });
};

const updateCoordinateDisplay = () => {
    form.value.latitude = parseFloat(form.value.latitude.toFixed(6));
    form.value.longitude = parseFloat(form.value.longitude.toFixed(6));
};

const flyToLocation = (lat, lng, zoom) => {
    if (!map || !marker) return;
    map.flyTo([lat, lng], zoom, { duration: 1.5 });
    marker.setLatLng([lat, lng]);
    form.value.latitude = parseFloat(lat.toFixed(6));
    form.value.longitude = parseFloat(lng.toFixed(6));
};

const locateMe = () => {
    geoLocateMsg.value = '';

    // Check if geolocation is supported
    if (!navigator.geolocation) {
        geoLocateMsg.value = 'Geolocation is not supported by your browser. Please click on the map to set your location.';
        return;
    }

    // Check for secure context (HTTPS or localhost)
    if (!window.isSecureContext) {
        geoLocateMsg.value = 'Location access requires a secure connection (HTTPS). Please click on the map to set your location manually.';
        return;
    }

    // Show loading state
    geoLocateMsg.value = 'Detecting your location...';

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            flyToLocation(lat, lng, 16);
            geoLocateMsg.value = '';
        },
        (error) => {
            let msg = 'Could not get your location. ';
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    msg += 'Location permission was denied. Please click on the map to set your location.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    msg += 'Location information unavailable. Please click on the map to set your location.';
                    break;
                case error.TIMEOUT:
                    msg += 'Location request timed out. Please click on the map to set your location.';
                    break;
                default:
                    msg += 'Please click on the map to set your location.';
            }
            geoLocateMsg.value = msg;
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000
        }
    );
};
const isSecureOrigin = ref(window.isSecureContext);

const resetMap = () => {
    if (map && marker) {
        flyToLocation(defaultLat, defaultLng, 13);
        geoLocateMsg.value = '';
    }
};

// ============================================
// WATCH CITY CHANGE
// ============================================
watch(() => form.value.city_id, async (newVal) => {
    form.value.subcity_id = null;
    form.value.woreda_id = null;
    subcities.value = [];
    woredas.value = [];

    if (!newVal) return;

    try {
        const [subRes, woredaRes] = await Promise.all([
            api.get(`/cities/${newVal}/subcities`),
            api.get(`/cities/${newVal}/woredas`)
        ]);

        subcities.value = subRes.data;
        woredas.value = woredaRes.data;

        const selectedCity = cities.value.find(c => c.id == newVal);
        if (selectedCity) {
            const hasSubcities = subcities.value.length > 0;
            const coords = getCityCoords(selectedCity, hasSubcities);
            flyToLocation(coords.lat, coords.lng, coords.zoom);
        }
    } catch (e) {
        console.error("Error loading location data", e);
    }
});

// ============================================
// WATCH SUBCITY CHANGE
// ============================================
watch(() => form.value.subcity_id, async (newVal) => {
    form.value.woreda_id = null;
    woredas.value = [];

    if (!newVal) return;

    try {
        const res = await api.get(`/cities/${form.value.city_id}/woredas?subcity_id=${newVal}`);
        woredas.value = res.data;

        const selectedSubcity = subcities.value.find(s => s.id == newVal);
        if (selectedSubcity) {
            const coords = getSubcityCoords(selectedSubcity);
            flyToLocation(coords.lat, coords.lng, coords.zoom);
        }
    } catch (e) {
        console.error("Error loading woredas", e);
    }
});

const errors = ref({});
const isLoading = ref(false);

const handleFileChange = (event, fieldName) => {
    const file = event.target.files[0];
    if (file) { form.value[fieldName] = file; }
};

const submit = async () => {
    isLoading.value = true;
    errors.value = {};

    if (!form.value.shop_name_en && !form.value.shop_name_am && !form.value.shop_name_or) {
        errors.value.shop_name = [t('validation.at_least_one_language')];
        isLoading.value = false;
        window.scrollTo(0, 0);
        return;
    }

    if (!form.value.agree_terms) {
        errors.value.agree_terms = [t('validation.agree_terms_required')];
        isLoading.value = false;
        window.scrollTo(0, 0);
        return;
    }

    if (!form.value.latitude || !form.value.longitude) {
        errors.value.map_location = [t('validation.pick_location_required')];
        isLoading.value = false;
        window.scrollTo(0, 0);
        return;
    }
    
    const formData = new FormData();
    
    formData.append('first_name', form.value.first_name);
    formData.append('last_name', form.value.last_name);
    formData.append('gender', form.value.gender);
    formData.append('email', form.value.email);
    formData.append('phone_number', form.value.phone_number);
    formData.append('birthdate', form.value.birthdate);
    formData.append('password', form.value.password);
    formData.append('password_confirmation', form.value.password_confirmation);
    formData.append('locale', locale.value);
    
    formData.append('shop_name', form.value.shop_name_en); 
    formData.append('shop_description', form.value.description_en);
    if (form.value.shop_name_en) formData.append('shop_name_en', form.value.shop_name_en);
    if (form.value.shop_name_am) formData.append('shop_name_am', form.value.shop_name_am);
    if (form.value.shop_name_or) formData.append('shop_name_or', form.value.shop_name_or);
    if (form.value.description_en) formData.append('description_en', form.value.description_en);
    if (form.value.description_am) formData.append('description_am', form.value.description_am);
    if (form.value.description_or) formData.append('description_or', form.value.description_or);
    
    formData.append('slang', form.value.slang);
    formData.append('business_license_number', form.value.business_license_number);
    formData.append('tax_id', form.value.tax_id);
    formData.append('bank_name', form.value.bank_name);
    formData.append('bank_account_name', form.value.bank_account_name);
    formData.append('bank_account_number', form.value.bank_account_number);

    formData.append('city_id', form.value.city_id);
    if (form.value.subcity_id) formData.append('subcity_id', form.value.subcity_id);
    if (form.value.woreda_id) formData.append('woreda_id', form.value.woreda_id);
    formData.append('street', form.value.street);
    formData.append('landmark', form.value.landmark);

    formData.append('latitude', form.value.latitude);
    formData.append('longitude', form.value.longitude);

    if (form.value.shop_logo) formData.append('shop_logo', form.value.shop_logo);
    if (form.value.identity_document) formData.append('identity_document', form.value.identity_document);
    if (form.value.business_license_document) formData.append('business_license_document', form.value.business_license_document);
    if (form.value.tax_registration_document) formData.append('tax_registration_document', form.value.tax_registration_document);

    try {
        const response = await api.post('/register/artisan', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        alert(response.data.message || t('common.success_create'));
        router.push('/login');
        
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
            console.log("--- VALIDATION FAILED ---", errors.value);
            window.scrollTo(0, 0);
        } else {
            console.error(error);
            alert(t('common.error_occurred'));
        }
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <head><!-- Must be the FIRST thing in <head> -->
        <meta charset="UTF-8">
    </head>
  <div class="min-h-screen bg-white flex flex-col items-center justify-center py-12 px-4">
    <div class="w-full max-w-3xl bg-white p-8 rounded-lg border border-gray-200 shadow-sm">
      
      <!-- Header -->
      <div class="mb-8 text-center border-b pb-6">
        <h1 class="text-3xl font-bold text-green-600">{{ $t('artisan.register_title') }}</h1>
        <p class="text-gray-500 mt-2">{{ $t('artisan.register_desc') }}</p>
      </div>

      <!-- GLOBAL ERROR BOX -->
      <div v-if="Object.keys(errors).length > 0" class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded">
          <div class="flex">
              <div class="ml-3">
                  <h3 class="text-sm font-medium text-red-800">
                      Please correct the following errors:
                  </h3>
                  <div class="mt-2 text-sm text-red-700">
                      <ul class="list-disc list-inside space-y-1">
                          <li v-for="(errorArray, field) in errors" :key="field">
                              <span class="font-semibold capitalize">{{ field.replace('_', ' ') }}</span>: {{ errorArray.join(', ') }}
                          </li>
                      </ul>
                  </div>
              </div>
          </div>
      </div>

      <form @submit.prevent="submit" class="flex flex-col gap-8">
        
        <!-- Section 1: Personal Info -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">{{ $t('artisan.personal_info') }}</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('user.first_name') }} *</label>
                    <input type="text" v-model="form.first_name" required 
                        class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.first_name}">
                    <p v-if="errors.first_name" class="text-red-500 text-xs mt-1">{{ errors.first_name[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('user.last_name') }} *</label>
                    <input type="text" v-model="form.last_name" required 
                        class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.last_name}">
                    <p v-if="errors.last_name" class="text-red-500 text-xs mt-1">{{ errors.last_name[0] }}</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('user.gender') }} *</label>
                <div class="flex items-center space-x-6 mt-1">
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" v-model="form.gender" value="male" name="gender_artisan"
                            class="w-4 h-4 text-emerald-600 bg-gray-100 border-gray-300 focus:ring-emerald-500">
                        <span class="ml-2 text-sm text-gray-700">{{ $t('gender.male') }}</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="radio" v-model="form.gender" value="female" name="gender_artisan"
                            class="w-4 h-4 text-emerald-600 bg-gray-100 border-gray-300 focus:ring-emerald-500">
                        <span class="ml-2 text-sm text-gray-700">{{ $t('gender.female') }}</span>
                    </label>
                </div>
                <p v-if="errors.gender" class="text-red-500 text-xs mt-1">{{ errors.gender[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('user.email') }} *</label>
                <input type="email" v-model="form.email" required 
                    class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-500"
                    :class="{'border-red-500': errors.email}">
                <p v-if="errors.email" class="text-red-500 text-xs mt-1">{{ errors.email[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('user.phone') }} *</label>
                <input type="tel" v-model="form.phone_number" required 
                    class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-green-500"
                    :class="{'border-red-500': errors.phone_number}">
                <p v-if="errors.phone_number" class="text-red-500 text-xs mt-1">{{ errors.phone_number[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('user.birthdate') }} *</label>
                <input type="date" v-model="form.birthdate" required 
                    class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                    :class="{'border-red-500': errors.birthdate}">
                <p v-if="errors.birthdate" class="text-red-500 text-xs mt-1">{{ errors.birthdate[0] }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('user.password') }} *</label>
                    <input type="password" v-model="form.password" required 
                        class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.password}">
                    <p v-if="errors.password" class="text-red-500 text-xs mt-1">{{ errors.password[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('user.password_confirm') }} *</label>
                    <input type="password" v-model="form.password_confirmation" required 
                        class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.password_confirmation}">
                </div>
            </div>
        </div>

        <!-- Section 2: Shop Details -->
        <div class="space-y-6">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">{{ $t('artisan.shop_details') }}</h3>
            
            <div class="p-4 border rounded bg-gray-50" :class="{'border-red-500': errors.shop_name_en || errors.description_en}">
                <h4 class="font-bold text-gray-700 mb-3">English</h4>
                <input type="text" v-model="form.shop_name_en" placeholder="Shop Name" 
                    class="w-full border p-2 rounded mb-2" :class="{'border-red-500': errors.shop_name_en}">
                <textarea v-model="form.description_en" rows="2" placeholder="Description" class="w-full border p-2 rounded"></textarea>
            </div>
            
            <div class="p-4 border rounded bg-gray-50">
                <h4 class="font-bold text-gray-700 mb-3">አማርኛ</h4>
                <input lang="am" type="text" v-model="form.shop_name_am" placeholder="የመደብ ስም" class="w-full border p-2 rounded mb-2">
                <textarea v-model="form.description_am" rows="2" placeholder="መግለጫ" class="w-full border p-2 rounded"></textarea>
            </div>
            
            <div class="p-4 border rounded bg-gray-50">
                <h4 class="font-bold text-gray-700 mb-3">Afaan Oromoo</h4>
                <input type="text" v-model="form.shop_name_or" placeholder="Maqaa Dukaalaa" class="w-full border p-2 rounded mb-2">
                <textarea v-model="form.description_or" rows="2" placeholder="Ibsa" class="w-full border p-2 rounded"></textarea>
            </div>
            <p v-if="errors.shop_name" class="text-red-500 text-xs mt-1">{{ errors.shop_name[0] }}</p>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('artisan.slang') }} *</label>
                <input type="text" v-model="form.slang" required 
                    placeholder="e.g., Handmade Crafts, Traditional Jewelry, Woodwork..."
                    class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500"
                    :class="{'border-red-500': errors.slang}">
                <p class="text-xs text-gray-500 mt-1">Brief category or tagline that describes your craftsmanship</p>
                <p v-if="errors.slang" class="text-red-500 text-xs mt-1">{{ errors.slang[0] }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium">Logo</label>
                    <input type="file" @change="handleFileChange($event, 'shop_logo')" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100 cursor-pointer" />
                </div>
            </div>
        </div>

        <!-- Section 3: PICKUP LOCATION -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">{{ $t('artisan.pickup_location') }}</h3>
            <p class="text-xs text-gray-500">{{ $t('artisan.pickup_location_desc') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ $t('address.city') }} *</label>
                    <select v-model="form.city_id" required 
                        class="mt-1 w-full border p-2 rounded bg-white focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.city_id}">
                        <option value="" disabled>Select City</option>
                        <option v-for="c in cities" :key="c.id" :value="c.id">
                            {{ c['name_' + locale] || c.name }}
                        </option>
                    </select>
                    <p v-if="errors.city_id" class="text-red-500 text-xs mt-1">{{ errors.city_id[0] }}</p>
                </div>
                <div v-if="form.city_id && subcities.length > 0">
                    <label class="block text-sm font-medium text-gray-700">{{ $t('address.subcity') }} *</label>
                    <select v-model="form.subcity_id" required class="mt-1 w-full border p-2 rounded bg-white focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.subcity_id}">
                        <option value="" disabled>Select Subcity</option>
                        <option v-for="s in subcities" :key="s.id" :value="s.id">
                            {{ s['name_' + locale] || s.name }}
                        </option>
                    </select>
                    <p v-if="errors.subcity_id" class="text-red-500 text-xs mt-1">{{ errors.subcity_id[0] }}</p>
                </div>

                <div v-if="form.city_id && woredas.length > 0">
                    <label class="block text-sm font-medium text-gray-700">{{ $t('address.woreda') }} *</label>
                    <select v-model="form.woreda_id" required class="mt-1 w-full border p-2 rounded bg-white focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.woreda_id}">
                        <option value="" disabled>Select Woreda</option>
                        <option v-for="w in woredas" :key="w.id" :value="w.id">
                            {{ w['name_' + locale] || w.name }}
                        </option>
                    </select>
                    <p v-if="errors.woreda_id" class="text-red-500 text-xs mt-1">{{ errors.woreda_id[0] }}</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('address.street') }} *</label>
                <input type="text" v-model="form.street" required 
                    class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500"
                    :class="{'border-red-500': errors.street}">
                <p v-if="errors.street" class="text-red-500 text-xs mt-1">{{ errors.street[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('address.landmark') }}</label>
                <input type="text" v-model="form.landmark" class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500">
            </div>

            <!-- MAP SECTION -->
            <div class="space-y-3" :class="{'border border-red-500 rounded-lg p-3': errors.latitude || errors.longitude || errors.map_location}">
                <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-gray-700">
                    <span class="text-red-500">*</span> Pin Your Exact Pickup Location on Map
                </label>
                <div class="flex gap-2">
                    <!-- Only show Locate Me on localhost or HTTPS -->
                    <button v-if="isSecureOrigin" type="button" @click="locateMe" 
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 rounded-full hover:bg-green-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Locate Me
                    </button>
                    <button type="button" @click="resetMap" 
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Reset
                    </button>
                </div>
            </div>

                <p class="text-xs text-gray-500">Click on the map or drag the marker to set your exact pickup location. Map auto-adjusts when you select city/subcity.</p>
                
                <!-- Geolocation Message -->
                <div v-if="geoLocateMsg" class="flex items-start gap-2 p-2.5 bg-amber-50 border border-amber-200 rounded-lg">
                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    <span class="text-xs text-amber-700">{{ geoLocateMsg }}</span>
                </div>

                <!-- Map Container -->
                <div ref="mapContainer" class="w-full h-[300px] md:h-[350px] rounded-lg border-2 border-gray-200 z-0"></div>
                
                <!-- Coordinate Display -->
                <div class="flex flex-wrap gap-4 mt-2">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded font-mono text-xs">
                            Lat: {{ form.latitude || '9.025000' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded font-mono text-xs">
                            Lng: {{ form.longitude || '38.746900' }}
                        </span>
                    </div>
                </div>
                
                <p v-if="errors.latitude" class="text-red-500 text-xs mt-1">{{ errors.latitude[0] }}</p>
                <p v-if="errors.longitude" class="text-red-500 text-xs mt-1">{{ errors.longitude[0] }}</p>
                <p v-if="errors.map_location" class="text-red-500 text-xs mt-1">{{ errors.map_location[0] }}</p>
            </div>
        </div>

        <!-- Section 4: Business & Bank Info -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">{{ $t('artisan.business_bank') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">License # *</label>
                    <input type="text" v-model="form.business_license_number" required 
                        class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.business_license_number}">
                    <p v-if="errors.business_license_number" class="text-red-500 text-xs mt-1">{{ errors.business_license_number[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tax ID *</label>
                    <input type="text" v-model="form.tax_id" required 
                        class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.tax_id}">
                    <p v-if="errors.tax_id" class="text-red-500 text-xs mt-1">{{ errors.tax_id[0] }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Bank *</label>
                    <input type="text" v-model="form.bank_name" required 
                        class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.bank_name}">
                     <p v-if="errors.bank_name" class="text-red-500 text-xs mt-1">{{ errors.bank_name[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Acc Name *</label>
                    <input type="text" v-model="form.bank_account_name" required 
                        class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.bank_account_name}">
                    <p v-if="errors.bank_account_name" class="text-red-500 text-xs mt-1">{{ errors.bank_account_name[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Acc Number *</label>
                    <input type="text" v-model="form.bank_account_number" required 
                        class="mt-1 w-full border p-2 rounded focus:ring-2 focus:ring-green-500"
                        :class="{'border-red-500': errors.bank_account_number}">
                    <p v-if="errors.bank_account_number" class="text-red-500 text-xs mt-1">{{ errors.bank_account_number[0] }}</p>
                </div>
            </div>
        </div>

        <!-- Section 5: Documents -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">Documents</h3>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">ID Card *</label>
                    <input type="file" @change="handleFileChange($event, 'identity_document')" accept=".pdf,.jpg,.png" required 
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 cursor-pointer" />
                    <p v-if="errors.identity_document" class="text-red-500 text-xs mt-1">{{ errors.identity_document[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Business License *</label>
                    <input type="file" @change="handleFileChange($event, 'business_license_document')" accept=".pdf,.jpg,.png" required 
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 cursor-pointer" />
                    <p v-if="errors.business_license_document" class="text-red-500 text-xs mt-1">{{ errors.business_license_document[0] }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tax Document *</label>
                    <input type="file" @change="handleFileChange($event, 'tax_registration_document')" accept=".pdf,.jpg,.png" required 
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 cursor-pointer" />
                    <p v-if="errors.tax_registration_document" class="text-red-500 text-xs mt-1">{{ errors.tax_registration_document[0] }}</p>
                </div>
            </div>
        </div>

        <!-- Section 6: Terms and Conditions -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">Terms & Conditions</h3>
            
            <div class="p-4 bg-gray-50 rounded-lg border" :class="{'border-red-500': errors.agree_terms}">
                <label class="flex items-start cursor-pointer gap-3">
                    <input type="checkbox" v-model="form.agree_terms" 
                        class="mt-1 w-5 h-5 text-green-600 rounded border-gray-300 focus:ring-green-500 cursor-pointer">
                    <span class="text-sm text-gray-700 leading-relaxed">
                        I have read and agree to the 
                        <a href="/terms-and-conditions" target="_blank" class="text-green-600 hover:text-green-800 font-medium underline underline-offset-2">
                            Terms and Conditions
                        </a>, 
                        <a href="/privacy-policy" target="_blank" class="text-green-600 hover:text-green-800 font-medium underline underline-offset-2">
                            Privacy Policy
                        </a>, and 
                        <a href="/artisan-guidelines" target="_blank" class="text-green-600 hover:text-green-800 font-medium underline underline-offset-2">
                            Artisan Guidelines
                        </a>. 
                        I understand that my registration will be reviewed and I must comply with all platform rules and regulations.
                    </span>
                </label>
                <p v-if="errors.agree_terms" class="text-red-500 text-xs mt-2 ml-8">{{ errors.agree_terms[0] }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <a href="/terms-and-conditions" target="_blank" 
                    class="flex items-center gap-2 p-3 text-sm text-gray-600 bg-white border rounded-lg hover:border-green-300 hover:text-green-700 transition-colors">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Terms & Conditions
                </a>
                <a href="/privacy-policy" target="_blank" 
                    class="flex items-center gap-2 p-3 text-sm text-gray-600 bg-white border rounded-lg hover:border-green-300 hover:text-green-700 transition-colors">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Privacy Policy
                </a>
                <a href="/artisan-guidelines" target="_blank" 
                    class="flex items-center gap-2 p-3 text-sm text-gray-600 bg-white border rounded-lg hover:border-green-300 hover:text-green-700 transition-colors">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Artisan Guidelines
                </a>
            </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" :disabled="isLoading" 
            class="w-full py-3 px-4 border border-transparent rounded-md shadow-sm text-lg font-semibold text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 transition-colors duration-200">
            {{ isLoading ? $t('common.saving') : $t('artisan.submit_apply') }}
        </button>
      </form>
    </div>
  </div>
</template>

<style>
@import 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
</style>