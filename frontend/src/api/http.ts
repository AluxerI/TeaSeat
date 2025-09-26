export enum HttpStatus{
    OK=200,
    CREATED=201,
    NO_CONTENT=204,
    
    MULTIPLE_CHOICES=300,
    MOVED_PERMANENTLY=301,
    FOUND=302,
    NOT_MODIFIED=304,

    BAD_REQUEST=400,
    UNAUTHORIZED=401,
    FORBIDDEN=403,
    NOT_FOUND=404,
    CONFLICT=409,

    INTERNAL_SERVER_ERROR=500,
    NOT_IMPLEMENTED=501,
    BAD_GATEWAY=502,
    SERVICE_UNAVAILABLE=503,
    GATEWAY_TIMEOUT=504,

    
}
export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';

export interface HttpRequestOptions {
    method?: HttpMethod;
    headers?: Record<string, string>;
    body?: any;
}

export interface HttpResponse<T = any> {
    status: number;
    statusText: string;
    headers: Headers;
    data: T;
}

const request = async <T>(url:string|URL,options?:HttpRequestOptions):Promise<HttpResponse<T>> =>{
    const response = await fetch(url.toString(),options);
    const data = await response.json() as T;


    return {
        status:response.status,
        statusText:response.statusText,
        headers:response.headers,
        data
    }
}
const isJson = (body:any):boolean=>{
    try {
        JSON.parse(body);
        return true;
    } catch (error) {
        return false;
    }
}

const whatContentLength = (body:any):number|undefined=>{
    if(typeof body === "string"){
        return body.length;
    }
    if(typeof body === "object"){
        if(body instanceof ArrayBuffer){
            return body.byteLength;
        }
        if(body instanceof Blob){
            return body.size;
        }
        if(body instanceof FormData){
            return undefined;
        }
        if(isJson(body)){
            return JSON.stringify(body).length;
        }
    }
}

const whatContentType = (body:any):string|undefined=>{
    switch(typeof body){
        case "string": return "text/plain";
        case "object": {
            if(Array.isArray(body)){
                return "application/json";
            }
            if(body instanceof ArrayBuffer){
                return "application/octet-stream";
            }
            
            if(body instanceof Blob){
                return body.type;
            }

            if(body instanceof FormData){
                return undefined;
            }
            
            if(isJson(body) ){
            return "application/json";}
            break;
        }
        

        default: return undefined;
    }
}

export const http = {
    get:<T>(url:string|URL,options?:HttpRequestOptions):Promise<HttpResponse<T>>=> request<T>(url,{...options,
        headers:{}
        ,method:'GET',
    
    }),
    post:<T>(url:string|URL,body?:any,options?:HttpRequestOptions):Promise<HttpResponse<T>>=> request<T>(url,{...options,
        headers:{
            'Content-Type':whatContentType(body) ?? 'application/json',
            'Content-Length':whatContentLength(body)?.toString()?? "", 
            ...options?.headers
        },
        method:'POST'

    }),
    put:<T>(url:string|URL,body?:any,options?:HttpRequestOptions):Promise<HttpResponse<T>>=> request<T>(url,{...options,
        headers:{
            'Content-Type':whatContentType(body) ?? 'application/json',
            'Content-Length':whatContentLength(body)?.toString()?? "",},
        method:'PUT',
            ...options?.headers

    }),
    
    }