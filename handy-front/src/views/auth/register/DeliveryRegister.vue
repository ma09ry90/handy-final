<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/plugins/axios'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const { t, locale } = useI18n()
const router = useRouter()

const loading = ref(false)
const success = ref(false)
const errors = ref({})
const imagePreview = ref(null)
const imageError = ref('')
const isDragging = ref(false)

const txt = computed(() => ({
  title: t('delivery_reg.title') || 'Register as Delivery Personnel',
  sub: t('delivery_reg.sub') || 'Join our logistics network and start earning.',
  personal_info: t('delivery_reg.personal_info') || 'Personal Information',
  first_name: t('delivery_reg.first_name') || 'First Name',
  last_name: t('delivery_reg.last_name') || 'Last Name',
  email: t('delivery_reg.email') || 'Email Address',
  password: t('delivery_reg.password') || 'Password',
  password_confirm: t('delivery_reg.password_confirm') || 'Confirm Password',
  phone: t('delivery_reg.phone') || 'Phone Number',
  birthdate: t('delivery_reg.birthdate') || 'Date of Birth',
  gender: t('delivery_reg.gender') || 'Gender',
  male: t('delivery_reg.male') || 'Male',
  female: t('delivery_reg.female') || 'Female',
  profile_photo: t('delivery_reg.profile_photo') || 'Passport Size Profile Photo',
  profile_photo_hint: t('delivery_reg.profile_photo_hint') || 'Upload a clear passport-size photo of your face (no sunglasses, no hats)',
  drag_drop: t('delivery_reg.drag_drop') || 'Drag & drop your photo here, or',
  browse: t('delivery_reg.browse') || 'browse files',
  accepted_formats: t('delivery_reg.accepted_formats') || 'Accepted: JPG, PNG, JPEG (Max 2MB)',
  min_resolution: t('delivery_reg.min_resolution') || 'Minimum resolution: 300x400px',
  remove_image: t('delivery_reg.remove_image') || 'Remove',
  change_image: t('delivery_reg.change_image') || 'Change Photo',
  vehicle_info: t('delivery_reg.vehicle_info') || 'Vehicle Information',
  vehicle_type: t('delivery_reg.vehicle_type') || 'Vehicle Type',
  select_type: t('delivery_reg.select_type') || 'Select vehicle type',
  motorcycle: t('delivery_reg.motorcycle') || 'Motorcycle',
  car: t('delivery_reg.car') || 'Car',
  van: t('delivery_reg.van') || 'Van',
  plate_number: t('delivery_reg.plate_number') || 'Plate Number',
  vehicle_model: t('delivery_reg.vehicle_model') || 'Vehicle Model',
  vehicle_color: t('delivery_reg.vehicle_color') || 'Vehicle Color',
  additional_info: t('delivery_reg.additional_info') || 'Additional Information',
  national_id: t('delivery_reg.national_id') || 'National ID Number',
  driving_license: t('delivery_reg.driving_license') || 'Driving License Number',
  license_expiry: t('delivery_reg.license_expiry') || 'License Expiry Date',
  emergency_name: t('delivery_reg.emergency_name') || 'Emergency Contact Name',
  emergency_phone: t('delivery_reg.emergency_phone') || 'Emergency Contact Phone',
  address_info: t('delivery_reg.address_info') || 'Address Information',
  address_info_desc: t('delivery_reg.address_info_desc') || 'Provide your current residential or operational address.',
  documents: t('delivery_reg.documents') || 'Documents',
  national_id_doc: t('delivery_reg.national_id_doc') || 'National ID Card',
  driving_license_doc: t('delivery_reg.driving_license_doc') || 'Driving License',
  vehicle_reg_doc: t('delivery_reg.vehicle_reg_doc') || 'Vehicle Registration Document',
  terms_text: t('delivery_reg.terms_text') || 'By registering, you agree to undergo a background check.',
  submit: t('delivery_reg.submit') || 'Submit Application',
  has_account: t('delivery_reg.has_account') || 'Already have an account?',
  login_here: t('delivery_reg.login_here') || 'Log in here',
  success_title: t('delivery_reg.success_title') || 'Application Submitted!',
  success_msg: t('delivery_reg.success_msg') || 'Your application is under review.',
  validation: {
    required: t('delivery_reg.validation.required') || 'This field is required',
    email_invalid: t('delivery_reg.validation.email_invalid') || 'Please enter a valid email',
    password_min: t('delivery_reg.validation.password_min') || 'Password must be at least 8 characters',
    password_mismatch: t('delivery_reg.validation.password_mismatch') || 'Passwords do not match',
    phone_invalid: t('delivery_reg.validation.phone_invalid') || 'Please enter a valid phone number',
    image_required: t('delivery_reg.validation.image_required') || 'Profile photo is required',
    image_type: t('delivery_reg.validation.image_type') || 'Only JPG, PNG, and JPEG formats are allowed',
    image_size: t('delivery_reg.validation.image_size') || 'Image size must be less than 2MB',
    image_resolution: t('delivery_reg.validation.image_resolution') || 'Image resolution must be at least 300x400px',
  }
}))

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
  phone_number: '',
  birthdate: '',
  gender: '',
  
  // Profile
  profile_image: null,
  
  // Vehicle & Additional
  vehicle_type: '',
  vehicle_plate_number: '',
  vehicle_model: '',
  vehicle_color: '',
  national_id_number: '',
  driving_license_number: '',
  driving_license_expiry: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',

  // Address Fields
  city_id: '',
  subcity_id: '',
  woreda_id: '',
  street: '',
  landmark: '',

  // Documents
  national_id_document: null,
  driving_license_document: null,
  vehicle_registration_document: null,
})

