import { api } from "./api"

const catalogApi = {
    async getCatalog():Promise<Catalog[]>{
        const response = await api.get<Catalog[]>(`catalog`);
        return response.data 
    }
}