import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../../hooks/useAuth";
import { useCustomerCart } from "../../hooks/useCustomerCart";
import { useWishlist } from "../../hooks/useWishlist";
import { getAdminPanelUrl } from "../../utils/adminUrl";
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
    </a>
  );
}

export default function Header() {
  const navigate = useNavigate();
  const [menuOpen, setMenuOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const { user, isAuthenticated, isAdmin, loading, logout } = useAuth();
  const {
    cart,
    loading: cartLoading,
    initialized: cartInitialized,
    itemCount,
    refreshCart,
    openMiniCart,
  } = useCustomerCart();
  const wishlist = useWishlist(user?.id ?? null);

  // Шапка загружает корзину один раз, чтобы Badge был правильным и после
  // обновления страницы. Ошибка появится внутри Drawer при его открытии.
  useEffect(() => {
    if (isAuthenticated && !cartInitialized && !cartLoading) {
      void refreshCart().catch(() => undefined);
    }
  }, [cartInitialized, cartLoading, isAuthenticated, refreshCart]);

  const handleWishlistClick = () => {
    if (!isAuthenticated) {
      navigate("/login", { state: { from: "/profile?section=favorites" } });
      return;
    }
    navigate("/profile?section=favorites");
  };

  const handleCartClick = () => {
    if (!isAuthenticated) {
      navigate("/login", { state: { from: "/cart" } });
      return;
    }
    openMiniCart();
    if (!cart && !cartLoading) void refreshCart().catch(() => undefined);
  };

  const leftNav = [
    { label: "Главная", href: "/" },
    { label: "Категории", href: "/category" },
  ];

  const rightNav = [
    { label: "О компании", href: "/about" },
    { label: "Каталог", href: "/catalog" },
  ];

  return (
    <header className="header">
      <img className="header__leaf header__leaf--left"  src="/header/leaves.png" alt="" aria-hidden="true" />
      <img className="header__leaf header__leaf--right" src="/header/leaves.png" alt="" aria-hidden="true" />

      <div className="header__inner">

        <div className="header__body">

          <div className="header__main">

            {/* ── Верхний ряд: ☰ Главная Категории · Лого · О компании Каталог ── */}
            <div className="header__row header__row--top">
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

                <nav className="header__nav header__nav--left" aria-label="Основная навигация">
                  {leftNav.map((item) => (
                    <a key={item.label} href={item.href} className="header__nav-link">
                      {item.label}
                    </a>
                  ))}
                </nav>
              </div>

              <Logo />

              <div className="header__right">
                <nav className="header__nav header__nav--right" aria-label="Дополнительная навигация">
                  {rightNav.map((item) => (
                    <a key={item.label} href={item.href} className="header__nav-link">
                      {item.label}
                    </a>
                  ))}
                </nav>
              </div>
            </div>

            {/* ── Нижний ряд: поиск ── */}
            <div className="header__row header__row--bottom">
              <div className="header__search-block">
                <img src="/header/search.png" alt="" aria-hidden="true" className="header__search-frame" />
                <input
                  type="search"
                  placeholder="Поиск товаров..."
                  className="header__search-input"
                  aria-label="Поиск товаров"
                />
              </div>
            </div>

          </div>

          <div className="header__actions">
            <button
              type="button"
              className="header__icon-btn header__cart-button"
              aria-label={wishlist.count ? `Избранное, товаров: ${wishlist.count}` : "Избранное"}
              onClick={handleWishlistClick}
            >
              <HeartIcon size={20} />
              {wishlist.count > 0 && <span className="header__cart-badge">{wishlist.count > 99 ? "99+" : wishlist.count}</span>}
            </button>
            {/* id остаётся целью анимации конструктора, но клик теперь открывает
                общий Drawer без ухода с текущей страницы. */}
            <button
              type="button"
              className="header__icon-btn header__cart-button"
              aria-label={itemCount ? `Корзина, позиций: ${itemCount}` : "Корзина"}
              id="constructor-cart-target"
              onClick={handleCartClick}
            >
              <CartIcon size={20} />
              {itemCount > 0 && <span className="header__cart-badge">{itemCount > 99 ? "99+" : itemCount}</span>}
            </button>

            {loading ? (
              <div className="header__user" />
            ) : isAuthenticated ? (
              <div className="header__user-wrap">
                <button
                  className="header__user"
                  onClick={() => setUserMenuOpen(v => !v)}
                  aria-label="Профиль пользователя"
                  aria-expanded={userMenuOpen}
                >
                  <div className="header__avatar" aria-hidden="true">
                    {user?.name?.charAt(0).toUpperCase() || "U"}
                  </div>
                  <ChevronDownIcon size={16} />
                </button>

                {userMenuOpen && (
                  <>
                    <div className="header__user-overlay" onClick={() => setUserMenuOpen(false)} />
                    <div className="header__user-dropdown">
                      <div className="header__user-dropdown-header">
                        {user?.name}
                        <span className="header__user-dropdown-email">{user?.email}</span>
                      </div>
                      {isAdmin && (
                        <a href={getAdminPanelUrl()} className="header__user-dropdown-item">Админка</a>
                      )}
                      <a href="/profile" className="header__user-dropdown-item">Профиль</a>
                      <a href="/profile?section=orders" className="header__user-dropdown-item">Заказы</a>
                      <hr className="header__user-dropdown-divider" />
                      <button
                        className="header__user-dropdown-item header__user-dropdown-item--danger"
                        onClick={logout}
                      >
                        Выйти
                      </button>
                    </div>
                  </>
                )}
              </div>
            ) : (
              <div className="header__auth">
                <a href="/login" className="header__auth-link">Войти</a>
                <a href="/register" className="header__auth-btn">Регистрация</a>
              </div>
            )}
          </div>

        </div>

        {/* ── Мобильное меню ── */}
        {menuOpen && (
          <nav className="header__mobile-nav" aria-label="Мобильная навигация">
            {[...leftNav, ...rightNav].map((item) => (
              <a key={item.label} href={item.href} className="header__mobile-link">
                {item.label}
              </a>
            ))}
            <div className="header__mobile-search">
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
