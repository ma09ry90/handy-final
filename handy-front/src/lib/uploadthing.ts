import { generateVueHelpers } from "@uploadthing/vue"; // ✅ Fixed: Vue instead of React

export const { useUploadThing } = generateVueHelpers({
  url: "/api/uploadthing", 
});