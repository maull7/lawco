"""
Scraper Direktori Putusan Mahkamah Agung RI (live, tanpa simpan ke disk).
Memakai curl_cffi untuk TLS fingerprint impersonation agar lolos proteksi Cloudflare portal MA.
"""

import hashlib
import json
import os
import re
import threading
import time
from typing import Dict, List, Optional

from bs4 import BeautifulSoup
from curl_cffi import requests


class MahkamahAgungScraper:
    BASE_URL = "https://putusan3.mahkamahagung.go.id"

    # Throttle global ke portal MA (dipakai bersama semua thread server): jeda minimal antar request,
    # dan setelah HTTP 429 semua request langsung ditolak sampai masa tunggu habis agar IP tidak makin diblokir.
    MIN_GAP_SECONDS = 1.0
    # Tanpa Retry-After, 429 tidak berarti boleh mencoba lagi cepat: tunggu konservatif 5 menit.
    COOLDOWN_429_SECONDS = int(os.environ.get("MA_COOLDOWN_429_SECONDS", "300"))
    _gate = threading.Lock()
    _next_start = 0.0
    _blocked_until = 0.0

    def __init__(self, impersonate: str = "chrome", timeout: int = 90, delay: float = 1.0):
        self.impersonate = impersonate
        self.timeout = timeout
        self.delay = delay
        self._local = threading.local()
        self.headers = {
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Accept-Language": "id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7",
            "DNT": "1",
            "Upgrade-Insecure-Requests": "1",
        }

    @property
    def last_error(self) -> str:
        """Error terakhir milik thread ini (server melayani beberapa request paralel)."""
        return getattr(self._local, "error", "")

    @last_error.setter
    def last_error(self, value: str) -> None:
        self._local.error = value

    @classmethod
    def blocked_seconds(cls) -> int:
        """Sisa masa tunggu setelah HTTP 429 (0 jika portal boleh diakses)."""
        with cls._gate:
            remaining = cls._blocked_until - time.monotonic()
        return int(remaining) + 1 if remaining > 0 else 0

    def portal_get(self, url: str, **kwargs):
        """GET ke portal MA lewat throttle global. Return None jika portal sedang dalam masa tunggu 429."""
        cls = MahkamahAgungScraper
        with cls._gate:
            now = time.monotonic()
            if now < cls._blocked_until:
                self.last_error = f"HTTP 429: portal MA membatasi request, coba lagi {int(cls._blocked_until - now) + 1} detik lagi"
                return None
            wait = max(0.0, cls._next_start - now)
            cls._next_start = max(now, cls._next_start) + cls.MIN_GAP_SECONDS
        if wait:
            time.sleep(wait)

        response = requests.get(url, **kwargs)
        if response.status_code == 429:
            retry_after = response.headers.get("Retry-After", "")
            seconds = int(retry_after) if retry_after.isdigit() else cls.COOLDOWN_429_SECONDS
            with cls._gate:
                cls._blocked_until = max(cls._blocked_until, time.monotonic() + seconds)
        return response

    def fetch_page(self, url: str, max_retries: int = 2) -> Optional[str]:
        """Mengambil teks HTML dengan TLS impersonation. HTTP 429 langsung gagal (tanpa retry) agar tidak memperpanjang blokir."""
        # 'chrome' terbukti profil paling handal untuk Cloudflare portal MA; profil lain (safari/edge) memicu 403.
        impersonate_profiles = ["chrome", "chrome120"]
        for attempt in range(max_retries):
            try:
                prof = impersonate_profiles[attempt % len(impersonate_profiles)]
                response = self.portal_get(
                    url,
                    headers=self.headers,
                    impersonate=prof,
                    timeout=self.timeout
                )
                if response is None:
                    return None
                if response.status_code == 200 and len(response.text) > 1000:
                    return response.text
                self.last_error = f"HTTP {response.status_code} ({len(response.text)} bytes) dari {url}"
                if response.status_code == 429:
                    wait = max(1, int(MahkamahAgungScraper._blocked_until - time.monotonic()) + 1)
                    self.last_error = f"HTTP 429: portal MA membatasi request, coba lagi {wait} detik lagi"
                    return None
                if response.status_code in (403, 502, 503):
                    time.sleep(2.0 * (attempt + 1))
                else:
                    return None
            except Exception as e:
                self.last_error = f"{type(e).__name__}: {e}"
                # Portal MA kadang butuh 10-50 detik per halaman; retry setelah timeout hanya melewati batas waktu klien Laravel.
                if "timed out" in str(e).lower():
                    return None
                time.sleep(2.0 * (attempt + 1))

        return None

    def parse_list_page(self, html_content: str) -> List[Dict]:
        """
        Mengekstrak daftar putusan dari tab utama aktif.
        """
        soup = BeautifulSoup(html_content, "html.parser")

        container = soup.find("div", id="tabs-1")
        if not container:
            container = soup.find("div", class_="col_two_third")
        if not container:
            pagination = soup.find(class_="pagination")
            if pagination:
                container = pagination.find_parent("div", class_="tab-content") or pagination.find_parent("div", class_="postcontent") or soup
            else:
                container = soup.find("div", class_="tab-content") or soup

        entries = container.find_all("div", class_="entry-c")
        results = []
        seen_urls = set()

        for entry in entries:
            try:
                link_elem = entry.find("a", href=lambda h: h and "/direktori/putusan/" in h)
                if not link_elem:
                    continue

                url_putusan = link_elem["href"]
                if not url_putusan.startswith("http"):
                    url_putusan = self.BASE_URL + url_putusan

                if any(p.name == "footer" or "col_one_third" in (p.get("class") or []) for p in entry.parents):
                    continue

                if url_putusan in seen_urls:
                    continue
                seen_urls.add(url_putusan)

                judul = link_elem.get_text(strip=True)

                pengadilan = ""
                klasifikasi = ""
                breadcrumb_div = entry.find("div", class_="small")
                if breadcrumb_div:
                    crumbs = [a.get_text(strip=True) for a in breadcrumb_div.find_all("a")]
                    if len(crumbs) > 1:
                        pengadilan = crumbs[1]
                    if len(crumbs) > 2:
                        klasifikasi = " > ".join(crumbs[2:])

                tgl_register, tgl_putus, tgl_upload = "", "", ""
                meta_divs = entry.find_all("div", class_="small")
                for mdiv in meta_divs:
                    raw_text = mdiv.get_text(separator=" ", strip=True)
                    if "Register" in raw_text or "Putus" in raw_text or "Upload" in raw_text:
                        reg_m = re.search(r"Register\s*:\s*([\d-]+)", raw_text)
                        put_m = re.search(r"Putus\s*:\s*([\d-]+)", raw_text)
                        upl_m = re.search(r"Upload\s*:\s*([\d-]+)", raw_text)
                        if reg_m: tgl_register = reg_m.group(1)
                        if put_m: tgl_putus = put_m.group(1)
                        if upl_m: tgl_upload = upl_m.group(1)

                ringkasan = ""
                detail_div = entry.find("div", class_=False)
                if detail_div:
                    raw_detail = detail_div.get_text(separator=" ", strip=True)
                    cleaned = re.sub(r'[\x00-\x1f\x7f-\x9f\ufffd]', ' ', raw_detail)
                    cleaned = re.sub(r'\s*[-—–]\s*', ' — ', cleaned)
                    cleaned = re.sub(r'\d+\s*—\s*\d+\s*$', '', cleaned)
                    ringkasan = re.sub(r'\s+', ' ', cleaned).strip()

                # Cari tahun
                tahun = 0
                year_match = re.search(r'\b(20[0-2][0-9]|19[8-9][0-9])\b', judul)
                if year_match:
                    tahun = int(year_match.group(1))
                elif tgl_putus:
                    parts = tgl_putus.split("-")
                    if len(parts) == 3 and parts[-1].isdigit():
                        tahun = int(parts[-1])

                results.append({
                    "judul": judul,
                    "url": url_putusan,
                    "pengadilan": pengadilan,
                    "klasifikasi": klasifikasi,
                    "tgl_register": tgl_register,
                    "tgl_putus": tgl_putus,
                    "tgl_upload": tgl_upload,
                    "tahun": tahun,
                    "ringkasan": ringkasan,
                })
            except Exception:
                continue

        return results

    def extract_sidebar_filters(self, soup: BeautifulSoup) -> List[Dict]:
        col = soup.find("div", class_="col_one_third")
        if not col:
            return []

        cards = []
        for card in col.find_all("div", class_="card"):
            header_el = card.find(class_="card-header")
            if not header_el:
                continue
            title = header_el.get_text(strip=True)

            table = card.find("table")
            if table and "Tahun" in title:
                thead_ths = [th.get_text(strip=True) for th in table.find_all("th")]
                tbody = table.find("tbody")
                col_groups = []
                if tbody:
                    tds = tbody.find_all("td")
                    for idx, td in enumerate(tds):
                        group_name = thead_ths[idx] if idx < len(thead_ths) else f"Kolom {idx+1}"
                        col_items = []
                        for a in td.find_all("a"):
                            href = a.get("href", "")
                            badge_el = a.find(class_=re.compile(r"badge"))
                            count_str = badge_el.get_text(strip=True) if badge_el else ""
                            if badge_el:
                                badge_el.decompose()
                            label = a.get_text(strip=True)
                            if not label or label.lower() == "selengkapnya":
                                continue
                            count_fmt = f"{int(count_str):,}".replace(",", ".") if count_str.isdigit() else count_str

                            m_kat = re.search(r"/kategori/([a-zA-Z0-9\-]+)", href)
                            m_peng = re.search(r"/pengadilan/([a-zA-Z0-9\-]+)", href)
                            m_thn = re.search(r"/tahun/(\d{4})", href)
                            m_per = re.search(r"/tahunjenis/([a-zA-Z0-9\-]+)", href)

                            periode_val = m_per.group(1) if m_per else group_name.lower()
                            tahun_val = int(m_thn.group(1)) if m_thn else (int(label) if label.isdigit() else 0)

                            col_items.append({
                                "label": label,
                                "count": count_fmt,
                                "kategori": m_kat.group(1) if m_kat else "",
                                "peradilan": ("pengadilan/" + m_peng.group(1)) if m_peng and m_peng.group(1) not in ("index", "profil") else "",
                                "tahun": tahun_val,
                                "periode": periode_val,
                                "is_active": "text-success" in a.get("class", [])
                            })
                        col_groups.append({
                            "name": group_name,
                            "periode": group_name.lower(),
                            "items": col_items
                        })

                cards.append({
                    "title": title,
                    "is_table": True,
                    "columns": col_groups
                })
                continue

            items = []
            for a in card.find_all("a"):
                href = a.get("href", "")
                full_text = a.get_text(strip=True)
                badge_el = a.find(class_=re.compile(r"badge"))
                count_str = ""
                label = ""
                is_active = "text-success" in a.get("class", [])

                if badge_el:
                    if "badge-success" in badge_el.get("class", []):
                        is_active = True
                    count_str = badge_el.get_text(strip=True)
                    badge_el.decompose()
                    label = a.get_text(strip=True)
                else:
                    m = re.search(r"^(.*?)([\d\.\,]+)$", full_text)
                    if m and len(m.group(2)) >= 4 and not m.group(1).strip().endswith("Tahun"):
                        label = m.group(1).strip()
                        count_str = m.group(2).strip()
                    else:
                        label = full_text

                label = label.strip()
                if not label:
                    continue

                if count_str.isdigit():
                    count_formatted = f"{int(count_str):,}".replace(",", ".")
                else:
                    count_formatted = count_str

                m_kat = re.search(r"/kategori/([a-zA-Z0-9\-]+)", href)
                m_peng = re.search(r"/pengadilan/([a-zA-Z0-9\-]+)", href)
                m_thn = re.search(r"/tahun/(\d{4})", href)
                m_per = re.search(r"/tahunjenis/([a-zA-Z0-9\-]+)", href)

                kategori_slug = m_kat.group(1) if m_kat else ""
                pengadilan_slug = ""
                # Card "Peradilan" menautkan ke profil lingkungan peradilan (/pengadilan/index/ditjen/<x>), bukan direktori.
                # Portal hanya punya direktori untuk Mahkamah Agung; lingkungan lain tidak bisa dipakai sebagai filter.
                m_ditjen = re.search(r"/ditjen/([a-zA-Z0-9\-]+)", href)
                if m_ditjen:
                    if m_ditjen.group(1) != "mahkamah-agung":
                        continue
                    pengadilan_slug = "pengadilan/mahkamah-agung"
                elif m_peng:
                    p_id = m_peng.group(1)
                    if p_id not in ("index", "profil"):
                        pengadilan_slug = "pengadilan/" + p_id
                    else:
                        m_all_p = re.findall(r"/pengadilan/([a-zA-Z0-9\-]+)", href)
                        if m_all_p and m_all_p[-1] not in ("index", "profil"):
                            pengadilan_slug = "pengadilan/" + m_all_p[-1]

                tahun_val = int(m_thn.group(1)) if m_thn else 0
                periode_val = m_per.group(1) if m_per else "putus"

                items.append({
                    "label": label,
                    "count": count_formatted,
                    "kategori": kategori_slug,
                    "peradilan": pengadilan_slug,
                    "tahun": tahun_val,
                    "periode": periode_val,
                    "is_active": is_active
                })

            if items and title == "Peradilan":
                # Opsi reset agar filter Mahkamah Agung bisa dilepas lagi dari sidebar.
                items.insert(0, {"label": "Semua Peradilan", "count": "", "kategori": "", "peradilan": "", "tahun": 0, "periode": "putus", "is_active": False})
            if items:
                cards.append({"title": title, "is_table": False, "items": items})

        return cards

    @staticmethod
    def count_filtered_total(sidebar_filters: List[Dict], kategori: Optional[str]) -> Optional[int]:
        """
        Total putusan sesuai filter aktif, dari card "Direktori" di sidebar portal MA.
        Angka di card itu sudah mengikuti filter tahun/pengadilan; jika klasifikasi dipilih,
        pakai angka klasifikasi tersebut, selain itu jumlahkan semua klasifikasi.
        """
        card = next((c for c in sidebar_filters if not c.get("is_table") and "Direktori" in c["title"]), None)
        if not card:
            return None

        counts = []
        for item in card["items"]:
            digits = item["count"].replace(".", "").replace(",", "")
            if not item["kategori"] or not digits.isdigit():
                continue
            if kategori and kategori != "semua":
                if item["kategori"] == kategori or item["is_active"]:
                    return int(digits)
                continue
            counts.append(int(digits))

        return sum(counts) if counts else None

    def extract_pagination_info(self, soup: BeautifulSoup, current_page: int) -> Dict:
        max_page = current_page
        pagination = soup.find(class_="pagination")

        if pagination:
            last_link = pagination.find("a", string=lambda s: s and "last" in s.lower())
            if last_link:
                ci_page = last_link.get("data-ci-pagination-page", "")
                if ci_page and ci_page.isdigit():
                    max_page = max(max_page, int(ci_page))
                else:
                    href = last_link.get("href", "")
                    matches = re.findall(r'/page/(\d+)', href)
                    if matches:
                        max_page = max(max_page, int(matches[-1]))

            if max_page == current_page:
                for a in pagination.find_all("a"):
                    ci_page = a.get("data-ci-pagination-page", "")
                    if ci_page and ci_page.isdigit():
                        max_page = max(max_page, int(ci_page))
                    else:
                        text = a.get_text(strip=True)
                        if text.isdigit():
                            max_page = max(max_page, int(text))

        return {
            "current_page": current_page,
            "max_page": max(max_page, current_page),
            "has_next": current_page < max_page,
            "has_prev": current_page > 1
        }

    # Cache daftar putusan di memori server: request ulang (mis. setelah Laravel timeout) langsung dapat hasil,
    # request yang sama saat masih diproses menunggu hasil yang sama, dan halaman berikutnya diambil di belakang layar.
    LIST_CACHE_SECONDS = 6 * 3600
    LIST_CACHE_MAX_ENTRIES = 3000
    # Disimpan juga ke disk agar tidak hilang saat scraper di-restart.
    LIST_CACHE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".cache", "putusan")
    # Prefetch halaman berikutnya dimatikan default: menambah request ke portal dan memperbesar risiko 429.
    PREFETCH_NEXT = os.environ.get("MA_PREFETCH_NEXT", "0") == "1"
    _list_cache: Dict = {}
    _inflight: Dict = {}
    _cache_lock = threading.Lock()

    def cached_live_page(
        self,
        kategori: Optional[str] = None,
        peradilan: Optional[str] = None,
        tahun: Optional[int] = None,
        periode: str = "putus",
        page: int = 1,
        prefetch_next: Optional[bool] = None,
    ) -> Dict:
        cls = MahkamahAgungScraper
        key = (kategori or "", peradilan or "", tahun or 0, periode, page)
        if prefetch_next is None:
            prefetch_next = cls.PREFETCH_NEXT
        cache_file = os.path.join(cls.LIST_CACHE_DIR, hashlib.md5(json.dumps(key).encode()).hexdigest() + ".json")

        with cls._cache_lock:
            hit = cls._list_cache.get(key)
            if not hit and os.path.exists(cache_file):
                try:
                    with open(cache_file, encoding="utf-8") as fh:
                        stored = json.load(fh)
                    hit = (stored["expires_at"], stored["result"])
                    cls._list_cache[key] = hit
                except (OSError, ValueError, KeyError):
                    hit = None
            if hit and hit[0] > time.time():
                return hit[1]
            event = cls._inflight.get(key)
            is_owner = event is None
            if is_owner:
                event = threading.Event()
                cls._inflight[key] = event

        if not is_owner:
            event.wait(self.timeout + 15)
            with cls._cache_lock:
                hit = cls._list_cache.get(key)
            if hit:
                return hit[1]
            return {
                "success": False,
                "error": "Gagal membuka portal MA: permintaan yang sama baru saja gagal, coba lagi beberapa detik lagi",
                "items": [],
                "pagination": {"current_page": page, "max_page": 1, "has_next": False, "has_prev": page > 1},
            }

        try:
            result = self.fetch_live_page(kategori=kategori, peradilan=peradilan, tahun=tahun, periode=periode, page=page)
            if result.get("success") and result.get("items"):
                with cls._cache_lock:
                    # ponytail: dikosongkan total saat penuh; ganti LRU jika memori server terbatas.
                    if len(cls._list_cache) >= cls.LIST_CACHE_MAX_ENTRIES:
                        cls._list_cache.clear()
                    cls._list_cache[key] = (time.time() + cls.LIST_CACHE_SECONDS, result)
                try:
                    os.makedirs(cls.LIST_CACHE_DIR, exist_ok=True)
                    with open(cache_file + ".tmp", "w", encoding="utf-8") as fh:
                        json.dump({"expires_at": time.time() + cls.LIST_CACHE_SECONDS, "result": result}, fh)
                    os.replace(cache_file + ".tmp", cache_file)
                except OSError:
                    pass
                if prefetch_next and cls.blocked_seconds() == 0 and result.get("pagination", {}).get("has_next"):
                    threading.Thread(
                        target=self.cached_live_page,
                        kwargs={"kategori": kategori, "peradilan": peradilan, "tahun": tahun, "periode": periode, "page": page + 1, "prefetch_next": False},
                        daemon=True,
                    ).start()
            return result
        finally:
            with cls._cache_lock:
                cls._inflight.pop(key, None)
            event.set()

    def fetch_live_page(
        self,
        kategori: Optional[str] = None,
        peradilan: Optional[str] = None,
        tahun: Optional[int] = None,
        periode: str = "putus",
        page: int = 1
    ) -> Dict:
        """
        Mengambil 1 halaman tertentu secara live tanpa menyimpannya ke disk (Real-Time Live Streaming).
        """
        is_semua_kat = not kategori or kategori in (None, "", "semua")
        is_semua_peng = not peradilan or peradilan in (None, "", "semua")

        if not is_semua_peng:
            pengadilan_clean = peradilan.replace("pengadilan/", "")
            base_url = f"{self.BASE_URL}/direktori/index/pengadilan/{pengadilan_clean}"
            if not is_semua_kat:
                base_url += f"/kategori/{kategori}"
            if tahun and tahun > 0:
                base_url += f"/tahunjenis/{periode}/tahun/{tahun}"
        elif not is_semua_kat:
            base_url = f"{self.BASE_URL}/direktori/index/kategori/{kategori}"
            if tahun and tahun > 0:
                base_url += f"/tahunjenis/{periode}/tahun/{tahun}"
        elif tahun and tahun > 0:
            base_url = f"{self.BASE_URL}/direktori/index/tahunjenis/{periode}/tahun/{tahun}"
        else:
            base_url = f"{self.BASE_URL}/direktori/index"

        if is_semua_kat and is_semua_peng and not (tahun and tahun > 0):
            if page <= 1:
                target_url = f"{self.BASE_URL}/direktori.html"
            else:
                target_url = f"{self.BASE_URL}/direktori/index/page/{page}.html"
        else:
            if page <= 1:
                target_url = f"{base_url}.html"
            else:
                target_url = f"{base_url}/page/{page}.html"

        html = self.fetch_page(target_url)
        if not html:
            return {
                "success": False,
                "error": f"Gagal membuka portal MA: {self.last_error}",
                "url": target_url,
                "items": [],
                "pagination": {"current_page": page, "max_page": 1, "has_next": False, "has_prev": page > 1}
            }

        soup = BeautifulSoup(html, "html.parser")
        items = self.parse_list_page(html)
        pagination_info = self.extract_pagination_info(soup, page)

        sidebar_filters = self.extract_sidebar_filters(soup)
        total_decisions = self.count_filtered_total(sidebar_filters, kategori)

        return {
            "success": True,
            "url": target_url,
            "items": items,
            "sidebar_filters": sidebar_filters,
            "total_count": total_decisions,
            "pagination": pagination_info
        }


    def download_pdf(self, decision_url: str) -> Dict:
        """
        Mengambil URL download PDF dari halaman detail putusan dan mengembalikan isi filenya (bytes).
        """
        html = self.fetch_page(decision_url)
        if not html:
            return {"success": False, "error": f"Gagal membuka halaman putusan MA: {self.last_error}"}

        soup = BeautifulSoup(html, "html.parser")
        pdf_url = None
        pdf_name = "putusan.pdf"

        for a in soup.find_all("a", href=True):
            href = a["href"]
            if "/download_file/" in href and "/pdf/" in href:
                pdf_url = href
                text = a.get_text(strip=True)
                if text.endswith(".pdf"):
                    pdf_name = text
                break

        if not pdf_url:
            return {"success": False, "not_found": True, "error": "Link download PDF tidak ditemukan pada putusan ini"}

        try:
            # Tanpa self.headers: header khusus HTML (Accept text/html dll.) membuat portal MA membalas 403 untuk file PDF.
            res = self.portal_get(
                pdf_url,
                impersonate=self.impersonate,
                timeout=45
            )
            if res is None:
                return {"success": False, "error": self.last_error}
            # Putusan tanpa lampiran tetap punya link download, tetapi portal MA membalasnya dengan halaman HTML.
            if res.status_code == 200 and not res.content.lstrip().startswith(b"%PDF"):
                return {"success": False, "not_found": True, "error": "Portal MA belum menyediakan lampiran PDF untuk putusan ini"}
            if res.status_code == 200 and len(res.content) > 500:
                return {"success": True, "pdf_url": pdf_url, "filename": pdf_name, "content": res.content}
            return {"success": False, "error": f"HTTP status {res.status_code} saat mengunduh PDF"}
        except Exception as e:
            return {"success": False, "error": str(e)}
