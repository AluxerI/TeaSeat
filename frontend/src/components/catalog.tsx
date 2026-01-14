import React, { useEffect, useRef } from "react"
import Images from "../utils/Images"


export type typePic = 'svg' | 'jpeg' | 'png'
type Picture = {
    name: string,
    alt: string,
    type: typePic
}
export interface catalogItemProp {
    picture_part: Picture,
    picture_button: Picture,
    label: string,
    description: string,
    price: number,
}

export const catalogItem: React.FC<catalogItemProp> = ({
    picture_part,
    picture_button,
    label,
    description,
    price
}
) => {
    
    const arrow_pic:Picture = {
        name:"arrow-left-buy",
        alt: "arrow-left-buy",
        type: "svg"
    }
    return (
        <div className="card-item">
            <div className="images">

                <Images
                    alt={picture_part.alt}
                    name={picture_part.name}
                    type={picture_part.type}
                ></Images>

                <Images
                    alt={picture_button.alt}
                    name={picture_button.name}
                    type={picture_button.type}
                >

                </Images>

            </div>

            <div className="label">
                <label className="label-text">
                    {label}
                </label>

                <p className="tea-coffee">
                    {description}
                </p>

                <div className="price-svg-strelka">
                    <p className="tea-coffee-price">
                        От {price}₽
                    </p>
                    <button>
                        <Images
                            name={arrow_pic.name}
                            alt={arrow_pic.alt}
                            type={arrow_pic.type}
                        ></Images>
                    </button>
                </div>
            </div>
        </div>
    )
}

