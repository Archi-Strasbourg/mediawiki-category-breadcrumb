<?php

namespace CategoryBreadcrumb;

use MediaWiki\MediaWikiServices;
use Skin;
use Title;

class CategoryBreadcrumb
{
    public static function checkTree(&$tree)
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

    public static function getDuplicates(&$tree)
    {
        $catList = [];
        $duplicates = [];
        foreach ($tree as $category => $subtree) {
            $iterator = new \RecursiveArrayIterator($subtree);
            $recursive = new \RecursiveIteratorIterator(
                $iterator,
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($recursive as $key => $value) {
                $catList[$category][] = $key;
            }
        }
        $movedCats = [];
        foreach ($catList as $category => $flatTree) {
            foreach ($catList as $otherCategory => $otherFlatTree) {
                if (!in_array($category, $movedCats) && $category != $otherCategory) {
                    if ($flatTree == $otherFlatTree) {
                        $duplicates[$category][] = $otherCategory;
                        $movedCats[] = $otherCategory;
                        unset($tree[$otherCategory]);
                    }
                }
            }
        }

        return $duplicates;
    }

    public static function checkParentCategory(&$tree)
    {
        global $wgShowBreadcrumbCategories;
        if (isset($wgShowBreadcrumbCategories)) {
            foreach ($tree as $category => $subtree) {
                $showCategory = false;
                $iterator = new \RecursiveArrayIterator($subtree);
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

    public static function getFlatTree($tree)
    {
        $flatTree = [];
        $recursive = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($tree),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($recursive as $key => $value) {
            $flatTree[] = $key;
        }

        return $flatTree;
    }

    /**
     * Duplicated method because it is protected.
     *
     * @param Skin $sktemplate
     * @param array $tree
     * @return string
     *
     * @see Skin::drawCategoryBrowser()
     */
    protected static function drawCategoryBrowser(Skin $sktemplate, array $tree): string
    {
        $return = '';
        $linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

        foreach ($tree as $element => $parent) {
            if (empty($parent)) {
                $return .= "\n";
            } else {
                $return .= self::drawCategoryBrowser($sktemplate, $parent) . ' &gt; ';
            }

            $eltitle = Title::newFromText($element);
            $return .= $linkRenderer->makeLink($eltitle, $eltitle->getText());
        }

        return $return;
    }

    /**
     * @param Skin $sktemplate
     * @param $tpl
     * @return bool
     */
    public static function main(Skin &$sktemplate, &$tpl): bool
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
        $duplicates = self::getDuplicates($parenttree);
        $flatTree = self::getFlatTree($parenttree);

        // Skin object passed by reference cause it can not be
        // accessed under the method subfunction drawCategoryBrowser
        $tempout = explode("\n", self::drawCategoryBrowser($sktemplate, $parenttree));

        // Clean out bogus first entry
        unset($tempout[0]);

        if (empty($tempout)) {
            return true;
        }

        asort($tempout);

        $breadcrumbs = '<div id="category-bread-crumbs">';
        foreach ($tempout as $i => $line) {
            $curCat = array_keys($parenttree)[$i - 1];
            if (isset($duplicates[$curCat])) {
                foreach ($duplicates[$curCat] as $duplicate) {
                    $eltitle = Title::newFromText($duplicate);
                    $line .= '|'.\Linker::link($eltitle, htmlspecialchars($eltitle->getText()));
                }
            }
            foreach ($flatTree as $category) {
                $shortCat = preg_replace('/.+\:/', '', $category);
                if (count($flatTree) >= 5 && preg_replace('/_\(.*\)/', '', $shortCat) == 'Autre') {
                    $escapedShortCat = str_replace('_', ' ', $shortCat);
                    $line = str_replace('>'.$escapedShortCat, ' style="display:none;">'.$escapedShortCat, $line);
                    $line = str_replace($escapedShortCat.'</a> &gt;', $escapedShortCat.'</a> <span style="display:none;">&gt;</span>', $line);
                }
            }
            foreach ($flatTree as $category) {
                $shortCat = preg_replace('/.+\:/', '', $category);
                $line = str_replace(
                    ' ('.preg_replace('/_\(.+\)/', '', $shortCat).')',
                    '',
                    $line
                );
            }
            $breadcrumbs .= '<div>'.$line.'</div>';
        }
        $breadcrumbs .= '</div>';

        // append to the existing subtitle text;
        $tpl->set('subtitle', $tpl->data['subtitle'].$breadcrumbs);

        return true;
    }
}
