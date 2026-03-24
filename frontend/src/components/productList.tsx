import { Grid } from "@mui/material";
import { catalogApi } from "../api/catalogAPI";
import { useAsync } from "../hooks/useAsync";
import { ProductItem } from "./productItem";
import { Product } from "../interfaces/catalog";
import { Picture } from "../types/utils";
import { typePic } from "./catalog";
import { Discount } from "../types/catalog";
import { CategoryAPI } from "../api/categoryAPI";
import { useMemo } from "react";

export const ProductList = () => {
  const products = useAsync(() => catalogApi.getProducts(), true);
  const category = useAsync(() => catalogApi.getCategory(), true);

  const EnrichedProducts = useMemo(() => {
    if (!products || !category) return [];
    const categoryMap = new Map(category.data?.map((c) => [c.name, c]));
    return products.data?.map((product) => ({
      ...product,
      category_path: {
        category: categoryMap.get(product.category_path.category),
      },
    }));
  }, [products, category]);

  return (
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
      {EnrichedProducts?.map((value) => {
        console.log(value.images.background.split("."));
        const part_background: Picture = {
          name: value.images.background.split(".")[0],
          type: value.images.background.split(".")[1] as typePic,
          alt: value.images.background.split(".")[0],
        };
        const part_product: Picture = {
          name: value.images.main.split(".")[0],
          type: value.images.main.split(".")[1] as typePic,
          alt: value.images.main.split(".")[0],
        };

        return (
          <ProductItem
            background={part_background}
            brand={value.brand}
            label={value.name}
            weight={value.weight_grams}
            has_discount={value.pricing.has_discount}
            discounts={Array(Discount.form(value.pricing.personal_discount))}
            pic_product={part_product}
            inventory={value.inventory}
            is_available={value.is_available && value.total_quantity > 0}
            pricing={value.sold_count}
            key={value.id}
            which_category={value.category_path.category!} // not safefull
            //rating={}
            date_create={value.created_at}
            date_update={value.update_at}
          ></ProductItem>
        );
      })}
    </Grid>
  );
};
