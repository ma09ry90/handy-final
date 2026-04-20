import { createRouteHandler } from "uploadthing/express";
import { uploadRouter } from "./router.js";

// Create the Uploadthing Express handler
const handler = createRouteHandler({
  router: uploadRouter,
});

// Vercel requires the standard (req, res) signature
export default async (req, res) => {
  // Use 'await' instead of 'return' to prevent Vercel routing conflicts
  await handler(req, res);
};

// Tell Vercel NOT to parse the body
export const config = {
  api: {
    bodyParser: false,
  },
};