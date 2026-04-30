<?php

declare(strict_types=1);

/**
 * Plugin Copy Categories
 * https://github.com/torvista/Zen_Cart-Copy_Categories
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version 30 Apr 2026 torvista
 */

$define = [
    'TEXT_COPY_CATEGORIES_CATEGORY_ENABLED' => 'Enable the copied category/categories?',
    'TEXT_COPY_CATEGORIES_COPY_PRODUCTS' => 'Copy products too?',
    'TEXT_COPY_CATEGORIES_COPY_PRODUCTS_LINKED' => 'Copy products as <strong>linked</strong>',
    'TEXT_COPY_CATEGORIES_COPY_PRODUCTS_DUPLICATED' => 'Copy products as <strong>duplicates</strong> (new products with the same name)',
    'TEXT_COPY_CATEGORIES_COPY_PRODUCTS_NO' => 'Do <strong>not</strong> copy products, only the category structure.',
    'TEXT_COPY_CATEGORIES_INFO_HEADING' => 'Copy category "%1$s" (#%2$u)',
    'TEXT_COPY_CATEGORIES_INTRO' => 'Duplicate this category and it\'s subcategories.',
    'TEXT_COPY_CATEGORIES_METATAGS' => 'Copy category metatags',
    'TEXT_COPY_CATEGORIES_NAME' => 'New category name',
    'TEXT_COPY_CATEGORIES_PRODUCTS_IN_TARGET' => 'ERROR: Copy Category aborted, target category "%1$s" (#%2$u) contains product(s).',
    'TEXT_COPY_CATEGORIES_PRODUCTS_LINKED' => '<strong>Product: "%1$s</strong>" (#%2$u) in source category "%3$s" (#%4$u), linked to target category "%5$s" (#%6$u)',
    'TEXT_COPY_CATEGORIES_PRODUCTS_DUPLICATED' => '<strong>Product: "%1$s</strong>" (#%2$u) from source category "%3$s" (#%4$u), duplicated in target category "%5$s" (#%6$u)',
    'TEXT_COPY_CATEGORIES_TO_CATEGORY' => '<strong>Category:</strong> "%1$s" (#%2$u) copied to category "%3$s" (#%4$u)',
];

return $define;
