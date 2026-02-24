import { Category } from "../interfaces/catalog";
import { Discount, Rating } from "../types/catalog";
import { Picture } from "../types/utils";
import Images from "../utils/Images";


interface ProductProp{
    background:Picture;
    pic_product:Picture;
    label:string;
    brand:string;

    pricing:number;
    weight:number;
    rating: Rating;
    has_discount: boolean;
    discounts: Discount[];

    which_category: Category;
    is_available: boolean;
    inventory: Warehouse[];
    date_create: Date;
    date_update: Date;

}

export const ProductItem=
({
    background,
    pic_product,
    label,
    brand,
    pricing,
    weight,
    rating,
    has_discount,
    discounts,
    which_category,
    is_available,
    inventory,
    date_create,
    date_update,

}:ProductProp)=>{

    return <div className="product-card">
        <div className="head-card">
            <Images
            name=""
            alt=""
            about=""
            className="background-card"
            >

            </Images>
        </div>
        <div className="body-card">

        </div>
    </div>
}
