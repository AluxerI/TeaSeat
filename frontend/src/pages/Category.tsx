import { Grid } from "@mui/material";
import Footer from "../ui/footer/footer"
import Header from "../ui/header/header"
import { useLocation } from 'react-router-dom';
import { CatalogItem, Picture } from "../components/catalogItem";
import Images from "../utils/Images";
import { catalogApi } from "../api/catalogAPI";
import { useAsync } from "../hooks/useAsync";



export const PageCategory = ()=>{

    const pictureButtonTeaConst: Picture = {name:"pages/catalog/details/tea",alt:"image for details", type:'svg'}
    const pictureButtonCoffeConst: Picture = {name:"pages/catalog/details/coffe",alt:"image for details", type:'svg'}
    const pictureButtonSwettyConst: Picture = {name:"pages/catalog/details/swetty",alt:"image for details", type:'svg'}
    const pictureButtonGiftConst: Picture = {name:"pages/catalog/details/gift",alt:"image for details", type:'svg'}

    const picturePartTeaConst: Picture = {name:"pages/catalog/tea_back",alt:"tea picture of background",type:"svg"}
    const picturePartCoffeConst: Picture = {name:"pages/catalog/coffe_back",alt:"tea picture of background",type:"svg"}
    const picturePartSweetyConst: Picture = {name:"pages/catalog/swetty_back",alt:"tea picture of background",type:"svg"}
    const picturePartGiftConst: Picture = {name:"pages/catalog/gift_back",alt:"tea picture of background",type:"svg"}

    
    

    const location = useLocation()
    const catalog_Api = catalogApi;
    const hookCategory = useAsync(()=>catalog_Api.getCategory(),true); 
    const listCateggory = []
    if(hookCategory.data)
    for(const item of hookCategory.data){
        listCateggory.push(`<CatalogItem description=${item.id} label=${item.name}`)
    }

    return(
        <>
        <Header/>

        <div className="body-page">
            {location.pathname==="/category"&&<p className="location-descrip">
                Главная/Категории
            </p>
            }

            <div className="lines_and_names_shop">
                <div className="line">

                </div>
                <p className="names">Чайные посиделки</p>

                <div className="line">
                    
                </div>
            </div>

            <Grid 
            container
            direction="row"
            justifyContent="center"
            alignItems="center"
            className = "grid-category"
            >
                
                
                <CatalogItem description='Премиальные чаи из Китая и Японии' label='Чайный набор' picture_button={pictureButtonTeaConst} 
                picture_part={picturePartTeaConst} price={20} filter_color="rgba(76, 175, 80,0.8)"/>

                <CatalogItem description='Эксклюзивный кофе из Вьетнама и Индонезии' label='Кофейные наборы' picture_button={pictureButtonCoffeConst} 
                picture_part={picturePartCoffeConst} price={20}filter_color="rgba(139,69,19,0.9)"/>

                <CatalogItem description='Традиционные лакомства и десерты' label='Сладости' picture_button={pictureButtonSwettyConst} 
                picture_part={picturePartSweetyConst} price={20} filter_color="rgba(219,39,119,0.8)"/>

                <CatalogItem description='Создайте уникальный подарочный набор' label='Конструктор подарков' picture_button={pictureButtonGiftConst} 
                picture_part={picturePartGiftConst} price={20} filter_color="rgba(107,33,168,0.9)"/>
            </Grid>
            
            <div className="achivments">
                <div className="first-achiv">
                    <Images name="pages/catalog/achivment/delivery" alt="fast delivery" type="svg"/>
                    <h6 className="delivery-title"> Быстрая доставка</h6>
                    <p className="delivery-descripe">Доставим за 1-3 дня</p>
                </div>
                <div className="sec-achiv">
                    <Images name="pages/catalog/achivment/quality" alt="quality guarantee" type="svg"/>
                    <h6 className="guarantee-title">Гарантия качества</h6>
                    <p className="guarantee-descripe">Только оригинальные продукты от проверенных поставщиков</p>
                </div>
                
                <div className="third-achiv">
                    <Images name="pages/catalog/achivment/gift" alt="beautiful packaging" type="svg"/>
                    <h6 className="packaging-title">Красивая упаковка</h6>
                    <p className="packaging-descripe">Каждый заказ упаковывается в подарочную коробку</p>
                </div>
            </div>


        </div>
        <Footer/>
        </>
    )
}