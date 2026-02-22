
import { Catalog, Category, Meta, Product } from "../interfaces/catalog";
import { api } from "./api"

type Json = {[key:string]: unknown}&Record<string,unknown>;
const catalog_path = `/catalog`

async function getCatalog():Promise<Catalog|undefined>{
    try{
        const response = await api.get<Catalog>(catalog_path);
        return response.data as Catalog}
    catch(e:unknown){
        if(e instanceof Error){
            console.error(`Error when loading catalog: ${e}`)
        }
        else{
            console.error(`Unknow error`);
        }
        return undefined
    }
    }

export const catalogApi = {
    
    
    async getProducts():Promise<Product[]>{

        let catalog:Catalog|string = (await getCatalog())!;
        
        catalog=JSON.stringify(catalog);
        const answer:Json = JSON.parse(catalog)

        const ans:Catalog = answer['data'] as Catalog
        
        return ans['products'] as Product[]
    },
    async getCategory():Promise<Category[]>{
        let catalog:Catalog|string = (await getCatalog())!;
        catalog=JSON.stringify(catalog);
        const answer:Json = JSON.parse(catalog);

        const ans:Catalog = answer['data'] as Catalog;

        return ans['categories'] as Category[] 
    },
    async getMeta():Promise<Meta>{
        let catalog:Catalog|string = (await getCatalog())!;
        catalog = JSON.stringify(catalog);
        const answer:Json = JSON.parse(catalog);

        const ans:Catalog = answer['data'] as Catalog;
        return ans['meta'] as Meta

    }
}