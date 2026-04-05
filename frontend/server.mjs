/**
 * SSR: в dev — Vite middleware + render; в prod — статика из dist/client + entry-server.
 * Запуск: node server.mjs | node server.mjs --dev
 * PORT (по умолчанию 3008), VITE_SSR_API_ORIGIN — база для API из Node (напр. http://gg-nginx).
 */
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

import compression from 'compression';
import express from 'express';

/**
 * Безопасная замена маркера в шаблоне.
 * String.replace интерпретирует $&, $`, $' в replacement — функция этого не делает.
 */
function safeReplace(str, marker, replacement) {
  const idx = str.indexOf(marker);
  if (idx === -1) return str;
  return str.slice(0, idx) + replacement + str.slice(idx + marker.length);
}

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const resolve = (p) => path.resolve(__dirname, p);

const isDev = process.argv.includes('--dev');
const port = Number(process.env.PORT) || 3008;

async function createDevServer() {
  const { createServer: createViteServer } = await import('vite');
  const app = express();
  /** Один HTTP-сервер для Express и HMR — иначе в middlewareMode WS на :24678, а клиент ходит на :80/:3008. */
  const httpServer = http.createServer(app);
  const vite = await createViteServer({
    root: __dirname,
    server: {
      middlewareMode: true,
      hmr: { server: httpServer },
    },
    appType: 'custom',
  });
  app.use(vite.middlewares);
  app.get(/.*/, async (req, res, next) => {
    try {
      const url = req.originalUrl;
      let template = fs.readFileSync(resolve('index.html'), 'utf-8');
      template = await vite.transformIndexHtml(url, template);
      const { render } = await vite.ssrLoadModule('/src/entry-server.ts');
      const result = await render(url, {
        cookie: req.headers.cookie,
        host: req.headers.host,
      });
      const stateJson = JSON.stringify(result.piniaState ?? {}).replace(/</g, '\\u003c');
      const html = safeReplace(
        safeReplace(template, '<!--app-html-->', result.html),
        '<!--pinia-state-->',
        stateJson,
      );
      res.status(200).set({ 'Content-Type': 'text/html' }).end(html);
    } catch (e) {
      vite.ssrFixStacktrace(e);
      next(e);
    }
  });
  return httpServer;
}

async function createProdServer() {
  const app = express();
  app.use(compression());

  const clientDir = resolve('dist/client');
  app.use('/assets', express.static(path.join(clientDir, 'assets'), { immutable: true, maxAge: '1y' }));
  app.use(express.static(clientDir, { index: false }));

  const template = fs.readFileSync(path.join(clientDir, 'index.html'), 'utf-8');
  const serverPath = resolve('dist/server/entry-server.js');
  const { render } = await import(pathToFileURL(serverPath).href);

  app.get(/.*/, async (req, res) => {
    try {
      const url = req.originalUrl;
      const result = await render(url, {
        cookie: req.headers.cookie,
        host: req.headers.host,
      });
      const stateJson = JSON.stringify(result.piniaState ?? {}).replace(/</g, '\\u003c');
      const html = safeReplace(
        safeReplace(template, '<!--app-html-->', result.html),
        '<!--pinia-state-->',
        stateJson,
      );
      res.status(200).type('html').send(html);
    } catch (e) {
      console.error(e);
      res.status(500).type('html').send('SSR error');
    }
  });
  return app;
}

async function main() {
  const server = isDev ? await createDevServer() : await createProdServer();
  server.listen(port, '0.0.0.0', () => {
    console.log(`[ssr] ${isDev ? 'dev' : 'prod'} http://0.0.0.0:${port}`);
  });
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