// Address Logic
const cities = ref([]);
const subcities = ref([]);
const woredas = ref([]);

onMounted(async () => {
  try {
    const res = await api.get('/cities');
    cities.value = res.data;
  } catch (e) { console.error(e); }
});

watch(() => form.value.city_id, async (newVal) => {
  form.value.subcity_id = null;
  form.value.woreda_id = null;
  subcities.value = [];
  woredas.value = [];
  if (!newVal) return;
  try {
    const subRes = await api.get(`/cities/${newVal}/subcities`);
    subcities.value = subRes.data;
    const woredaRes = await api.get(`/cities/${newVal}/woredas`);
    woredas.value = woredaRes.data;
  } catch (e) { console.error("Error loading location data", e); }
});

watch(() => form.value.subcity_id, async (newVal) => {
  form.value.woreda_id = null;
  woredas.value = [];
  if (!newVal) return;
  try {
    const res = await api.get(`/cities/${form.value.city_id}/woredas?subcity_id=${newVal}`);
    woredas.value = res.data;
  } catch (e) { console.error("Error loading woredas", e); }
});

// Image Validation Config
const validationConfig = {
  maxImageSize: 2 * 1024 * 1024,
  allowedTypes: ['image/jpeg', 'image/jpg', 'image/png'],
  minWidth: 300,
  minHeight: 400
}

const inputClass = (field) => ({
  'w-full border p-2.5 rounded-lg focus:ring-2 focus:ring-green-500 focus:outline-none transition': true,
  'border-red-500 focus:ring-red-500': errors.value[field] || (field === 'profile_image' && imageError.value),
  'border-gray-300': !errors.value[field] && !(field === 'profile_image' && imageError.value)
})

const validateImageDimensions = (file) => {
  return new Promise((resolve, reject) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      URL.revokeObjectURL(url)
      if (img.width >= validationConfig.minWidth && img.height >= validationConfig.minHeight) {
        resolve(true)
      } else {
        reject(new Error(txt.value.validation.image_resolution))
      }
    }
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Failed to load image')) }
    img.src = url
  })
}

const validateImage = async (file) => {
  imageError.value = ''
  if (!file) { imageError.value = txt.value.validation.image_required; return false }
  if (!validationConfig.allowedTypes.includes(file.type)) { imageError.value = txt.value.validation.image_type; return false }
  if (file.size > validationConfig.maxImageSize) { imageError.value = txt.value.validation.image_size; return false }
  try { await validateImageDimensions(file) } catch (error) { imageError.value = error.message; return false }
  return true
}

const handleImageSelect = async (event) => {
  const file = event.target.files[0]
  if (file) {
    const isValid = await validateImage(file)
    if (isValid) {
      form.value.profile_image = file
      const reader = new FileReader()
      reader.onload = (e) => { imagePreview.value = e.target.result }
      reader.readAsDataURL(file)
    } else {
      form.value.profile_image = null; imagePreview.value = null; event.target.value = ''
    }
  }
}

