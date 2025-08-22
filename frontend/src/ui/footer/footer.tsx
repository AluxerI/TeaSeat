import { JSX } from "react";
import Images from "../../utils/Images"

const Footer = ()=>(
    <footer>
        <div className="blockup">
            <Images
                alt="Logo footer"
                className="Logo footer"

                folder='header'
                name="logo"
                type='svg'
            ></Images>
            
            <div className="about-company">
                <h4 className="company">
                    <a href="#">О компании</a>
                </h4>

                <nav className="rekvizit">
                    <a className="rekvizits" >Реквизиты</a>
                    <a className="blog">Блог</a>
                    <a className="library">Библиотека</a>
                </nav>

            </div>

            <div className="clients">
                <h4 className="clientam">
                    <a href="#">Клиентам</a>
                </h4>

                <nav className="dostavka">
                    <a className="Dostavka" href="#">Доставка и оплата</a>
                    <a className="Prices" href="#">Скидки и акции</a>
                    <a className="Answers" href="#">Вопросы ответы</a>
                </nav>
            </div>

            <div className="feedback">
                <div className="button-tg-feedback">

                    <button type="button" className="button-tg-feedback-part1">
                        <span className="tg-button-text">Подписаться на рассылку</span>
                    </button>
                    <button type="button" className="button-tg-feedback-part2">
                        
                        <Images
                            className="button-tg-feedback-img"
                            alt="button tg"
                            name="button-telegram-group"
                            type="svg"
                            folder="footer/media"
                        ></Images>
                        
                    </button>
                </div>

                <div className="place-part">
                    <Images
                        className="place-img"
                        alt="place-image"
                        name="place"
                        type="svg"
                        folder="footer/base-place"
                    ></Images>
                    <p className="place-text">
                    г Тула, Продуктовый рынок, улица Металлургов, 43Б/2.
                    </p>
                </div>

                <div className="number-part">
                    <Images
                    className="number-img"
                    alt="number-image"
                    name="phone"
                    type="svg"
                    folder="footer/base-place"
                    ></Images>
                    <p className="number-text">
                    +7(000)000-00-00
                    </p>
                </div>

                <div className="email-part">
                <Images
                    className="email-img"
                    alt="email-image"
                    name="email"
                    type="svg"
                    folder="footer/base-place"
                    ></Images>
                <p className="email text">
                    kolyatamindarov@gmail.com
                </p>

                </div>
            </div>
        </div>

        <div className="media">
            
                <div className="left-line"></div>
                <div className="block-media">
                    <a href="http://" className="trans">
                        <Images
                        className="tg-logo-media"
                        name="media-tg"
                        alt='tg-logo-media'
                        type="svg"
                        folder="footer/media"
                        ></Images>
                    </a>
                    <a href="http://" className="trans">
                        <Images
                        className="vk-logo-media"
                        name="vk-logo"
                        alt='vk-logo-media'
                        type="svg"
                        folder="footer/media"
                        ></Images>
                    </a>
                    <a href="http://">
                        <Images
                        className="whatsapp-logo-media"
                        name="whatsapp"
                        alt='whatsapp-logo-media'
                        type="svg"
                        folder="footer/media"
                        ></Images>
                    </a>
                </div>

                <div className="right-line"></div>
        </div>
        
        <div className="license">
            <p className="corp-name">2011-2025 © Чайные Посиделки интернет-магазин </p>
            <p className="magazine">ООО “Чайные Посиделки”</p>
        </div>

    </footer>
);

export default Footer;
