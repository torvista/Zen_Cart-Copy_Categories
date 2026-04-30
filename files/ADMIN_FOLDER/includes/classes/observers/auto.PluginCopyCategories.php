<?php

declare(strict_types=1);

/** Plugin Copy Categories
 * https://github.com/torvista/Zen_Cart-Copy_Categories
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version auto.PluginCopyCategories 30 Apr 2026 torvista
 */
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    die('Illegal Access');
}

/**
 * This observer class adds a Copy Category (and contained products) functionality to the admin category-product listing page.
 */
class zcObserverPluginCopyCategories extends base
{
    // debug logging
    private bool $debug = false;
    private string $logfile = DIR_FS_LOGS . '/debug_PluginCopyCategories.log';
    public function __construct()
    {
        // Initialise logfile
        if ($this->debug) {
            file_put_contents($this->logfile, date('d/m/Y H:i:s') . PHP_EOL . 'class __construct:' . __CLASS__ . PHP_EOL);
        }
        // Usage:
        //        if ($this->debug) {
        //            error_log(PHP_EOL . __LINE__ .  ': fn ' . __FUNCTION__ . PHP_EOL, 3, $this->logfile);
        //        }

        // Load the language definition file
        global $languageLoader;
        $filename = 'plugin_copy_categories.php';
        $languageLoader->loadExtraLanguageFiles(DIR_WS_LANGUAGES, $_SESSION['language'], $filename);

        $this->attach($this, [
                'NOTIFY_ADMIN_PROD_LISTING_ADD_ICON_CATEGORY', // add the additional Copy Category icon after the action M Move Category on the Category Listing
                'NOTIFY_ADMIN_PROD_LISTING_DEFAULT_ACTION',    // capture the action-case-options (copy_category, copy_category_confirm) when they fall through to the switch-default action
                'NOTIFY_ADMIN_PROD_LISTING_DEFAULT_INFOBOX'    // display infobox for copy categories options
            ]
        );
    }

    /**
     * Add an icon C for Copy Category to the category Actions
     *
     * @param $class
     * @param $eventID
     * @param $category
     * @param $additional_icons
     * @return void
     *
     * $zco_notifier->notify('NOTIFY_ADMIN_PROD_LISTING_ADD_ICON_CATEGORY', $category, $additional_icons);
     */
    public function notify_admin_prod_listing_add_icon_category(&$class, $eventID, $category, &$additional_icons): void
    {
        global $cPath;
        $additional_icons = '<a href="' .
            zen_href_link(
                FILENAME_CATEGORY_PRODUCT_LISTING,
                (empty($cPath) ? '' : 'cPath=' . $cPath) . '&cID=' . $category['categories_id'] . '&action=copy_category' .
                (empty($_GET['page']) ? '' : '&page=' . $_GET['page'])
            ) .
            '" class="btn btn-sm btn-default btn-copy" role="button" title="' . ICON_COPY_TO . '"><strong>C</strong></a>';
    }

