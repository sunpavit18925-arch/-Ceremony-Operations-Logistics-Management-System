/** @type {import('next').NextConfig} */
const nextConfig = {
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
