import os
import sys
import io
import re
import urllib.request
import zipfile
import xml.etree.ElementTree as ET
import pymysql
from dotenv import load_dotenv

# Load database configuration from Laravel .env
load_dotenv(os.path.join(os.path.dirname(__file__), '..', '.env'))

DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = int(os.getenv('DB_PORT', 3306))
DB_DATABASE = os.getenv('DB_DATABASE', 'investalaw')
DB_USERNAME = os.getenv('DB_USERNAME', 'root')
DB_PASSWORD = os.getenv('DB_PASSWORD', '')

ZIP_URL = "https://github.com/ir-nlp-csui/indo-law/archive/refs/heads/master.zip"
ZIP_CACHE = os.path.join(os.path.dirname(__file__), "indo_law_dataset.zip")

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

def download_dataset():
    if os.path.exists(ZIP_CACHE) and os.path.getsize(ZIP_CACHE) > 10000000:
        print(f"[*] Menggunakan file zip cache yang sudah ada: {ZIP_CACHE}")
        return ZIP_CACHE

    print(f"[*] Mengunduh dataset 22.000+ putusan MA dari {ZIP_URL}...")
    req = urllib.request.Request(ZIP_URL, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req, timeout=120) as resp:
        with open(ZIP_CACHE, "wb") as f:
            while True:
                chunk = resp.read(1024 * 512)
                if not chunk:
                    break
                f.write(chunk)
    print(f"[+] Selesai mengunduh dataset: {ZIP_CACHE} ({os.path.getsize(ZIP_CACHE)} bytes)")
    return ZIP_CACHE

def clean_title_and_number(kepala_text, doc_id):
    if not kepala_text:
        return f"Putusan No. {doc_id}", None, None

    lines = [line.strip() for line in kepala_text.split("\n") if line.strip()]
    doc_number = f"Putusan {doc_id[:8]}"
    year = None

    for line in lines[:8]:
        if "nomor" in line.lower() or "no." in line.lower():
            doc_number = line.upper()
            break

    # Cari tahun
    m = re.search(r'\b(20[0-2][0-9]|19[8-9][0-9])\b', kepala_text)
    if m:
        year = int(m.group(1))

    return doc_number, year

def import_records(limit=250):
    zip_path = download_dataset()
    conn = get_db_connection()

    print(f"[*] Membuka zip dataset dan mengekstrak hingga {limit} putusan...")
    inserted = 0

    with zipfile.ZipFile(zip_path, 'r') as zf:
        file_list = [n for n in zf.namelist() if n.endswith('.xml')]
        print(f"[+] Total putusan di dalam arsip: {len(file_list)} dokumen.")

        batch = []
        for filename in file_list:
            if inserted + len(batch) >= limit:
                break

            try:
                content = zf.read(filename).decode('utf-8', errors='ignore')
                root = ET.fromstring(content)
                attrib = root.attrib

                doc_id = attrib.get('id', filename.replace('.xml', '').split('/')[-1])
                source_url = attrib.get('url', f"https://putusan3.mahkamahagung.go.id/direktori/putusan/{doc_id}.html")
                classification = attrib.get('klasifikasi', 'Putusan')
                court_name = attrib.get('lembaga_peradilan', 'Mahkamah Agung')
                verdict = attrib.get('amar', 'Putusan')

                # Format nama lembaga agar rapi
                court_formatted = court_name.upper().replace('-', ' ')

                # Baca teks kepala putusan & fakta
                kepala = root.find('kepala_putusan')
                kepala_text = kepala.text if kepala is not None and kepala.text else ""
                
                doc_number, year = clean_title_and_number(kepala_text, doc_id)

                summary_elem = root.find('fakta_hukum') or root.find('pertimbangan_hukum') or root.find('amar_putusan')
                summary = summary_elem.text.strip()[:600] if summary_elem is not None and summary_elem.text else kepala_text[:400]

                batch.append((
                    doc_number,
                    doc_number,
                    court_formatted,
                    classification.replace('-', ' ').title(),
                    verdict.title(),
                    None,
                    year,
                    summary,
                    f"https://putusan3.mahkamahagung.go.id/direktori/download_file/{doc_id}/pdf/{doc_id}",
                    source_url,
                    'mahkamah_agung'
                ))

                if len(batch) >= 50:
                    with conn.cursor() as cursor:
                        cursor.executemany("""
                            INSERT INTO legal_decisions 
                            (decision_number, title, court_name, classification, verdict, decision_date, year, summary, file_url, source_url, source, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE 
                                title = VALUES(title),
                                court_name = VALUES(court_name),
                                classification = VALUES(classification),
                                verdict = VALUES(verdict),
                                year = VALUES(year),
                                summary = VALUES(summary),
                                file_url = VALUES(file_url),
                                updated_at = NOW()
                        """, batch)
                    inserted += len(batch)
                    print(f"[+] Berhasil mengimpor {inserted}/{limit} putusan...")
                    batch = []

            except Exception as e:
                continue

        if batch:
            with conn.cursor() as cursor:
                cursor.executemany("""
                    INSERT INTO legal_decisions 
                    (decision_number, title, court_name, classification, verdict, decision_date, year, summary, file_url, source_url, source, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE 
                        title = VALUES(title),
                        court_name = VALUES(court_name),
                        classification = VALUES(classification),
                        verdict = VALUES(verdict),
                        year = VALUES(year),
                        summary = VALUES(summary),
                        file_url = VALUES(file_url),
                        updated_at = NOW()
                """, batch)
            inserted += len(batch)

    conn.close()
    print(f"\n[SELESAI] Total {inserted} putusan Mahkamah Agung berhasil dimasukkan ke database!")

if __name__ == '__main__':
    limit = 150
    if len(sys.argv) > 1:
        try:
            limit = int(sys.argv[1])
        except ValueError:
            pass
    import_records(limit)
