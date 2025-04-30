<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Чайные Посиделки</title>
  </head>
  <body>
    <!-- Header Section -->
    <header>
      <!-- Верхняя (зелёная) часть хедера -->
      <div class="header-top">
        <button type="button">Чай</button>
        <button type="button">Кофе</button>
        <button type="button">Сладости</button>
      </div>

      <!-- Нижняя часть хедера -->
      <div class="header-bottom">
        <!-- Полоска навигации -->
        <nav class="header-nav">
          <ul>
            <li><a href="#">Контакты</a></li>
            <li><a href="#">О компании</a></li>
            <li><a href="#">Доставка</a></li>
            <li><a href="#">Каталог</a></li>
          </ul>

          <!-- Иконки соц. сетей -->
          <div class="header-social-icons">
            <a href="#" aria-label="VK"><img src="#" alt="VK" /></a>
            <a href="#" aria-label="Youtube"><img src="#" alt="Youtube" /></a>
            <a href="#" aria-label="Other"><img src="#" alt="Other" /></a>
          </div>

          <!-- Контактный телефон -->
          <div class="contact-number">
            <a href="tel:+123456789">+123456789</a>
          </div>

          <!-- Кнопка логина / регистрации -->
          <button type="button">Вход/Регистрация</button>
        </nav>

        <!-- Логотип и строка поиска -->
        <div class="header-main">
          <div class="header-logo">
            <img src="logo.png" alt="Логотип компании" />
          </div>
          <div class="header-search-bar">
            <input type="text" placeholder="Поиск..." aria-label="Search" />
            <button type="submit">🔍</button>
          </div>
        </div>
      </div>
    </header>
    <br>
    {{-- <div>
      @foreach ($products as $item)
      {{ $item -> idname }}
      @endforeach
    </div> --}}
      

    <!-- Основной контент -->
    <main>
      <!-- Секция: популярное -->
      <section id="popular">
        <h2>Популярное</h2>
        <div class="slider">
          <!-- Слайдер популярного -->
          <div class="slider-container">
            <div class="slider-item">
              <img src="product1.jpg" alt="Товар 1" />
              <p>Название товара 1</p>
            </div>
            <div class="slider-item">
              <img src="product2.jpg" alt="Товар 2" />
              <p>Название товара 2</p>
            </div>
            <div class="slider-item">
              <img src="product3.jpg" alt="Товар 3" />
              <p>Название товара 3</p>
            </div>
            <div class="slider-item">
              <img src="product4.jpg" alt="Товар 4" />
              <p>Название товара 4</p>
            </div>
            <div class="slider-item">
              <img src="product5.jpg" alt="Товар 5" />
              <p>Название товара 5</p>
            </div>
          </div>
          <!-- Slider Navigation -->
          <div class="slider-controls">
            <button class="slider-prev">⬅️</button>
            <button class="slider-next">➡️</button>
          </div>
        </div>
      </section>

      <!-- ........... -->
      <!-- АКЦИИ -->
      <!-- ........... -->

      <!-- Section: Map -->
      <section id="map">
        <h2>Адрес нашего магазина</h2>
        <!-- Embedded Map -->
        <div class="map-container">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3153.8354345098956!2d-122.41941568468153!3d37.7749297797591!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8085809c77c4b315%3A0x6d4cdabeaa264bdb!2sSan%20Francisco%2C%20CA!5e0!3m2!1sen!2sus!4v1618033988749895!5m2!1sen!2sus"
            width="600"
            height="450"
            style="border: 0"
            allowfullscreen=""
            loading="lazy"
          >
          </iframe>
        </div>
        <!-- Информация: адрес, график, телефон -->
        <div class="address-info">
          <div class="address-item">
            <img src="location-icon.png" alt="Иконка локации" />
            <p>Магазин "Чайные Посиделки"</p>
          </div>
          <div class="address-item">
            <img src="clock-icon.png" alt="Иконка часов" />
            <p>График работы: 10:00 - 22:00</p>
          </div>
          <div class="address-item">
            <img src="phone-icon.png" alt="Иконка телефона" />
            <p>Телефон: <a href="tel:+123456789">+123456789</a></p>
          </div>
        </div>
      </section>
    </main>

    <footer>
      <div>Магазин "Чайные Посиделки"</div>
      <div>г. Тула, Подростковый рынок, ул. Металлургов, 435/г.</div>
      <div>+7 (000) 000-00-00</div>
    </footer>
  </body>
</html>
