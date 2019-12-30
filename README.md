# ScandiPWA_Performance

> This module is WIP

### Goal (TODO):

> CASE ONE: The data can not be requested along with product collection (**only post-load is possible**)

1. After collection load, checked for requested fields in schema (using $info) for each additional info category
2. If additional info was requested, the helper requested & returned the info for all products at once
3. The loop through all loaded products applying data from helpers to each specific product

> CASE TWO: The data can be requested before load (**field can be resolved with collection load**)

1. The collection processor goes through collection, it adds requested fields to a collection
2. If field requires additional work, it is formatted after collection load
3. If field needs no formatting it is automatically out-putted in resulting data array

### Potential Issues 

a. The code duplicates in each of 5 places were the collection was loaded [REQUIRES ABSTRACTION]
b. The data structures are common to be different from place to place, a check if field was requested or no is hard:
1. ConfigurableVariant: `variants > product`
2. Default: `products > items`
3. Cart, Wish-list: `items > product`

## What is done

GraphQL schema reading trait: ScandiPWA\Performance\Model\Resolver\Products\PostProcessorTrait

Classes implementing the post-processor:
1. ScandiPWA\CatalogGraphQl\Model\Resolver\ConfigurableVariant
2. ScandiPWA\CatalogGraphQl\Model\Resolver\Products\Query\Filter
3. ScandiPWA\QuoteGraphQl\Model\Resolver\GetCartForCustomer
4. ScandiPWA\WishlistGraphQl\Model\Resolver\WishlistItemsResolver

Classes implementing the pre-processor:
1. ScandiPWA\CatalogGraphQl\Model\Variant\Collection
2. ScandiPWA\WishlistGraphQl\Model\Resolver\WishlistItemsResolver

Classes added as pre-processors:
1. ScandiPWA\ReviewsGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor\ReviewProcessor
2. ScandiPWA\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessor\ImagesProcessor