const handleDrop = async (event) => {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file) {
    const isValid = await validateImage(file)
    if (isValid) {
      form.value.profile_image = file
      const reader = new FileReader()
      reader.onload = (e) => { imagePreview.value = e.target.result }
      reader.readAsDataURL(file)
    } else { form.value.profile_image = null; imagePreview.value = null }
  }
}

const removeImage = () => {
  form.value.profile_image = null; imagePreview.value = null; imageError.value = ''
}

// Generic handler for document files
const handleFileChange = (event, fieldName) => {
  const file = event.target.files[0];
  if (file) { form.value[fieldName] = file; }
};

const validateForm = () => {
  const localErrors = {}
  if (!form.value.first_name.trim()) localErrors.first_name = [txt.value.validation.required]
  if (!form.value.last_name.trim()) localErrors.last_name = [txt.value.validation.required]
  if (!form.value.email.trim()) localErrors.email = [txt.value.validation.required]
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) localErrors.email = [txt.value.validation.email_invalid]
  if (!form.value.password) localErrors.password = [txt.value.validation.required]
  else if (form.value.password.length < 8) localErrors.password = [txt.value.validation.password_min]
  if (!form.value.password_confirmation) localErrors.password_confirmation = [txt.value.validation.required]
  else if (form.value.password !== form.value.password_confirmation) localErrors.password_confirmation = [txt.value.validation.password_mismatch]
  if (!form.value.phone_number.trim()) localErrors.phone_number = [txt.value.validation.required]
  else if (!/^[\+]?[0-9\s\-]{8,15}$/.test(form.value.phone_number)) localErrors.phone_number = [txt.value.validation.phone_invalid]
  if (!form.value.gender) localErrors.gender = [txt.value.validation.required]
  if (!form.value.vehicle_type) localErrors.vehicle_type = [txt.value.validation.required]
  if (!form.value.vehicle_plate_number.trim()) localErrors.vehicle_plate_number = [txt.value.validation.required]
  if (!form.value.national_id_number.trim()) localErrors.national_id_number = [txt.value.validation.required]
  if (!form.value.city_id) localErrors.city_id = [txt.value.validation.required]
  if (!form.value.street.trim()) localErrors.street = [txt.value.validation.required]
  
  if (!form.value.profile_image) imageError.value = txt.value.validation.image_required
  return localErrors
}

