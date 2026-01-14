import React, { ReactNode, CSSProperties } from "react";

interface GridProps {
  children?: ReactNode;
  columns?: number | 'auto-fill' | 'auto-fit';
  minWidth?: string;
  rows?: number | 'auto-fill' | 'auto-fit';
  minHeight?: string;
  spacing?: string;
  align?: CSSProperties['alignItems'];
  justify?: CSSProperties['justifyItems'];
  className?: string;
  style?: CSSProperties;
}

export const Grid: React.FC<GridProps> = ({
  children,
  columns = 'auto-fit',
  minWidth = '250px',
  rows,
  minHeight = '100px',
  spacing = '1.5rem',
  align = 'stretch',
  justify = 'stretch',
  className = '',
  style,
  ...props
}) => {
  const gridStyle: CSSProperties = {
    display: 'grid',
    gridTemplateColumns: typeof columns === 'number'
      ? `repeat(${columns}, 1fr)`
      : columns === 'auto-fill' || columns === 'auto-fit'
      ? `repeat(${columns}, minmax(${minWidth}, 1fr))`
      : columns,
    gridTemplateRows: rows
      ? (typeof rows === 'number'
        ? `repeat(${rows}, 1fr)`
        : rows === 'auto-fill' || rows === 'auto-fit'
        ? `repeat(${rows}, minmax(${minHeight}, 1fr))`
        : rows)
      : 'auto',
    gap: spacing,
    alignItems: align,
    justifyItems: justify,
    width: '100%',
    ...style,
  };

  return (
    <div className={`grid ${className}`} style={gridStyle} {...props}>
      {children}
    </div>
  );
};