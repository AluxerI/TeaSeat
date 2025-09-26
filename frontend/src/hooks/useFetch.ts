import { useEffect, useState } from "react";
import { http, HttpStatus } from "../api/http";

function useFetch<T>(url:string){
    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<Error | null>(null);
    const [loading, setLoading] = useState<boolean>(true);

    useEffect(() => {
        http.get<T>(url)
            .then(response => {
                if(response.status >= HttpStatus.OK && response.status < HttpStatus.MULTIPLE_CHOICES)
                    setData(response.data);
                else
                    setError(new Error(`Error: ${response.status} ${response.statusText}`));
            }     
        )
            .catch(err => setError(err))
            .finally(()=>setLoading(false));
    },[url]);

    return {data,error,loading};
}