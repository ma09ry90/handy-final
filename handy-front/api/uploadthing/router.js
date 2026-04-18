import { createUploadthing } from "uploadthing/next";

const f = createUploadthing();

// Define your file router (Pure JS version)
export const uploadRouter = {
  // This endpoint handles images, videos, AND 3D models
  productMedia: f({
    image: { maxFileSize: "4MB", maxFileCount: 5 },
    video: { maxFileSize: "32MB", maxFileCount: 1 },
    "3d-model": { maxFileSize: "10MB", maxFileCount: 1 },
  })
    .onUploadComplete(async ({ file }) => {
      console.log(`Uploaded: ${file.url}`);
      return { uploadedBy: "artisan" };
    }),
};