import { error } from "console"
import { useHttp, UseHttpConfig } from "../hooks/useHttp"
import { Item, User } from "../types/clients.api"
import { configItem, configUser } from "../utils/configs-service"
import { HttpError } from "../utils/http";

const resultUser= useHttp<User>(
    configUser
)


export const useItems=(baseURL:string,)=>{
    
    const {execute,...rest}=useHttp<Item>(
        configItem
    )

    return{
        ...rest,
        getItem:(productId:number)=> execute(`/item/${productId}`,'GET'),
        getCatalog:(filters:any)=>execute(`/catalog`,'GET',undefined,{params:filters}),
        createItem: (data:any) => execute('/items','POST',data),
        updateItem: (productId:number,data:any) => execute(`/items/${productId}`,'PUT',data),
        deleteItem: (productId:number) => execute(`/items/${productId}`,'DELETE')
    }
}