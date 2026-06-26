import React from 'react';
import styles from './Footer.module.scss';
import Logo from './Logo';
import SocialMolecule from './SocialMolecule';

const Footer: React.FC = () => {
  return (
    <footer className={styles.footer}>
      <div className={styles.footerInner}>
        <div className={styles.colBrand}>
          <div className={styles.brandRow}>
            <Logo />
            <div className={styles.brandText}>
              <p className={styles.brandName}>Чайные посиделки</p>
              <p className={styles.brandTagline}>
                Ваш путеводитель в мире чая и кофе
              </p>
            </div>
          </div>
        </div>

        <nav className={styles.col} aria-label="Продукты">
          <h4 className={styles.colTitle}>Продукты</h4>
          <ul className={styles.colList}>
            {['Кофе в зёрнах','Молотый кофе','Листовой чай','Чайные пакетики'].map(item => (
              <li key={item}><a href="/" className={styles.colLink} onClick={e => e.preventDefault()}>{item}</a></li>
            ))}
          </ul>
        </nav>

        <nav className={styles.col} aria-label="Компания">
          <h4 className={styles.colTitle}>Компания</h4>
          <ul className={styles.colList}>
            {['О нас','Наша история','Карьера','Партнёры'].map(item => (
              <li key={item}><a href="/" className={styles.colLink} onClick={e => e.preventDefault()}>{item}</a></li>
            ))}
          </ul>
        </nav>

        <div className={styles.col}>
          <h4 className={styles.colTitle}>Контакты</h4>
          <address className={styles.contactsAddress}>
            <a href="tel:+74951234567" className={styles.contactLink}>+7 (495) 123-45-67</a>
            <a href="mailto:info@teacoffee.ru" className={styles.contactLink}>info@teacoffee.ru</a>
          </address>
        </div>

        <div className={styles.socialWrap}>
          <SocialMolecule />
        </div>
      </div>

      <div className={styles.footerBottom}>
        <p>© 2024 Чайные посиделки. Все права защищены.</p>
      </div>
    </footer>
  );
};

export default Footer;
