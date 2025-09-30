import { useCallback, useEffect, useRef, useState } from "react";
import { http, HttpError, HttpStatus } from "../api/http";

function useFetch<T>(url:string){
<<<<<<< Updated upstream
    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<HttpError | null>(null);
=======
    const [response, setData] = useState<T | null>(null);
    const [error, setError] = useState<Error | null>(null);
>>>>>>> Stashed changes
    const [loading, setLoading] = useState<boolean>(true);
    const abortControllerRef = useRef<AbortController>(null);

    const execute = useCallback(async(url:string,options:Parameters<typeof  ):{
        if(abortControllerRef.current){
            abortControllerRef.current.abort();
        }
        abortControllerRef.current = new AbortController();
        setLoading(true);
        setError(null);

<<<<<<< Updated upstream
        try{
            const response = await http.get<T>(url,{})
        }

    })

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
=======
}

export default useFetch;
>>>>>>> Stashed changes
