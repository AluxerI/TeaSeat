import { useState } from "react";


interface SearchBarProps{
    onSearch: (querie:string) => void;
    placeholder:string;
    initialValue: string;
}

const SearchBar=({onSearch,placeholder="Search",initialValue}:SearchBarProps) =>{
    const [querie,setQuery] = useState(initialValue);
    
}