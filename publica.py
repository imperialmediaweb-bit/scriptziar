#!/usr/bin/env python3
"""
Publică un articol pe toate ziarele și colectează linkurile.

Utilizare:
  python publica.py

Te întreabă titlul și conținutul, publică pe toate site-urile
din sites.txt și îți arată toate linkurile la final.

Folosește XML-RPC (activat implicit pe WordPress).
Nu trebuie să configurezi nimic pe site-uri.
"""

import sys
import time
import xmlrpc.client
from concurrent.futures import ThreadPoolExecutor, as_completed


def load_sites():
    sites = []
    with open("sites.txt", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            parts = line.split("|")
            if len(parts) != 3:
                print(f"  Linie invalidă (trebuie URL|user|parola): {line}")
                continue
            sites.append({
                "url": parts[0].strip().rstrip("/"),
                "user": parts[1].strip(),
                "password": parts[2].strip(),
            })
    return sites


def publish_one(site, title, content, category):
    """Publică pe un singur site via XML-RPC. Returnează linkul."""
    url = f"{site['url']}/xmlrpc.php"
    client = xmlrpc.client.ServerProxy(url)

    post_data = {
        "post_type": "post",
        "post_status": "publish",
        "post_title": title,
        "post_content": content,
    }

    if category:
        # Caută categoria existentă
        try:
            terms = client.wp.getTerms(1, site["user"], site["password"], "category")
            for term in terms:
                if term["name"].lower() == category.lower():
                    post_data["terms"] = {"category": [term["term_id"]]}
                    break
        except Exception:
            pass

    post_id = client.wp.newPost(1, site["user"], site["password"], post_data)

    # Ia linkul articolului publicat
    post = client.wp.getPost(1, site["user"], site["password"], post_id, ["link"])
    return post["link"]


def main():
    sites = load_sites()
    if not sites:
        print("Nu sunt site-uri în sites.txt!")
        print("Format: URL|username|parola (un site per linie)")
        sys.exit(1)

    print(f"Site-uri găsite: {len(sites)}\n")

    # Ia datele articolului
    title = input("Titlu articol: ").strip()
    if not title:
        print("Titlul e obligatoriu!")
        sys.exit(1)

    print("Conținut (HTML). Scrie pe mai multe linii, termină cu o linie goală:")
    content_lines = []
    while True:
        line = input()
        if line == "":
            break
        content_lines.append(line)
    content = "\n".join(content_lines)

    if not content:
        print("Conținutul e obligatoriu!")
        sys.exit(1)

    category = input("Categorie (opțional, Enter pentru a sări): ").strip()

    print(f"\nPublic pe {len(sites)} site-uri...\n")

    results = []
    errors = []
    start = time.time()

    with ThreadPoolExecutor(max_workers=10) as ex:
        futures = {}
        for site in sites:
            future = ex.submit(publish_one, site, title, content, category)
            futures[future] = site["url"]

        for future in as_completed(futures):
            url = futures[future]
            try:
                link = future.result()
                results.append(link)
                print(f"  ✓ {link}")
            except Exception as e:
                errors.append({"url": url, "error": str(e)})
                print(f"  ✗ {url}: {e}")

    elapsed = time.time() - start

    # Rezultat final
    print(f"\n{'=' * 60}")
    print(f"GATA! {len(results)} publicate, {len(errors)} erori ({elapsed:.1f}s)")
    print(f"{'=' * 60}")

    if results:
        results.sort()
        print("\nToate linkurile:\n")
        for link in results:
            print(link)

        # Salvează automat
        filename = f"linkuri_{int(time.time())}.txt"
        with open(filename, "w", encoding="utf-8") as f:
            f.write(f"Articol: {title}\n")
            f.write(f"Data: {time.strftime('%Y-%m-%d %H:%M')}\n")
            f.write(f"Total: {len(results)} site-uri\n\n")
            for link in results:
                f.write(link + "\n")
        print(f"\nLinkuri salvate în: {filename}")

    if errors:
        print(f"\nErori ({len(errors)}):")
        for e in errors:
            print(f"  {e['url']}: {e['error']}")


if __name__ == "__main__":
    main()
