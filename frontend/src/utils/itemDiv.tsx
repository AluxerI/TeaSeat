import React from "react";

interface ItemDivProps {
  colSpan?: string | number;
  rowSpan?: string | number;
  colStart?: string | number;
  colEnd?: string | number;
  rowStart?: string | number;
  rowEnd?: string | number;
  width?: string | number;
  height?: string | number;
  children?: React.ReactNode;
  className?: string;
  style?: React.CSSProperties;
  onClick?: () => void;
}

const ItemDiv: React.FC<ItemDivProps> = ({
  colSpan,
  rowSpan,
  colStart,
  colEnd,
  rowStart,
  rowEnd,
  width,
  height,
  children,
  className = '',
  style = {},
  onClick,
  ...rest
}) => {
  const computedStyles: React.CSSProperties = {
    ...style,
    ...(colSpan && { gridColumn: typeof colSpan === 'number' ? `span ${colSpan}` : colSpan }),
    ...(rowSpan && { gridRow: typeof rowSpan === 'number' ? `span ${rowSpan}` : rowSpan }),
    ...(colStart && { gridColumnStart: colStart }),
    ...(colEnd && { gridColumnEnd: colEnd }),
    ...(rowStart && { gridRowStart: rowStart }),
    ...(rowEnd && { gridRowEnd: rowEnd }),
    ...(width && { width: typeof width === 'number' ? `${width}px` : width }),
    ...(height && { height: typeof height === 'number' ? `${height}px` : height }),
  };

  return (
    <div
      className={`item-div ${className}`}
      style={computedStyles}
      onClick={onClick}
      {...rest}
    >
      {children}
    </div>
  );
};

export default ItemDiv;