import { Grid } from "@mui/material";
import { catalogApi } from "../api/catalogAPI";
import { useAsync } from "../hooks/useAsync";
import { ProductItem } from "./productItem";
import { Product } from "../interfaces/catalog";
import { Picture } from "../types/utils";
import { typePic } from "./catalog";
import { Discount } from "../types/catalog";
import { CategoryAPI } from "../api/categoryAPI";



export const ProductList = ()=>{
    const products = useAsync(()=> catalogApi.getProducts(),true)
    const catAPI = CategoryAPI
    
    if(products.data !== null)
        if(products.data.length)
        for(const arr of products.data!){
            console.log(arr)
            
        }

    return (
    <Grid
    container
    alignItems={"center"}
    display={"flex"}

    >
        <div className="item">
            {
            
            products.data?.map((value:Product)=>{

                const part_background:Picture = {name:value.image.background.split('.')[0],type:value.image.background.split('.')[1] as typePic, alt:value.image.
                    background.split('.')[0]}
                const part_product:Picture = {name:value.image.product.split('.')[0],type:value.image.product.split('.')[1] as typePic, alt:value.image.
                    product.split('.')[0]}
                const category = await catAPI.getCategoryByName(value.category_path.category) 

                return(
                <ProductItem background={part_background} brand={value.brand}
                label={value.name} weight={value.weight_grams} has_discount={value.pricing.has_discount}
                discounts={Array(Discount.form(value.pricing.personal_discount))}
                pic_product={part_product}
                inventory={value.inventory}
                is_available={value.is_available && value.total_quantity>0}
                pricing={value.sold_count}
                key={value.id}
                which_category={category!} // not safefull
                //rating={}
                date_create={value.created_at}
                date_update={value.update_at}
                ></ProductItem>
            )})}
        </div>
        
    </Grid>
    )
}