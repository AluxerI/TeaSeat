import { useAsync } from "../hooks/useAsync";
import { Category } from "../interfaces/catalog";
import { catalogApi } from "./catalogAPI";

export const CategoryAPI={
    categories : catalogApi.getCategory(),

    async getCategoryByName(name:string):Promise<Category|null>{
        const categori = await this.categories
        categori.forEach((value)=>{
            if(value.name === name)
                return value
        })
        return null
    }
}