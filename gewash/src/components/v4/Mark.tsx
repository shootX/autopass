export function Mark({ size = 52, light = false }: { size?: number; light?: boolean }) {
  const forest = light ? "#FFFFFF" : "#14482F";
  return (
    <svg width={size} height={size} viewBox="0 0 100 100" aria-hidden>
      <path fill={forest} d="M8 0H37A8 8 0 0 1 45 8V37A8 8 0 0 1 37 45H8A8 8 0 0 1 0 37V8A8 8 0 0 1 8 0ZM8 55H37A8 8 0 0 1 45 63V92A8 8 0 0 1 37 100H8A8 8 0 0 1 0 92V63A8 8 0 0 1 8 55Z" />
      <path fill="#B5DD3A" d="M63 0H92A8 8 0 0 1 100 8V37A8 8 0 0 1 92 45H63A8 8 0 0 1 55 37V8A8 8 0 0 1 63 0Z" />
      <path fill={forest} fillRule="evenodd" d="M63 55H92A8 8 0 0 1 100 63V92A8 8 0 0 1 92 100H63A8 8 0 0 1 55 92V63A8 8 0 0 1 63 55ZM77.5 64.45C81.64 69.1 86.5 74.37 86.5 79.95A9 9 0 0 1 68.5 79.95C68.5 74.37 73.36 69.1 77.5 64.45Z" />
    </svg>
  );
}
