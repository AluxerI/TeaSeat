import { Grid } from "@mui/material";
import { ProductItem } from "./productItem";
import type { Product } from "../interfaces/catalog";
import { normalizeAssetUrl } from "../utils/assetUrl";

interface ProductListProps {
  products: Product[];
  onQuickView: (product: Product) => void;
}

export const ProductList = ({ products, onQuickView }: ProductListProps) => (
  <Grid
    container
    direction="row"
    flexWrap="wrap"
    justifyContent="stretch"
    alignItems="center"
    className="grid-category"
    rowSpacing={2}
    columnSpacing={3}
  >
    {products.map((product) => (
      <ProductItem
        key={product.id}
        productId={product.id}
        image={normalizeAssetUrl(product.main_image)}
        backgroundImage={normalizeAssetUrl(product.background_image)}
        label={product.name}
        brand={product.brand}
        ratingAverage={product.rating_average}
        reviewsCount={product.reviews_count ?? 0}
        finalPrice={product.final_price}
        originalPrice={product.original_price}
        discountPercent={product.discount_percent}
        measurement={{
          stockUnit: product.stock_unit,
          saleStep: product.sale_step,
          priceUnitQuantity: product.price_unit_quantity,
        }}
        availableQuantity={product.total_quantity}
        isAvailable={product.is_available}
        onQuickView={() => onQuickView(product)}
      />
    ))}
  </Grid>
);
