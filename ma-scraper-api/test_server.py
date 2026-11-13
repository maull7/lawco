"""Self-check server tanpa akses jaringan ke portal MA: python -m unittest test_server"""

import json
import threading
import unittest
import unittest.mock
import urllib.error
import urllib.request

from curl_cffi.requests import Headers

from scraper import MahkamahAgungScraper
from server import create_server


class FakeScraper:
    def __init__(self):
        self.calls = []

    def fetch_live_page(self, **kwargs):
        self.calls.append(kwargs)
        return {"success": True, "items": [{"judul": "Putusan X"}], "pagination": {}}

    def cached_live_page(self, **kwargs):
        return self.fetch_live_page(**kwargs)

    def download_pdf(self, url):
        return {"success": True, "filename": "putusan x.pdf", "content": b"%PDF-1.4" + b"x" * 600}

    def blocked_seconds(self):
        return 0


class ServerTest(unittest.TestCase):
    def setUp(self):
        self.scraper = FakeScraper()
        self.server = create_server(self.scraper, "127.0.0.1", 0, "secret")
        self.base = f"http://127.0.0.1:{self.server.server_address[1]}"
        threading.Thread(target=self.server.serve_forever, daemon=True).start()

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()

    def get(self, path, token="secret"):
        request = urllib.request.Request(self.base + path, headers={"X-API-Key": token} if token else {})
        try:
            with urllib.request.urlopen(request) as response:
                return response.status, response.headers, response.read()
        except urllib.error.HTTPError as e:
            return e.code, e.headers, e.read()

    def test_health_is_public(self):
        self.assertEqual(self.get("/health", token=None)[0], 200)

    def test_missing_or_wrong_token_is_rejected(self):
        self.assertEqual(self.get("/putusan", token=None)[0], 401)
        self.assertEqual(self.get("/putusan", token="wrong")[0], 401)

    def test_putusan_passes_filters_to_scraper(self):
        status, _, body = self.get("/putusan?page=3&tahun=2026&kategori=pidana")
        self.assertEqual(status, 200)
        self.assertEqual(json.loads(body)["items"][0]["judul"], "Putusan X")
        self.assertEqual(self.scraper.calls[0], {
            "kategori": "pidana", "peradilan": None, "tahun": 2026,
            "periode": "putus", "page": 3,
        })

    def test_invalid_numbers_fall_back_to_defaults(self):
        self.get("/putusan?page=abc&tahun=-1")
        self.assertEqual((self.scraper.calls[0]["page"], self.scraper.calls[0]["tahun"]), (1, None))

    def test_pdf_only_allows_mahkamah_agung_hosts(self):
        for url in ("https://evil.com/x", "https://evilmahkamahagung.go.id/x", "file:///etc/passwd", ""):
            self.assertEqual(self.get("/pdf?url=" + url)[0], 400, url)

    def test_pdf_is_streamed(self):
        status, headers, body = self.get("/pdf?url=https://putusan3.mahkamahagung.go.id/direktori/putusan/a.html")
        self.assertEqual(status, 200)
        self.assertEqual(headers["Content-Type"], "application/pdf")
        self.assertEqual(headers["X-Filename"], "putusan%20x.pdf")
        self.assertTrue(body.startswith(b"%PDF"))

    def test_unknown_path_is_404(self):
        self.assertEqual(self.get("/nope")[0], 404)

    def test_missing_pdf_attachment_is_404_with_reason(self):
        self.scraper.download_pdf = lambda url: {"success": False, "not_found": True, "error": "Portal MA belum menyediakan lampiran PDF untuk putusan ini"}
        status, _, body = self.get("/pdf?url=https://putusan3.mahkamahagung.go.id/direktori/putusan/a.html")
        self.assertEqual(status, 404)
        self.assertEqual(json.loads(body)["error"], "Portal MA belum menyediakan lampiran PDF untuk putusan ini")

    def test_other_pdf_failures_stay_502(self):
        self.scraper.download_pdf = lambda url: {"success": False, "error": "HTTP status 403 saat mengunduh PDF"}
        self.assertEqual(self.get("/pdf?url=https://putusan3.mahkamahagung.go.id/direktori/putusan/a.html")[0], 502)

    def test_portal_timeout_is_504(self):
        self.scraper.download_pdf = lambda url: {"success": False, "error": "Failed to perform, curl: (28) Operation timed out after 45001 milliseconds with 0 bytes received."}
        status, _, body = self.get("/pdf?url=https://putusan3.mahkamahagung.go.id/direktori/putusan/a.html")
        self.assertEqual(status, 504)
        self.assertTrue(json.loads(body)["timeout"])

    def test_pdf_failure_while_portal_blocked_is_429_with_retry_after(self):
        self.scraper.download_pdf = lambda url: {"success": False, "error": "HTTP 403: verifikasi bot Cloudflare portal MA"}
        self.scraper.blocked_seconds = lambda: 120
        status, headers, body = self.get("/pdf?url=https://putusan3.mahkamahagung.go.id/direktori/putusan/a.html")
        self.assertEqual(status, 429)
        self.assertEqual(headers["Retry-After"], "120")
        self.assertEqual(json.loads(body)["retry_after"], 120)


