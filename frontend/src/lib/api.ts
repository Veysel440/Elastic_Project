import ky from "ky";
const api = ky.create({
    prefixUrl: import.meta.env.VITE_API_BASE,
    headers: { "X-API-Key": import.meta.env.VITE_API_KEY, "Content-Type":"application/json" },
});

export type LatLon = { lat:number; lon:number };
export type WithinResp = { items: StoreItem[]; count: number };
export type HeatTile = { key:number; doc_count:number; centroid?:{ lat:number; lon:number } };
export type HeatResp = { tiles: HeatTile[] };
export type StoreItem = { id:string; name?:string|null; loc:{lat:number;lon:number} };
export type StoreDoc = StoreItem & { service_area?: { type:"polygon"; coordinates:number[][][] } };

export const createStore = (name:string, loc:LatLon) =>
    api.post("stores", { json: { name, lat:loc.lat, lon:loc.lon } }).json<{id:string}>();

export const setArea = (id:string, coordinates:number[][]) =>
    api.post(`stores/${id}/area`, { json: { coordinates } }).json();

export const nearest = (lat:number, lon:number, radius_km=5, limit=3) =>
    api.get(`stores/near`, { searchParams:{ lat, lon, radius_km, limit }}).json<{items:any[]}>();

export const eligible = (lat:number, lon:number) =>
    api.get(`delivery/eligible`, { searchParams:{ lat, lon }}).json<{eligible:boolean;stores:any[]}>();

export const within = (bbox:{min_lat:number;min_lon:number;max_lat:number;max_lon:number;limit?:number}) =>
    api.get(`stores/within`, { searchParams:bbox as any }).json<WithinResp>();

export const heat = (z=7) =>
    api.get(`stores/heat`, { searchParams:{ z }}).json<HeatResp>();


export const getStore = (id:string) =>
    api.get(`stores/${id}`).json<StoreDoc>();
