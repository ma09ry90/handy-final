import { createRouteHandler } from "uploadthing/express";
import { uploadRouter } from "./router.js";

// ✅ Export the handler directly. No (req, res) wrapper!
export default createRouteHandler({
  router: uploadRouter,
});

export const config = {
  api: {
    bodyParser: false,
  },
};