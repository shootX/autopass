import React, { useRef, useEffect, useState } from 'react';
import maplibregl from 'maplibre-gl';
import MapView, { Marker } from '@vis.gl/react-maplibre';
import 'maplibre-gl/dist/maplibre-gl.css';
import { GEORGIA_BOUNDS, georgiaMapStyle, prepareGeorgiaMap } from './georgiaMap';
import { Droplet, Phone, Route } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import type { Branch } from '@/hooks/useFetchBranches';
import { Source, Layer } from '@vis.gl/react-maplibre';
import { useTranslation } from '@/hooks/useTranslation';
import { DemoMap } from './DemoMap';
import { branchPhoto, hourSpan, isRoundTheClock, kmBetween } from '@/lib/v4';
import { Navigation } from 'lucide-react';

const liveMap = import.meta.env.VITE_LIVE_MAP === 'true';

type BranchMapProps = {
  branches: Branch[];
  selectedBranchId: number | null;
  onSelect: (id: number | null) => void;
  variant?: 'full' | 'panel' | 'strip';
};

type MapMenuState = {
  visible: boolean;
  apps: Array<{ id: string; title: string }>;
  lat: number | string | null;
  lng: number | string | null;
};

export function BranchMap({ branches, selectedBranchId, onSelect, variant = 'full' }: BranchMapProps) {
  const mapRef = useRef<any>(null);
  const navigate = useNavigate();
  const [mapReady, setMapReady] = useState(false);
  const [routeGeoJSON, setRouteGeoJSON] = useState<any>(null);
  const [userLocation, setUserLocation] = useState<{ lng: number; lat: number } | null>(null);
  
  // Безопасное получение филиала
  const selectedBranch = branches.find((b) => b.id === Number(selectedBranchId)) ?? branches[0];
  const t = useTranslation();

  // Состояние для отображения меню навигаторов
  const [mapMenu, setMapMenu] = useState<MapMenuState>({
    visible: false,
    apps: [],
    lat: null,
    lng: null,
  });

  useEffect(() => {
    const handleNativeMessage = (event: MessageEvent) => {
      try {
        const message = JSON.parse(event.data);
        
        if (message.type === 'NAVIGATORS_LIST') {
          // Проверяем: если кнопку нажали НЕ в этом компоненте, то просто игнорируем событие
          const initiator = sessionStorage.getItem('active_navigation_initiator');
          if (initiator !== 'BranchMap') return;

          const { navigators, lat, lng } = message.payload;
          setMapMenu({
            visible: true,
            apps: navigators,
            lat,
            lng,
          });
        }
      } catch (e) {
        // Игнорируем нерелевантные сообщения от сторонних скриптов
      }
    };

    window.addEventListener('message', handleNativeMessage);
    return () => window.removeEventListener('message', handleNativeMessage);
  }, []);

  // 1. Инициирует проверку карт в нативном приложении Expo
  const handleRouteClick = async () => {
    if (!selectedBranch) return;

    // Помечаем, что запрос ушел именно из компонента Карты
    sessionStorage.setItem('active_navigation_initiator', 'BranchMap');

    window.ReactNativeWebView?.postMessage(
      JSON.stringify({
        type: 'navigate',
        lat: selectedBranch.lat,
        lng: selectedBranch.lng,
      }),
    );
  };

  // 2. Отправляет команду в Expo на открытие конкретной выбранной карты
  const handleSelectMapApp = (appId: string) => {
    if (window.ReactNativeWebView) {
      window.ReactNativeWebView.postMessage(
        JSON.stringify({
          type: 'open_selected_navigator',
          payload: { 
            id: appId, 
            lat: mapMenu.lat, 
            lng: mapMenu.lng 
          }
        })
      );
    }
    // Скрываем модальное окно и чистим за собой инициатора
    setMapMenu({ visible: false, apps: [], lat: null, lng: null });
    sessionStorage.removeItem('active_navigation_initiator');
  };

  const handleMarkerClick = (branch: Branch) => {
    onSelect(branch.id);
    mapRef.current?.flyTo({
      center: [branch.lng, branch.lat],
      zoom: 15,
      essential: true,
    });
  };

  useEffect(() => {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
      (position) => setUserLocation({ lng: position.coords.longitude, lat: position.coords.latitude }),
      () => undefined,
      { maximumAge: 60000, timeout: 4000 },
    );
  }, []);

  useEffect(() => {
    if (!liveMap) return;
    let cancelled = false;
    prepareGeorgiaMap()
      .then(() => {
        if (!cancelled) setMapReady(true);
      })
      .catch(() => {
        if (!cancelled) setMapReady(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    document.body.classList.toggle('map-locked', !!selectedBranch);
    return () => {
      document.body.classList.remove('map-locked');
    };
  }, [selectedBranch]);

  return (
    <div className="branch-map-wrapper">
      {liveMap && mapReady ? (
      <MapView
        ref={mapRef}
        mapLib={maplibregl}
        initialViewState={{
          longitude: selectedBranch ? (selectedBranch.lng as number) : 43.6,
          latitude: selectedBranch ? (selectedBranch.lat as number) : 42.2,
          zoom: selectedBranch ? 14 : 6.4,
        }}
        mapStyle={georgiaMapStyle}
        maxBounds={GEORGIA_BOUNDS}
        minZoom={6}
        maxZoom={16}
        style={{ width: '100%', height: '100%' }}
        attributionControl={true}
        onLoad={() => mapRef.current?.resize()}
        scrollZoom={{ around: 'center' }}
        dragPan={true}
        touchZoomRotate={true}
        doubleClickZoom={false}
      >
        {branches.map((branch) => (
          <Marker
            key={branch.id}
            longitude={branch.lng as number}
            latitude={branch.lat as number}
            anchor="center"
            onClick={() => handleMarkerClick(branch)}
          >
            <span className={`ap-pin${Number(selectedBranchId) === branch.id ? ' sel' : ''}`} aria-hidden>
              <Droplet size={Number(selectedBranchId) === branch.id ? 18 : 12} fill="currentColor" />
            </span>
          </Marker>
        ))}
        {userLocation && (
          <Marker longitude={userLocation.lng} latitude={userLocation.lat} anchor="center">
            <span className="branch-user-dot" />
          </Marker>
        )}
        {routeGeoJSON && (
          <Source
            id="route"
            type="geojson"
            data={{
              type: 'Feature',
              geometry: routeGeoJSON,
            }}
          >
            <Layer
              id="route-line"
              type="line"
              paint={{
                'line-color': '#2D7F50',
                'line-width': 4,
              }}
            />
          </Source>
        )}
      </MapView>
      ) : liveMap ? (
        <div className="branch-map-placeholder" />
      ) : (
        <DemoMap
          branches={branches}
          selectedBranchId={selectedBranch?.id ?? null}
          onSelect={(id) => {
            const branch = branches.find((item) => item.id === id);
            if (branch) handleMarkerClick(branch);
          }}
        />
      )}

      {selectedBranch && variant === 'full' && (
        <div className="ap-branch-card v4-branch-card">
          <div className="ph" style={{ backgroundImage: `url(${branchPhoto(selectedBranch)})` }}>
            {selectedBranch.isOpen === true && <span className="v4-chip ok" style={{ position: 'absolute', left: 10, top: 10, background: '#fff' }}>● {t('BranchInfoPanel.status.open')}</span>}
            {selectedBranch.isOpen === false && <span className="v4-chip" style={{ position: 'absolute', left: 10, top: 10, background: '#fff', color: 'var(--ap-error)' }}>{t('BranchInfoPanel.status.close')}</span>}
            {isRoundTheClock(selectedBranch.workStart, selectedBranch.workEnd) && <span className="v4-chip lime" style={{ position: 'absolute', left: 10, top: 10 }}>24/7</span>}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginTop: 12, padding: '0 4px' }}>
            <button type="button" onClick={() => navigate(`/branches/${selectedBranch.id}`)} style={{ flex: 1, border: 0, background: 'transparent', textAlign: 'left', padding: 0, cursor: 'pointer' }}>
              <h3>{selectedBranch.name}</h3>
              <p>{selectedBranch.address}</p>
            </button>
            <button type="button" className="v4-ib" style={{ width: 40, height: 40, borderRadius: 12 }} onClick={handleRouteClick} aria-label="მარშრუტი">
              <Navigation size={18} />
            </button>
          </div>
          <div className="meta">
            {userLocation && kmBetween(userLocation.lat, userLocation.lng, selectedBranch.lat, selectedBranch.lng) != null && (
              <div>
                <b>{kmBetween(userLocation.lat, userLocation.lng, selectedBranch.lat, selectedBranch.lng)!.toFixed(1)} კმ</b>
                <span>მანძილი</span>
              </div>
            )}
            {hourSpan(selectedBranch.workStart, selectedBranch.workEnd) && (
              <div>
                <b>{hourSpan(selectedBranch.workStart, selectedBranch.workEnd)}</b>
                <span>საათები</span>
              </div>
            )}
            {selectedBranch.phone && (
              <a href={`tel:+${String(selectedBranch.phone).replace(/\D/g, '')}`} style={{ textDecoration: 'none', color: 'inherit' }}>
                <b>{t('Branches.call') || 'ზარი'}</b>
                <span><Phone size={12} /></span>
              </a>
            )}
          </div>
          <button
            type="button"
            className="ap-btn"
            style={{ marginTop: 12, height: 52 }}
            onClick={() => navigate('/wash-appointment', { state: { selectedBranchId: selectedBranch.id } })}
          >
            სერვისზე ჩაწერა
          </button>
        </div>
      )}
      {selectedBranch && variant === 'panel' && (
        <div className="ap-branch-card">
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
            <button type="button" onClick={() => navigate(`/branches/${selectedBranch.id}`)} style={{ flex: 1, border: 0, background: 'transparent', textAlign: 'left', padding: 0, cursor: 'pointer' }}>
              <h3>{selectedBranch.name}</h3>
              <p>{selectedBranch.address}</p>
            </button>
            {selectedBranch.isOpen === true && <span className="ap-open">{t('BranchMap.panel.status.open')}</span>}
            {selectedBranch.isOpen === false && <span className="ap-closed">{t('BranchInfoPanel.status.close')}</span>}
          </div>
          <button type="button" className="ap-btn" style={{ marginTop: 18, height: 52 }} onClick={() => navigate('/wash-appointment', { state: { selectedBranchId: selectedBranch.id } })}>
            სერვისზე ჩაწერა
          </button>
          <button type="button" onClick={handleRouteClick} aria-label="route" style={{ marginTop: 10, border: 0, background: 'transparent', color: 'var(--ap-ink)', cursor: 'pointer' }}>
            <Route size={18} />
          </button>
        </div>
      )}

      {/* Модальное окно выбора навигаторов (только для iOS при наличии нескольких карт) */}
      {mapMenu.visible && (
        <div style={styles.overlay} onClick={() => {
          setMapMenu(prev => ({ ...prev, visible: false }));
          sessionStorage.removeItem('active_navigation_initiator');
        }}>
          <div style={styles.menuContainer} onClick={(e) => e.stopPropagation()}>
            
            <div style={styles.list}>
              {mapMenu.apps.map((app) => (
                <button 
                  key={app.id} 
                  type="button"
                  onClick={() => handleSelectMapApp(app.id)} 
                  style={styles.appBtn}
                >
                  {app.title}
                </button>
              ))}
            </div>

            <button 
              type="button"
              onClick={() => {
                setMapMenu({ visible: false, apps: [], lat: null, lng: null });
                sessionStorage.removeItem('active_navigation_initiator');
              }} 
              style={styles.cancelBtn}
            >
              Отмена
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

const styles = {
  overlay: {
    position: 'fixed' as const,
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.4)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 9999,
  },
  menuContainer: {
    backgroundColor: '#fff',
    padding: '20px',
    borderRadius: '16px',
    width: '280px',
    boxShadow: '0 4px 20px rgba(0,0,0,0.15)',
    display: 'flex',
    flexDirection: 'column' as const,
    gap: '12px',
  },
  title: {
    margin: '0 0 4px 0',
    textAlign: 'center' as const,
    color: '#333',
    fontSize: '16px',
    fontWeight: 'bold',
  },
  list: {
    display: 'flex',
    flexDirection: 'column' as const,
    gap: '8px',
  },
  appBtn: {
    padding: '12px',
    border: '1px solid #E5E5EA',
    borderRadius: '10px',
    backgroundColor: '#F2F2F7',
    fontSize: '15px',
    fontWeight: '600',
    color: '#007AFF',
    cursor: 'pointer',
    textAlign: 'center' as const,
  },
  cancelBtn: {
    padding: '8px',
    border: 'none',
    backgroundColor: 'transparent',
    color: '#FF3B30',
    fontSize: '15px',
    cursor: 'pointer',
    marginTop: '4px',
  },
};
