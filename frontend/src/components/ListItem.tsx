
import { PropTypes } from "@material-ui/core"
import { teaTypes } from "../types"
import React from "react";

type MaxWidthType = 
  | 'maxWidthXs'
  | 'maxWidthSm'
  | 'maxWidthMd'
  | 'maxWidthLg'
  | 'maxWidthXl';

interface UniversalListProps<T>{
    items:T[],
    cardWidth:number,
    cardHeight:number,
    gap:number,
    columns:number,
    rows:number,
    alignment: teaTypes.alignmentType,
    maxWidth: MaxWidthType
    
}
const UniversalList = ({
    items,

}):React.FC<ListInt=>{

}