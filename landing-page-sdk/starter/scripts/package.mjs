import { mkdir, readFile, readdir, stat, writeFile } from "node:fs/promises";
import path from "node:path";
import zlib from "node:zlib";

await import("./build.mjs");
await mkdir("dist-package", { recursive: true });

const files = [["manifest.json", await readFile("manifest.json")]];
async function collect(dir) {
  for (const entry of await readdir(dir)) {
    const full = path.join(dir, entry);
    const info = await stat(full);
    if (info.isDirectory()) await collect(full);
    else files.push([full.replaceAll("\\", "/"), await readFile(full)]);
  }
}
await collect("dist");

function crc32(buf) {
  let c = ~0;
  for (let i = 0; i < buf.length; i++) {
    c ^= buf[i];
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  }
  return ~c >>> 0;
}

const chunks = [];
const central = [];
let offset = 0;
for (const [name, body] of files) {
  const filename = Buffer.from(name);
  const compressed = zlib.deflateRawSync(body);
  const crc = crc32(body);
  const local = Buffer.alloc(30);
  local.writeUInt32LE(0x04034b50, 0);
  local.writeUInt16LE(20, 4);
  local.writeUInt16LE(8, 8);
  local.writeUInt32LE(crc, 14);
  local.writeUInt32LE(compressed.length, 18);
  local.writeUInt32LE(body.length, 22);
  local.writeUInt16LE(filename.length, 26);
  chunks.push(local, filename, compressed);

  const cd = Buffer.alloc(46);
  cd.writeUInt32LE(0x02014b50, 0);
  cd.writeUInt16LE(20, 4);
  cd.writeUInt16LE(20, 6);
  cd.writeUInt16LE(8, 10);
  cd.writeUInt32LE(crc, 16);
  cd.writeUInt32LE(compressed.length, 20);
  cd.writeUInt32LE(body.length, 24);
  cd.writeUInt16LE(filename.length, 28);
  cd.writeUInt32LE(offset, 42);
  central.push(cd, filename);
  offset += local.length + filename.length + compressed.length;
}

const centralSize = central.reduce((sum, item) => sum + item.length, 0);
const end = Buffer.alloc(22);
end.writeUInt32LE(0x06054b50, 0);
end.writeUInt16LE(files.length, 8);
end.writeUInt16LE(files.length, 10);
end.writeUInt32LE(centralSize, 12);
end.writeUInt32LE(offset, 16);

await writeFile("dist-package/landing-page.zip", Buffer.concat([...chunks, ...central, end]));
console.log("Packaged dist-package/landing-page.zip");
