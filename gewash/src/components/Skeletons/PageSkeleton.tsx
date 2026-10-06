import ContentLoader from 'react-content-loader';

// Вспомогательный компонент для скелетона текста/кнопки
const SkeletonBlock = ({
  width = '100%',
  height = '100%',
  borderRadius = 8,
}: {
  width?: string | number;
  height?: string | number;
  borderRadius?: number;
}) => (
  <ContentLoader
    speed={2}
    width="100%"
    height="100%"
    backgroundColor="#e2e8f0"
    foregroundColor="#f1f5f9"
    style={{ width: '100%', height: '100%' }}
  >
    <rect x="0" y="0" rx={borderRadius} ry={borderRadius} width="100%" height="100%" />
  </ContentLoader>
);

export default function PageSkeleton() {
  return (
    <div style={{ width: '100%', minHeight: '100vh', boxSizing: 'border-box' }}>
      <header className="header-container">
        <div style={{ width: 24, height: 24 }}>
          <SkeletonBlock borderRadius={4} />
        </div>
        <div style={{ width: 120, height: 20 }}>
          <SkeletonBlock borderRadius={6} />
        </div>
        <div style={{ width: 24, height: 24 }}>
          <SkeletonBlock borderRadius={4} />
        </div>
      </header>

      <main style={{ padding: '16px', display: 'flex', flexDirection: 'column', gap: '16px' }}>
        <div style={{ display: 'flex', gap: '12px', width: '100%' }}>
          <div style={{ flex: 1, height: 42 }}>
            <SkeletonBlock borderRadius={10} />
          </div>
          <div style={{ flex: 1, height: 42 }}>
            <SkeletonBlock borderRadius={10} />
          </div>
        </div>

        {[1, 2, 3].map((item) => (
          <div key={item} style={{ width: '100%', height: 126 }}>
            <SkeletonBlock borderRadius={16} />
          </div>
        ))}
      </main>

      <div className="nav-bar-wrapper">
        <nav className="nav-bar-container">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} style={{ width: 48, height: 48 }}>
              <SkeletonBlock borderRadius={12} />
            </div>
          ))}
        </nav>
      </div>
    </div>
  );
}
