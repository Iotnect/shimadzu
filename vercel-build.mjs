import { cpSync, existsSync, mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

if (!process.env.DET_WORKLOG_URL) {
    const snapshot = JSON.parse(readFileSync(resolve('storage/app/private/det-worklog.json'), 'utf8'));
    if (snapshot.demo || !Array.isArray(snapshot.jobs) || snapshot.jobs.length === 0) {
        throw new Error('Deploy the actual production snapshot, not demo or empty records.');
    }
    console.log(`Bundling ${snapshot.jobs.length} production records for the PHP backend.`);
}

const output = resolve('public/vercel-assets');
mkdirSync(output, { recursive: true });

for (const asset of ['build', 'images', 'favicon.ico', 'favicon.svg', 'robots.txt']) {
    const source = resolve('public', asset);
    if (existsSync(source)) {
        cpSync(source, resolve(output, asset), { recursive: true });
    }
}

console.log('Prepared static assets for Vercel; PHP source and private storage are excluded.');
