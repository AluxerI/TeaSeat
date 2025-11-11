
import { GridClassKey, PropTypes } from "@material-ui/core"
import { teaTypes } from "../types"
import React, { JSX, useEffect, useRef } from "react";
import { catalogItem } from "./catalog";
import { useInterval, useSize } from "ahooks";
import ItemDiv from "../utils/itemDiv";

type MaxWidthType = 
  | 'maxWidthXs'
  | 'maxWidthSm'
  | 'maxWidthMd'
  | 'maxWidthLg'
  | 'maxWidthXl';

type Direction = 'left'|'right'

interface GridItem<T>{
  id:number,
  data:T,
  position?:{
    row:number;
    column:number;
  };
}
interface GridProps<T>{
    items: GridProps<T>[],
    cardWidth:number,
    cardHeight:number,
    gap:number,
    direction: Direction,
    columns:number,
    rows:number,
    alignment: teaTypes.alignmentType,
    maxWidth: MaxWidthType,
    timeInterval: number,


    onClick?: (item:T,index:number) => void;
    onItemsChange?: (items:GridItem<T>[]) => void;

}

const colSpanBuilder = (colSpan:number) => {
  if(colSpan) return `span ${colSpan}`
}

const rowSpanBuilder = (rowSpan:number) => {
  if(rowSpan) return `span ${rowSpan}`;
}
const Grid = <T,>(
{
  items,
  cardHeight,
  cardWidth,
  gap,
  columns: colSpan,
  direction,
  rows: rowSpan,
  alignment,
  maxWidth,
  timeInterval = 100,

  onClick
}:GridProps<T>
,
position:number[]
): React.ReactElement=>{
  
  var colStart;
  var colEnd;
  if(Array.isArray(position)){
    position = position.reverse();
    colStart = position[0] + 1;
    if(position.length == 2){
      colEnd = position[1] +1;
    }
  }
  const containerSize = useSize(containerRef);

  const handleItemClick = (item:T,index:number):void => {
    onClick?.(item,index);
  }

  return(
    <ItemDiv
      colSpan={colSpanBuilder(colSpan)}
      rowSpan={rowSpanBuilder(rowSpan)}
      colStart={colStart}
      width={cardWidth}
      height={cardHeight}
      colEnd={colEnd}
      onClick={()=>handleItemClick}
    >
      {items as React.ReactNode}
    </ItemDiv>
  )
}