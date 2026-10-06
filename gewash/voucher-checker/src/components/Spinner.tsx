/** Amber arc on dark button (matches product style). */
export default function Spinner({ className = "" }: { className?: string }) {
  return (
    <span className={`ui-spinner ${className}`.trim()} role="status" aria-label="Loading" />
  );
}
