import maplibregl, { type StyleSpecification } from "maplibre-gl";
import { FileSource, PMTiles, Protocol } from "pmtiles";

const ARCHIVE_NAME = "georgia.pmtiles";
const CACHE_NAME = "gewash-map-v3";

export const GEORGIA_BOUNDS: [[number, number], [number, number]] = [
  [39.35, 40.95],
  [46.85, 43.65],
];

function activeProtocol(): Protocol {
  const registry = globalThis as { __gewashMapProtocol?: Protocol };
  const current = registry.__gewashMapProtocol;
  if (current && typeof current.add === "function" && typeof current.tile === "function") {
    return current;
  }

  const created = new Protocol();
  maplibregl.addProtocol("pmtiles", created.tile);
  registry.__gewashMapProtocol = created;
  return created;
}

let ready: Promise<void> | null = null;

async function loadArchive(): Promise<ArrayBuffer> {
  const url = new URL(`/maps/${ARCHIVE_NAME}?v=3`, window.location.origin).href;

  if ("caches" in window) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(url);
    if (cached) return cached.arrayBuffer();

    const response = await fetch(url);
    if (!response.ok) throw new Error("Georgia map failed to load");
    await cache.put(url, response.clone());
    return response.arrayBuffer();
  }

  const response = await fetch(url);
  if (!response.ok) throw new Error("Georgia map failed to load");
  return response.arrayBuffer();
}

export function prepareGeorgiaMap(): Promise<void> {
  if (!ready) {
    ready = loadArchive()
      .then((buffer) => {
        const file = new File([buffer], ARCHIVE_NAME);
        activeProtocol().add(new PMTiles(new FileSource(file)));
      })
      .catch((error) => {
        ready = null;
        throw error;
      });
  }
  return ready;
}

const roadWidth = [
  "interpolate",
  ["linear"],
  ["zoom"],
  6,
  ["match", ["get", "class"], ["motorway", "trunk"], 1.1, 0.4],
  11,
  ["match", ["get", "class"], ["motorway", "trunk"], 2.4, ["primary", "primary_link"], 1.6, 0.8],
  14,
  ["match", ["get", "class"], ["motorway", "trunk"], 5, ["primary", "primary_link"], 3.5, ["secondary", "secondary_link"], 2.6, 1.4],
  16,
  ["match", ["get", "class"], ["motorway", "trunk"], 8, ["primary", "primary_link"], 6, ["secondary", "secondary_link"], 4.5, 2.2],
] as const;

export const georgiaMapStyle: StyleSpecification = {
  version: 8,
  glyphs: "/maps/fonts/{fontstack}/{range}.pbf",
  sources: {
    georgia: {
      type: "vector",
      url: `pmtiles://${ARCHIVE_NAME}`,
      attribution: "© OpenStreetMap",
    },
  },
  layers: [
    { id: "background", type: "background", paint: { "background-color": "#efece4" } },
    {
      id: "water",
      type: "fill",
      source: "georgia",
      "source-layer": "water",
      paint: { "fill-color": "#c5dceb" },
    },
    {
      id: "waterway",
      type: "line",
      source: "georgia",
      "source-layer": "waterway",
      paint: { "line-color": "#b7d2e4", "line-width": 1.4 },
    },
    {
      id: "road-case",
      type: "line",
      source: "georgia",
      "source-layer": "roads",
      paint: {
        "line-color": "#b7b1a6",
        "line-width": ["interpolate", ["linear"], ["zoom"], 6, 1.4, 12, 3.2, 14, 5.5, 16, 8],
        "line-opacity": 0.9,
      },
    },
    {
      id: "road",
      type: "line",
      source: "georgia",
      "source-layer": "roads",
      paint: {
        "line-color": [
          "match",
          ["get", "class"],
          ["motorway", "trunk", "motorway_link", "trunk_link"],
          "#e3b36a",
          ["primary", "primary_link"],
          "#e6c56a",
          "#8f8a80",
        ],
        "line-width": roadWidth as unknown as number,
      },
    },
    {
      id: "road-name",
      type: "symbol",
      source: "georgia",
      "source-layer": "road_name",
      minzoom: 13,
      layout: {
        "symbol-placement": "line",
        "text-field": ["get", "name"],
        "text-font": ["Noto Sans Regular"],
        "text-size": 12,
        "text-max-angle": 30,
      },
      paint: {
        "text-color": "#3f3c36",
        "text-halo-color": "#efece4",
        "text-halo-width": 1.6,
      },
    },
  ],
};
