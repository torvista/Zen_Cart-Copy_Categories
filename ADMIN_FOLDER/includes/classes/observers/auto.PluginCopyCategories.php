<?php

declare(strict_types=1);
/** Plugin Copy Categories
 * https://github.com/torvista/Zen_Cart-Copy_Categories
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version torvista 2022 Nov 29
 */
if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
    die('Illegal Access');
}

/**
 * This observer class adds a Copy Category (and contained products) functionality to the admin category-product listing page.
 */
class zcObserverPluginCopyCategories extends base
{
    var bool $debug = false;

    public function __construct()
    {
        // Load the language definition file for the current language
        $language_file = DIR_WS_LANGUAGES . $_SESSION['language'] . '/' . 'plugin_copy_categories.php';
        if (file_exists($language_file)) {
            include_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/' . 'plugin_copy_categories.php');
        } elseif (file_exists(DIR_WS_LANGUAGES . 'english' . '/' . 'plugin_copy_categories.php')) {
            include_once(DIR_WS_LANGUAGES . 'english' . '/' . 'plugin_copy_categories.php');
        }

        $this->attach($this, [
                'NOTIFY_ADMIN_PROD_LISTING_ADD_ICON_CATEGORY', // add the additional Copy icon after action M Move Category
                'NOTIFY_ADMIN_PROD_LISTING_DEFAULT_ACTION',    // MODIFIED Notifier capture the action-case-options (copy_category, copy_category_confirm) when they fall through to the switch-default action
                'NOTIFY_ADMIN_PROD_LISTING_DEFAULT_INFOBOX'    // NEW Notifier
            ]
        );
    }

//add a Copy Category icon
//$zco_notifier->notify('NOTIFY_ADMIN_PROD_LISTING_ADD_ICON_CATEGORY', $category, $additional_icons);
    public function notify_admin_prod_listing_add_icon_category(&$class, $eventID, $p1, &$p2)
    {
        global $cPath;
        $p2 = '<a href="' .
            zen_href_link(
                FILENAME_CATEGORY_PRODUCT_LISTING,
                (empty($cPath) ? '' : 'cPath=' . $cPath) . '&cID=' . $p1['categories_id'] . '&action=copy_category' .
                (empty($_GET['page']) ? '' : '&page=' . $_GET['page'])
            ) .
            '" class="btn btn-sm btn-default btn-copy" role="button" title="' . ICON_COPY_TO . '"><strong>C</strong></a>';
    }

//handle the copy-category and copy_category_confirm actions
//$zco_notifier->notify('NOTIFY_ADMIN_PROD_LISTING_DEFAULT_ACTION', $action, $clearAction);
    public function notify_admin_prod_listing_default_action(&$class, $eventID, $p1, &$p2)
    {
        global $cPath, $messageStack;

        //initial copy: set POST to display copy Category infobox
        if ($p1 === 'copy_category') {
            $_POST['copy_category'] = 'copy_category';
            $p2 = false;
            return;
        }

        //process the copy confirm
        if ($p1 === 'copy_category_confirm') {
            $p2 = false;

            if (isset($_POST['categories_id'], $_POST['copy_to_category_id'])) {
                $categories_id = (int)$_POST['categories_id'];
                $new_parent_id = (int)$_POST['copy_to_category_id'];
                $categories_with_products = [];

                $products_in_categories = zen_get_categories_products_list($new_parent_id, true);
                if (count($products_in_categories) > 0) {
                    foreach ($products_in_categories as $key => $value) {
                        $categories_with_products[] = substr($value, strrpos($value, "_"));
                    }
                    //should be only one value
                    $categories_with_products = array_unique($categories_with_products);
                }
                //abort if there are products in the destination category
                if (in_array($new_parent_id, $categories_with_products)) {
                    $messageStack->add_session(sprintf(TEXT_COPY_CATEGORY_PRODUCTS_IN_TARGET, zen_get_category_name($new_parent_id), $new_parent_id), 'error');
                    zen_redirect(zen_href_link(FILENAME_CATEGORY_PRODUCT_LISTING, 'cPath=' . $cPath));
                } else {
                    $this->_copy_categories_tree($categories_id, $new_parent_id);
                    zen_redirect(zen_href_link(FILENAME_CATEGORY_PRODUCT_LISTING, 'cPath=' . (int)$_POST['copy_to_category_id']));
                }
            }
        }
    }

//handle the copy-category action t display the infobox
//$zco_notifier->notify('NOTIFY_ADMIN_PROD_LISTING_DEFAULT_INFOBOX', $action, $heading, $contents);
    public function notify_admin_prod_listing_default_infobox(&$class, $eventID, $p1, &$p2, &$p3)
    {
        global $cInfo, $cPath, $current_category_id;
        if (!empty($p2) && !empty($p3)) {
            return;
        }
        if ($p1 === 'copy_category') {
            $heading[] = ['text' => '<b>' . sprintf(TEXT_INFO_HEADING_COPY_CATEGORY, $cInfo->categories_name, $cInfo->categories_id) . '</b>'];
            $contents = ['form' => zen_draw_form('categories', FILENAME_CATEGORY_PRODUCT_LISTING, 'action=copy_category_confirm&cPath=' . $cPath, 'post', 'class="form-horizontal"') . zen_draw_hidden_field('categories_id', $cInfo->categories_id)];

            $contents[] = ['text' => TEXT_COPY_CATEGORIES_INTRO];
            $contents[] = [
                'text' => TEXT_COPY_CATEGORIES_NAME . '<br>' . zen_draw_input_field('copied_category_name', $cInfo->categories_name, 'placeholder="' . $cInfo->categories_name . '" size="31" maxlength="30" class=form-control')
            ];
            $contents[] = ['text' => '<label>' . zen_draw_checkbox_field('copy_metatags', 'on', true) . ' ' . TEXT_COPY_CATEGORY_METATAGS . '</label>'];
            $contents[] = [
                'text' => '<b>' . TEXT_COPY_CATEGORY_COPY_PRODUCTS . '</b>'
                    . '<div class="radio"><label>' . zen_draw_radio_field('copy_products', 'copy_products_no', true) . TEXT_COPY_CATEGORY_COPY_PRODUCTS_NO . '</label></div>'
                    . '<div class="radio"><label>' . zen_draw_radio_field('copy_products', 'copy_products_linked') . TEXT_COPY_CATEGORY_COPY_PRODUCTS_LINKED . '</label></div>'
                //. '<div class="radio"><label>' . zen_draw_radio_field('copy_products', 'copy_products_duplicate') . TEXT_COPY_CATEGORY_COPY_PRODUCTS_DUPLICATE . '</label></div>'
            ];
            $contents[] = ['text' => sprintf(TEXT_COPY_CATEGORY, $cInfo->categories_name, $cInfo->categories_id)];
            $test = zen_get_category_tree();
            $contents[] = ['text' => zen_draw_pull_down_menu('copy_to_category_id', zen_get_category_tree(), $current_category_id, 'class="form-control"')];
            $contents[] = [
                'align' => 'center',
                'text' => '<button type="submit" class="btn btn-primary">' . IMAGE_COPY . '</button> <a href="' . zen_href_link(FILENAME_CATEGORY_PRODUCT_LISTING, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id) . '" class="btn btn-default" role="button">' . IMAGE_CANCEL . '</a>'
            ];
            $p2 = $heading;
            $p3 = $contents;
        }
    }

