#!/usr/bin/env python3
"""
Colectează linkurile unui articol de pe toate site-urile.

Utilizare simplă:
  python linkuri.py "titlul articolului"
  python linkuri.py "titlul articolului" > linkuri.txt

Site-urile se citesc din sites.txt (un URL per linie).
"""

import sys
from concurrent.futures import ThreadPoolExecutor, as_completed

import requests


def main():
    if len(sys.argv) < 2:
        print("Utilizare: python linkuri.py \"titlul articolului\"")
        sys.exit(1)

    titlu = sys.argv[1]

    # Citește site-urile din sites.txt
    with open("sites.txt") as f:
        sites = [line.strip() for line in f if line.strip() and not line.startswith("#")]

    print(f'Caut "{titlu}" pe {len(sites)} site-uri...\n', file=sys.stderr)

    def cauta(url):
        try:
            r = requests.get(
                f"{url.rstrip('/')}/wp-json/wp/v2/posts",
                params={"search": titlu, "per_page": 1},
                timeout=15,
            )
            if r.status_code == 200 and r.json():
                return r.json()[0]["link"]
        except Exception:
            pass
        return None

    gasit = 0
    with ThreadPoolExecutor(max_workers=20) as ex:
        futures = {ex.submit(cauta, url): url for url in sites}
        for future in as_completed(futures):
            link = future.result()
            if link:
                gasit += 1
                print(link)

    print(f"\nGăsite: {gasit}/{len(sites)}", file=sys.stderr)


if __name__ == "__main__":
    main()
