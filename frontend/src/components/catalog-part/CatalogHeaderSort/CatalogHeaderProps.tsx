import { useEffect, useState } from "react";
import { catalogApi } from "../../../api/catalogAPI";
import { Category } from "../../../interfaces/catalog";
import { useAsync } from "../../../hooks/useAsync";

interface Tag {
  id: string;
  label: string;
  subcategories: {
    id: number;
    name: string;
    category_id: number;
    sub_subcategories: {
      id: number;
      name: string;
      subcategory_id: number;
    }[];
  }[];
}

interface CatalogHeaderProps {
  total?: number;
  tags?: Tag[];
  sortValue?: string;
  onRemoveTag?: (id: string) => void;
  onSortChange?: (value: string) => void;
  onShowAll?: () => void;
}

const SORT_OPTIONS = [
  { value: "popular", label: "По популярности" },
  { value: "price_asc", label: "По цене: дешевле" },
  { value: "price_desc", label: "По цене: дороже" },
  { value: "new", label: "По новизне" },
];

const mapCategoriesToTags = (categories: Category[]): Tag[] =>
  categories.map((category) => ({
    id: category.id.toString(),
    label: category.name,
    subcategories: category.subcategories,
  }));

const CatalogHeader = ({
  total: propTotal,
  tags: propTags,
  sortValue: propSortValue = "popular",
  onRemoveTag = () => {},
  onSortChange = () => {},
  onShowAll = () => {},
}: CatalogHeaderProps) => {
  const [sortValue, setSortValue] = useState(propSortValue);
  const [tags, setTags] = useState<Tag[]>([]);

  const meta = useAsync(() => catalogApi.getMeta());
  const categoryState = useAsync(() => catalogApi.getCategory());

  useEffect(() => {
    setSortValue(propSortValue);
  }, [propSortValue]);

  useEffect(() => {
    if (propTags) {
      setTags(propTags);
      return;
    }

    if (categoryState.data) {
      setTags(mapCategoriesToTags(categoryState.data));
    }
  }, [propTags, categoryState.data]);

  const total = propTotal ?? meta.data?.total_products ?? 0;

  const handleSortChange = (value: string) => {
    setSortValue(value);
    onSortChange(value);
  };

  const handleRemoveTag = (tagId: string) => {
    setTags((prev) => prev.filter((tag) => tag.id !== tagId));
    onRemoveTag(tagId);
  };

  const handleShowAll = () => {
    if (!propTags && categoryState.data) {
      setTags(mapCategoriesToTags(categoryState.data));
    }
    onShowAll();
  };

  return (
    <div className="catalog-header">
      <div className="catalog-header__top">
        <h2 className="catalog-header__title">Каталог</h2>
        <span className="catalog-header__total">
          Найдено: {total} товаров
        </span>

        <select
          className="catalog-header__sort"
          value={sortValue}
          onChange={(e) => handleSortChange(e.target.value)}
        >
          {SORT_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>

      {/* Теги */}
      <div className="catalog-header__tags">
        {tags.map((tag) => (
          <span key={tag.id} className="catalog-header__tag">
            {tag.label}
            <button
              className="catalog-header__tag-remove"
              onClick={() => handleRemoveTag(tag.id)}
            >
              X
            </button>
          </span>
        ))}

        <button className="catalog-header__show-all" onClick={handleShowAll}>
          Все категории
        </button>
      </div>
    </div>
  );
};

export default CatalogHeader;
