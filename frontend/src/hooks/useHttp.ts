import { useCallback, useEffect, useRef, useState } from "react";
import { http, HttpError, HttpMethod, HttpRequestOptions, HttpResponse } from "../utils/http";

interface UseHttpState<T>{
    data:T|null;
    loading:boolean;
    error:HttpError|null;
}

interface UseHttpReturn<T> extends UseHttpState<T>{
    execute:(
        executeUrl?: string | URL,
        executeMethod?: HttpMethod, 
        executeBody?: any,
        executeOptions?: HttpRequestOptions
    )=> Promise<void>;
    abort:()=> void;
}
export interface UseHttpConfig<T>{
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
        url,
        method='GET',
        body,
        options,
        autoExecute,
        onSuccess,
        onError
        
    } = config

    const [state,setState] = useState<UseHttpState<T>>({
        data:null,
        loading:false,
        error:null
    })

    const abortControllerRef = useRef<AbortController | null>(null);

    const execute = useCallback(async (
        executeUrl?:string|URL,
        executeMethod?: HttpMethod,
        executeBody?:any,
        executeOptions?:HttpRequestOptions
    )=>{
        const requestUrl = executeUrl || url;
        const requestMethod = executeMethod || method;
        const requestBody = executeBody || body;
        const requestOptions = executeOptions || options;

        if(!requestUrl){
            throw new Error('URL is required for HTTP request');
        }
        // this is to cancel the request
        if(abortControllerRef.current){
            abortControllerRef.current.abort();
        }

        //create new AbortController for this request
        const abortController = new AbortController();
        abortControllerRef.current = abortController;

        setState(prev=>({...prev,loading:true,error:null}));

        try{

        let response: HttpResponse<T>;
        switch(requestMethod){
            case "GET":
                response = await http.get<T>(requestUrl,{
                    ...requestOptions,
                    signal: abortController.signal,
                });
                break;

            case "POST":
                response = await http.post<T>(requestUrl,requestBody,{
                    ...requestOptions,
                    signal: abortController.signal,
                })
                break;
            case "PUT":
                response = await http.put<T>(requestUrl,requestBody,{
                    ...requestOptions,
                    signal: abortController.signal,
                })
                break;

            case "DELETE":
                response = await http.delete<T>(requestUrl,{
                    ...requestOptions,
                    signal: abortController.signal,
                })
                break;

            case "PATCH":
                response = await http.patch<T>(requestUrl,{
                    ...requestOptions,
                    signal:abortController.signal,
                })
                break;
        }
        // План защиты
        // ТРОЙНАЯ ЗАЩИТА:
        // 1. Не отменен ли запрос?
        // 2. Не размонтирован ли компонент?
        // 3. Не произошло ли еще что-то?
        

        // Проверка на отмену запросу и компонент не размонтирован должен быть
        if(!abortController.signal.aborted){
            setState({
                data:response.data,
                error:null,
                loading:false
            })
            onSuccess?.(response.data);
        }
    } catch(error){
        // Когда вызываем abortController.abort(), fetch бросает ошибку, это так-то сигнал отмены и надо выйти из него
        if(error instanceof Error && error.name === 'AbortError'){
            return;
        }

        // Но если запрос отменен, то проверяем
        if(!abortController.signal.aborted){
            const httpError = error as HttpError;
            setState(prev=>({
                ...prev,
                loading:false,
                error: httpError
            }));
            onError?.(httpError);
        }
    }
        finally{
            // Очищаю ссылку если у нас тот же контроллер
            if(abortControllerRef.current === abortController){
                abortControllerRef.current=null;
            }
        }

    },[url,method,body,options,onSuccess,onError]);

    // для прерываний
    const abort = useCallback(()=>{
        if(abortControllerRef.current){
            abortControllerRef.current.abort();
            abortControllerRef.current=null;
            setState(prev=>({...prev,loading:false}))
        }
    },[])

    // Автоматическое выполнение при инициализации
    useEffect(()=>{
        if(autoExecute && url){
            execute();
        }
    },[autoExecute,url])// убрал execute из зависимостей

    //очищаю при размонтировании
    useEffect(()=>{
        return ()=>{
            if(abortControllerRef.current){
                abortControllerRef.current.abort();
                abortControllerRef.current=null;
            }
        }
    },[]);

    return(
        {
        ...state,
        execute,
        abort
        }
    )
}

export{}