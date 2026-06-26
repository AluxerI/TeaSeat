import { useState } from "react";
import "./../../scss/main.scss";

function MenuIcon({ size = 22 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="3" y1="6" x2="21" y2="6" />
      <line x1="3" y1="12" x2="21" y2="12" />
      <line x1="3" y1="18" x2="21" y2="18" />
    </svg>
  );
}

function XIcon({ size = 22 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="18" y1="6" x2="6" y2="18" />
      <line x1="6" y1="6" x2="18" y2="18" />
    </svg>
  );
}

function SearchIcon({ size = 18 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="11" cy="11" r="8" />
      <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </svg>
  );
}

function HeartIcon({ size = 20 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
    </svg>
  );
}

function CartIcon({ size = 20 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="9" cy="21" r="1" />
      <circle cx="20" cy="21" r="1" />
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
    </svg>
  );
}

function ChevronDownIcon({ size = 16 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="6 9 12 15 18 9" />
    </svg>
  );
}

function Logo() {
  return (
    <a href="/" className="header__logo" aria-label="Чайные посиделки — на главную">
      <svg width="64" height="64" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="28" cy="28" r="28" fill="#5a2d0c" />
        <ellipse cx="28" cy="38" rx="13" ry="3.5" fill="#c8903c" opacity="0.35" />
        <path d="M17 26 Q17 36 28 36 Q39 36 39 26 Z" fill="#c8903c" opacity="0.9" />
        <rect x="16" y="24" width="24" height="3" rx="1.5" fill="#e0a84a" />
        <path d="M39 27 Q45 27 45 31 Q45 35 39 35" stroke="#e0a84a" strokeWidth="2" fill="none" strokeLinecap="round" />
        <path d="M22 22 Q21 18 23 15" stroke="#8ecfcf" strokeWidth="1.4" fill="none" strokeLinecap="round" opacity="0.8" />
        <path d="M28 21 Q27 17 29 13" stroke="#8ecfcf" strokeWidth="1.4" fill="none" strokeLinecap="round" opacity="0.8" />
        <path d="M34 22 Q33 18 35 15" stroke="#8ecfcf" strokeWidth="1.4" fill="none" strokeLinecap="round" opacity="0.8" />
        <ellipse cx="28" cy="31" rx="4" ry="2" fill="#5a2d0c" opacity="0.4" transform="rotate(-15 28 31)" />
      </svg>
      <span className="header__logo-text">Чайные посиделки</span>
    </a>
  );
}

export default function Header() {
  const [menuOpen, setMenuOpen] = useState(false);

  const navItems = [
    { label: "Главная",    href: "#" },
    { label: "Контакты",   href: "#" },
    { label: "О компании", href: "#" },
    { label: "Каталог",    href: "#" },
  ];

  return (
    <header className="header">
      <img className="header__leaf header__leaf--left"  src="/header/leaves.png" alt="" aria-hidden="true" />
      <img className="header__leaf header__leaf--right" src="/header/leaves.png" alt="" aria-hidden="true" />

      <div className="header__inner">
        <div className="header__row">

          <div className="header__left">
            <button
              onClick={() => setMenuOpen((o) => !o)}
              className="header__burger header__burger--mobile"
              aria-label={menuOpen ? "Закрыть меню" : "Открыть меню"}
              aria-expanded={menuOpen}
            >
              {menuOpen ? <XIcon size={22} /> : <MenuIcon size={22} />}
            </button>

            <button className="header__burger header__burger--desktop" aria-label="Меню">
              <MenuIcon size={22} />
            </button>

            <nav className="header__nav" aria-label="Основная навигация">
              {navItems.map((item) => (
                <a key={item.label} href={item.href} className="header__nav-link">
                  {item.label}
                </a>
              ))}
            </nav>
          </div>

          <Logo />

          <div className="header__actions">
            <div className="header__search">
              <span className="header__search-icon" aria-hidden="true">
                <SearchIcon size={18} />
              </span>
              <input
                type="search"
                placeholder="Поиск товаров..."
                className="header__search-input"
                aria-label="Поиск товаров"
              />
            </div>

            <button className="header__icon-btn" aria-label="Избранное">
              <HeartIcon size={20} />
            </button>

            <button className="header__icon-btn" aria-label="Корзина, 3 товара">
              <CartIcon size={20} />
              <span className="header__cart-badge" aria-hidden="true">3</span>
            </button>

            <button className="header__user" aria-label="Профиль пользователя">
              <div className="header__avatar" aria-hidden="true">U</div>
              <ChevronDownIcon size={16} />
            </button>
          </div>
        </div>

        {menuOpen && (
          <nav className="header__mobile-nav" aria-label="Мобильная навигация">
            {navItems.map((item) => (
              <a key={item.label} href={item.href} className="header__mobile-link">
                {item.label}
              </a>
            ))}
            <div className="header__mobile-search">
              <span aria-hidden="true"><SearchIcon size={18} /></span>
              <input
                type="search"
                placeholder="Поиск товаров..."
                className="header__search-input"
                aria-label="Поиск товаров"
              />
            </div>
          </nav>
        )}
      </div>
    </header>
  );
}
