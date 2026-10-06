import ContentLoader from "react-content-loader";

export default function CustomerInfoSkeleton() {
  return (
    <ContentLoader
      speed={2}
      width={400}
      height={220}
      viewBox="0 0 400 220"
      backgroundColor="#d9d9d9"
      foregroundColor="#ededed"
    >
      {/* Заголовок */}
      <rect x="0" y="10" rx="4" ry="4" width="180" height="24" />

      {/* Телефон */}
      <rect x="0" y="50" rx="4" ry="4" width="200" height="20" />
      <rect x="0" y="80" rx="4" ry="4" width="250" height="40" />
      <rect x="220" y="80" rx="4" ry="4" width="24" height="25" />

      {/* Email — с отступом */}
      <rect x="0" y="150" rx="4" ry="4" width="200" height="20" />
      <rect x="0" y="180" rx="4" ry="4" width="320" height="40" />
      <rect x="270" y="180" rx="4" ry="4" width="24" height="24" />
    </ContentLoader>
  );
}