class DownloadPdfTest(unittest.TestCase):
    page = '<a href="https://putusan3.mahkamahagung.go.id/direktori/download_file/abc/pdf/x">putusan x.pdf</a>'

    def download(self, body):
        scraper = MahkamahAgungScraper()
        response = unittest.mock.Mock(status_code=200, content=body, headers=Headers())
        with unittest.mock.patch.object(scraper, "fetch_page", return_value=self.page), \
                unittest.mock.patch("scraper.requests.get", return_value=response):
            return scraper.download_pdf("https://putusan3.mahkamahagung.go.id/direktori/putusan/a.html")

    def test_real_pdf_is_returned(self):
        result = self.download(b"%PDF-1.7" + b"x" * 600)
        self.assertTrue(result["success"])
        self.assertEqual(result["filename"], "putusan x.pdf")

    def tearDown(self):
        MahkamahAgungScraper._pdf_links = {}
        MahkamahAgungScraper._next_start = 0.0
        MahkamahAgungScraper._gap_seconds = MahkamahAgungScraper.MIN_GAP_SECONDS

    def test_retry_reuses_pdf_link_without_opening_detail_page_again(self):
        scraper = MahkamahAgungScraper()
        response = unittest.mock.Mock(status_code=200, content=b"%PDF-1.7" + b"x" * 600, headers=Headers())
        with unittest.mock.patch.object(scraper, "fetch_page", return_value=self.page) as fetch_page, \
                unittest.mock.patch("scraper.requests.get", side_effect=[Exception("Operation timed out"), response]):
            first = scraper.download_pdf("https://putusan3.mahkamahagung.go.id/direktori/putusan/a.html")
            second = scraper.download_pdf("https://putusan3.mahkamahagung.go.id/direktori/putusan/a.html")
        self.assertFalse(first["success"])
        self.assertTrue(second["success"])
        self.assertEqual(fetch_page.call_count, 1)

    def test_html_instead_of_pdf_is_reported_as_missing_attachment(self):
        result = self.download(b"<!DOCTYPE html><html>" + b"x" * 600)
        self.assertFalse(result["success"])
        self.assertTrue(result["not_found"])
        self.assertEqual(result["error"], "Portal MA belum menyediakan lampiran PDF untuk putusan ini")


