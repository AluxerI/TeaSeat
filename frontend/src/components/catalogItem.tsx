import React, { useState } from "react";
import Images from "../utils/Images";
import zIndex from "@mui/material/styles/zIndex";
import { Picture } from "../types/utils";



export interface CatalogItemProps {
  picture_part: Picture;
  picture_button: Picture;
  label: string;
  description: string;
  price: number;
  filter_color?:string;
}

// Стили в виде констант CSSProperties
const styles = {
  cardItem: {
    backgroundColor: 'rgba(255, 255, 255, 1)',
    borderRadius: '16px',
    boxShadow: '0 4px 20px rgba(0, 0, 0, 0.08)',
    overflow: 'hidden',
    transition: 'all 0.3s ease',
    display: 'flex',
    flexDirection: 'column' as const,
    height: '100%',
    width: '100%',
    maxWidth: '320px',
    margin: '0 auto',
    position: 'relative' as const,
  },
  
  imagesContainer: {
    position: 'relative' as const,
    width: '100%',
    height: '200px',
    overflow: 'hidden',
    margin: 0,
    padding: 0,
    flexShrink: 0,
  },
  
  imageOverlay: {
    position: 'absolute' as const,
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(76, 175, 80, 0)', // Зеленый цвет с 0 opacity по умолчанию
    transition: 'background-color 0.3s ease',
    zIndex: 2,
    pointerEvents: 'none' as const,
  },
  
  imageWrapper: {
    position: 'absolute' as const,
    top: 0,
    left: 0,
    width: '100%',
    height: '100%',
    margin: 0,
    padding: 0,
  },
  ////////
  //
  ////////
  image: {
    width: '100%',
    height: '100%',
    objectFit: 'cover' as const,
    transition: 'transform 0.3s ease',
    display: 'block',
    
    margin: 0,
    padding: 0,
    border: 'none',
    position: 'fixed' as const,
    top: 0,
    left: 0,
  },
  
  buttonImage: {
    position: 'absolute' as const,
    bottom: '100px',
    right: '135px',
    width: '40px',
    height: '40px',
    borderRadius: '50%',
    backgroundColor: 'white',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    boxShadow: '0 2px 8px rgba(0, 0, 0, 0.15)',
    transition: 'all 0.2s ease',
    zIndex: 10,
    border: 'none',
    cursor: 'pointer',
  },
  
  labelContainer: {
    padding: '20px',
    display: 'flex',
    flexDirection: 'column' as const,
    flexGrow: 1,
    textAlign: 'center' as const,
  },
  
  labelText: {
    fontSize: '18px',
    fontWeight: 700,
    color: '#ffffff',
    marginBottom: '8px',
    lineHeight: 1.3,
    zIndex:2
  },
  
  description: {
    color: '#ffffff',
    fontSize: '14px',
    lineHeight: 1.5,
    marginBottom: '20px',
    flexGrow: 1,
    zIndex:2
  },
  
  priceContainer: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '12px',
    marginTop: 'auto',
    
  },
  
  price: {
    fontSize: '18px',
    fontWeight: 700,
    color: '#ffffff',
    margin: 0,
    whiteSpace: 'nowrap' as const,
    zIndex:2
  },
  
  arrowButton: {
    width: '36px',
    height: '36px',
    borderRadius: '50%',
    border: 'none',
    backgroundColor: '#f7fafc',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    cursor: 'pointer',
    transition: 'all 0.2s ease',
    fontSize: '16px',
    fontWeight: 600,
    color: '#2d3748',
  },
} as const;

export const CatalogItem: React.FC<CatalogItemProps> = ({
  picture_part,
  picture_button,
  label,
  description,
  price,
  filter_color
}) => {
  const [isHovered, setIsHovered] = useState(false);
  const [isImageHovered, setIsImageHovered] = useState(false);
  const [isButtonHovered, setIsButtonHovered] = useState(false);
  const [isArrowHovered, setIsArrowHovered] = useState(false);

  // Форматируем цену (добавляем разделители тысяч)
  const formatPrice = (price: number): string => {
    return price.toLocaleString('ru-RU');
  };

  const new_style = {...styles.image,backgroundColor:filter_color}

  return (
    <div 
      className="catalog-item"
      style={{
        ...styles.cardItem,
        transform: isHovered ? 'translateY(-8px)' : 'translateY(0)',
        boxShadow: isHovered ? '0 8px 30px rgba(0, 0, 0, 0.12)' : '0 4px 20px rgba(0, 0, 0, 0.08)',
      }}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      <div 
        className="catalog-item__images"
        style={styles.imagesContainer}
        onMouseEnter={() => setIsImageHovered(true)}
        onMouseLeave={() => setIsImageHovered(false)}
      >
        <div 
          style={{
            ...styles.imageOverlay,}}
        />
        <div style={styles.imageWrapper}>
          <Images
            alt={picture_part.alt}
            name={picture_part.name}
            type={picture_part.type}
            style={{
              ...new_style,
              transform: isImageHovered ? 'scale(1.05)' : 'scale(1)',
            }}
          />
        </div>
        <div
          style={styles.buttonImage}
        >
          <Images
              alt={picture_button.alt}
              name={picture_button.name}
              type={picture_button.type}
            />
        </div>
      </div>

      <div className="catalog-item__content" style={styles.labelContainer}>
        <h3 className="catalog-item__label" style={styles.labelText}>
          {label}
        </h3>
        <p className="catalog-item__description" style={styles.description}>
          {description}
        </p>
        <div className="catalog-item__footer" style={styles.priceContainer}>
          <div className="catalog-item__price" style={styles.price}>
            От {formatPrice(price)}₽
          </div>
          <button 
            className="catalog-item__arrow-button"
            style={{
              ...styles.arrowButton,
              backgroundColor: isArrowHovered ? '#edf2f7' : '#f7fafc',
              transform: isArrowHovered ? 'translateX(4px)' : 'translateX(0)',
            }}
            onMouseEnter={() => setIsArrowHovered(true)}
            onMouseLeave={() => setIsArrowHovered(false)}
            onClick={() => console.log('Клик по карточке:', label)}
            aria-label={`Подробнее о ${label}`}
          >
            →
          </button>
        </div>
      </div>
    </div>
  );
};