<?php
namespace CategoryBreadcrumb;

class CategoryBreadcrumb
{

    private static function checkTree(&$tree)
    {
        global $wgHiddenCategories;
        foreach ($tree as $key => &$value) {
            if (isset($wgHiddenCategories) && in_array(preg_replace('/.+\:/', '', $key), $wgHiddenCategories)) {
                unset($tree[$key]);
            }
            if (is_array($value)) {
                self::checkTree($value);
            }
        }
    }

    private static function checkParentCategory(&$tree)
    {
        global $wgShowBreadcrumbCategories;
        if (isset($wgShowBreadcrumbCategories)) {
            foreach ($tree as $category => $subtree) {
                $showCategory = false;
                $iterator  = new \RecursiveArrayIterator($subtree);
                $recursive = new \RecursiveIteratorIterator(
                    $iterator,
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($recursive as $key => $value) {
                    if (in_array(preg_replace('/.+\:/', '', $key), $wgShowBreadcrumbCategories)) {
                        $showCategory = true;
                        break;
                    }
                }
                if (!$showCategory) {
                    unset($tree[$category]);
                }
            }
        }
    }

    private static function getFlatTree($tree)
    {
        $flatTree = array();
        $iterator  = new \RecursiveArrayIterator($tree);
        $recursive = new \RecursiveIteratorIterator(
            $iterator,
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($recursive as $key => $value) {
            $flatTree[] = $key;
        }
        return $flatTree;
    }

    public static function main(&$sktemplate, &$tpl)
    {
        global $wgHiddenCategories;
        $title = $sktemplate->getTitle();

        if ($title == null) {
            return true;
        }

        // get category tree
        $parenttree = $title->getParentCategoryTree();
        self::checkParentCategory($parenttree);
        self::checkTree($parenttree);
        $flatTree = self::getFlatTree($parenttree);

        // Skin object passed by reference cause it can not be
        // accessed under the method subfunction drawCategoryBrowser
        $tempout = explode("\n", $sktemplate->drawCategoryBrowser($parenttree));

        // Clean out bogus first entry
        unset($tempout[0]);

        if (empty($tempout)) {
            return true;
        }

        asort($tempout);

        $breadcrumbs = '<div id="category-bread-crumbs">';
        foreach ($tempout as $line) {
            foreach ($flatTree as $category) {
                $line = str_replace(
                    ' ('.preg_replace('/.+\:/', '', $category).')',
                    '',
                    $line
                );
            }
            $breadcrumbs .= '<div>' . $line . '</div>';
        }
        $breadcrumbs .= '</div>';

        // append to the existing subtitle text;
        $tpl->set('subtitle', $tpl->data['subtitle'] . $breadcrumbs);

        return true;
    }
}
