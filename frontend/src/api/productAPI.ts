
import { FormItem, Item as Item, ItemFilters, Responce, User } from "../interfaces/clients.api"


import { HttpError } from "../utils/http";
import { log } from "console";
import { api } from "./api";



type ProductId = number;

export const ItemApi = {
    async getItemsByfilter(filter:ItemFilters={}):Promise<Responce<Item>> {

        const params = new URLSearchParams();
        Object.entries(filter).forEach(([key,value])=>{
            if (value !== undefined && value !== null){
                params.append(key,String(value));
            }
        })
        
        const responce = await api.get<Item[]>("/item",{params})
        return responce.data as unknown as Responce<Item>;
    },
    async getItem(id:ProductId):Promise<Responce<Item>>{
        const responce = await api.get<Item>(`/item/${id}`)
        return responce.data as unknown as Responce<Item>;
    },
    async getAllItem():Promise<Responce<Item[]>>{
        const responce = await api.get<Item[]>(`/item`)
        return responce.data as unknown as Responce<Item[]>
    },
    async createItem(body:FormItem):Promise<Item>{
        const bodyObj = new FormData();

        Object.entries(body).forEach(([key,value])=>{
            console.log(key)
            console.log(value);
        })

        const response = await api.post<Item>('/items', bodyObj, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
        return response.data;
    }
}