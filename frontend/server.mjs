/**
 * SSR: в dev — Vite middleware + render; в prod — статика из dist/client + entry-server.
 * Запуск: node server.mjs | node server.mjs --dev
 * PORT (по умолчанию 3008), VITE_SSR_API_ORIGIN — база для API из Node (напр. http://gg-nginx).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

import compression from 'compression';
import express from 'express';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const resolve = (p) => path.resolve(__dirname, p);

const isDev = process.argv.includes('--dev');
const port = Number(process.env.PORT) || 3008;

async function createDevServer() {
  const { createServer: createViteServer } = await import('vite');
  const app = express();
  const vite = await createViteServer({
    root: __dirname,
    server: { middlewareMode: true },
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
      const html = template
        .replace('<!--app-html-->', result.html)
        .replace('<!--pinia-state-->', stateJson);
      res.status(200).set({ 'Content-Type': 'text/html' }).end(html);
    } catch (e) {
      vite.ssrFixStacktrace(e);
      next(e);
    }
  });
  return app;
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
      const html = template
        .replace('<!--app-html-->', result.html)
        .replace('<!--pinia-state-->', stateJson);
      res.status(200).type('html').send(html);
    } catch (e) {
      console.error(e);
      res.status(500).type('html').send('SSR error');
    }
  });
  return app;
}

async function main() {
  const app = isDev ? await createDevServer() : await createProdServer();
  app.listen(port, '0.0.0.0', () => {
    console.log(`[ssr] ${isDev ? 'dev' : 'prod'} http://0.0.0.0:${port}`);
  });
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
