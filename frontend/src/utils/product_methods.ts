import { Category, Product } from "../interfaces/catalog";

export const sort_by_brands = (products: Product[], what_return: "object" | "count"): Set<string> | number => {
    const brands = new Set<string>()

    products.forEach(product => brands.add(product.brand))

    if (what_return === "object") {
        return brands
    } else {
        return brands.size
    }
}

export const sort_by_category = (category: Category[], products: Product[], what_return: "object" | "count"): Set<string> | number => {
    const names = new Set<string>()

    products.forEach(product => {
        if (category.some(c => c.name === product.category_path.category)) {
            names.add(product.category_path.category)
        }
    })

    if (what_return === "object") {
        return names
    } else {
        return names.size
    }
}

