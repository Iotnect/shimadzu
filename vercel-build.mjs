import { cpSync, existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const output = resolve('public/vercel-assets');
mkdirSync(output, { recursive: true });

for (const asset of ['build', 'images', 'favicon.ico', 'favicon.svg', 'robots.txt']) {
    const source = resolve('public', asset);
    if (existsSync(source)) {
        cpSync(source, resolve(output, asset), { recursive: true });
    }
}

console.log('Prepared static assets for Vercel; PHP source and private storage are excluded.');
