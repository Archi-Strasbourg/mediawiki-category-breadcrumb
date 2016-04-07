<?php
namespace CategoryBreadcrumb;

class CategoryBreadcrumb
{
    public static function main(&$sktemplate, &$tpl)
    {
        $title = $sktemplate->getTitle();

        if ($title == null) {
            return true;
        }

        // get category tree
        $parenttree = $title->getParentCategoryTree();

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
            $breadcrumbs .= '<div>' . $line . '</div>';
        }
        $breadcrumbs .= '</div>';

        // append to the existing subtitle text;
        $tpl->set('subtitle', $tpl->data['subtitle'] . $breadcrumbs);

        return true;
    }
}
