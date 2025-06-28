import { useEffect, useState } from "react";

function useFetch<T>(utl:string){
    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<Error | null>(null);
    const [loading, setLoading] = useState<boolean>(true);

    useEffect

}