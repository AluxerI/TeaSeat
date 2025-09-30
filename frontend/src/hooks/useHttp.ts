import { useCallback, useRef, useState } from "react";
import { HttpError, HttpMethod, HttpRequestOptions } from "../utils/http";

interface UseHttpState<T>{
    data:T|null;
    loading:boolean;
    error:HttpError|null;
}

interface UseHttpReturn<T> extends UseHttpState<T>{
    execute:()=> Promise<void>;
    abort:()=> void;
}
interface UseHttpConfig<T>{
    url?:string|URL;
    method?: HttpMethod;
    body?: any;
    options?: HttpRequestOptions;
    autoExecute?: boolean;
    onSuccess?: (data:T)=>void;
    onError?: (error:HttpError)=>void;
}

export function useHttp<T>(config: UseHttpConfig<T>):UseHttpReturn<T>{
    const{
        
    } = config

    const [state,setState] = useState<UseHttpState<T>>({
        data:null,
        loading:false,
        error:null
    })

    const abortControllerRef = useRef<AbortController | null>(null);

    const execute = useCallback(async (executeUrl?:string|URL,executeMethod?: HttpMethod,executeBody?:any))
}