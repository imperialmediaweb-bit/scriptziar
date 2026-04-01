#!/usr/bin/env python3
"""
Publicare RAPIDĂ pe mai multe site-uri WordPress (în paralel).
Uploadează pozele O SINGURĂ DATĂ per site (fără duplicare).
Colectează toate linkurile la final.

Utilizare:
  python publish_fast.py --title "Titlul Articolului" \
                         --content article.html \
                         --image poza.jpg \
                         --category "Știri" \
                         --sites sites.json \
                         --workers 10
"""

import argparse
import json
import mimetypes
import os
import re
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path
from urllib.parse import urlparse

import requests
from requests.auth import HTTPBasicAuth


def load_sites(sites_file):
    with open(sites_file, "r", encoding="utf-8") as f:
        return json.load(f)


def get_auth(site):
    return HTTPBasicAuth(site["username"], site["app_password"])


def upload_image(site, image_path):
    """Uploadează o imagine pe un site WordPress. Returnează attachment ID și URL."""
    url = f"{site['url'].rstrip('/')}/wp-json/wp/v2/media"
    filename = os.path.basename(image_path)
    mime_type = mimetypes.guess_type(image_path)[0] or "image/jpeg"

    with open(image_path, "rb") as img:
        headers = {
            "Content-Disposition": f'attachment; filename="{filename}"',
            "Content-Type": mime_type,
        }
        resp = requests.post(
            url, headers=headers, data=img,
            auth=get_auth(site), timeout=60,
        )

    if resp.status_code not in (200, 201):
        raise Exception(f"Upload imagine eșuat: {resp.status_code} - {resp.text[:200]}")

    data = resp.json()
    return data["id"], data["source_url"]


def upload_content_images(site, content, image_dir):
    """Găsește imaginile locale din conținut, le uploadează O SINGURĂ DATĂ,
    și înlocuiește URL-urile. Fără duplicare."""
    uploaded_cache = {}

    img_pattern = re.compile(r'(<img[^>]+src=["\'])([^"\']+)(["\'][^>]*>)', re.IGNORECASE)

    def replace_img(match):
        prefix, src, suffix = match.group(1), match.group(2), match.group(3)

        parsed = urlparse(src)
        if parsed.scheme in ("http", "https"):
            return match.group(0)

        img_path = src if os.path.isabs(src) else os.path.join(image_dir, src)
        if not os.path.exists(img_path):
            return match.group(0)

        abs_path = os.path.abspath(img_path)
        if abs_path not in uploaded_cache:
            _, remote_url = upload_image(site, img_path)
            uploaded_cache[abs_path] = remote_url

        return f"{prefix}{uploaded_cache[abs_path]}{suffix}"

    return img_pattern.sub(replace_img, content), uploaded_cache


def get_or_create_category(site, category_name):
    """Caută sau creează o categorie."""
    url = f"{site['url'].rstrip('/')}/wp-json/wp/v2/categories"

    resp = requests.get(
        url, params={"search": category_name, "per_page": 10},
        auth=get_auth(site), timeout=30,
    )
    if resp.status_code == 200:
        for cat in resp.json():
            if cat["name"].lower() == category_name.lower():
                return cat["id"]

    resp = requests.post(
        url, json={"name": category_name},
        auth=get_auth(site), timeout=30,
    )
    if resp.status_code in (200, 201):
        return resp.json()["id"]
    return None


