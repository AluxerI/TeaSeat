// Для аутефикации
export interface LoginData{
    email:string,
    password:string
}
export interface RegisterData{
    name:string,
    email:string,
    password:string,
    password_conf:string
}

export interface User{
    id:number,
    name:string,
    email:string,
    email_verif_at:string,
    phone_verif:number,
    created_at:Date,
    updated_at:Date
}

export interface Item{
    id:number,
    name:string,
    description:string,
    price:number,
    user_id:number,
    created_at:Date,
    updated_at:Date
}

