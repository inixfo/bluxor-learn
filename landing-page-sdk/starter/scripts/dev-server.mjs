import http from "node:http";
import { readFile } from "node:fs/promises";
import path from "node:path";

const port = Number(process.env.PORT || 4174);
const root = path.resolve("src");

http.createServer(async (req, res) => {
  const url = req.url === "/" ? "/index.html" : req.url;
  const file = url === "/mock-context.json" ? path.resolve("mock-context.json") : path.join(root, url);
  try {
    const body = await readFile(file);
    res.end(body);
  } catch {
    res.statusCode = 404;
    res.end("Not found");
  }
}).listen(port, () => console.log(`Starter running at http://127.0.0.1:${port}`));