def publish_to_site(site, title, content, image_path, image_dir, category):
    """Publică un articol pe UN singur site. Rulează într-un thread separat."""
    site_name = site.get("name", site["url"])

    # 1. Procesează imaginile din conținut (fără duplicare)
    processed_content, _ = upload_content_images(site, content, image_dir)

    # 2. Featured image
    featured_id = None
    if image_path and os.path.exists(image_path):
        featured_id, _ = upload_image(site, image_path)

    # 3. Categorie
    post_data = {
        "title": title,
        "content": processed_content,
        "status": "publish",
    }
    if featured_id:
        post_data["featured_media"] = featured_id
    if category:
        cat_id = get_or_create_category(site, category)
        if cat_id:
            post_data["categories"] = [cat_id]

    # 4. Publică
    url = f"{site['url'].rstrip('/')}/wp-json/wp/v2/posts"
    resp = requests.post(url, json=post_data, auth=get_auth(site), timeout=60)

    if resp.status_code not in (200, 201):
        raise Exception(f"{resp.status_code} - {resp.text[:200]}")

    data = resp.json()
    return {
        "site": site_name,
        "url": site["url"],
        "post_id": data["id"],
        "link": data["link"],
    }


def main():
    parser = argparse.ArgumentParser(
        description="Publicare RAPIDĂ pe mai multe site-uri WordPress (în paralel)"
    )
    parser.add_argument("--title", required=True, help="Titlul articolului")
    parser.add_argument("--content", required=True, help="Fișier HTML cu conținutul")
    parser.add_argument("--image", help="Featured image")
    parser.add_argument("--image-dir", default=".", help="Director cu imaginile din conținut")
    parser.add_argument("--category", help="Categoria articolului")
    parser.add_argument("--sites", default="sites.json", help="Fișier JSON cu site-urile")
    parser.add_argument("--workers", type=int, default=10,
                        help="Câte site-uri procesează simultan (default: 10)")
    parser.add_argument("--output", help="Fișier de ieșire pentru linkuri")

    args = parser.parse_args()

    content = Path(args.content).read_text(encoding="utf-8")
    sites = load_sites(args.sites)

    print(f"Publicare pe {len(sites)} site-uri cu {args.workers} threaduri simultane...\n")

    results = []
    errors = []
    start_time = time.time()

    with ThreadPoolExecutor(max_workers=args.workers) as executor:
        future_to_site = {}
        for site in sites:
            future = executor.submit(
                publish_to_site, site, args.title, content,
                args.image, args.image_dir, args.category,
            )
            future_to_site[future] = site

        for future in as_completed(future_to_site):
            site = future_to_site[future]
            site_name = site.get("name", site["url"])
            try:
                result = future.result()
                results.append(result)
                print(f"  ✓ {site_name}: {result['link']}")
            except Exception as e:
                errors.append({"site": site_name, "error": str(e)})
                print(f"  ✗ {site_name}: {e}")

    elapsed = time.time() - start_time

    # Sumar
    print(f"\n{'=' * 60}")
    print(f"GATA! {len(results)} publicate, {len(errors)} erori ({elapsed:.1f}s)")
    print(f"{'=' * 60}")

    if results:
        # Sortează alfabetic după nume site
        results.sort(key=lambda r: r["site"])
        print("\nToate linkurile:")
        for r in results:
            print(f"  {r['link']}")

    if errors:
        print("\nErori:")
        for e in errors:
            print(f"  {e['site']}: {e['error']}")

    # Salvează linkurile
    if args.output:
        with open(args.output, "w", encoding="utf-8") as f:
            f.write(f"Articol: {args.title}\n")
            f.write(f"Data: {time.strftime('%Y-%m-%d %H:%M')}\n")
            f.write(f"Total: {len(results)} site-uri | Timp: {elapsed:.1f}s\n\n")
            for r in results:
                f.write(f"{r['link']}\n")
        print(f"\nLinkuri salvate în: {args.output}")

    # JSON complet
    results_file = "last_publish_results.json"
    with open(results_file, "w", encoding="utf-8") as f:
        json.dump({
            "title": args.title,
            "time_seconds": round(elapsed, 1),
            "results": results,
            "errors": errors,
        }, f, indent=2, ensure_ascii=False)
    print(f"JSON salvat în: {results_file}")

    if errors:
        sys.exit(1)


if __name__ == "__main__":
    main()
