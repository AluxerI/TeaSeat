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
    <article className="product-card">
      <div className="product-card__head">
        <Images
          name={pic_product.name}
          alt={pic_product.alt}
          type={pic_product.type}
          className="product-card__image"
        ></Images>
      </div>
      <div
        className="product-card__body"
        style={
          background.name
            ? { backgroundImage: `url(${background.name}.${background.type})` }
            : undefined
        }
      >
        <section className="section">
          <h5 className="label-product">{label}</h5>
          <div className="gramm-and-price">
            <form action="" className="grams-form">
              <p className="count-gramms">{weight} г</p>
              <div className="block-buttons">
                <button type="button" onClick={upWeight}>
                  <Images
                    name="https://cdn-icons-png.flaticon.com/512/271/271239"
                    type="png"
                    alt="icon-up"
                  ></Images>
                </button>
                <button
                  type="button"
                  onClick={downWeight}
                  className="arrow-down"
                >
                  <Images
                    name="https://cdn-icons-png.flaticon.com/512/271/271210"
                    type="png"
                    alt="icon-down"
                  ></Images>
                </button>
              </div>

              <p className="price">{pricing} Р</p>
            </form>
          </div>

          <div className="button-for-buy">
            <button type="button" className="at-cart">
              В корзину
            </button>
            <button type="button" className="quickView">
              <Images
                name="https://cdn-icons-png.freepik.com/256/64/64911"
                type="png"
                alt="icon-quick-view"
              ></Images>
            </button>
          </div>
        </section>
      </div>
    </article>
  );
};
