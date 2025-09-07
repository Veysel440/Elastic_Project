import { useEffect, useRef, useState } from "react";
import maplibregl from "maplibre-gl";
import type { LngLatLike, IControl, Map as MapType, GeoJSONSource } from "maplibre-gl";
import MapboxDraw from "@mapbox/mapbox-gl-draw";
import {
    createStore, setArea, nearest, eligible, within, heat, getStore,
    type StoreItem, type StoreDoc
} from "../lib/api";
import type { FeatureCollection, Point } from "geojson";

const IST: LngLatLike = [28.9784, 41.0082];
const STYLE = "https://demotiles.maplibre.org/style.json";
type FC = FeatureCollection<Point, Record<string, unknown>>;

export default function MapPage() {
    const ref = useRef<HTMLDivElement>(null);
    const mapRef = useRef<MapType | null>(null);
    const drawRef = useRef<MapboxDraw | null>(null);

    const [lastId, setLastId] = useState<string>();
    const [stores, setStores] = useState<StoreItem[]>([]);
    const [showHeat, setShowHeat] = useState(true);
    const [showPoints, setShowPoints] = useState(true);

    // Ölçüm
    const [measureMode, setMeasureMode] = useState(false);
    const [measureTotalKm, setMeasureTotalKm] = useState(0);
    const measureCoordsRef = useRef<number[][]>([]);

    // Ekleme modu + isim
    const [addMode, setAddMode] = useState(false);
    const [nameSeq, setNameSeq] = useState(1);
    const [storeName, setStoreName] = useState("");

    // Mod ref’leri: click handler tek sefer kayıtlı kalsın
    const measureModeRef = useRef(measureMode);
    const addModeRef = useRef(addMode);
    useEffect(() => { measureModeRef.current = measureMode; }, [measureMode]);
    useEffect(() => { addModeRef.current = addMode; }, [addMode]);

    useEffect(() => {
        if (!ref.current) return;
        const map = new maplibregl.Map({ container: ref.current, style: STYLE, center: IST, zoom: 11 });
        map.addControl(new maplibregl.NavigationControl(), "top-right");

        const draw = new MapboxDraw({ displayControlsDefault: false, controls: { polygon: true, trash: true } });
        map.addControl(draw as unknown as IControl, "top-left");

        map.on("load", async () => {
            if (!map.getSource("stores")) {
                map.addSource("stores", { type: "geojson", data: emptyFC() });
                map.addLayer({ id: "stores-circle", type: "circle", source: "stores",
                    paint: { "circle-radius": 5, "circle-opacity": 0.9 }});
            }
            if (!map.getSource("heat")) {
                map.addSource("heat", { type: "geojson", data: emptyFC() });
                map.addLayer({ id: "heatmap", type: "heatmap", source: "heat", maxzoom: 18,
                    paint: { "heatmap-weight": ["coalesce", ["get","w"], 0.1],
                        "heatmap-intensity": 1, "heatmap-radius": 24, "heatmap-opacity": 0.6 }});
            }
            if (!map.getSource("measure")) {
                map.addSource("measure", { type: "geojson", data: emptyFC() });
                map.addLayer({ id: "measure-line", type: "line", source: "measure",
                    paint: { "line-color": "#ff4d00", "line-width": 3 }});
                map.addLayer({ id: "measure-pts", type: "circle", source: "measure",
                    paint: { "circle-radius": 4, "circle-color": "#ff4d00" },
                    filter: ["==", ["geometry-type"], "Point"]});
            }
            await refreshWithin(); await refreshHeat();
        });

        map.on("moveend", async () => { await refreshWithin(); await refreshHeat(); });

        // Tek handler: modlara göre davran
        map.on("click", async (e) => {
            if (measureModeRef.current) {
                measureCoordsRef.current.push([e.lngLat.lng, e.lngLat.lat]);
                renderMeasure();
                return;
            }
            if (addModeRef.current || (e.originalEvent as MouseEvent).shiftKey) {
                await addStoreAt(e.lngLat.lng, e.lngLat.lat);
                return;
            }
        });

        drawRef.current = draw;
        mapRef.current = map;
        return () => map.remove();
    }, []);

    function emptyFC(): FC { return { type: "FeatureCollection", features: [] }; }

    async function refreshWithin() {
        const map = mapRef.current; if (!map) return;
        const b = map.getBounds();
        const data = await within({ min_lat: b.getSouth(), min_lon: b.getWest(),
            max_lat: b.getNorth(), max_lon: b.getEast(), limit: 500 });
        setStores(data.items);
        const fc: FC = {
            type: "FeatureCollection",
            features: data.items.map(it => ({
                type: "Feature",
                geometry: { type: "Point", coordinates: [it.loc.lon, it.loc.lat] },
                properties: { id: it.id, name: it.name ?? "" }
            }))
        };
        (map.getSource("stores") as GeoJSONSource).setData(fc);
        map.setLayoutProperty("stores-circle", "visibility", showPoints ? "visible" : "none");
    }

    async function refreshHeat() {
        const map = mapRef.current; if (!map) return;
        const z = Math.max(1, Math.min(29, Math.floor(map.getZoom()) + 3));
        const data = await heat(z);
        const max = Math.max(1, ...data.tiles.map(t => t.doc_count));
        const fc: FC = {
            type: "FeatureCollection",
            features: data.tiles.filter(t => t.centroid).map(t => ({
                type: "Feature",
                geometry: { type: "Point", coordinates: [t.centroid!.lon, t.centroid!.lat] },
                properties: { w: t.doc_count / max }
            }))
        };
        (map.getSource("heat") as GeoJSONSource).setData(fc);
        map.setLayoutProperty("heatmap", "visibility", showHeat ? "visible" : "none");
    }

    // Mağaza ekleme yardımcıları
    async function addStoreAt(lon: number, lat: number) {
        const name = storeName.trim() || `Şube ${nameSeq}`;
        const res = await createStore(name, { lat, lon });
        setLastId(res.id);
        setNameSeq(n => n + 1);
        new maplibregl.Marker().setLngLat([lon, lat]).addTo(mapRef.current!);
        await refreshWithin(); await refreshHeat();
    }
    async function addStoreAtCenter() {
        const c = mapRef.current?.getCenter(); if (!c) return;
        await addStoreAt(c.lng, c.lat);
    }

    // Ölçüm
    function renderMeasure() {
        const map = mapRef.current; if (!map) return;
        const coords = measureCoordsRef.current;
        const features: any[] = [];
        if (coords.length >= 2) features.push({ type: "Feature", properties: {}, geometry: { type: "LineString", coordinates: coords } });
        for (const c of coords) features.push({ type: "Feature", properties: {}, geometry: { type: "Point", coordinates: c } });
        (map.getSource("measure") as GeoJSONSource).setData({ type: "FeatureCollection", features });
        setMeasureTotalKm(totalDistanceKm(coords));
    }
    function totalDistanceKm(coords: number[][]): number {
        let t = 0; for (let i = 1; i < coords.length; i++) t += haversine(coords[i-1][1], coords[i-1][0], coords[i][1], coords[i][0]);
        return t;
    }
    function haversine(lat1:number, lon1:number, lat2:number, lon2:number): number {
        const R = 6371, toRad = (d:number)=>d*Math.PI/180;
        const dLat = toRad(lat2-lat1), dLon = toRad(lon2-lon1);
        const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;
        return 2*R*Math.asin(Math.sqrt(a));
    }
    function clearMeasure() {
        measureCoordsRef.current = [];
        (mapRef.current?.getSource("measure") as GeoJSONSource)?.setData(emptyFC());
        setMeasureTotalKm(0);
    }

    async function savePolygon() {
        if (!lastId || !drawRef.current) return;
        const feats = drawRef.current.getAll();
        const poly = feats.features.find(f => f.geometry.type === "Polygon") as any;
        if (!poly) return;
        await setArea(lastId, poly.geometry.coordinates[0]);
    }

    async function findNearest() {
        const c = mapRef.current?.getCenter(); if (!c) return;
        const res = await nearest(c.lat, c.lng, 5, 3);
        alert(res.items.map(i => i.name || i.id).join(", "));
    }
    async function checkEligible() {
        const c = mapRef.current?.getCenter(); if (!c) return;
        const res = await eligible(c.lat, c.lng);
        alert(res.eligible ? "Uygun" : "Uygun değil");
    }
    async function useMyLocation() {
        if (!mapRef.current || !navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((pos) => {
            mapRef.current!.flyTo({ center: [pos.coords.longitude, pos.coords.latitude], zoom: 13 });
        });
    }
    async function selectStore(id: string) {
        const map = mapRef.current, draw = drawRef.current; if (!map || !draw) return;
        const s: StoreDoc = await getStore(id);
        map.flyTo({ center: [s.loc.lon, s.loc.lat], zoom: 14 });
        const all = draw.getAll(); if (all.features.length) draw.delete(all.features.map(f => f.id as string));
        if (s.service_area?.coordinates?.[0]) {
            draw.add({ id: `area-${s.id}`, type: "Feature", properties: { name: s.name ?? "" },
                geometry: { type: "Polygon", coordinates: s.service_area.coordinates[0] } } as any);
            setLastId(s.id);
        }
    }

    return (
        <div style={{ height: "100%", display: "grid", gridTemplateColumns: "340px 1fr" }}>
            <aside style={{ background: "#181818", color: "#fff", padding: 10, overflow: "auto" }}>
                <div style={{ display: "grid", gap: 8, marginBottom: 8 }}>
                    <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                        <button onClick={useMyLocation}>Konumum</button>
                        <button onClick={savePolygon}>Polygonu Kaydet</button>
                        <button onClick={findNearest}>5km içi en yakın 3</button>
                        <button onClick={checkEligible}>Merkez teslimat?</button>
                    </div>

                    <div style={{ display: "grid", gap: 6 }}>
                        <label>Mağaza adı</label>
                        <input
                            value={storeName}
                            onChange={(e) => setStoreName(e.target.value)}
                            placeholder={`Şube ${nameSeq}`}
                        />
                        <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                            <button onClick={() => setAddMode(m => !m)} style={{ background: addMode ? "#0b6" : undefined }}>
                                {addMode ? "Ekleme modu: AÇIK" : "Ekleme modu: KAPALI"}
                            </button>
                            <button onClick={addStoreAtCenter}>Merkezde Ekle</button>
                        </div>
                        <small style={{ opacity: .8 }}>
                            Ekleme modu açıkken haritaya tıklayarak mağaza eklersin. Ad boşsa otomatik “Şube N” verilir.
                        </small>
                    </div>

                    <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
                        <button
                            onClick={() => setMeasureMode(m => !m)}
                            style={{ background: measureMode ? "#ff4d00" : undefined }}>
                            {measureMode ? "Ölçüm: AÇIK" : "Ölçüm: KAPALI"}
                        </button>
                        <button onClick={clearMeasure}>Ölçümü Temizle</button>
                        <span style={{ opacity: .85 }}>Toplam: {measureTotalKm.toFixed(3)} km</span>
                    </div>

                    <div style={{ display: "flex", gap: 12 }}>
                        <label style={{ display: "flex", alignItems: "center", gap: 6 }}>
                            <input type="checkbox" checked={showPoints}
                                   onChange={async e => { setShowPoints(e.target.checked); await refreshWithin(); }} />
                            Noktalar
                        </label>
                        <label style={{ display: "flex", alignItems: "center", gap: 6 }}>
                            <input type="checkbox" checked={showHeat}
                                   onChange={async e => { setShowHeat(e.target.checked); await refreshHeat(); }} />
                            Isı
                        </label>
                    </div>
                </div>

                <h4 style={{ margin: "6px 0" }}>Görünümdeki mağazalar</h4>
                <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "grid", gap: 6 }}>
                    {stores.map(s => (
                        <li key={s.id}>
                            <button style={{ width: "100%", textAlign: "left" }} onClick={() => selectStore(s.id)}>
                                {s.name ?? s.id}
                            </button>
                        </li>
                    ))}
                </ul>
            </aside>

            <div style={{ height: "100%" }} ref={ref} />
        </div>
    );
}
