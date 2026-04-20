import { createRouteHandler } from "uploadthing/express";
import { uploadRouter } from "./router.js";

const handler = createRouteHandler({
  router: uploadRouter,
});

export default async (req, res) => {
  await handler(req, res);
};

export const config = {
  api: {
    bodyParser: false,
  },
};