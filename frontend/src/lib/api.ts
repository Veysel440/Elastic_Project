import ky, { HTTPError } from "ky";

const baseHeaders: Record<string,string> = { "Content-Type":"application/json" };
const API_KEY = import.meta.env.VITE_API_KEY as string | undefined;
if (API_KEY) baseHeaders["X-API-Key"] = API_KEY;

export const api = ky.create({
    prefixUrl: import.meta.env.VITE_API_BASE,
    headers: baseHeaders,
});

export async function safe<T>(p: Promise<T>): Promise<T> {
    try { return await p; }
    catch (e) {
        if (e instanceof HTTPError) {
            const body = await e.response.text();
            throw new Error(`HTTP ${e.response.status} ${e.response.statusText} — ${body}`);
        }
        throw e;
    }
}

export type LatLon = { lat:number; lon:number };
export type StoreItem = { id:string; name?:string|null; loc:{lat:number;lon:number} };
export type StoreDoc = StoreItem & { service_area?: { type:"polygon"; coordinates:number[][][] } };

export const createStore = (name:string, loc:LatLon) =>
    safe(api.post("stores", { json: { name, lat:loc.lat, lon:loc.lon } }).json<{id:string}>());
export const setArea = (id:string, coordinates:number[][]) =>
    safe(api.post(`stores/${id}/area`, { json: { coordinates } }).json());
export const nearest = (lat:number, lon:number, radius_km=5, limit=3) =>
    safe(api.get(`stores/near`, { searchParams:{ lat, lon, radius_km, limit }}).json<{items:any[]}>());
export const eligible = (lat:number, lon:number) =>
    safe(api.get(`delivery/eligible`, { searchParams:{ lat, lon }}).json<{eligible:boolean;stores:any[]}>());
export const within = (bbox:{min_lat:number;min_lon:number;max_lat:number;max_lon:number;limit?:number}) =>
    safe(api.get(`stores/within`, { searchParams:bbox as any }).json<{items:any[];count:number}>());
export const heat = (z=7) =>
    safe(api.get(`stores/heat`, { searchParams:{ z }}).json<{tiles:any[]}>());
export const getStore = (id:string) =>
    safe(api.get(`stores/${id}`).json<any>());
