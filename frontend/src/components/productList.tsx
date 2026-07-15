import { Grid } from "@mui/material";
import { ProductItem } from "./productItem";
import { Product, Category } from "../interfaces/catalog";
import { Picture } from "../types/utils";
import { typePic } from "./catalog";
import { Discount } from "../types/catalog";
import { useMemo } from "react";

// Убирает origin бэкенда из URL картинок, чтобы запрос шёл через CRA-прокси
function stripOrigin(url: string): string {
  return url.replace(/^https?:\/\/[^\/]+/, "");
}

interface ProductListProps {
  products: Product[];
  categories: Category[];
}

export const ProductList = ({ products, categories }: ProductListProps) => {
  const enriched = useMemo(() => {
    const categoryMap = new Map(categories?.map((c) => [c.name, c]));
    return products?.map((product) => ({
      ...product,
      category_path: {
        category: categoryMap.get(product.category_path.category),
      },
    }));
  }, [products, categories]);

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
      {enriched?.map((value) => {
        const bgUrl = stripOrigin(value.background_image);
        const imgUrl = stripOrigin(value.main_image);
        const part_background: Picture = {
          name: bgUrl.split(".").slice(0,-1).join(),
          type: bgUrl.split(".").slice(-1).join() as typePic,
          alt: bgUrl.split(".").slice(0,-1).join()
        };
        const part_product: Picture = {
          name: imgUrl.split(".").slice(0,-1).join(),
          type: imgUrl.split(".").slice(-1).join() as typePic,
          alt: imgUrl.split(".").slice(0,-1).join(),
        };

        return (
          <ProductItem
            background={part_background}
            brand={value.brand}
            label={value.name}
            weight={value.weight_grams}
            has_discount={value.discount != null}
            discounts={value.discount ? Array(Discount.form(value.discount.value)) : []}
            pic_product={part_product}
            inventory={value.inventory}
            is_available={value.is_available && value.total_quantity > 0}
            pricing={value.final_price}
            key={value.id}
            which_category={value.category_path.category!}
            date_create={value.created_at}
            date_update={value.update_at}
          ></ProductItem>
        );
      })}
    </Grid>
  );
};
