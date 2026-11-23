import { fileURLToPath } from "node:url";

/** @type {import('next').NextConfig} */
const nextConfig = {
  outputFileTracingRoot: fileURLToPath(new URL(".", import.meta.url)),
  async redirects() {
    return [
      {
        source: '/index.php',
        destination: '/',
        permanent: false,
      },
      {
        source: '/:path*.php',
        destination: '/:path*',
        permanent: false,
      },
    ];
  },
};

export default nextConfig;
