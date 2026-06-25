import { defineConfig } from 'vite';
import fs from 'fs';

const hotFile = 'dist/hot';

function hotFilePlugin() {
  return {
    name: 'hot-file',
    configureServer() {
      fs.mkdirSync('dist', { recursive: true });
      fs.writeFileSync(hotFile, 'http://localhost:5173');

      const cleanup = () => {
        try { fs.unlinkSync(hotFile); } catch {}
      };
      process.on('exit', cleanup);
      process.on('SIGINT', () => { cleanup(); process.exit(); });
      process.on('SIGTERM', () => { cleanup(); process.exit(); });
    },
  };
}

export default defineConfig({
  plugins: [hotFilePlugin()],
  build: {
    manifest: true,
    outDir: 'dist',
    rollupOptions: {
      input: {
        board: 'src/js/board.js',
        pages: 'src/js/pages.js',
      },
    },
  },
  server: {
    origin: 'http://localhost:5173',
  },
});
