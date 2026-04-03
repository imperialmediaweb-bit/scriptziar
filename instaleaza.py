#!/usr/bin/env python3
"""
Instalează fix-ul pentru poze duplicate pe toate site-urile
prin DirectAdmin API (reseller).

Utilizare:
  python instaleaza.py
"""

import sys
import urllib.parse

import requests


PLUGIN_CONTENT = r'''<?php
/**
 * Plugin Name: Fix Poze Duplicate
 * Description: Elimina automat pozele duplicate consecutive din articole
 * Version: 1.0
 */
add_filter("the_content", "fix_poze_duplicate", 20);
function fix_poze_duplicate($content) {
    $content = preg_replace_callback(
        '#(<(?:p|figure|div)[^>]*>\s*<img\s[^>]*src=["\x27]([^"\x27]+)["\x27][^>]*>\s*</(?:p|figure|div)>)\s*(<(?:p|figure|div)[^>]*>\s*<img\s[^>]*src=["\x27]([^"\x27]+)["\x27][^>]*>\s*</(?:p|figure|div)>)#i',
        function($matches) {
            if ($matches[2] === $matches[4]) return $matches[1];
            return $matches[0];
        },
        $content
    );
    $content = preg_replace_callback(
        '#(<img\s[^>]*src=["\x27]([^"\x27]+)["\x27][^>]*>)\s*(<img\s[^>]*src=["\x27]([^"\x27]+)["\x27][^>]*>)#i',
        function($matches) {
            if ($matches[2] === $matches[4]) return $matches[1];
            return $matches[0];
        },
        $content
    );
    return $content;
}
'''


def parse_da_response(text):
    """Parsează răspunsul DirectAdmin (URL-encoded sau JSON)."""
    # Încearcă JSON
    try:
        import json
        data = json.loads(text)
        if isinstance(data, list):
            return data
        if isinstance(data, dict):
            return list(data.keys())
    except Exception:
        pass

    # Încearcă URL-encoded: list[]=val1&list[]=val2
    values = []
    for part in text.split("&"):
        if "=" in part:
            key, val = part.split("=", 1)
            val = urllib.parse.unquote(val)
            if val:
                values.append(val)

    return values


def get_users(host, username, password):
    """Ia lista de useri din DirectAdmin reseller."""
    resp = requests.get(
        f"{host}/CMD_API_SHOW_USERS",
        auth=(username, password),
        timeout=30,
        verify=False,
    )
    return parse_da_response(resp.text)


def get_user_domains(host, username, password, user):
    """Ia domeniile unui user - încearcă mai multe metode."""

    # Metoda 1: SHOW_USER_DOMAINS cu login-as
    try:
        resp = requests.get(
            f"{host}/CMD_API_SHOW_USER_DOMAINS",
            auth=(f"{username}|{user}", password),
            timeout=15,
            verify=False,
        )
        domains = parse_da_response(resp.text)
        if domains:
            return domains
    except Exception:
        pass

    # Metoda 2: SHOW_USER_DOMAINS fără login-as dar cu parametru user
    try:
        resp = requests.get(
            f"{host}/CMD_API_SHOW_USER_DOMAINS",
            params={"user": user},
            auth=(username, password),
            timeout=15,
            verify=False,
        )
        domains = parse_da_response(resp.text)
        if domains:
            return domains
    except Exception:
        pass

    # Metoda 3: ADDITIONAL_DOMAINS cu login-as
    try:
        resp = requests.get(
            f"{host}/CMD_API_ADDITIONAL_DOMAINS",
            auth=(f"{username}|{user}", password),
            timeout=15,
            verify=False,
        )
        domains = parse_da_response(resp.text)
        # Adaugă și domeniul principal
        main_domain = get_main_domain(host, username, password, user)
        if main_domain and main_domain not in domains:
            domains.insert(0, main_domain)
        if domains:
            return domains
    except Exception:
        pass

    # Metoda 4: USER_CONFIG pentru domeniul principal
    main = get_main_domain(host, username, password, user)
    if main:
        return [main]

    return []


def get_main_domain(host, username, password, user):
    """Ia domeniul principal al unui user."""
    try:
        resp = requests.get(
            f"{host}/CMD_API_SHOW_USER_CONFIG",
            params={"user": user},
            auth=(username, password),
            timeout=15,
            verify=False,
        )
        for part in resp.text.split("&"):
            if "=" in part:
                key, val = part.split("=", 1)
                if key == "domain":
                    return urllib.parse.unquote(val)
    except Exception:
        pass
    return None


def upload_plugin(host, username, password, user, domain):
    """Uploadează pluginul prin DirectAdmin File Manager API."""
    base_path = f"/domains/{domain}/public_html/wp-content/mu-plugins"

    # Creează directorul mu-plugins
    requests.post(
        f"{host}/CMD_API_FILE_MANAGER",
        auth=(f"{username}|{user}", password),
        data={
            "action": "folder",
            "path": f"/domains/{domain}/public_html/wp-content",
            "name": "mu-plugins",
        },
        timeout=30,
        verify=False,
    )

    # Scrie fișierul
    resp = requests.post(
        f"{host}/CMD_FILE_MANAGER",
        auth=(f"{username}|{user}", password),
        data={
            "action": "edit",
            "path": base_path,
            "filename": "fix-poze-duplicate.php",
            "text": PLUGIN_CONTENT,
            "page": "filemanager",
        },
        timeout=30,
        verify=False,
    )

    return resp.status_code in (200, 302)


def main():
    import urllib3
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

    print("=== Instalare Fix Poze Duplicate pe toate site-urile ===\n")

    host = input("URL DirectAdmin (ex: https://web7.gazduire.net:2222): ").strip().rstrip("/")
    username = input("Username reseller: ").strip()
    password = input("Parola: ").strip()

    print("\nIau lista de useri...")
    users = get_users(host, username, password)

    if not users:
        print("Nu am găsit useri. Verifică credențialele.")
        sys.exit(1)

    print(f"Găsiți {len(users)} useri.\n")

    ok = 0
    erori = 0

    for user in users:
        # Ia domeniile userului
        domains = get_user_domains(host, username, password, user)

        if not domains:
            print(f"  - {user}: niciun domeniu găsit")
            continue

        for domain in domains:
            try:
                upload_plugin(host, username, password, user, domain)
                print(f"  ✓ {domain}")
                ok += 1
            except Exception as e:
                print(f"  ✗ {domain}: {e}")
                erori += 1

    print(f"\n{'=' * 40}")
    print(f"Gata! {ok} instalate, {erori} erori.")


if __name__ == "__main__":
    main()