    private function _copy_categories_tree($source_category_id, $target_parent_id): void
    {
        global $cPath, $db, $messageStack;

        if ($this->debug) {
            echo 'fn _copy_categories_tree START<br>';
        }

        //insert new category record
        $db->Execute(
            'INSERT INTO ' . TABLE_CATEGORIES . '
            (categories_image, parent_id, sort_order, date_added, last_modified, categories_status)
                  SELECT categories_image, ' . $target_parent_id . ' AS parent_id, sort_order, NOW() AS date_added, NOW() AS last_modified, categories_status
                  FROM ' . TABLE_CATEGORIES . ' WHERE categories_id = ' . $source_category_id
        );

        //get the category_id of the new/target category record
        $target_category_id = zen_db_insert_id();

        //copy category details from source to target
        if (isset($_POST['copied_category_name']) && (int)$source_category_id === (int)$_POST['categories_id']) {
            $category_name = '"' . zen_db_prepare_input($_POST['copied_category_name']) . '"';
        } else {
            $category_name = 'categories_name';
        }

        $db->Execute(
            'INSERT INTO ' . TABLE_CATEGORIES_DESCRIPTION . ' (categories_id, language_id, categories_name, categories_description)
                  SELECT ' . $target_category_id . ' AS categories_id, language_id, ' . $category_name . ', categories_description
                FROM ' . TABLE_CATEGORIES_DESCRIPTION . ' WHERE categories_id = ' . $source_category_id
        );

        if (isset($_POST['copy_metatags']) && $_POST['copy_metatags'] === 'on') {
            $db->Execute(
                'INSERT INTO ' . TABLE_METATAGS_CATEGORIES_DESCRIPTION . ' (categories_id, language_id, metatags_title, metatags_keywords, metatags_description)
                  SELECT ' . $target_category_id . ' AS categories_id, language_id, metatags_title, metatags_keywords, metatags_description
                FROM ' . TABLE_METATAGS_CATEGORIES_DESCRIPTION . ' WHERE categories_id = ' . $source_category_id
            );
        }

        $messageStack->add_session(sprintf(TEXT_COPY_CATEGORY_TO_CATEGORY, zen_get_category_name($source_category_id), $source_category_id, zen_get_category_name($target_parent_id), $target_parent_id), 'success');

        if ($this->debug) {
            echo 'fn _copy ' . __LINE__ . ':$source_category_id=' . $source_category_id . ' | $target_parent_id=' . $target_parent_id . ' | $target_category_id=' . $target_category_id . '<br>';
        }

        if ($_POST['copy_products'] === 'copy_products_linked' || $_POST['copy_products'] === 'copy_products_duplicate') {
            if ($this->debug) {
                echo 'fn _copy ' . __LINE__ . ':copy_products<br>';
            }

            $products_to_copy = zen_get_categories_products_list($source_category_id, true, false);
            //function creates $categories_products_id_list as a global. Need to unset it here or products in each category are appended to array
            unset ($GLOBALS['categories_products_id_list']);

            if ($this->debug) {
                echo 'fn _copy ' . __LINE__;
            }
            if ($_POST['copy_products'] === 'copy_products_linked') {
                foreach ($products_to_copy as $key => $source_category) {
                    zen_link_product_to_category($key, $target_category_id);
                    $messageStack->add_session(sprintf(TEXT_COPY_CATEGORY_PRODUCTS_LINKED, zen_get_products_name($key), $key, zen_get_category_name($source_category), $source_category, zen_get_category_name($target_category_id), $target_category_id), 'success');
                }
            } else {//todo duplicate products:need to use copy_product_confirm in a loop
                echo '';//prevent inspection warning for empty statement
            }
        }
        $childs = $db->Execute('SELECT categories_id FROM ' . TABLE_CATEGORIES . ' WHERE parent_id = ' . $source_category_id);
        $child_ids = [];
        foreach ($childs as $child) {
            $child_ids[] = $child['categories_id'];
        }
        foreach ($child_ids as $child) {
            $this->_copy_categories_tree($child, $target_category_id);
        }
        if ($this->debug) {
            echo 'fn _copy_categories_tree END<br>';
        }
    }
}
