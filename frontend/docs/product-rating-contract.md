# Product rating contract for frontend

The catalog card only needs aggregates. Please add these fields to the product
resource used by catalog list/detail:

```json
{
  "rating_average": 4.67,
  "reviews_count": 12
}
```

`rating_average` may be `null` when there are no approved reviews;
`reviews_count` should then be `0`. Compute them in the query (`withAvg` /
`withCount`, filtered to reviews visible to customers), not by loading every
review into PHP.

Full review text should be a separate paginated read (for example
`GET /api/products/{product}/reviews`) or a product-detail relation loaded on
demand. Do not attach every review to every catalog item: that makes the list
payload grow with review history and creates avoidable N+1 work.