    /**
     * Handle the copy-category and copy_category_confirm actions
     *
     * @param $class
     * @param $eventID
     * @param $action
     * @param $clearAction
     * @return void
     *
     * $zco_notifier->notify('NOTIFY_ADMIN_PROD_LISTING_DEFAULT_ACTION', $action, $clearAction);
     */
    public function notify_admin_prod_listing_default_action(&$class, $eventID, $action, &$clearAction): void
    {
        global $cPath, $messageStack;
        if ($this->debug) {
            error_log(PHP_EOL . __LINE__ .  ': fn ' . __FUNCTION__ . ', $action="' . $action . '", $clearAction="' . $clearAction . '"' . PHP_EOL, 3, $this->logfile);
        }

        // Display of Infobox options
        // Set a value for POST and return, to subsequently display the Copy Category infobox
        if ($action === 'copy_category') {
            $_POST['copy_category'] = 'copy_category';
            $clearAction = false;
            if ($this->debug) {
                error_log(__LINE__ .  ': $action="' . $action . '", $clearAction=false' . PHP_EOL, 3, $this->logfile);
            }
            return;
        }

        // Process the "copy_category_confirm" action
        if ($action === 'copy_category_confirm' && isset($_POST['copy_from_category_id'], $_POST['copy_to_category_id'])) {
            $clearAction = false;
            $copy_from_category_id = (int)$_POST['copy_from_category_id'];
            $new_parent_id = (int)$_POST['copy_to_category_id'];
            $categories_with_products = [];

            // Get products in the TARGET category: there should be none
            $products_in_categories = zen_get_categories_products_list($new_parent_id, include_deactivated:true, include_child:false);
            // e.g.: [product] => category
            //    [9393] => 6861
            if ($this->debug) {
                error_log(__LINE__ .  ': products in TARGET category #' . $new_parent_id . "\n" . print_r($products_in_categories, true) . PHP_EOL, 3, $this->logfile);
            }
            if (count($products_in_categories) > 0) {
                foreach ($products_in_categories as $value) {
                    $categories_with_products[] = strrpos($value, '_') === false ? $value : substr($value, strrpos($value, '_'));
                }
                //should be only one value
                $categories_with_products = array_unique($categories_with_products);
            }

            // Abort if there are products in the destination category
            if (in_array($new_parent_id, $categories_with_products)) {
                $messageStack->add_session(sprintf(TEXT_COPY_CATEGORIES_PRODUCTS_IN_TARGET, zen_get_category_name($new_parent_id), $new_parent_id), 'error');
                zen_redirect(zen_href_link(FILENAME_CATEGORY_PRODUCT_LISTING, 'cPath=' . $cPath));
            } else {
                $this->_copy_categories_tree($copy_from_category_id, $new_parent_id);
                zen_redirect(zen_href_link(FILENAME_CATEGORY_PRODUCT_LISTING, 'cPath=' . (int)$_POST['copy_to_category_id']));
            }
        }
    }

    /**
     * Handle the copy-category C action to display the infobox
     *
     * @param $class
     * @param $eventID
     * @param $action
     * @param $heading
     * @param $contents
     * @return void
     *
     * $zco_notifier->notify('NOTIFY_ADMIN_PROD_LISTING_DEFAULT_INFOBOX', $action, $heading, $contents);
     */
    public function notify_admin_prod_listing_default_infobox(&$class, $eventID, $action, &$heading, &$contents): void
    {
        global $cInfo, $cPath, $current_category_id, $languages;
        if (!empty($heading) && !empty($contents)) {
            return;
        }
        if ($action === 'copy_category') {
            if ($this->debug) {
                 error_log(PHP_EOL . __LINE__ .  ': fn ' . __FUNCTION__ . PHP_EOL, 3, $this->logfile);
               }
            $heading[] = ['text' => '<b>' . sprintf(TEXT_COPY_CATEGORIES_INFO_HEADING, $cInfo->categories_name, $cInfo->categories_id) . '</b>'];
            $contents = [
                'form' => zen_draw_form('categories', FILENAME_CATEGORY_PRODUCT_LISTING, 'action=copy_category_confirm&cPath=' . $cPath, 'post', 'class="form-horizontal"') .
                    zen_draw_hidden_field('copy_from_category_id', $cInfo->categories_id)
            ];

            $contents[] = ['text' => TEXT_COPY_CATEGORIES_INTRO];

            foreach ($languages as $language) {
                //[0] => Array
                //        (
                //            [id] => 1
                //            [name] => English
                //            [code] => en
                //            [image] => icon.gif
                //            [directory] => english
                //        )
                $category_name_lang = zen_get_category_name($cInfo->categories_id, $language['id']);
                $contents[] = [
                    'text' => (count($languages) > 1 ?  zen_image(DIR_WS_CATALOG_LANGUAGES . $language['directory'] . '/images/' . $language['image'], $language['name']) . ' ' : '') . TEXT_COPY_CATEGORIES_NAME . '<br>' .
                        zen_draw_input_field('copied_category_names[' . $language['id'] . ']', $category_name_lang, 'placeholder="' . $category_name_lang . '" ' . zen_set_field_length(TABLE_CATEGORIES_DESCRIPTION, 'categories_name') . ' class=form-control')
                ];
            }
            $contents[] = ['text' => '<label>' . zen_draw_checkbox_field('copy_metatags', 'yes', true) . ' ' . TEXT_COPY_CATEGORIES_METATAGS . '</label>'];
            $contents[] = ['text' => '<label>' . zen_draw_checkbox_field('enable_copied_categories', 'yes', true) . ' ' . TEXT_COPY_CATEGORIES_CATEGORY_ENABLED . '</label>'];
            $contents[] = [
                'text' => '<br><b>' . TEXT_COPY_CATEGORIES_COPY_PRODUCTS . '</b>'
                    . '<div class="radio"><label>' . zen_draw_radio_field('copy_products', 'copy_products_no', true) . TEXT_COPY_CATEGORIES_COPY_PRODUCTS_NO . '</label></div>'
                    . '<div class="radio"><label>' . zen_draw_radio_field('copy_products', 'copy_products_linked') . TEXT_COPY_CATEGORIES_COPY_PRODUCTS_LINKED . '</label></div>'
                    . '<div class="radio"><label>' . zen_draw_radio_field('copy_products', 'copy_products_duplicated') . '<strong>DO NOT USE!</strong><br>' .  TEXT_COPY_CATEGORIES_COPY_PRODUCTS_DUPLICATED . '</label></div>'
            ];
            $contents[] = ['text' => sprintf(TEXT_COPY_CATEGORIES, $cInfo->categories_name, $cInfo->categories_id)];

            $contents[] = ['text' => zen_draw_pull_down_menu('copy_to_category_id', zen_get_category_tree(), $current_category_id, 'class="form-control"')];
            $contents[] = [
                'align' => 'center',
                'text' => '<button type="submit" class="btn btn-primary">' . IMAGE_COPY . '</button> <a href="' . zen_href_link(FILENAME_CATEGORY_PRODUCT_LISTING, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id) . '" class="btn btn-default" role="button">' . IMAGE_CANCEL . '</a>'
            ];
        }
    }

