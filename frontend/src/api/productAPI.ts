import { useHttp, UseHttpConfig } from "../hooks/useHttp"
import { Product, ProductFilters, Responce, User } from "../types/clients.api"
import { configItem, configUser } from "../utils/configs-service"
import axios from "axios";
import { HttpError } from "../utils/http";

const resultUser= useHttp<User>(
    configUser
)

const API_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';




const api = axios.create(
    {
        baseURL:API_URL,
        timeout: 5000,
        headers:{
            "Content-Type":"application/json",
            'Accept': 'application/json',
        }
    }
)

export const productApi = {
    async getProduct(filter:ProductFilters={}):Promise<Responce<Product>> {

        const params = new URLSearchParams();
        Object.
        
        const responce = await api.get<Product[]>("/items",{params})
        return
    }
}