/** Centered spinner for shop / discounts / giveaway lists while data loads. */
export default function ShopAsyncLoader() {
  return (
    <div className="shop-async-loader" aria-busy="true">
      <span className="spinner" />
    </div>
  );
}
