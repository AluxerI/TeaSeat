import React from "react";

interface ImageProps extends React.ImgHTMLAttributes<HTMLImageElement> {
  /** 
   * Имя файла без расширения (обязательно для локальных изображений)
   * Пример: "logo"
   */
  name: string;
  /** 
   * Расширение файла (обязательно для локальных изображений)
   * Пример: "svg", "png", "jpg"
   */
  type: string;
  /** 
   * Путь внутри папки public (опционально)
   * Пример: "header" для public/header/
   */
  folder?: string;
  /** 
   * Абсолютный URL изображения (для внешних ресурсов)
   * Пример: "https://example.com/image.png"
   */
  src?: string;
  alt: string;
}

const Images: React.FC<ImageProps> = ({
  name,
  type,
  folder = "",
  src,
  alt,
  className,
  ...props
}) => {
  // Обработка абсолютных путей
  const isAbsolutePath = src 
    ? /^(https?:|data|\/\/)/i.test(src) 
    : false;

  // Формирование пути для локальных изображений
  const getLocalImagePath = () => {
    const cleanFolder = folder.replace(/^\/|\/$/g, '');
    const pathSegments = ["", cleanFolder, `${name}.${type}`].filter(Boolean);
    return pathSegments.join("/");
  };

  // Определение итогового пути
  const imagePath = isAbsolutePath 
    ? src 
    : src 
      ? `/${src.replace(/^\//, "")}` 
      : getLocalImagePath();

  return (
    <img 
      src={imagePath} 
      alt={alt} 
      className={className}
      {...props}
    />
  );
};

export default Images;