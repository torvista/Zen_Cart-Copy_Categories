# Zen Cart - Copy Categories

For Zen Cart 1.58/php 8.1+

30/11/2022: OPEN TO TESTING BY THE COMMUNITY  
Please report the exact process of replicating the  bugs/unexpected behaviour in GitHub:

https://github.com/torvista/Zen_Cart-Copy_Categories/issues

DO NOT TRY THIS ON A PRODUCTION SITE AND BE PREPARED TO USE A DATABASE RESTORE DURING TESTING TO "RESET" THINGS IF YOU BREAK IT.

Installation
1) Copy the three files into the ADMIN FOLDER.
2) Compare your ZC158-version of category_product_listing.php against the reference  category_product_listing.158a php included here to ensure you have the code associated with these notifiers:

'NOTIFY_ADMIN_PROD_LISTING_DEFAULT_ACTION',    // capture the action-case-options (copy_category, copy_category_confirm) when they fall through to the switch-default action  
'NOTIFY_ADMIN_PROD_LISTING_DEFAULT_INFOBOX'    // display infobox for copy categories options 

The first was modified after the release of ZC158, the second was added.

Changelog
30/11/2022: torvista  
rewritten to use observers.