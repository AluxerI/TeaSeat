import { Category } from "../interfaces/catalog";
import { Warehouse } from "../interfaces/warehouse";
import { Discount, Rating } from "../types/catalog";
import { Picture } from "../types/utils";
import Images from "../utils/Images";

interface ProductProp {
  background: Picture;
  pic_product: Picture;
  label: string;
  brand: string;

  pricing: number;
  weight: number;
  //rating: Rating;
  has_discount: boolean;
  discounts: Discount[];

  which_category: Category;
  is_available: boolean;
  inventory: Warehouse[];
  date_create: Date;
  date_update: Date;
}

export const ProductItem = ({
  background,
  pic_product,
  label,
  brand,
  pricing,
  weight,
  //rating,
  has_discount,
  discounts,
  which_category,
  is_available,
  inventory,
  date_create,
  date_update,
}: ProductProp) => {
  let step = 5;

  const upWeight = () => {
    weight += step;
  };
  const downWeight = () => {
    weight -= step;
  };
  return (
    <div className="product-card">
      <div className="product-card__head">
        <Images
          name={pic_product.name}
          alt={pic_product.alt}
          type={pic_product.type}
          className="product-card__image"
        ></Images>

      </div>
      <div className="body-card">
        <section className="section">
          <h5 className="label-product">{label}</h5>
          <div className="gramm-and-price">
            <form action="" className="grams-form">
              <Images
                className="background"
                name={background.name}
                alt={background.alt}
                type={background.type}
              ></Images>
              <p className="count-gramms">{weight}</p>
              <button type="button" onClick={upWeight}>
                <Images
                  name="https://cdn-icons-png.flaticon.com/512/271/271239"
                  type="png"
                  alt="icon-up"
                ></Images>
              </button>
              <button type="button" onClick={downWeight}>
                <Images
                  name="https://cdn-icons-png.flaticon.com/512/271/271210"
                  type="png"
                  alt="icon-down"
                ></Images>
              </button>
            </form>
            <p className="price">{pricing}</p>
          </div>
        </section>
      </div>
    </div>
  );
};
