import { ProductList } from "../components/productList"
import Footer from "../ui/footer/footer"
import Header from "../ui/header/header"

export const PageCatalog = () => {
    
    return(
        <>
        <Header/>

        <ProductList></ProductList>

        <Footer/>
        </>
    )
}