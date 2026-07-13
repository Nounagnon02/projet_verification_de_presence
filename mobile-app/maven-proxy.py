#!/usr/bin/env python3
"""
Simple Maven proxy that downloads artifacts using wget (which works)
and serves them to Gradle (which has TLS issues in this environment).

Uses wget --spider for HEAD requests (fast) and wget for GET (streamed).
"""
import http.server
import urllib.parse
import subprocess
import os
import sys
import hashlib
import shutil
import re
import tempfile

CACHE_DIR = "/tmp/maven-proxy-cache"
PORT = 8888
MAVEN_BASE = "https://repo.maven.apache.org/maven2"
GOOGLE_MAVEN = "https://dl.google.com/dl/android/maven2"

os.makedirs(CACHE_DIR, exist_ok=True)

def get_remote_url(path):
    """Map local path to remote URL and choose cache storage."""
    if path.startswith("/maven2/"):
        return MAVEN_BASE + path[len("/maven2"):]
    elif path.startswith("/android/"):
        return GOOGLE_MAVEN + path[len("/android"):]
    return None

def get_content_type(path):
    if path.endswith(".aar"):
        return "application/octet-stream"
    elif path.endswith(".pom"):
        return "application/xml"
    elif path.endswith(".jar"):
        return "application/java-archive"
    elif path.endswith(".module"):
        return "application/json"
    elif path.endswith(".gradle"):
        return "text/plain"
    elif path.endswith(".properties"):
        return "text/plain"
    elif path.endswith(".sha1"):
        return "text/plain; charset=utf-8"
    elif path.endswith(".md5"):
        return "text/plain; charset=utf-8"
    return "application/octet-stream"

def check_remote(remote_url, timeout=15):
    """Quick HEAD check using wget --spider. Returns True, content_length or False, None."""
    try:
        result = subprocess.run(
            ["wget", "--spider", "-T", str(timeout), "-o", "/dev/stderr", remote_url],
            capture_output=True, text=True, timeout=timeout + 5
        )
        if result.returncode == 0:
            # Try to extract size from output
            for line in result.stderr.split("\n"):
                if "Length:" in line:
                    m = re.search(r'Length:\s*(\d+)', line)
                    if m:
                        return True, int(m.group(1))
                if "Taille" in line or "Size" in line.lower():
                    m = re.search(r':?\s*(\d+)\s*\(', line)
                    if m:
                        return True, int(m.group(1))
            return True, -1
        return False, None
    except (subprocess.TimeoutExpired, Exception) as e:
        return False, None


class MavenProxyHandler(http.server.BaseHTTPRequestHandler):
    def do_HEAD(self):
        self._handle(head_only=True)

    def do_GET(self):
        self._handle(head_only=False)

    def _handle(self, head_only=False):
        parsed = urllib.parse.urlparse(self.path)
        path = parsed.path

        remote_url = get_remote_url(path)
        if not remote_url:
            self.send_response(404)
            self.end_headers()
            return

        cache_key = hashlib.md5(remote_url.encode()).hexdigest()
        cache_file = os.path.join(CACHE_DIR, cache_key)
        cache_meta = cache_file + ".meta"
        content_type = get_content_type(path)

        # Check if cached
        if os.path.exists(cache_file) and os.path.exists(cache_meta):
            size = os.path.getsize(cache_file)
            self.send_response(200)
            self.send_header("Content-Type", content_type)
            self.send_header("Content-Length", str(size))
            self.end_headers()
            if not head_only:
                with open(cache_file, "rb") as f:
                    shutil.copyfileobj(f, self.wfile)
            self.log_message(f"CACHED {path} ({size} bytes)")
            return

        # Not cached. For HEAD, do a quick spider check.
        if head_only:
            exists, length = check_remote(remote_url)
            if exists:
                self.send_response(200)
                if length > 0:
                    self.send_header("Content-Length", str(length))
                self.end_headers()
                self.log_message(f"CHECK {path} exists, size={length}")
            else:
                self.send_response(404)
                self.end_headers()
                self.log_message(f"CHECK {path} NOT FOUND")
            return

        # GET: Download with wget to temp file, then cache and serve
        self.log_message(f"DOWNLOADING {remote_url}")
        tmp_file = cache_file + ".tmp." + next(tempfile._get_candidate_names())
        try:
            result = subprocess.run(
                ["wget", "-q", "-O", tmp_file, "--no-check-certificate", remote_url],
                capture_output=True, text=True, timeout=300
            )
            if result.returncode != 0:
                self.send_response(404)
                self.end_headers()
                self.log_message(f"FAILED {path}: {result.stderr[:200]}")
                if os.path.exists(tmp_file):
                    os.unlink(tmp_file)
                return

            size = os.path.getsize(tmp_file)
            self.send_response(200)
            self.send_header("Content-Type", content_type)
            self.send_header("Content-Length", str(size))
            self.end_headers()

            with open(tmp_file, "rb") as f:
                shutil.copyfileobj(f, self.wfile)
            self.wfile.flush()

            # Cache
            os.rename(tmp_file, cache_file)
            with open(cache_meta, "w") as f:
                f.write(content_type)
            self.log_message(f"SERVED {path} ({size} bytes)")
        except subprocess.TimeoutExpired:
            self.send_response(504)
            self.end_headers()
            self.log_message(f"TIMEOUT {path}")
            if os.path.exists(tmp_file):
                os.unlink(tmp_file)
        except Exception as e:
            self.send_response(500)
            self.end_headers()
            self.log_message(f"ERROR {path}: {e}")
            if os.path.exists(tmp_file):
                os.unlink(tmp_file)

    def log_message(self, format, *args):
        print(f"[{self.command}] {args[0] if args else ''}", flush=True)


if __name__ == "__main__":
    server = http.server.HTTPServer(("127.0.0.1", PORT), MavenProxyHandler)
    print(f"Maven proxy running on http://127.0.0.1:{PORT}", flush=True)
    print(f"  /maven2/ -> {MAVEN_BASE}", flush=True)
    print(f"  /android/ -> {GOOGLE_MAVEN}", flush=True)
    server.serve_forever()
