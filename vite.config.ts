import { publicPagesPlugin } from "./scripts/public-pages.ts";
import react, { reactCompilerPreset } from "@vitejs/plugin-react";
import babel from "@rolldown/plugin-babel";
import { defineConfig, loadEnv } from "vite";
import tailwindcss from "@tailwindcss/vite";

// https://vite.dev/config/

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");

  return {
    plugins: [
      publicPagesPlugin(),
      react(),
      babel({ presets: [reactCompilerPreset()] }),
      tailwindcss(),
    ],
    server: {
      headers: {
        "Referrer-Policy": "no-referrer",
        "X-Content-Type-Options": "nosniff",
        "X-Frame-Options": "DENY",
      },
      proxy: {
        "/robots.txt": {
          target: env.PHP_API_URL,
          changeOrigin: true,
          rewrite: () => "/api/seo/robots",
        },
        "^/sitemap.*\\.xml": {
          target: env.PHP_API_URL,
          changeOrigin: true,
          rewrite: (path) =>
            "/api/seo/sitemap?path=" + encodeURIComponent(path.split("?")[0]),
        },
        "/api": {
          target: env.PHP_API_URL,
          changeOrigin: true,
        },
      },
    },
  };
});
