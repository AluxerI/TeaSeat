import { Category, Product } from "../interfaces/catalog";

export const sort_by_category = (category: Category[],products: Product[])=>{

    const productByCategory = new Map<string,string[]>()

    for(var value of category){
        productByCategory.set(value.name,[])
    }

    products.forEach(product => {
        // be bebug
        productByCategory.get(product.category_path.category)?.push(product.id.toString())

    });    

}
