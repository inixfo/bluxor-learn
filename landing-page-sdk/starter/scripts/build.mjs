import { cp, mkdir, rm } from "node:fs/promises";

await rm("dist", { recursive: true, force: true });
await mkdir("dist/assets", { recursive: true });
await cp("src/index.html", "dist/index.html");
await cp("src/assets", "dist/assets", { recursive: true });
await cp("mock-context.json", "dist/mock-context.json");
console.log("Built dist/");
