//scss
import Images from '../../utils/Images';
import './../../scss/main.scss';

//files


const Header = ()=>(
    <header>
        <Images 
        alt='Logo' 
        className='Logo'
        folder='header'
        name="logo"
        type='svg'
        ></Images>
        

        <div className="button_tools">
            <nav className="main_buttons">
                <ul>
                    <li><a href="http://">Главная</a></li>
                    <li><a href="http://">Контакты</a></li>
                    <li><a href="http://">О компании</a></li>
                    <li><a href="http://">Каталог</a></li>
                </ul>
                <aside className="media">
                    <a href="http://">
                        <Images
                            alt='vk'
                            className='media-vk'
                            folder='header/media'
                            name='icons8-vk 1'
                            type='svg'
                            > 
                        </Images>
                    </a>
                    
                    <a href="">
                        <Images
                            alt='tg'
                            className='media-tg'
                            folder='header/media'
                            name='icons8-youtube 1'
                            type='svg'
                            >
                        
                        </Images>
                    </a>
                    <p className="number" >+7(000)000-00-00</p>
                    
                </aside>

            </nav>

            <div className="blocks">
                <div className="container">
                    <details>
                        <summary>ЧАЙ</summary>
                        <a href=""></a>
                    </details>
                </div>
                <div className="container">
                    <details>
                        <summary>КОФЕ</summary>
                        <a href=""></a>
                    </details>
                </div>
                <div className="container">
                    <details>
                        <summary>СЛАДОСТИ</summary>
                        <a href=""></a>
                    </details>
                </div>
                <div className="container">
                    <details>
                        <summary>ПОДАРОЧНЫЕ НАБОРЫ</summary>
                        <a href=""></a>
                    </details>
                </div>
                    

                
            </div>
        </div>

    </header>
);

export default Header