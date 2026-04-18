import { createRouteHandler } from "uploadthing/express";
import { uploadRouter } from "./router";

// This wraps Uploadthing in a format Vercel understands
const handler = createRouteHandler({
  router: uploadRouter,
});

// Vercel expects a default export with (req, res)
export default async (req, res) => {
  return handler(req, res);
};

// Tell Vercel NOT to parse the body, so Uploadthing can handle the upload stream
export const config = {
  api: {
    bodyParser: false,
  },
};