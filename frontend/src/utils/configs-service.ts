import { UseHttpConfig } from "../hooks/useHttp"
import { Item, User } from "../interfaces/clients.api"

export const configUser: UseHttpConfig<User> = {
    url: '/user',
    method: 'GET',
    autoExecute:true,
    onSuccess: (user)=> {console.log("Пользователь загружен: ", user.name)},
    onError:(error)=>{console.log(`Ошибка загрузки пользователя ${error.name} статус: ${error.status},(${error.statusText}). ${error.message}`)}
}

export const configItem:UseHttpConfig<Item>={
    url:'/item',
    method:'GET',
    autoExecute:true,
    onSuccess:(item)=>{console.log(`${item.name}:${item.id} успешно загружен`)},
    onError:(error)=>{console.log(`Ошибка загрузки ${error.name} статус: ${error.status},(${error.statusText}). ${error.message}`)}
}
