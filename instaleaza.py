#!/usr/bin/env python3
"""
Instalează fix-ul pentru poze duplicate pe toate site-urile
prin DirectAdmin API (reseller).

Utilizare:
  python instaleaza.py

Te întreabă datele de DirectAdmin și face totul automat.
"""

import json
import sys
import urllib.parse

import requests


# Conținutul pluginului care fixează pozele duplicate
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


def da_api(host, username, password, command, params=None, login_as=None):
    """Apelează DirectAdmin API."""
    url = f"{host}/CMD_API_{command}"

    auth_user = f"{username}|{login_as}" if login_as else username

    resp = requests.post(
        url,
        data=params or {},
        auth=(auth_user, password),
        timeout=30,
        verify=False,  # Unele servere DA au certificate self-signed
    )

    return resp.text


def get_users(host, username, password):
    """Ia lista de useri din DirectAdmin reseller."""
    resp = requests.get(
        f"{host}/CMD_API_SHOW_USERS",
        auth=(username, password),
        timeout=30,
        verify=False,
    )

    # DirectAdmin returnează format URL-encoded: list[]=user1&list[]=user2
    users = []
    for part in resp.text.split("&"):
        if "=" in part:
            key, val = part.split("=", 1)
            if "list" in key:
                users.append(urllib.parse.unquote(val))

    return users


def get_user_domains(host, username, password, user):
    """Ia domeniile unui user."""
    resp = requests.get(
        f"{host}/CMD_API_SHOW_USER_DOMAINS",
        auth=(f"{username}|{user}", password),
        timeout=30,
        verify=False,
    )

    domains = []
    for part in resp.text.split("&"):
        if "=" in part:
            key, val = part.split("=", 1)
            if "list" in key:
                domains.append(urllib.parse.unquote(val))

    return domains


def upload_plugin(host, username, password, user, domain):
    """Uploadează pluginul prin DirectAdmin File Manager API."""

    # Creează directorul mu-plugins
    requests.post(
        f"{host}/CMD_API_FILE_MANAGER",
        auth=(f"{username}|{user}", password),
        data={
            "action": "folder",
            "path": f"/domains/{domain}/public_html/wp-content/mu-plugins",
            "name": "mu-plugins",
        },
        timeout=30,
        verify=False,
    )

    # Scrie fișierul pluginului
    resp = requests.post(
        f"{host}/CMD_FILE_MANAGER",
        auth=(f"{username}|{user}", password),
        data={
            "action": "edit",
            "path": f"/domains/{domain}/public_html/wp-content/mu-plugins",
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
        domains = get_user_domains(host, username, password, user)
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
