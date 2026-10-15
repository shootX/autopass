import { useNavigate } from 'react-router-dom';
import '@/styles/settings.scss';
import { LanguageDropdownSheet } from '../ui/LanguageDropdownSheet';
import { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import type { RootState, AppDispatch } from '@/store';
import { setLanguage } from '@/store/langSlice';
import { useTranslation } from '@/hooks/useTranslation';
import { languageNativeName } from '@/lib/languageLabel';
import {
  leftArrowUrl,
  langSettingIconUrl,
  rightArrowUrl,
  leaveSettingsIconUrl,
  deleteSettingsIconUrl,
} from '@/assets/staticUrls';
import Header from '@/components/Header';
import { logoutSession } from "@/lib/sessionRefresh";

export default function SettingsPage() {
  const navigate = useNavigate();
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const dispatch = useDispatch<AppDispatch>();
  const currentLang = useSelector((s: RootState) => s.lang.currentLang);
  const [selectedLang, setSelectedLang] = useState<RootState['lang']['currentLang']>(currentLang);
  const [logoutModalOpen, setLogoutModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const userRole = useSelector((s: RootState) => s.user.role);
  const isManager = userRole === 'manager';

  useEffect(() => {
    if (dropdownOpen) {
      setSelectedLang(currentLang);
    }
  }, [dropdownOpen, currentLang]);

  const languageLabel = languageNativeName(currentLang);

  const handleSaveLanguage = () => {
    dispatch(setLanguage(selectedLang));
    setDropdownOpen(false);
  };

  const handleLogout = () => {
    void logoutSession();
  };

  const t = useTranslation();

  return (
    <div>
      <Header
        variant="bar"
        title={t('Sidebar.menu.settings')}
        titleAs="p"
        endSpacer
        left={<img onClick={() => navigate(-1)} src={leftArrowUrl} alt="" />}
      />
      <div
        style={{
          margin: '16px',
          borderRadius: '16px',
          boxShadow: '0 2px 4px 2px rgba(0, 0, 0, 0.1)',
          padding: '24px 16px 12px 16px',
        }}
        className="settings-lng-block"
      >
        <div
          className="setting-item-wrapper"
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            cursor: 'pointer',
          }}
          onClick={() => setDropdownOpen(true)}
        >
          <div>
            <img className="setting-left-icon" src={langSettingIconUrl} alt="" />
            <span>{t('Settings.language')}</span>
          </div>
          <div>
            <span>{languageLabel}</span>
            <img className="setting-right-icon" src={rightArrowUrl} alt="" />
          </div>
        </div>
        <div
          className="setting-item-wrapper"
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            cursor: 'pointer',
          }}
          onClick={() => setLogoutModalOpen(true)}
        >
          <div>
            <img className="setting-left-icon" src={leaveSettingsIconUrl} alt="" />
            <span>{t('Settings.account')}</span>
          </div>
          <div>
            <span>{t('Settings.logout')}</span>
            <img className="setting-right-icon" src={rightArrowUrl} alt="" />
          </div>
        </div>
      </div>
      {!isManager && (
        <div
          style={{
            margin: '16px',
            borderRadius: '16px',
            boxShadow: '0 2px 4px 2px rgba(0, 0, 0, 0.1)',
            padding: '24px 16px 12px 16px',
          }}
        >
          <div
            className="setting-item-wrapper"
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              cursor: 'pointer',
            }}
            onClick={() => setDeleteModalOpen(true)}
          >
            <div>
              <img
                style={{ backgroundColor: '#D64541' }}
                className="setting-left-icon"
                src={deleteSettingsIconUrl}
                alt=""
              />{' '}
              <span>{t('Settings.deleteAccount')}</span>
            </div>
            <div>
              <span style={{ color: '#D64541', marginRight: '12px' }}>
                {t('Settings.deleteBtn')}
              </span>
              <svg
                width="8"
                height="15"
                viewBox="0 0 8 15"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  d="M1 1.5L7 7.5L0.999999 13.5"
                  stroke="#D64541"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </div>
          </div>
        </div>
      )}
      {logoutModalOpen && (
        <div className="modal-backdrop">
          <div className="modal-window">
            <h2>{t('Settings.logout')}?</h2>
            <h3>{t('Settings.sure_logout')}</h3>
            <div className="modal-actions">
              <button onClick={() => setLogoutModalOpen(false)}>{t('Settings.cancel')}</button>
              <button onClick={handleLogout}>{t('Settings.confirmLogout')}</button>
            </div>
          </div>
        </div>
      )}
      {deleteModalOpen && (
        <div className="modal-backdrop">
          <div className="modal-window">
            <h2 style={{ color: '#D64541' }}>{t('Settings.deleteAccount')}?</h2>
            <h3>{t('Settings.sure_delete')}</h3>
            <div className="modal-actions">
              <button onClick={() => setDeleteModalOpen(false)}>{t('Settings.cancel')}</button>
              <button
                onClick={() => {
                  console.log('Account deletion confirmed');
                  setDeleteModalOpen(false);
                }}
              >
                {t('Settings.remove_button')}
              </button>
            </div>
          </div>
        </div>
      )}

      {dropdownOpen && (
        <LanguageDropdownSheet
          open={dropdownOpen}
          setOpen={setDropdownOpen}
          savedLang={currentLang}
          selectedLang={selectedLang}
          setSelectedLang={setSelectedLang}
          onSave={handleSaveLanguage}
        />
      )}
    </div>
  );
}
