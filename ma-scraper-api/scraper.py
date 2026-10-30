"""
Scraper Direktori Putusan Mahkamah Agung RI (live, tanpa simpan ke disk).
Memakai curl_cffi untuk TLS fingerprint impersonation agar lolos proteksi Cloudflare portal MA.
"""

import re
import time
from typing import Dict, List, Optional

from bs4 import BeautifulSoup
from curl_cffi import requests


class MahkamahAgungScraper:
    BASE_URL = "https://putusan3.mahkamahagung.go.id"

    def __init__(self, impersonate: str = "chrome", timeout: int = 30, delay: float = 1.0):
        self.impersonate = impersonate
        self.timeout = timeout
        self.delay = delay
        self.last_error = ""
        self.headers = {
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Accept-Language": "id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7",
            "DNT": "1",
            "Upgrade-Insecure-Requests": "1",
        }

    def fetch_page(self, url: str, max_retries: int = 3) -> Optional[str]:
        """Mengambil teks HTML dengan TLS impersonation dan penanganan retry saat rate limit."""
        impersonate_profiles = ["chrome", "safari15_5", "chrome124"]
        for attempt in range(max_retries):
            try:
                prof = impersonate_profiles[attempt % len(impersonate_profiles)]
                response = requests.get(
                    url,
                    headers=self.headers,
                    impersonate=prof,
                    timeout=self.timeout
                )
                if response.status_code == 200 and len(response.text) > 1000:
                    return response.text
                self.last_error = f"HTTP {response.status_code} ({len(response.text)} bytes) dari {url}"
                if response.status_code in (429, 403, 503):
                    time.sleep(1.0 * (attempt + 1))
                else:
                    return None
            except Exception as e:
                self.last_error = f"{type(e).__name__}: {e}"
                time.sleep(1.0 * (attempt + 1))

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
                if m_peng:
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
            return {"success": False, "error": "Link download PDF tidak ditemukan pada putusan ini"}

        try:
            # Tanpa self.headers: header khusus HTML (Accept text/html dll.) membuat portal MA membalas 403 untuk file PDF.
            res = requests.get(
                pdf_url,
                impersonate=self.impersonate,
                timeout=45
            )
            if res.status_code == 200 and len(res.content) > 500:
                return {"success": True, "pdf_url": pdf_url, "filename": pdf_name, "content": res.content}
            return {"success": False, "error": f"HTTP status {res.status_code} saat mengunduh PDF"}
        except Exception as e:
            return {"success": False, "error": str(e)}
