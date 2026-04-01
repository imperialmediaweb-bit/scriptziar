#!/usr/bin/env python3
"""
Publicare articol pe mai multe site-uri WordPress.
Uploadează pozele O SINGURĂ DATĂ per site (fără duplicare).
Colectează toate linkurile la final.

Utilizare:
  python publish.py --title "Titlul Articolului" \
                    --content article.html \
                    --image poza.jpg \
                    --category "Știri" \
                    --sites sites.json
"""

import argparse
import json
import mimetypes
import os
import re
import sys
import time
from pathlib import Path
from urllib.parse import urlparse

import requests
from requests.auth import HTTPBasicAuth


def load_sites(sites_file):
    with open(sites_file, "r", encoding="utf-8") as f:
        sites = json.load(f)
    return sites


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
            url,
            headers=headers,
            data=img,
            auth=get_auth(site),
            timeout=60,
        )

    if resp.status_code not in (200, 201):
        raise Exception(
            f"Eroare upload imagine pe {site['name']}: "
            f"{resp.status_code} - {resp.text[:300]}"
        )

    data = resp.json()
    return data["id"], data["source_url"]


def upload_content_images(site, content, image_dir):
    """Găsește toate imaginile locale din conținut, le uploadează O SINGURĂ DATĂ,
    și înlocuiește URL-urile din HTML.

    Aceasta rezolvă problema dublării pozelor:
    - Fiecare imagine se uploadează exact o dată per site
    - Se ține un cache cu imaginile deja uploadate
    - URL-urile din conținut se înlocuiesc cu cele de pe site
    """
    uploaded_cache = {}  # local_path -> remote_url

    # Găsește toate src-urile din taguri <img>
    img_pattern = re.compile(r'(<img[^>]+src=["\'])([^"\']+)(["\'][^>]*>)', re.IGNORECASE)

    def replace_img(match):
        prefix = match.group(1)
        src = match.group(2)
        suffix = match.group(3)

        # Verifică dacă e o cale locală (nu un URL extern)
        parsed = urlparse(src)
        if parsed.scheme in ("http", "https"):
            # URL extern - nu uploadăm, lăsăm așa
            return match.group(0)

        # Determină calea completă a imaginii
        if os.path.isabs(src):
            img_path = src
        else:
            img_path = os.path.join(image_dir, src)

        if not os.path.exists(img_path):
            print(f"  ⚠ Imaginea nu există: {img_path}")
            return match.group(0)

        # Verifică cache-ul - nu uploadăm de 2 ori aceeași imagine
        abs_path = os.path.abspath(img_path)
        if abs_path in uploaded_cache:
            remote_url = uploaded_cache[abs_path]
        else:
            print(f"  Upload imagine: {os.path.basename(img_path)}")
            _, remote_url = upload_image(site, img_path)
            uploaded_cache[abs_path] = remote_url

        return f"{prefix}{remote_url}{suffix}"

    new_content = img_pattern.sub(replace_img, content)
    return new_content, uploaded_cache


def publish_article(site, title, content, featured_image_id=None, category_name=None):
    """Publică un articol pe un site WordPress."""
    url = f"{site['url'].rstrip('/')}/wp-json/wp/v2/posts"

    post_data = {
        "title": title,
        "content": content,
        "status": "publish",
    }

    if featured_image_id:
        post_data["featured_media"] = featured_image_id

    # Dacă e specificată o categorie, o căutăm sau o creăm
    if category_name:
        cat_id = get_or_create_category(site, category_name)
        if cat_id:
            post_data["categories"] = [cat_id]

    resp = requests.post(
        url,
        json=post_data,
        auth=get_auth(site),
        timeout=60,
    )

    if resp.status_code not in (200, 201):
        raise Exception(
            f"Eroare publicare pe {site['name']}: "
            f"{resp.status_code} - {resp.text[:300]}"
        )

    data = resp.json()
    return data["id"], data["link"]


def get_or_create_category(site, category_name):
    """Caută o categorie pe site. Dacă nu există, o creează."""
    url = f"{site['url'].rstrip('/')}/wp-json/wp/v2/categories"

    # Caută categoria
    resp = requests.get(
        url,
        params={"search": category_name, "per_page": 10},
        auth=get_auth(site),
        timeout=30,
    )

    if resp.status_code == 200:
        categories = resp.json()
        for cat in categories:
            if cat["name"].lower() == category_name.lower():
                return cat["id"]

    # Creează categoria dacă nu există
    resp = requests.post(
        url,
        json={"name": category_name},
        auth=get_auth(site),
        timeout=30,
    )

    if resp.status_code in (200, 201):
        return resp.json()["id"]

    print(f"  ⚠ Nu am putut crea categoria '{category_name}' pe {site['name']}")
    return None


