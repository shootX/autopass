#!/bin/sh
set -eu
root="$(CDPATH= cd -- "$(dirname "$0")" && pwd)"
work="${TMPDIR:-/tmp}/geowash-map"
mkdir -p "$work"
curl -L --fail -o "$work/georgia.osm.pbf" "https://download.geofabrik.de/europe/georgia-latest.osm.pbf"
cp "$root/config.json" "$root/process.lua" "$work/"
docker run --rm -v "$work:/data" ghcr.io/systemed/tilemaker:master \
  /data/georgia.osm.pbf \
  --output /data/georgia.pmtiles \
  --config /data/config.json \
  --process /data/process.lua
cp "$work/georgia.pmtiles" "$root/../../public/maps/georgia.pmtiles"
