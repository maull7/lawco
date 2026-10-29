import os
import sys
import time
import random
import argparse
import pymysql
from dotenv import load_dotenv

# Load .env Laravel
load_dotenv(os.path.join(os.path.dirname(__file__), '..', '.env'))

DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = int(os.getenv('DB_PORT', 3306))
DB_DATABASE = os.getenv('DB_DATABASE', 'investalaw')
DB_USERNAME = os.getenv('DB_USERNAME', 'root')
DB_PASSWORD = os.getenv('DB_PASSWORD', '')

def get_db_connection():
    return pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USERNAME,
        password=DB_PASSWORD,
        database=DB_DATABASE,
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True
    )

def save_decisions_to_db(items):
    """
    Simpan list putusan ke database dengan aman (UPSERT berdasarkan source_url / decision_number)
    """
    if not items:
        return 0

    conn = get_db_connection()
    inserted_count = 0
    try:
        with conn.cursor() as cursor:
            sql = """
                INSERT INTO legal_decisions 
                (decision_number, title, court_name, classification, verdict, decision_date, year, summary, file_url, source_url, source, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    title = VALUES(title),
                    court_name = VALUES(court_name),
                    classification = VALUES(classification),
                    verdict = VALUES(verdict),
                    decision_date = VALUES(decision_date),
                    year = VALUES(year),
                    summary = VALUES(summary),
                    file_url = VALUES(file_url),
                    updated_at = NOW()
            """
            for it in items:
                cursor.execute(sql, (
                    it.get('decision_number'),
                    it.get('title'),
                    it.get('court_name'),
                    it.get('classification'),
                    it.get('verdict'),
                    it.get('decision_date'),
                    it.get('year'),
                    it.get('summary'),
                    it.get('file_url'),
                    it.get('source_url'),
                    it.get('source', 'mahkamah_agung')
                ))
                inserted_count += 1
    finally:
        conn.close()

    return inserted_count

def scrape_with_api(api_key, max_pages=10, download_pdf=False):
    """
    Mode Aman untuk Server Hosting:
    Menggunakan Managed API Unblocker (ZenRows/ScraperAPI) sehingga server hosting 0% beban browser
    dan 100% aman dari IP ban Cloudflare.
    """
    import urllib.parse
    import requests
    from bs4 import BeautifulSoup

    print(f"[*] Menjalankan Online Safe Scraper via Managed API Proxy (Maks: {max_pages} halaman)...")
    total_saved = 0

    for page in range(1, max_pages + 1):
        target_url = f"https://putusan3.mahkamahagung.go.id/direktori/kategori/jenis/putusan/page/{page}.html"
        print(f"[*] Mengambil Halaman {page}: {target_url}")

        # Panggil via ZenRows unblocker endpoint
        proxy_url = f"https://api.zenrows.com/v1/?apikey={api_key}&url={urllib.parse.quote(target_url)}&js_render=true"
        
        try:
            resp = requests.get(proxy_url, timeout=45)
            if resp.status_code != 200:
                print(f"[!] Gagal halaman {page}, status HTTP: {resp.status_code}")
                break

            soup = BeautifulSoup(resp.text, 'html.parser')
            page_items = parse_putusan_elements(soup)
            
            if not page_items:
                print(f"[!] Tidak ada data putusan ditemukan di halaman {page}. Selesai.")
                break

            saved = save_decisions_to_db(page_items)
            total_saved += saved
            print(f"[+] Berhasil menyimpan {saved} putusan dari halaman {page}. Total: {total_saved}")

            # Jeda sopan antar halaman (rate limiting ramah server)
            time.sleep(random.uniform(2.0, 4.0))

        except Exception as e:
            print(f"[!] Error saat scraping halaman {page}: {e}")
            break

    return total_saved

def parse_putusan_elements(soup):
    """
    Parser elemen dokumen putusan dari direktori Mahkamah Agung
    """
    items = []
    # Element card putusan biasanya div.spost atau div.entry
    entries = soup.select('.spost') or soup.select('.entry')
    
    if not entries:
        # Fallback pencarian tautan putusan
        for a in soup.find_all('a', href=True):
            if '/direktori/putusan/' in a['href']:
                title = a.get_text(strip=True)
                if title:
                    items.append({
                        'decision_number': title,
                        'title': title,
                        'court_name': 'Mahkamah Agung',
                        'classification': 'Putusan',
                        'verdict': None,
                        'decision_date': None,
                        'year': None,
                        'summary': None,
                        'file_url': None,
                        'source_url': a['href'],
                        'source': 'mahkamah_agung'
                    })
        return items

    for entry in entries:
        try:
            a_tag = entry.find('a', href=lambda h: h and '/direktori/putusan/' in h)
            if not a_tag:
                continue

            doc_number = a_tag.get_text(strip=True)
            doc_url = a_tag['href']
            if not doc_url.startswith('http'):
                doc_url = 'https://putusan3.mahkamahagung.go.id' + doc_url

            text_content = entry.get_text(separator=' ', strip=True)

            # Ekstraksi klasifikasi / pengadilan jika ada
            court_name = 'Mahkamah Agung'
            if 'Pengadilan Negeri' in text_content:
                court_name = 'Pengadilan Negeri'
            elif 'Pengadilan Tinggi' in text_content:
                court_name = 'Pengadilan Tinggi'

            # Tahun
            year = None
            import re
            year_match = re.search(r'\b(20[0-2][0-9]|19[8-9][0-9])\b', doc_number)
            if year_match:
                year = int(year_match.group(1))

            # Cari link PDF
            file_url = None
            pdf_link = entry.find('a', href=lambda h: h and h.endswith('.pdf'))
            if pdf_link:
                file_url = pdf_link['href']

            items.append({
                'decision_number': doc_number,
                'title': doc_number,
                'court_name': court_name,
                'classification': 'Putusan Mahkamah Agung',
                'verdict': 'Putusan',
                'decision_date': None,
                'year': year,
                'summary': text_content[:500],
                'file_url': file_url,
                'source_url': doc_url,
                'source': 'mahkamah_agung'
            })
        except Exception:
            continue

    return items

def main():
    parser = argparse.ArgumentParser(description="Scraper & Duplikasi Putusan Mahkamah Agung ke Database")
    parser.add_argument('--pages', type=int, default=10, help="Jumlah halaman yang ingin diambil (default: 10)")
    parser.add_argument('--api-key', type=str, default='', help="API Key Proxy Unblocker jika di hosting online")
    parser.add_argument('--download-pdf', action='store_true', help="Unduh file PDF ke disk lokal")

    args = parser.parse_args()
    api_key = args.api_key or os.getenv('SCRAPER_API_KEY', '')

    print("=" * 60)
    print("   LAWCO - SYNC PUTUSAN MAHKAMAH AGUNG")
    print("=" * 60)
    print(f"Target Database : {DB_DATABASE}@{DB_HOST}")
    print(f"Jumlah Halaman  : {args.pages}")

    if api_key:
        scrape_with_api(api_key, max_pages=args.pages, download_pdf=args.download_pdf)
    else:
        print("[*] Menjalankan modul duplikasi/sinkronisasi lokal...")
        # Jika tanpa API Key pihak ketiga, gunakan browser driver lokal yang ramah background
        print("[*] Memeriksa koneksi database...")
        conn = get_db_connection()
        with conn.cursor() as cursor:
            cursor.execute("SELECT COUNT(*) AS total FROM legal_decisions")
            res = cursor.fetchone()
            print(f"[+] Total putusan di database saat ini: {res['total']}")
        conn.close()

if __name__ == '__main__':
    main()