class CloudflareChallengeTest(unittest.TestCase):
    def tearDown(self):
        MahkamahAgungScraper._blocked_until = 0.0
        MahkamahAgungScraper._next_start = 0.0
        MahkamahAgungScraper._cookies = {}
        MahkamahAgungScraper._gap_seconds = MahkamahAgungScraper.MIN_GAP_SECONDS

    def test_portal_cookies_are_sent_back_on_next_request(self):
        scraper = MahkamahAgungScraper()
        first = unittest.mock.Mock(status_code=200, headers=Headers([("set-cookie", "__cf_bm=abc; HttpOnly; Path=/")]))
        second = unittest.mock.Mock(status_code=200, headers=Headers())
        with unittest.mock.patch("scraper.requests.get", side_effect=[first, second]) as get:
            scraper.portal_get("https://putusan3.mahkamahagung.go.id/a.html")
            scraper.portal_get("https://putusan3.mahkamahagung.go.id/b.html")
        self.assertIsNone(get.call_args_list[0].kwargs["cookies"])
        self.assertEqual(get.call_args_list[1].kwargs["cookies"], {"__cf_bm": "abc"})

    def fetch(self, status, headers):
        scraper = MahkamahAgungScraper()
        response = unittest.mock.Mock(status_code=status, headers=Headers(headers), text="x" * 6107)
        with unittest.mock.patch("scraper.requests.get", return_value=response) as get:
            html = scraper.fetch_page("https://putusan3.mahkamahagung.go.id/direktori.html")
        return scraper, html, get.call_count

    def test_gap_doubles_when_portal_slows_and_recovers_after_normal_responses(self):
        MahkamahAgungScraper.adjust_gap(slow=True)
        MahkamahAgungScraper.adjust_gap(slow=True)
        self.assertEqual(MahkamahAgungScraper._gap_seconds, MahkamahAgungScraper.MIN_GAP_SECONDS * 4)
        for _ in range(10):
            MahkamahAgungScraper.adjust_gap(slow=False)
        self.assertEqual(MahkamahAgungScraper._gap_seconds, MahkamahAgungScraper.MIN_GAP_SECONDS)

    def test_timeout_raises_gap(self):
        with unittest.mock.patch("scraper.requests.get", side_effect=Exception("Operation timed out")):
            with self.assertRaises(Exception):
                MahkamahAgungScraper().portal_get("https://putusan3.mahkamahagung.go.id/a.html")
        self.assertEqual(MahkamahAgungScraper._gap_seconds, MahkamahAgungScraper.MIN_GAP_SECONDS * 2)

    def test_challenge_blocks_portal_without_retrying(self):
        scraper, html, calls = self.fetch(403, {"cf-mitigated": "challenge"})
        self.assertIsNone(html)
        self.assertEqual(calls, 1)
        self.assertGreater(scraper.blocked_seconds(), 0)
        self.assertIn("verifikasi bot Cloudflare", scraper.last_error)

    def test_plain_403_does_not_block_portal(self):
        scraper, html, calls = self.fetch(403, {})
        self.assertIsNone(html)
        self.assertEqual(calls, 2)
        self.assertEqual(scraper.blocked_seconds(), 0)


class CountFilteredTotalTest(unittest.TestCase):
    sidebar = [
        {"title": "Direktori", "is_table": False, "items": [
            {"label": "Semua Direktori", "count": "", "kategori": "", "is_active": False},
            {"label": "Perdata", "count": "121.375", "kategori": "perdata-1", "is_active": False},
            {"label": "Pidana Umum", "count": "77.647", "kategori": "pidana-umum-1", "is_active": True},
        ]},
        {"title": "Tahun", "is_table": True, "columns": []},
    ]

    def test_selected_category_uses_its_own_count(self):
        self.assertEqual(MahkamahAgungScraper.count_filtered_total(self.sidebar, "pidana-umum-1"), 77647)

    def test_no_category_sums_all_categories(self):
        self.assertEqual(MahkamahAgungScraper.count_filtered_total(self.sidebar, None), 199022)
        self.assertEqual(MahkamahAgungScraper.count_filtered_total(self.sidebar, "semua"), 199022)

    def test_missing_directory_card_returns_none(self):
        self.assertIsNone(MahkamahAgungScraper.count_filtered_total([], None))


if __name__ == "__main__":
    unittest.main()
