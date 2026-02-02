import { Grid } from "@mui/material";
import Footer from "../ui/footer/footer"
import Header from "../ui/header/header"
import { useLocation } from 'react-router-dom';
import { CatalogItem, Picture } from "../components/catalogItem";



export const PageCatalog = ()=>{

    const pictureButtonTeaConst: Picture = {name:"pages/details/tea",alt:"image for details", type:'svg'}
    const pictureButtonCoffeConst: Picture = {name:"pages/details/coffe",alt:"image for details", type:'svg'}
    const pictureButtonSwettyConst: Picture = {name:"pages/details/swetty",alt:"image for details", type:'svg'}
    const pictureButtonGiftConst: Picture = {name:"pages/details/gift",alt:"image for details", type:'svg'}

    const picturePartTeaConst: Picture = {name:"pages/catalog/tea_back",alt:"tea picture of background",type:"svg"}
    const picturePartCoffeConst: Picture = {name:"pages/catalog/coffe_back",alt:"tea picture of background",type:"svg"}
    const picturePartSweetyConst: Picture = {name:"pages/catalog/swetty_back",alt:"tea picture of background",type:"svg"}
    const picturePartGiftConst: Picture = {name:"pages/catalog/gift_back",alt:"tea picture of background",type:"svg"}


    const location = useLocation()

    return(
        <>
        <Header/>


        {location.pathname==="/catalog"&&<p className="location-descrip">
            Главная/Каталог
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

        <Footer/>
        </>
    )
}