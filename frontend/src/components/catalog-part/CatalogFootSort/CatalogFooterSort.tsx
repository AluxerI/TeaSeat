import { Picture } from "../../../types/utils";

import Images from "./../../../utils/Images";

interface ConstructPropCart {
  pict: Picture;
  label: string;
  description: string;
}

export const CartConstruct = ({
  pict,
  label,
  description,
}: ConstructPropCart) => {
  return (
    <div className="construct__cart">
      <Images alt={pict.alt} name={pict.name} type={pict.type}></Images>
      <div className="construct__cart-info">
        <h4 className="construct__cart-label">{label}</h4>
        <p className="construct__cart-description">{description}</p>
      </div>
    </div>
  );
};

export const constructFootPart = () => {
  return (
    <div className="construct__main">
      <h3 className="construct__label">Конструктор подарков</h3>
      <p className="construct__description">
        Создайте уникальный подарочный набор
      </p>

      <div className="cart-construct-all">
        <CartConstruct
          label="Выберите товары"
          pict={{
            name: "construct-cart-1",
            alt: "construct-cart-1",
            type: "svg",
          }}
          description="Добавьте любимые чаи, кофе и десерты в свой набор"
        ></CartConstruct>
        <CartConstruct
          label="Оформите упаковку"
          pict={{
            name: "construct-cart-2",
            alt: "construct-cart-2",
            type: "svg",
          }}
          description="Выберите стильную коробку и дизайн упаковки"
        ></CartConstruct>
        <CartConstruct
          label="Получите подарок"
          pict={{
            name: "construct-cart-3",
            alt: "construct-cart-3",
            type: "svg",
          }}
          description="Доставим ваш товар прямо к двери"
        ></CartConstruct>

        <button type="button">Создать подарок</button>
      </div>
    </div>
  );
};
