
import { PropTypes } from "@material-ui/core"
import { teaTypes } from "../types"
import React, { JSX } from "react";
import { catalogItem } from "./catalog";

type MaxWidthType = 
  | 'maxWidthXs'
  | 'maxWidthSm'
  | 'maxWidthMd'
  | 'maxWidthLg'
  | 'maxWidthXl';

type Direction = 'left'|'right'

  interface UniversalListProps<T>{
    items:T[],
    cardWidth:number,
    cardHeight:number,
    gap:number,
    direction: Direction,
    columns:number,
    rows:number,
    alignment: teaTypes.alignmentType,
    maxWidth: MaxWidthType,

    onClick?: (item:T,index:number) => void;
}
const UniversalList = <T,>(
{
  items,
  gap,
  columns,
  direction,
  rows,
  alignment,
  maxWidth,

  onClick
}:UniversalListProps<T>
) =>{


  const listItems = items.map((item,index) => (
    <li key={index}
    >{String(item)}</li>
  ));

  if(alignment== )

  const styleList = {
  gap:`${gap}px`,
  maxWidth: `${maxWidth}px;`,
  

  }

  return(
    <div 
    
    className="list"
    style=
    >
      {listItems}
    </div>
  )
}