def main():
    parser = argparse.ArgumentParser(
        description="Publică un articol pe mai multe site-uri WordPress"
    )
    parser.add_argument("--title", required=True, help="Titlul articolului")
    parser.add_argument(
        "--content",
        required=True,
        help="Fișier HTML cu conținutul articolului",
    )
    parser.add_argument(
        "--image",
        help="Imagine principală (featured image)",
    )
    parser.add_argument(
        "--image-dir",
        default=".",
        help="Director cu imaginile referite în conținut (default: directorul curent)",
    )
    parser.add_argument(
        "--category",
        help="Categoria articolului",
    )
    parser.add_argument(
        "--sites",
        default="sites.json",
        help="Fișier JSON cu lista de site-uri (default: sites.json)",
    )
    parser.add_argument(
        "--delay",
        type=float,
        default=1.0,
        help="Pauză între site-uri în secunde (default: 1)",
    )
    parser.add_argument(
        "--output",
        help="Fișier de ieșire pentru linkuri (opțional)",
    )

    args = parser.parse_args()

    # Citește conținutul articolului
    content_path = Path(args.content)
    if not content_path.exists():
        print(f"Eroare: Fișierul '{args.content}' nu există.")
        sys.exit(1)
    content = content_path.read_text(encoding="utf-8")

    # Citește lista de site-uri
    sites = load_sites(args.sites)
    print(f"Găsite {len(sites)} site-uri în {args.sites}\n")

    # Rezultate
    results = []
    errors = []

    for i, site in enumerate(sites, 1):
        site_name = site.get("name", site["url"])
        print(f"[{i}/{len(sites)}] {site_name}")

        try:
            # 1. Procesează și uploadează imaginile din conținut (fără duplicare)
            processed_content, _ = upload_content_images(
                site, content, args.image_dir
            )

            # 2. Uploadează featured image (dacă există)
            featured_id = None
            if args.image:
                if not os.path.exists(args.image):
                    print(f"  ⚠ Featured image nu există: {args.image}")
                else:
                    print(f"  Upload featured image: {os.path.basename(args.image)}")
                    featured_id, _ = upload_image(site, args.image)

            # 3. Publică articolul
            post_id, post_link = publish_article(
                site, args.title, processed_content, featured_id, args.category
            )

            results.append({
                "site": site_name,
                "url": site["url"],
                "post_id": post_id,
                "link": post_link,
            })
            print(f"  ✓ Publicat: {post_link}\n")

        except Exception as e:
            errors.append({"site": site_name, "error": str(e)})
            print(f"  ✗ Eroare: {e}\n")

        # Pauză între site-uri
        if i < len(sites):
            time.sleep(args.delay)

    # Sumar final
    print("=" * 60)
    print(f"REZULTATE: {len(results)} publicate, {len(errors)} erori")
    print("=" * 60)

    if results:
        print("\nLinkuri publicate:")
        for r in results:
            print(f"  {r['site']}: {r['link']}")

    if errors:
        print("\nErori:")
        for e in errors:
            print(f"  {e['site']}: {e['error']}")

    # Salvează linkurile într-un fișier (opțional)
    if args.output:
        with open(args.output, "w", encoding="utf-8") as f:
            f.write(f"Articol: {args.title}\n")
            f.write(f"Data: {time.strftime('%Y-%m-%d %H:%M')}\n")
            f.write(f"Total: {len(results)} site-uri\n\n")
            for r in results:
                f.write(f"{r['site']}: {r['link']}\n")
        print(f"\nLinkurile au fost salvate în: {args.output}")

    # Salvează și un JSON cu toate rezultatele
    results_file = "last_publish_results.json"
    with open(results_file, "w", encoding="utf-8") as f:
        json.dump({"title": args.title, "results": results, "errors": errors}, f, indent=2, ensure_ascii=False)
    print(f"Rezultatele complete au fost salvate în: {results_file}")

    if errors:
        sys.exit(1)


if __name__ == "__main__":
    main()
