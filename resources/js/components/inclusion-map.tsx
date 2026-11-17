import { MapContainer, Rectangle, Tooltip } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';

export default function InclusionMap() {
  return <MapContainer center={[-2, 118]} zoom={4} scrollWheelZoom={false} className="inclusion-map" attributionControl={false} aria-label="Skema cakupan geografis Indonesia tanpa nilai provinsi"><Rectangle bounds={[[-11, 95], [6, 141]]} pathOptions={{ color: '#537f6d', fillColor: '#dce9dd', fillOpacity: 0.45, weight: 1 }}><Tooltip permanent direction="center">Indonesia · periksa lingkup setiap seri</Tooltip></Rectangle></MapContainer>;
}
