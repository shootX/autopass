import React, { useState, useEffect } from 'react';
import CallIcon from '@/assets/icons/ManagerOrder/call_icon.svg?react';
import PathIcon from '@/assets/icons/path-icon.svg?react';
import GeoIconYellow from '@/assets/icons/geo-icon-yellow.svg?react';
import CalendarIconYellow from '@/assets/icons/calendar-icon-yellow.svg?react';
import { useTranslation } from '@/hooks/useTranslation';

type Branch = {
  id: string;
  name: string;
  address: string;
  isOpen: boolean;
  lat: number | string;
  lng: number | string;
  manager: {
    phone: string;
  };
};

type Props = {
  branch: Branch;
  onGoToMap?: () => void;
  onGoToCalendar?: () => void;
};

type MapMenuState = {
  visible: boolean;
  apps: Array<{ id: string; title: string }>;
  lat: number | string | null;
  lng: number | string | null;
};

export function BranchInfoPanel({ branch, onGoToMap, onGoToCalendar }: Props) {
  const t = useTranslation();

  // Состояние для отображения меню навигаторов
  const [mapMenu, setMapMenu] = useState<MapMenuState>({
    visible: false,
    apps: [],
    lat: null,
    lng: null,
  });

  useEffect(() => {
    // Слушаем ответ от React Native со списком доступных карт
    const handleNativeMessage = (event: MessageEvent) => {
      try {
        const message = JSON.parse(event.data);
        
        if (message.type === 'NAVIGATORS_LIST') {
          // Проверяем: эта ли конкретная карточка инициировала вызов карт
          const initiator = sessionStorage.getItem('active_navigation_initiator');
          if (initiator !== `BranchInfoPanel_${branch.id}`) return;

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
  }, [branch.id]); // Добавлена зависимость по id для точечной фильтрации

  // 1. Инициирует проверку карт в нативном приложении Expo
  const handleRouteClick = (selectedBranch: any) => {
    if (!selectedBranch) return;

    // Записываем уникальный маркер нажатия для конкретной карточки
    sessionStorage.setItem('active_navigation_initiator', `BranchInfoPanel_${selectedBranch.id}`);

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
    // Скрываем модальное окно и чистим за собой сессию
    setMapMenu({ visible: false, apps: [], lat: null, lng: null });
    sessionStorage.removeItem('active_navigation_initiator');
  };

  return (
    <div className="branch-info-panel-card visible">
      <div className="branch-info-panel__content">
        <div>
          <h3 className="branch-info-panel__title">{branch.name}</h3>
          <p className="branch-info-panel__text">{branch.address}</p>
        </div>
        <div>
          <p
            className="branch-info-panel__status"
            style={{
              backgroundColor: branch.isOpen ? '#17BA68' : '#BA1717',
            }}
          >
            {branch.isOpen ? t('Branches.opened') : t('Branches.closed')}
          </p>
        </div>
      </div>

      <div className="branch-info-panel__actions">
        <a href={`tel:+${branch.manager.phone}`}>
          <button type="button" className="call-button">
            <CallIcon aria-hidden />
          </button>
        </a>

        <button type="button" onClick={() => handleRouteClick(branch)}>
          <PathIcon aria-hidden />
        </button>

        <button type="button" onClick={onGoToMap}>
          <GeoIconYellow aria-hidden />
        </button>
      </div>

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
              {t('WashAppointmentsCalendar.popup.cancelButton')}
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
