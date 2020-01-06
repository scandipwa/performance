# ScandiPWA_Performance

Enhanced performance of product loading.

### How to use

When adding a new resolved field to product interface, make sure to:
1. Understand when you are able to load it? If you can load it on collection, 
create a processor and register it in `CompositeCollectionProcessor` using DI.
2. If data is needed to be formatted, or you can not request data with collection,
use `DataPostProcessor`. Register a processor there, and return a product (as key => value array)
processing function (see example in implementations).
3. If data is impossible to request before collection load, but it is possible to append
the loaded data afterwards (using the collection itself) - use `CollectionPostProcessor`, register 
the processor there in the same way as for `DataPostProcessor`.

### Related modules:

- [quote-graphql](https://github.com/scandipwa/quote-graphql)
- [wishlist-graphql](https://github.com/scandipwa/wishlist-graphql)
- [catalog-graphql](https://github.com/scandipwa/catalog-graphql)
- [reviews-graphql](https://github.com/scandipwa/reviews-graphql)

### Initial motivation:

> CASE ONE: The data can not be requested along with product collection
> (**only post-load is possible**)

1. After collection load, checked for requested fields in schema (using $info) for each additional info category
2. If additional info was requested, the helper requested & returned the info for all products at once
3. The loop through all loaded products applying data from helpers to each specific product

> CASE TWO: The data can be requested before load
>(**field can be resolved with collection load**)

**This one is covered by M2 (by default) - we will just ignore this.**

1. The collection processor goes through collection, it adds requested fields to a collection
2. If field requires additional work, it is formatted after collection load
3. If field needs no formatting it is automatically out-putted in resulting data array

> CASE THREE: The data can be requested after collection load,
> but is based on the collection data, not product array.

### Potential Issues 

a. The code duplicates in each of 5 places were the collection was loaded [REQUIRES ABSTRACTION]
b. The data structures are common to be different from place to place, a check if field was requested or no is hard:
1. ConfigurableVariant: `variants/product`
2. Default: `products/items`
3. Cart, Wish-list: `items/product`
4. Orders: `order_products`

## What was implemented

### GraphQL schema reading trait _[new]_

**Class name**: `ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait`

**Motivation**: allows for GraphQL info parsing, can extract fields from path.
By default returns array of product fields, the product field parsing can be 
changed by overriding `getFieldContent` method.

**Used in**:

1. `ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor\Images`
2. `ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor\Stocks`
3. `ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor\Attributes`
4. `ScandiPWA\CatalogGraphQl\Model\Resolver\ConfigurableVariant`
5. `ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query\Filter`
6. `ScandiPWA\WishlistGraphQl\Model\Resolver\WishlistItemsResolver`
7. `ScandiPWA\QuoteGraphQl\Model\Resolver\ProductsResolver`

### Collection post processor _[new]_

**Class name**: `ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor`

**Motivation**: allows to post-process collection, for situations, where data is
applied on-top of loaded collection - media gallery data, product options data, etc.

**Used in**: 

1. `ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product`
2. `ScandiPWA\CatalogGraphQl\Model\Variant\Collection`

### Data post processor _[new]_

**Class name**: `ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor`

**Motivation**: allows for loaded product collection data post-processing. Accepts
array of products, resolve info and can efficiently process the product data.
Is made to prevent child fields of products to request the data in the loop.
Attribute, image, stock info is moved to this resolver out of product.

**Used in**:

1. `ScandiPWA\WishlistGraphQl\Model\Resolver\WishlistItemsResolver`
2. `ScandiPWA\QuoteGraphQl\Model\Resolver\GetCartForCustomer`
3. `ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query\Filter`
4. `ScandiPWA\QuoteGraphQl\Model\Resolver\ProductsResolver`
5. `ScandiPWA\CatalogGraphQl\Model\Resolver\ConfigurableVariant`

### Product data provider _[modified]_

**Class name**: `ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product`

**Motivation**: previously the collection post processor was implemented here (hard-coded).
Since it was moved into separate class, the logic had to be removed from origin.

**Used in**:

1. `ScandiPWA\QuoteGraphQl\Model\Resolver\ProductsResolver`
2. `ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query\Filter`

### Products Collection processor _[modified]_

**Class name**: `Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CompositeCollectionProcessor`

**Motivation**: The M2 implementation was OK, just added additional processors to it:
1. `ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor\ImagesProcessor`