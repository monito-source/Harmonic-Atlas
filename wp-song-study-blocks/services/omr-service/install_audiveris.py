#!/usr/bin/env python3
import json
import os
import platform
import sys
import urllib.request
import urllib.error
from pathlib import Path


RELEASES_API = "https://api.github.com/repos/Audiveris/audiveris/releases"


def fetch_json(url: str) -> dict:
    request = urllib.request.Request(
        url,
        headers={
            "Accept": "application/vnd.github+json",
            "User-Agent": "HarmonyAtlas-OMR-Docker-Build",
        },
    )
    with urllib.request.urlopen(request, timeout=60) as response:
        return json.loads(response.read().decode("utf-8"))


def fetch_release(version: str) -> dict:
    if not version or version == "latest":
        return fetch_json(f"{RELEASES_API}/latest")

    candidates = [version]
    if not version.startswith("v"):
        candidates.append(f"v{version}")

    errors: list[str] = []
    for candidate in candidates:
        try:
            return fetch_json(f"{RELEASES_API}/tags/{candidate}")
        except urllib.error.HTTPError as exc:
            errors.append(f"{candidate}: HTTP {exc.code}")
    raise SystemExit(f"Audiveris release not found for {version}. Tried: {', '.join(errors)}")


def architecture_tokens() -> list[str]:
    override = os.environ.get("AUDIVERIS_ARCH", "").strip().lower()
    if override:
        machine = override
    else:
        machine = platform.machine().lower()
    if machine in {"x86_64", "amd64"}:
        return ["x86_64", "amd64"]
    if machine in {"aarch64", "arm64"}:
        return ["aarch64", "arm64"]
    return [machine]


def architecture_label() -> str:
    return os.environ.get("AUDIVERIS_ARCH", "").strip() or platform.machine()


def score_asset(asset: dict, arch_tokens: list[str]) -> tuple[int, str]:
    name = str(asset.get("name", "")).lower()
    if not name.endswith(".deb"):
        return (-1, name)
    if not any(token in name for token in arch_tokens):
        return (-1, name)
    score = 10
    score += 20
    if "ubuntu24.04" in name or "ubuntu-24.04" in name:
        score += 30
    elif "ubuntu22.04" in name or "ubuntu-22.04" in name:
        score += 20
    elif "linux" in name:
        score += 10
    return (score, name)


def select_deb_asset(release: dict) -> dict:
    assets = release.get("assets", [])
    arch_tokens = architecture_tokens()
    ranked = sorted(
        ((score_asset(asset, arch_tokens), asset) for asset in assets),
        key=lambda item: item[0],
        reverse=True,
    )
    for (score, _name), asset in ranked:
        if score > 0:
            return asset

    names = ", ".join(str(asset.get("name", "")) for asset in assets)
    raise SystemExit(
        "No Linux .deb asset found for architecture "
        f"{architecture_label()}. Audiveris Linux release assets are architecture-specific; "
        f"use a linux/amd64 Docker build host or platform. Assets: {names}"
    )


def download(url: str, destination: Path) -> None:
    request = urllib.request.Request(url, headers={"User-Agent": "HarmonyAtlas-OMR-Docker-Build"})
    with urllib.request.urlopen(request, timeout=300) as response:
        with destination.open("wb") as file:
            while True:
                chunk = response.read(1024 * 1024)
                if not chunk:
                    break
                file.write(chunk)


def main() -> None:
    destination = Path(sys.argv[1] if len(sys.argv) > 1 else "/tmp/audiveris.deb")
    version = os.environ.get("AUDIVERIS_VERSION", "latest").strip()
    release = fetch_release(version)
    asset = select_deb_asset(release)
    url = asset.get("browser_download_url")
    if not url:
        raise SystemExit(f"Selected asset has no browser_download_url: {asset.get('name')}")

    print(f"Downloading Audiveris {release.get('tag_name')} asset {asset.get('name')}", flush=True)
    download(url, destination)
    print(f"Saved {destination} ({destination.stat().st_size} bytes)", flush=True)


if __name__ == "__main__":
    main()
