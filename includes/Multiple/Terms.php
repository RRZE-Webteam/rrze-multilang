<?php

namespace RRZE\Multilang\Multiple;

use RRZE\Multilang\Functions;

defined('ABSPATH') || exit;

class Terms
{
    public static function getObjectsOfPostCategories($postId, $postType)
    {
        return self::getPostTaxonomyTerms($postId, true, false);
    }

    public static function getPostTaxonomyTerms($postId, $categoryOnly, $targetId)
    {
        $sourceTaxonomyTermsObj = [];

        $postTaxonomies = get_object_taxonomies(get_post_type($postId), 'names');

        foreach ($postTaxonomies as $postTaxonomy) {

            if ($postTaxonomy == 'post_tag') {
                continue;
            }
            if (($postTaxonomy == 'category' && $categoryOnly) || ($postTaxonomy != 'category' && !$categoryOnly)) {

                $postTerms = wp_get_post_terms($postId, $postTaxonomy);

                if (self::hasParentTerms($postTerms)) {
                    $allTerms = get_terms($postTaxonomy, [
                        'type' => get_post_type($postId),
                        'hide_empty' => 0
                    ]);
                } else {
                    $allTerms = null;
                }

                $sourceTaxonomyTermsObj[$postTaxonomy] = [$postTerms, $allTerms];
            }
        }

        return apply_filters('rrze_multilang_post_taxonomy_terms', $sourceTaxonomyTermsObj, $targetId);
    }

    public static function hasParentTerms($terms)
    {
        foreach ($terms as $term) {
            if ($term->parent != 0) {
                return true;
            }
        }
        return false;
    }

    public static function setTargetCategories($postId, $sourceCategories, $postType)
    {
        self::setPostTaxonomyTerms($postId, $sourceCategories);
        return;
    }

    public static function setPostTaxonomyTerms($postId, $sourceTaxonomyTermsObj)
    {

        foreach ($sourceTaxonomyTermsObj as $tax => &$taxData) {

            $origPostTerms = $taxData[0];

            $origAllTerms = array_key_exists(1, $taxData) ? $taxData[1] : [];

            $allTerms = get_terms($tax, [
                'type' => get_post_type($postId),
                'hide_empty' => 0
            ]);

            $origAllTermsById = &Functions::hashObjBy($origAllTerms, 'term_id');
            $allTermsBySlug = &Functions::hashObjBy($allTerms, 'slug');

            $targetPostTermIds = [];

            foreach ($origPostTerms as &$postTerm) {

                array_push($targetPostTermIds, self::addTermRecursively($postTerm, $origAllTermsById, $allTermsBySlug));
            }

            unset($postTerm);

            wp_set_object_terms($postId, $targetPostTermIds, $tax);
        }

        unset($taxData);
    }

    public static function addTermRecursively($postTerm, &$origAllTermsById, &$allTermsBySlug)
    {

        if (array_key_exists($postTerm->slug, $allTermsBySlug)) {

            return $allTermsBySlug[$postTerm->slug]->term_id;
        }

        if ($postTerm->parent != 0) {

            $parentId = self::addTermRecursively($origAllTermsById[$postTerm->parent], $origAllTermsById, $allTermsBySlug);
        } else {

            $parentId = 0;
        }

        $new_term = wp_insert_term($postTerm->name, $postTerm->taxonomy, [
            'description' => $postTerm->description,
            'slug' => $postTerm->slug,
            'parent' => $parentId
        ]);

        $allTermsBySlug[$postTerm->slug] = (object) $new_term;

        return $new_term['term_id'];
    }
}