const submitForm = async () => {
  errors.value = {}; imageError.value = ''
  const localErrors = validateForm()
  if (Object.keys(localErrors).length > 0 || imageError.value) {
    errors.value = localErrors; window.scrollTo(0, 0); return
  }
  
  loading.value = true
  try {
    const formData = new FormData()
    
    // Append Text Fields
    Object.keys(form.value).forEach(key => {
      if (!['profile_image', 'national_id_document', 'driving_license_document', 'vehicle_registration_document'].includes(key) && form.value[key]) {
        formData.append(key, form.value[key])
      }
    })
    
    // Append Address conditionally
    if (form.value.subcity_id) formData.append('subcity_id', form.value.subcity_id)
    if (form.value.woreda_id) formData.append('woreda_id', form.value.woreda_id)
    
    // Append Locale
    formData.append('locale', locale.value)
    
    // Append Profile Image
    if (form.value.profile_image) formData.append('profile_image', form.value.profile_image)
    
    // Append Documents
    if (form.value.national_id_document) formData.append('national_id_document', form.value.national_id_document)
    if (form.value.driving_license_document) formData.append('driving_license_document', form.value.driving_license_document)
    if (form.value.vehicle_registration_document) formData.append('vehicle_registration_document', form.value.vehicle_registration_document)
    
    await api.post('/register/delivery', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    
    success.value = true; window.scrollTo(0, 0);
    router.push('/login');
  }  catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors;
      window.scrollTo(0, 0);
    } else {
      // Clean up the error message to hide ugly SQL text from the user
      let errorMsg = 'An error occurred during registration. Please try again.';
      
      if (error.response?.data?.message) {
        // If it's the duplicate key error, give a specific hint
        if (error.response.data.message.includes('Duplicate entry') && error.response.data.message.includes('phone_number')) {
          errorMsg = 'This phone number is already registered. Please use a different number or log in.';
        } else {
          errorMsg = error.response.data.message;
        }
      }
      
      alert(errorMsg);
    }
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
      <div class="max-w-4xl mx-auto px-6 h-16 flex justify-between items-center">
        <span class="text-2xl font-bold text-green-600">Handy</span>
        <LanguageSwitcher />
      </div>
    </header>

    <!-- Success State -->
    <div v-if="success" class="flex-grow flex items-center justify-center p-8">
      <div class="bg-white p-10 rounded-xl shadow-lg max-w-md text-center">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
          <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ txt.success_title }}</h2>
        <p class="text-gray-500 mb-6">{{ txt.success_msg }}</p>
        <button @click="router.push('/login')" class="bg-green-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-green-700 transition">
          {{ txt.login_here }}
        </button>
      </div>
    </div>

    <!-- Registration Form -->
    <main v-else class="flex-grow py-10 px-4 sm:px-8">
      <div class="bg-white p-8 sm:p-10 rounded-2xl shadow-xl w-full max-w-3xl mx-auto">
        
        <div class="text-center mb-8">
          <h1 class="text-3xl font-bold text-gray-900">{{ txt.title }}</h1>
          <p class="mt-2 text-gray-500">{{ txt.sub }}</p>
        </div>

        <!-- GLOBAL ERROR BOX -->
        <div v-if="Object.keys(errors).length > 0 || imageError" class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded">
          <div class="flex">
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
              <div class="mt-2 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                  <li v-for="(errorArray, field) in errors" :key="field">
                    <span class="font-semibold capitalize">{{ field.replace('_', ' ') }}</span>: {{ errorArray.join(', ') }}
                  </li>
                  <li v-if="imageError"><span class="font-semibold capitalize">Profile Photo</span>: {{ imageError }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <form @submit.prevent="submitForm" class="flex flex-col gap-8">
          
          <!-- Section 1: Personal Information -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">{{ txt.personal_info }}</h3>
            
            <!-- Profile Photo Upload -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ txt.profile_photo }} <span class="text-red-500">*</span>
              </label>
              
              <div class="bg-gray-50 p-3 rounded-lg mb-3 text-xs text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                <span>{{ txt.profile_photo_hint }}</span>
                <span>{{ txt.accepted_formats }}</span>
                <span>{{ txt.min_resolution }}</span>
              </div>

              <div v-if="!imagePreview"
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleDrop"
                :class="['border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-all duration-200', imageError ? 'border-red-400 bg-red-50' : isDragging ? 'border-green-500 bg-green-50' : 'border-gray-300 hover:border-green-400 hover:bg-gray-50']"
                @click="$refs.imageInput.click()">
                <input ref="imageInput" type="file" accept="image/jpeg,image/jpg,image/png" @change="handleImageSelect" class="hidden">
                <div class="flex flex-col items-center gap-2">
                  <div :class="['w-14 h-18 rounded-lg flex flex-col items-center justify-center border-2 border-dashed', imageError ? 'bg-red-100 border-red-300' : 'bg-gray-100 border-gray-300']">
                    <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                  </div>
                  <p class="text-sm text-gray-600">{{ txt.drag_drop }}</p>
                  <span class="text-sm text-green-600 font-semibold hover:underline">{{ txt.browse }}</span>
                </div>
              </div>

              <div v-else class="relative border-2 border-gray-200 rounded-xl overflow-hidden bg-gray-50 p-4">
                <div class="flex items-center gap-4">
                  <div class="relative w-32 h-40 bg-white border-2 border-gray-200 rounded-lg overflow-hidden shadow-sm flex-shrink-0">
                    <img :src="imagePreview" alt="Profile" class="w-full h-full object-cover">
                  </div>
                  <div class="flex-grow">
                    <p class="text-sm font-semibold text-gray-700 mb-1 flex items-center gap-1">
                      <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                      Photo uploaded
                    </p>
                    <p class="text-xs text-gray-500 mb-2">{{ form.profile_image?.name }} ({{ (form.profile_image?.size / 1024).toFixed(1) }} KB)</p>
                    <div class="flex gap-2">
                      <button type="button" @click="$refs.imageInput.click()" class="text-xs px-3 py-1.5 bg-green-50 text-green-700 rounded-md hover:bg-green-100 font-medium">{{ txt.change_image }}</button>
                      <button type="button" @click="removeImage" class="text-xs px-3 py-1.5 bg-red-50 text-red-600 rounded-md hover:bg-red-100 font-medium">{{ txt.remove_image }}</button>
                    </div>
                  </div>
                </div>
                <button type="button" @click="removeImage" class="absolute top-2 right-2 w-7 h-7 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-md transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
              <input ref="imageInput" type="file" accept="image/jpeg,image/jpg,image/png" @change="handleImageSelect" class="hidden">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.first_name }} <span class="text-red-500">*</span></label>
                <input v-model="form.first_name" type="text" :class="inputClass('first_name')" :placeholder="txt.first_name">
                <p v-if="errors.first_name" class="text-red-500 text-xs mt-1">{{ errors.first_name[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.last_name }} <span class="text-red-500">*</span></label>
                <input v-model="form.last_name" type="text" :class="inputClass('last_name')" :placeholder="txt.last_name">
                <p v-if="errors.last_name" class="text-red-500 text-xs mt-1">{{ errors.last_name[0] }}</p>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.gender }} <span class="text-red-500">*</span></label>
                <select v-model="form.gender" :class="inputClass('gender')">
                  <option value="">Select gender</option>
                  <option value="male">{{ txt.male }}</option>
                  <option value="female">{{ txt.female }}</option>
                </select>
                <p v-if="errors.gender" class="text-red-500 text-xs mt-1">{{ errors.gender[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.birthdate }}</label>
                <input v-model="form.birthdate" type="date" :class="inputClass('birthdate')">
                <p v-if="errors.birthdate" class="text-red-500 text-xs mt-1">{{ errors.birthdate[0] }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.email }} <span class="text-red-500">*</span></label>
                <input v-model="form.email" type="email" :class="inputClass('email')" placeholder="example@email.com">
                <p v-if="errors.email" class="text-red-500 text-xs mt-1">{{ errors.email[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.phone }} <span class="text-red-500">*</span></label>
                <input v-model="form.phone_number" type="tel" :class="inputClass('phone_number')" placeholder="+251 9XX XXX XXX">
                <p v-if="errors.phone_number" class="text-red-500 text-xs mt-1">{{ errors.phone_number[0] }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.password }} <span class="text-red-500">*</span></label>
                <input v-model="form.password" type="password" :class="inputClass('password')" :placeholder="txt.password">
                <p v-if="errors.password" class="text-red-500 text-xs mt-1">{{ errors.password[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.password_confirm }} <span class="text-red-500">*</span></label>
                <input v-model="form.password_confirmation" type="password" :class="inputClass('password_confirmation')" :placeholder="txt.password_confirm">
                <p v-if="errors.password_confirmation" class="text-red-500 text-xs mt-1">{{ errors.password_confirmation[0] }}</p>
              </div>
            </div>
          </div>

          <!-- Section 2: Vehicle & Additional Info -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">{{ txt.vehicle_info }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.vehicle_type }} <span class="text-red-500">*</span></label>
                <select v-model="form.vehicle_type" :class="inputClass('vehicle_type')">
                  <option value="">{{ txt.select_type }}</option>
                  <option value="motorcycle">{{ txt.motorcycle }}</option>
                  <option value="car">{{ txt.car }}</option>
                  <option value="van">{{ txt.van }}</option>
                </select>
                <p v-if="errors.vehicle_type" class="text-red-500 text-xs mt-1">{{ errors.vehicle_type[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.plate_number }} <span class="text-red-500">*</span></label>
                <input v-model="form.vehicle_plate_number" type="text" :class="inputClass('vehicle_plate_number')" :placeholder="txt.plate_number">
                <p v-if="errors.vehicle_plate_number" class="text-red-500 text-xs mt-1">{{ errors.vehicle_plate_number[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.vehicle_model }}</label>
                <input v-model="form.vehicle_model" type="text" :class="inputClass('vehicle_model')" placeholder="e.g. Toyota Corolla">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.vehicle_color }}</label>
                <input v-model="form.vehicle_color" type="text" :class="inputClass('vehicle_color')" placeholder="e.g. White">
              </div>
            </div>
          </div>

          <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">{{ txt.additional_info }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.national_id }} <span class="text-red-500">*</span></label>
                <input v-model="form.national_id_number" type="text" :class="inputClass('national_id_number')" :placeholder="txt.national_id">
                <p v-if="errors.national_id_number" class="text-red-500 text-xs mt-1">{{ errors.national_id_number[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.driving_license }}</label>
                <input v-model="form.driving_license_number" type="text" :class="inputClass('driving_license_number')" placeholder="License Number">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.license_expiry }}</label>
                <input v-model="form.driving_license_expiry" type="date" :class="inputClass('driving_license_expiry')">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.emergency_name }}</label>
                <input v-model="form.emergency_contact_name" type="text" :class="inputClass('emergency_contact_name')" placeholder="Full Name">
              </div>
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ txt.emergency_phone }}</label>
                <input v-model="form.emergency_contact_phone" type="tel" :class="inputClass('emergency_contact_phone')" placeholder="+251 9XX XXX XXX">
              </div>
            </div>
          </div>

          <!-- Section 3: Address Location -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">{{ txt.address_info }}</h3>
            <p class="text-xs text-gray-500">{{ txt.address_info_desc }}</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
                <select v-model="form.city_id" :class="inputClass('city_id')">
                  <option value="" disabled>Select City</option>
                  <option v-for="c in cities" :key="c.id" :value="c.id">{{ c['name_' + locale] || c.name }}</option>
                </select>
                <p v-if="errors.city_id" class="text-red-500 text-xs mt-1">{{ errors.city_id[0] }}</p>
              </div>
              <div v-if="form.city_id && subcities.length > 0">
                <label class="block text-sm font-medium text-gray-700 mb-1">Subcity <span class="text-red-500">*</span></label>
                <select v-model="form.subcity_id" :class="inputClass('subcity_id')">
                  <option value="" disabled>Select Subcity</option>
                  <option v-for="s in subcities" :key="s.id" :value="s.id">{{ s['name_' + locale] || s.name }}</option>
                </select>
                <p v-if="errors.subcity_id" class="text-red-500 text-xs mt-1">{{ errors.subcity_id[0] }}</p>
              </div>
              <div v-if="form.city_id && woredas.length > 0">
                <label class="block text-sm font-medium text-gray-700 mb-1">Woreda <span class="text-red-500">*</span></label>
                <select v-model="form.woreda_id" :class="inputClass('woreda_id')">
                  <option value="" disabled>Select Woreda</option>
                  <option v-for="w in woredas" :key="w.id" :value="w.id">{{ w['name_' + locale] || w.name }}</option>
                </select>
                <p v-if="errors.woreda_id" class="text-red-500 text-xs mt-1">{{ errors.woreda_id[0] }}</p>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Street <span class="text-red-500">*</span></label>
                <input v-model="form.street" type="text" :class="inputClass('street')" placeholder="Street name or area">
                <p v-if="errors.street" class="text-red-500 text-xs mt-1">{{ errors.street[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Landmark</label>
                <input v-model="form.landmark" type="text" :class="inputClass('landmark')" placeholder="Nearby famous place">
              </div>
            </div>
          </div>

          <!-- Section 4: Documents -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-l-4 border-yellow-400 pl-3">{{ txt.documents }}</h3>
            <div class="grid grid-cols-1 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ txt.national_id_doc }} *</label>
                <input type="file" @change="handleFileChange($event, 'national_id_document')" accept=".pdf,.jpg,.png" required 
                  class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100 cursor-pointer" />
                <p v-if="errors.national_id_document" class="text-red-500 text-xs mt-1">{{ errors.national_id_document[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ txt.driving_license_doc }} *</label>
                <input type="file" @change="handleFileChange($event, 'driving_license_document')" accept=".pdf,.jpg,.png" required 
                  class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100 cursor-pointer" />
                <p v-if="errors.driving_license_document" class="text-red-500 text-xs mt-1">{{ errors.driving_license_document[0] }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ txt.vehicle_reg_doc }} *</label>
                <input type="file" @change="handleFileChange($event, 'vehicle_registration_document')" accept=".pdf,.jpg,.png" required 
                  class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100 cursor-pointer" />
                <p v-if="errors.vehicle_registration_document" class="text-red-500 text-xs mt-1">{{ errors.vehicle_registration_document[0] }}</p>
              </div>
            </div>
          </div>

          <!-- Terms -->
          <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 flex items-start gap-3">
            <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-gray-600">{{ txt.terms_text }}</p>
          </div>

          <!-- Submit -->
          <button type="submit" :disabled="loading" 
            class="w-full bg-green-600 hover:bg-green-700 disabled:bg-green-400 disabled:cursor-not-allowed text-white font-bold py-3.5 rounded-lg shadow-lg transition flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <svg v-if="loading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ loading ? 'Submitting...' : txt.submit }}
          </button>

          <p class="text-center text-sm text-gray-500 mt-4">
            {{ txt.has_account }}
            <router-link to="/login" class="text-green-600 font-medium hover:underline">{{ txt.login_here }}</router-link>
          </p>
        </form>
      </div>
    </main>
  </div>
</template>