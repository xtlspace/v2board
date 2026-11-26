/**
 * Zero-dependency static server for the theme preview.
 *
 *   node public/theme/classic/preview-server.js [port]
 *
 * Then open http://localhost:4173/preview.html
 *
 * Preview query options:
 *   ?guest=1        open the login page (clears the mock session)
 *   ?lang=en-US     English UI
 *   ?color=green    default | green | black | darkblue
 *   ?sidebar=dark   light | dark
 *   ?header=light   light | dark
 */
const http = require('http');
const fs = require('fs');
const path = require('path');

const root = __dirname;
const port = Number(process.argv[2] || 4173);

const types = {
    '.html': 'text/html; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
    '.css': 'text/css; charset=utf-8',
    '.json': 'application/json; charset=utf-8',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
    '.ttf': 'font/ttf'
};

const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    let pathname = decodeURIComponent(url.pathname);
    if (pathname === '/') pathname = '/preview.html';
    const target = path.normalize(path.join(root, pathname));
    if (!target.startsWith(root)) {
        res.writeHead(403);
        res.end('Forbidden');
        return;
    }
    fs.readFile(target, (error, content) => {
        if (error) {
            res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
            res.end('Not found: ' + pathname);
            return;
        }
        res.writeHead(200, {
            'Content-Type': types[path.extname(target).toLowerCase()] || 'application/octet-stream',
            'Cache-Control': 'no-store'
        });
        res.end(content);
    });
});

server.listen(port, () => {
    console.log(`Classic theme preview: http://localhost:${port}/preview.html`);
    console.log(`Login page preview:   http://localhost:${port}/preview.html?guest=1`);
});