    /**
     * @param $source_category_id
     * @param $target_parent_id
     * @return void
     */
    private function _copy_categories_tree($source_category_id, $target_parent_id): void
    {
        global $db, $languages, $messageStack;
        if ($this->debug) {
            error_log(PHP_EOL . __LINE__ .  ': fn ' . __FUNCTION__ . PHP_EOL, 3, $this->logfile);
        }

        // Create the new parent category
        $sql = 'INSERT INTO ' . TABLE_CATEGORIES . ' (categories_image, parent_id, sort_order, date_added, last_modified, categories_status)
                  SELECT categories_image, :target_parent_id: AS parent_id, sort_order, NOW() AS date_added, NOW() AS last_modified, :categories_status: AS categories_status
                  FROM ' . TABLE_CATEGORIES . ' WHERE categories_id = :source_category_id:';
        $sql = $db->bindVars($sql, ':target_parent_id:', $target_parent_id, 'integer');
        $sql = $db->bindVars($sql, ':source_category_id:', $source_category_id, 'integer');
        $sql = $db->bindVars($sql, ':categories_status:', (isset($_POST['enable_copied_categories']) && $_POST['enable_copied_categories'] === 'yes' ? 1 : 0), 'integer');
        $db->Execute($sql);

        // Get the category_id of the new parent category
        $target_category_id = zen_db_insert_id();

        // 1: On the first pass/for the new parent category, the category NAME comes from the POST values set by the user in the infoBox
        foreach ($languages as $key => $language) {
            if (isset($_POST['copied_category_names'])) {
                $sql = 'INSERT INTO ' . TABLE_CATEGORIES_DESCRIPTION . ' (categories_id, language_id, categories_name, categories_description)
                  SELECT :target_category_id: AS categories_id, language_id, :category_name: AS categories_name, categories_description
                  FROM ' . TABLE_CATEGORIES_DESCRIPTION . ' WHERE categories_id = :source_category_id: AND language_id = :language_id:';
                $sql = $db->bindVars($sql, ':category_name:', $_POST['copied_category_names'][$language['id']], 'string');
                if ($this->debug) {
                    error_log(__LINE__ .  ': Initial Pass' . PHP_EOL, 3, $this->logfile);
                }

        // >1: On the subsequent/recursive/subcategory passes, the category name comes from the copied categories/source language loo
            } else {
                $sql = 'INSERT INTO ' . TABLE_CATEGORIES_DESCRIPTION . ' (categories_id, language_id, categories_name, categories_description)
                  SELECT :target_category_id: AS categories_id, language_id, categories_name, categories_description
                  FROM ' . TABLE_CATEGORIES_DESCRIPTION . ' WHERE categories_id = :source_category_id: AND language_id = :language_id:';
                if ($this->debug) {
                    error_log(__LINE__ .  ': Subsequent Pass' . PHP_EOL, 3, $this->logfile);
                }
            }
            $sql = $db->bindVars($sql, ':language_id:', $language['id'], 'integer');
            $sql = $db->bindVars($sql, ':source_category_id:', $source_category_id, 'integer');
            $sql = $db->bindVars($sql, ':target_category_id:', $target_category_id, 'integer');
            $db->Execute($sql);
            if ($this->debug) {
                error_log(__LINE__ .  ': $sql="' . $sql .'"' . PHP_EOL, 3, $this->logfile);
            }

            // Optionally copy the category metatags
            if (isset($_POST['copy_metatags']) && $_POST['copy_metatags'] === 'yes') {
                $sql = 'INSERT INTO ' . TABLE_METATAGS_CATEGORIES_DESCRIPTION . ' (categories_id, language_id, metatags_title, metatags_keywords, metatags_description)
                  SELECT :target_category_id: AS categories_id, :language_id: AS language_id, metatags_title, metatags_keywords, metatags_description
                FROM ' . TABLE_METATAGS_CATEGORIES_DESCRIPTION . ' WHERE categories_id = :source_category_id: AND language_id = :language_id:';
                $sql = $db->bindVars($sql, ':language_id:', $language['id'], 'integer');
                $sql = $db->bindVars($sql, ':source_category_id:', $source_category_id, 'integer');
                $sql = $db->bindVars($sql, ':target_category_id:', $target_category_id, 'integer');
                $db->Execute($sql);
                if ($this->debug) {
                    error_log(__LINE__ .  ': metatags copied, $sql="' . $sql .'"' . PHP_EOL, 3, $this->logfile);
                }
            }
        }
        unset($_POST['copied_category_names']);

        $messageStack->add_session(sprintf(TEXT_COPY_CATEGORIES_TO_CATEGORY, zen_get_category_name($source_category_id), $source_category_id, zen_get_category_name($target_parent_id), $target_parent_id), 'success');

        if ($_POST['copy_products'] === 'copy_products_linked' || $_POST['copy_products'] === 'copy_products_duplicated') {
            $products_to_copy = zen_get_categories_products_list($source_category_id, true, false);
            // e.g.: [product] => category
            //    [9393] => 6861
            if ($this->debug) {
                error_log(__LINE__ .  ': products to copy from #' . $source_category_id . "\n" . print_r($products_to_copy, true) . PHP_EOL, 3, $this->logfile);
            }

            // The previous function creates $GLOBALS['categories_products_id_list'].
            // Need to unset it on each pass, otherwise the products in each category are appended to the array
            unset ($GLOBALS['categories_products_id_list']);

            if ($_POST['copy_products'] === 'copy_products_linked') {
                if ($this->debug) {
                    error_log(__LINE__ .  ': copy products as LINKED' . PHP_EOL, 3, $this->logfile);
                }
                foreach ($products_to_copy as $key => $source_category) {
                    zen_link_product_to_category($key, $target_category_id);
                    $messageStack->add_session(sprintf(TEXT_COPY_CATEGORIES_PRODUCTS_LINKED, zen_get_products_name($key), $key, zen_get_category_name($source_category), $source_category, zen_get_category_name($target_category_id), $target_category_id), 'success');
                }

                // Get the subcategories of the source parent category
                $childs = $db->Execute('SELECT categories_id FROM ' . TABLE_CATEGORIES . ' WHERE parent_id = ' . $source_category_id);
                $child_ids = [];
                foreach ($childs as $child) {
                    $child_ids[] = $child['categories_id'];
                }
                // Parse the products in the subcategories
                foreach ($child_ids as $child) {
                    $this->_copy_categories_tree($child, $target_category_id);
                }
                return;
            }

            // TODO copy as Duplicated
            if ($_POST['copy_products'] === 'copy_products_duplicated')
            {
                foreach ($products_to_copy as $key => $source_category) {
                    $_POST['copy_as'] = 'duplicate';
                    $id = $key;

                    // Copied from Multiple Product Copy
                    $action = 'multiple_product_copy_return'; // used in copy_product_confirm.php (core modification required) to bypass default redirect and so allow multiple copy
                    $_POST['products_id'] = $id; // for copy_product_confirm
                    $_POST['categories_id'] = $target_category_id; // for copy_product_confirm
                    $product_type = zen_get_products_type($id); // for copy_product_confirm

                    // new product creation is handled by the following module, creating $dup_products_id (copy_attributes, copy_metatags, copy_linked_categories, copy_discounts also handled here)
                    // Why is this variable not already existing???
                    // If this is not existing here, an error is caused when it is referenced in copy_product_confirm.php
                    if (!isset($zco_notifier)) $zco_notifier = new notifier();

                    require zen_get_admin_module_from_directory($product_type, 'copy_product_confirm.php');
                    //get confirmation messages
                    if (isset($_SESSION['messageToStack']) && is_array($_SESSION['messageToStack'])) {
                        foreach ($_SESSION['messageToStack'] as $row) {
                            $messageStack->add($row['text'], $row['type']);
                        }
                        $_SESSION['messageToStack'] = '';
                    }

                    $dup_products_id = !empty($dup_products_id) ? $dup_products_id : 0; // $dup_products_id is the new product id created by the copy_products_confirm module, is integer. This check is added to satisfy the IDE.
                    if ($dup_products_id > 0) {
                        // reset products_price_sorter for searches etc.
                        zen_update_products_price_sorter($id);

                        $products_modified[] = [
                            'id' => $dup_products_id,
                            'model' => zen_get_products_model($dup_products_id),
                            'name' => zen_get_products_name($dup_products_id),
                            'category' => $target_category_id,
                            'master_category' => $target_category_id,
                            'quantity' => zen_get_products_stock($dup_products_id),
                            'price' => zen_get_products_display_price($dup_products_id),
                            'manufacturer' => zen_get_products_manufacturers_name($dup_products_id)
                        ];
                    } else {
                        $messageStack->add(ERROR_COPY_DUPLICATE_NO_DUP_ID, $_POST['products_id'], $_POST['categories_id']);
                        $action = 'find';
                    }
                    //eof Copied from Multiple Product Copy

                    $childs = $db->Execute('SELECT categories_id FROM ' . TABLE_CATEGORIES . ' WHERE parent_id = ' . $source_category_id);
                    $child_ids = [];
                    foreach ($childs as $child) {
                        $child_ids[] = $child['categories_id'];
                    }
                    foreach ($child_ids as $child) {
                        $this->_copy_categories_tree($child, $target_category_id);
                    }

                    $messageStack->add_session(sprintf(TEXT_COPY_CATEGORIES_PRODUCTS_DUPLICATED, zen_get_products_name($key), $key, zen_get_category_name($source_category), $source_category, zen_get_category_name($target_category_id), $target_category_id), 'success');
                }
                return;
            }
        }
            //should never get here
           error_log(__LINE__ .  ': fn ' . __FUNCTION__ . ', unhandled $_POST' . "\n" . print_r($_POST, true) . PHP_EOL, 3, $this->logfile);
   }
}
