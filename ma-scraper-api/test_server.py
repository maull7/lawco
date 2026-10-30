"""Self-check server tanpa akses jaringan ke portal MA: python -m unittest test_server"""

import json
import threading
import unittest
import urllib.error
import urllib.request

from scraper import MahkamahAgungScraper
from server import create_server


class FakeScraper:
    def __init__(self):
        self.calls = []

    def fetch_live_page(self, **kwargs):
        self.calls.append(kwargs)
        return {"success": True, "items": [{"judul": "Putusan X"}], "pagination": {}}

    def download_pdf(self, url):
        return {"success": True, "filename": "putusan x.pdf", "content": b"%PDF-1.4" + b"x" * 600}


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
