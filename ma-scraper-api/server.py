"""
MA Scraper API: backend HTTP terpisah untuk scraper putusan Mahkamah Agung.

Mode jalan (lihat .env.example):
    python server.py                      # lokal: http://127.0.0.1:8001
    python server.py --tunnel ngrok       # lokal + URL publik ngrok
    python server.py --tunnel cloudflare  # lokal + URL publik Cloudflare Tunnel

Endpoint:
    GET /health
    GET /putusan?page=&kategori=&peradilan=&periode=&tahun=
    GET /pdf?url=<url putusan mahkamahagung.go.id>

Semua endpoint selain /health wajib header X-API-Key jika MA_SCRAPER_TOKEN di-set.
"""

import argparse
import atexit
import hmac
import json
import os
import re
import subprocess
import sys
import threading
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Dict, Optional

from scraper import MahkamahAgungScraper

BASE_DIR = os.path.dirname(os.path.abspath(__file__))


def load_env_file(path: str) -> None:
    """Muat KEY=VALUE dari file .env tanpa menimpa environment variable yang sudah ada."""
    if not os.path.exists(path):
        return
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            os.environ.setdefault(key.strip(), value.strip().strip("\"'"))


def is_ma_url(url: str) -> bool:
    host = urllib.parse.urlparse(url).hostname or ""
    return host == "mahkamahagung.go.id" or host.endswith(".mahkamahagung.go.id")


def create_server(scraper: MahkamahAgungScraper, host: str, port: int, token: str) -> ThreadingHTTPServer:
    class Handler(BaseHTTPRequestHandler):
        def send_json(self, status: int, data: Dict) -> None:
            body = json.dumps(data, ensure_ascii=True).encode()
            self.send_response(status)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)

        def do_GET(self) -> None:
            parsed = urllib.parse.urlparse(self.path)
            params = {k: v[0].strip() for k, v in urllib.parse.parse_qs(parsed.query).items()}

            if parsed.path == "/health":
                return self.send_json(200, {"ok": True})

            if token and not hmac.compare_digest(self.headers.get("X-API-Key", ""), token):
                return self.send_json(401, {"success": False, "error": "Unauthorized"})

            if parsed.path == "/putusan":
                def to_int(key: str, default: int) -> int:
                    value = params.get(key, "")
                    return int(value) if value.isdigit() else default

                tahun = to_int("tahun", 0)
                result = scraper.fetch_live_page(
                    kategori=params.get("kategori") or None,
                    peradilan=params.get("peradilan") or None,
                    tahun=tahun if tahun > 0 else None,
                    periode=params.get("periode") or "putus",
                    page=max(1, to_int("page", 1)),
                )
                return self.send_json(200 if result["success"] else 502, result)

            if parsed.path == "/pdf":
                url = params.get("url", "")
                if not is_ma_url(url):
                    return self.send_json(400, {"success": False, "error": "URL harus dari mahkamahagung.go.id"})

                result = scraper.download_pdf(url)
                if not result["success"]:
                    return self.send_json(502, result)
                self.send_response(200)
                self.send_header("Content-Type", "application/pdf")
                self.send_header("Content-Length", str(len(result["content"])))
                self.send_header("X-Filename", urllib.parse.quote(result["filename"]))
                self.end_headers()
                self.wfile.write(result["content"])
                return

            self.send_json(404, {"success": False, "error": "Not found"})

    return ThreadingHTTPServer((host, port), Handler)


def start_tunnel(kind: str, port: int) -> subprocess.Popen:
    """Jalankan ngrok / cloudflared sebagai child process dan cetak URL publiknya begitu muncul."""
    local_url = f"http://127.0.0.1:{port}"

    if kind == "ngrok":
        cmd = ["ngrok", "http", local_url, "--log=stdout", "--log-format=json"]
        if os.environ.get("NGROK_URL"):
            cmd += ["--url", os.environ["NGROK_URL"]]
        if os.environ.get("NGROK_AUTHTOKEN"):
            cmd += ["--authtoken", os.environ["NGROK_AUTHTOKEN"]]
        url_pattern = re.compile(r'"url":"(https://[^"]+)"')
    else:
        cmd = ["cloudflared", "tunnel", "--no-autoupdate"]
        if os.environ.get("CLOUDFLARE_TUNNEL_TOKEN"):
            # Named tunnel: hostname publik diatur di dashboard Cloudflare (Public Hostname -> localhost:PORT).
            cmd += ["run", "--token", os.environ["CLOUDFLARE_TUNNEL_TOKEN"]]
        else:
            cmd += ["--url", local_url]
        url_pattern = re.compile(r"(https://[a-z0-9-]+\.trycloudflare\.com)")

    try:
        process = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
    except FileNotFoundError:
        sys.exit(f"'{cmd[0]}' tidak ditemukan di PATH. Install dulu lalu jalankan ulang.")
    atexit.register(process.terminate)

    def watch_output() -> None:
        announced = False
        for line in process.stdout:
            match = url_pattern.search(line)
            if match and not announced:
                announced = True
                print(f"\n[{kind}] URL publik: {match.group(1)}")
                print(f"[{kind}] Isi di .env Laravel: MA_SCRAPER_URL={match.group(1)}\n", flush=True)
            elif "error" in line.lower() or "ERR" in line:
                print(f"[{kind}] {line.rstrip()}", flush=True)
        print(f"[{kind}] proses tunnel berhenti (exit {process.wait()})", flush=True)

    threading.Thread(target=watch_output, daemon=True).start()
    if kind == "cloudflare" and os.environ.get("CLOUDFLARE_TUNNEL_TOKEN"):
        print("[cloudflare] Named tunnel aktif: pakai hostname yang diatur di dashboard Cloudflare.")
    return process


def main(argv: Optional[list] = None) -> None:
    load_env_file(os.path.join(BASE_DIR, ".env"))

    parser = argparse.ArgumentParser(description="MA Scraper API")
    parser.add_argument("--host", default=os.environ.get("HOST", "127.0.0.1"))
    parser.add_argument("--port", type=int, default=int(os.environ.get("PORT", "8001")))
    parser.add_argument("--tunnel", choices=["none", "ngrok", "cloudflare"], default=os.environ.get("TUNNEL", "none"))
    args = parser.parse_args(argv)

    token = os.environ.get("MA_SCRAPER_TOKEN", "")
    if args.tunnel != "none" and not token:
        sys.exit("MA_SCRAPER_TOKEN wajib diisi sebelum membuka tunnel publik (contoh: openssl rand -hex 32).")

    server = create_server(MahkamahAgungScraper(impersonate="chrome"), args.host, args.port, token)
    print(f"MA Scraper API: http://{args.host}:{args.port} (token {'aktif' if token else 'NONAKTIF'}, tunnel {args.tunnel})", flush=True)

    if args.tunnel != "none":
        start_tunnel(args.tunnel, args.port)

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()


if __name__ == "__main__":
    main()
