import Footer from "../ui/footer/footer"
import Header from "../ui/header/header"
import { 
  useLocation, 
 
} from 'react-router-dom';


const Catalog = ()=>{

    const location = useLocation()

    return(
        <>
        <Header/>

        <p className="location-descrip">
            
        </p>


        <Footer/>
        </>
    )
}