import { useSelector } from 'react-redux';
import { type RootState } from '@/store';
import { useTranslation } from '@/hooks/useTranslation';
import { useUserRole } from '../.././../hooks/useUserRole';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { customFetch } from '@/utils/customFetch';

export default function MyData() {
  const user = useSelector((state: RootState) => state.user.data);
  const [branch, setBranch] = useState<{ name: string; address: string } | null>(null);

  const role = useUserRole();

  useEffect(() => {
    const token = localStorage.getItem('access_token');

    customFetch(`${import.meta.env.VITE_API_URL}/manager/my-washes`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then(async (res) => {
        if (!res.ok) throw new Error('Failed to fetch wash info');
        const data = await res.json();

        const wash = data['my_wash'];
        if (wash) setBranch(wash);
      })
      .catch((err) => console.error('Wash fetch error:', err));
  }, []);
  const t = useTranslation();

  if (!user) return <div>Loading...</div>;

  return (
    <div className="my-data-container">
      <header>
        {t('MyData.header')}
        <Link to="/settings">
          <button
            style={{
              backgroundColor: '#183d69',
              color: '#f7b233',
              padding: '12px',
              borderRadius: '12px',
              fontSize: '14px',
            }}
            type="button"
          >
            {t('Sidebar.menu.settings')}
          </button>
        </Link>
      </header>

      <div className="my-info">
        <h2>{t('MyData.info.title')}</h2>

        <div className="name-info">
          <div className="f-name">
            <p>{t('MyData.info.firstName')}</p>
            <p className="bold">{user.firstName}</p>
          </div>
          <div className="s-name">
            <p>{t('MyData.info.lastName')}</p>
            <p className="bold">{user.lastName}</p>
          </div>
        </div>
        <div className="phone-number">
          <p>{t('MyData.info.phone')}</p>
          <p className="bold">{user.phone}</p>
        </div>
        <div className="type">
          <p>{t('MyData.info.role')}</p>
          <p className="bold">{t(`MyData.info.${role?.trim().toLowerCase()}`)}</p>
        </div>
        <div className="branch">
          <p>{t('MyData.info.branch')}</p>
          <p className="bold">{branch?.name}</p>
          <p>{branch?.address}</p>
        </div>
      </div>

      {/* <div className="my-statistic">
        <h3>{t('MyData.statistic.title')}</h3>
        <div className="statistic-button-block">
          <button className="statistic-btn">{t('MyData.statistic.buttons.day')}</button>
          <button className="statistic-btn">{t('MyData.statistic.buttons.week')}</button>
          <button className="statistic-btn">{t('MyData.statistic.buttons.month')}</button>
        </div>
      </div> */}
    </div>
  );
}
