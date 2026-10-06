import React, { useRef, useEffect, useState } from 'react';
import maplibregl from 'maplibre-gl';
import MapView, { Marker } from '@vis.gl/react-maplibre';
import 'maplibre-gl/dist/maplibre-gl.css';
import { GEORGIA_BOUNDS, georgiaMapStyle, prepareGeorgiaMap } from './georgiaMap';
import PinIcon from '../../public/images/branch-mark.png';
import CallIcon from '@/assets/icons/ManagerOrder/call_icon.svg?react';
import PathIcon from '@/assets/icons/path-icon.svg?react';
import CalendarIconYellow from '@/assets/icons/calendar-icon-yellow.svg?react';
import { useNavigate } from 'react-router-dom';
import type { Branch } from '@/hooks/useFetchBranches';
import { Source, Layer } from '@vis.gl/react-maplibre';
import { useTranslation } from '@/hooks/useTranslation';

type BranchMapProps = {
  branches: Branch[];
  selectedBranchId: number | null;
  onSelect: (id: number | null) => void;
};

type MapMenuState = {
  visible: boolean;
  apps: Array<{ id: string; title: string }>;
  lat: number | string | null;
  lng: number | string | null;
};

export function BranchMap({ branches, selectedBranchId, onSelect }: BranchMapProps) {
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

  const handleLocateMe = () => {
    if (!navigator.geolocation) return;

    navigator.geolocation.getCurrentPosition((position) => {
      const lng = position.coords.longitude;
      const lat = position.coords.latitude;
      setUserLocation({ lng, lat });
      mapRef.current?.flyTo({
        center: [lng, lat],
        zoom: 15,
        essential: true,
      });
    });
  };

  const handleAppointmentClick = () => {
    if (selectedBranch) {
      navigate('/wash-appointment', {
        state: {
          selectedBranchId: selectedBranch.id,
        },
      });
    }
  };

  useEffect(() => {
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
      {mapReady ? (
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
        style={{ width: '100%', height: '85vh' }}
        attributionControl={true}
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
            anchor="bottom"
            onClick={() => handleMarkerClick(branch)}
          >
            <img src={PinIcon} alt="marker" style={{ width: 52, height: 56, cursor: 'pointer' }} />
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
                'line-color': '#17BA68',
                'line-width': 4,
              }}
            />
          </Source>
        )}
      </MapView>
      ) : (
        <div className="branch-map-placeholder" />
      )}

      {selectedBranch && (
        <div className="branch-info-panel visible">
          <button type="button" className="branch-locate-btn" onClick={handleLocateMe} aria-label="My location">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>
              <circle cx="12" cy="12" r="3" fill="currentColor" />
              <circle cx="12" cy="12" r="7" stroke="currentColor" strokeWidth="2" />
              <path d="M12 2v3M12 19v3M2 12h3M19 12h3" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            </svg>
          </button>
          <div className="branch-info-panel__content">
            <div>
              <h3 className="branch-info-panel__title">{selectedBranch.name}</h3>
              <p className="branch-info-panel__text">{selectedBranch.address}</p>
            </div>
            <div>
              <p
                style={{
                  backgroundColor: selectedBranch.isOpen ? '#17BA68' : '#BA1717',
                }}
                className="branch-info-panel__status"
              >
                {selectedBranch.isOpen ? t('Branches.opened') : t('Branches.closed')}
              </p>
            </div>
          </div>

          <button
            type="button"
            className="pill-cta"
            onClick={() => navigate('/wash-appointment', { state: { selectedBranchId: selectedBranch.id } })}
          >
            ფილიალის არჩევა
            <span className="arrow">→</span>
          </button>
          <div className="branch-info-panel__actions">
            <a href={`tel:+${selectedBranch.manager.phone}`}>
              <button type="button">
                <CallIcon aria-hidden />
              </button>
            </a>
            <button type="button" onClick={handleRouteClick}>
              <PathIcon aria-hidden />
            </button>
          </div>
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
