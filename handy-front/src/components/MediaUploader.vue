<script setup>
import { useDropzone, useUploadThing } from '@uploadthing/vue';
import { ref, watch } from 'vue';

const props = defineModel('modelValue', { type: Array, default: () => [] });

const { startUpload, isUploading } = useUploadThing({
    onClientUploadComplete: (res) => {
      const newUrls = res.map(file => file.ufsUrl);
      props.modelValue.push(...newUrls);
    },
});

const { getRootProps, isDragActive } = useDropzone({
    onDrop: startUpload,
    accept: { 'image/*': [], 'video/*': [], 'model/*': ['.glb', '.gltf'] },
});

const inputRef = ref(null);
watch(isDragActive, (active) => {
  if (active) startUpload();
});
</script>

<template>
  <div v-bind="getRootProps()" 
       class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer hover:border-emerald-500 transition"
       @click="inputRef?.click()">
    <input ref="inputRef" type="file" class="hidden" multiple 
           accept="image/*,video/*,.glb,.gltf" @change="e => startUpload(e.target.files)" />
    
    <div v-if="isUploading" class="text-emerald-600 font-medium">
      Uploading to cloud (Please wait)...
    </div>
    <div v-else class="text-gray-500">
      <p class="font-medium">Drag & Drop Images, Videos, or AR Models here</p>
      <p class="text-xs text-gray-400 mt-1">Or click to browse</p>
    </div>
  </div>
</template>