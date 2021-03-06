<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Wfdownloads module
 *
 * @copyright       XOOPS Project (https://xoops.org)
 * @license         GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package         wfdownload
 * @since           3.23
 * @author          Xoops Development Team
 */

use XoopsModules\Wfdownloads;
use XoopsModules\Wfdownloads\{
    Helper,
    Utility
};
/** @var Helper $helper */
/** @var Utility $utility */

defined('XOOPS_ROOT_PATH') || exit('XOOPS root path not defined');
require_once __DIR__ . '/common.php';
/**
 * @param        $queryArray
 * @param        $andor
 * @param        $limit
 * @param        $offset
 * @param int    $userId
 * @param array  $categories
 * @param int    $sortBy
 * @param string $searchIn
 * @param string $extra
 *
 * @return array
 */
function wfdownloads_search($queryArray, $andor, $limit, $offset, $userId = 0, $categories = [], $sortBy = 0, $searchIn = '', $extra = '')
{
    $helper = Helper::getInstance();

    $userGroups = is_object($GLOBALS['xoopsUser']) ? $GLOBALS['xoopsUser']->getGroups() : [0 => XOOPS_GROUP_ANONYMOUS];

    /** @var \XoopsGroupPermHandler $grouppermHandler */
    $grouppermHandler         = xoops_getHandler('groupperm');
    $allowedDownCategoriesIds = $grouppermHandler->getItemIds('WFDownCatPerm', $userGroups, $helper->getModule()->mid());
    $downloads_lids           = $downloads_intersect = [];
    $criteria                 = new CriteriaCompo(new Criteria('cid', '(' . implode(',', $allowedDownCategoriesIds) . ')', 'IN'));
    if (0 != $userId) {
        $criteria->add(new Criteria('submitter', (int)$userId));
    }

    // changed and added - start - April 23, 2006 - jwe
    // moved these up here since we need to complete the $criteria object a little sooner now
    $criteria->setSort('published');
    $criteria->setOrder('DESC');

    // because count() returns 1 even if a supplied variable
    // is not an array, we must check if $querryarray is really an array
    $queryArray_count = 0;
    if ((is_array($queryArray) && $queryArray_count = count($queryArray)) || 0 != $userId) {
        // $userId != 0 added August 13 2007 -- ACCOUNTS FOR CASES WHERE THERE ARE NO QUERY TERMS BUT A USER ID IS PASSED -- FREEFORM SOLUTIONS
        if (0 == $queryArray_count) {
            $queryArray_count = 1; // AUGUST 13 2007 -- MAKE COUNT EQUAL 1 SINCE WE HAVE TO DO AT LEAST ONE SEARCH (BASED ON USER ID) EVEN IF THERE ARE NO QUERY TERMS -- FREEFORM SOLUTIONS
            $queryArray       = [];
        }

        // Formulize module support - jpc - start
        /*
                // queryarray[0] now handled inside loop -- perhaps this "0 out of loop, 1 and up inside loop" approach was an unsuccessful attempt to fix the "unset" bug.  Interesting that subcrit was unset prior to the FOR loop.
                $subCriteria = new \CriteriaCompo(new \Criteria("title", "%".$queryArray[0]."%", 'LIKE'), 'OR');
                $subCriteria->add(new \Criteria("description", "%".$queryArray[0]."%", 'LIKE'), 'OR');
                $criteria->add($subCriteria);
                unset($subCriteria);

                $allSubCriterias = new \CriteriaCompo(); // added to fix bug related to nesting of ( )
                for ($i = 0;$i < $queryArray_count;++$i) { // 1 changed to 0 so everything happens in one loop now
                    $subCriteria = new \CriteriaCompo(new \Criteria("title", "%".$queryArray[$i]."%", 'LIKE'), 'OR');
                    $subCriteria->add(new \Criteria("description", "%".$queryArray[$i]."%", 'LIKE'), 'OR');
                    $allSubCriterias->add($subCriteria, $andor); // $criteria changed to $allSubCriterias to fix bug
                    unset($subCriteria); // added to fix bug
                }
                $criteria->add($allSubCriterias); // added to fix bug

        There are two bugs in the above code: all subcrits need to be added to the main
        criteria as a group, so it was a bug to have them added one at a time as they
        were, since the nesting of the () in the rendered where clause is incorrect also
        there was a bug which caused only the first and last items in the query array to
        be processed, and the last item would be processed multiple times.  ie: terms
        "green, orange, black" resulted in a search for "green, black, black" -- this
        "bug" was introduced with php 4.4.0 (and some version of 5 as well) after a
        change in how objects are managed in memory or something like that.
        The fix is to specifically unset the $subCriteria object at the end of each
        iteration of the loop. The same bug hit Liase, Formulaire and Formulize.
        You can see the structure of the query by printing the output of $criteria's
        render or renderWhere method ( ie: print $criteria->renderWhere(); )
        However, the whole approach to handling queries has been changed, so the above
        code is unused.  It is included here for reference with regard to the bugs
        mentioned above.
        With custom forms, because a multi term query using AND could have some terms
        match only custom form fields and other terms match only native Wfdownloads
        fields, each term must be evaluated independently,
        across both modules, and then if an AND operator is in effect, the intersection
        of the results is returned.  If OR is in effect, then all results are returned.
        */
        // Determine what the custom forms are that need searching, if any
        if (Utility::checkModule('formulize')) {
            $fids = [];
            foreach ($allowedDownCategoriesIds as $cid) {
                $categoryObj = $helper->getHandler('Category')->get($cid);
                if (null !== $categoryObj && $fid = $categoryObj->getVar('formulize_fid')) {
                    $fids[] = $fid;
                }
            }

            // Set criteria for the captions that the user can see if necessary
            if ($fids && is_array($fids)) {
                $formulizeElementCriteria = new CriteriaCompo();
                $formulizeElementCriteria->add(new Criteria('ele_display', 1), 'OR');
                foreach ($userGroups as $group) {
                    $formulizeElementCriteria->add(new Criteria('ele_display', '%,' . $group . ',%', 'LIKE'), 'OR');
                }
                $formulizeElementCriteria->setSort('ele_order');
                $formulizeElementCriteria->setOrder('ASC');
            }
        }

        $downloadObjs = [];
        // Loop through all query terms
        foreach ($queryArray as $i => $iValue) {
            // Make a copy of the $criteria for use with this term only
            $queryCriteria = clone $criteria;

            // Setup criteria for searching the title and description fields of Wfdownloads for the current term
            $allSubCriterias = new CriteriaCompo();
            $thisSearchTerm  = count($queryArray) > 0 ? $queryArray[$i] : '';
            $subCriteria     = new CriteriaCompo();
            $subCriteria->add(new Criteria('title', '%' . $thisSearchTerm . '%', 'LIKE'), 'OR'); // search in title field
            $subCriteria->add(new Criteria('description', '%' . $thisSearchTerm . '%', 'LIKE'), 'OR'); // search in description fiels
            $allSubCriterias->add($subCriteria, $andor);
            unset($subCriteria);

            $saved_ids = [];

            // Find all IDs of entries in all custom forms which match the current term
            if (Utility::checkModule('formulize')) {
                foreach ($fids as $fid) {
                    if (null === $formulizeElementsHandler) {
                        $formulizeElementsHandler = $helper->getHandler('Elements', 'formulize');
                    }
                    require_once XOOPS_ROOT_PATH . '/modules/formulize/include/extract.php';
                    // Setup the filter string based on the elements in the form and the current query term
                    $formulizeElements = $formulizeElementsHandler->getObjects2($formulizeElementCriteria, $fid);
                    $filter_string     = '';
                    $indexer           = 0;
                    $start             = 1;
                    foreach ($formulizeElements as $formulizeElement) {
                        if ($start) {
                            $filter_string = $formulizeElement->getVar('ele_id') . '/**/' . $iValue;
                            $start         = 0;
                        } else {
                            $filter_string .= '][' . $formulizeElement->getVar('ele_id') . '/**/' . $iValue;
                        }
                    }
                    unset($formulizeElements);

                    // Query for the ids of the records in the form that match the queryarray
                    $data           = getData('', $fid, $filter_string, 'OR'); // is a 'formulize' function
                    $formHandle     = getFormHandleFromEntry($data[0], 'uid'); // is a 'formulize' function
                    $temp_saved_ids = [];
                    foreach ($data as $entry) {
                        // Gather all IDs for this $fid
                        $found_ids      = internalRecordIds($entry, $formHandle); // is a 'formulize' function
                        $temp_saved_ids = array_merge($temp_saved_ids, $found_ids);
                        unset($found_ids);
                    }
                    $saved_ids = array_merge($saved_ids, $temp_saved_ids); // merge this $fid's IDs with IDs from all previous $fids
                    unset($temp_saved_ids, $data);
                } // end of foreach $fids
            }
            // Formulize module support - jpc - end
            // Make a criteria object that includes the custom form ids that were found, if any
            if (count($saved_ids) > 0 && is_array($saved_ids)) {
                $subs_plus_custom = new CriteriaCompo(new Criteria('formulize_idreq', '(' . implode(',', $saved_ids) . ')', 'IN'));
                $subs_plus_custom->add($allSubCriterias, 'OR');
                $queryCriteria->add($subs_plus_custom);
                unset($allSubCriterias, $subs_plus_custom, $saved_ids);
            } else {
                $queryCriteria->add($allSubCriterias);
                unset($allSubCriterias);
            }

            // Check to see if this term matches any files
            $tempDownloadObjs = $helper->getHandler('Download')->getActiveDownloads($queryCriteria);
            unset($queryCriteria);

            // Make an array of the downloads based on the lid, and a separate list of all the lids found (the separate list is used in the case of an AND operator to derive an intersection of the hits across all search terms -- and it is used to determine the start and limit points of the main results array for an OR query)
            foreach ($tempDownloadObjs as $tempDownloadObj) {
                $downloadObjs[(int)$tempDownloadObj->getVar('lid')] = $tempDownloadObj;
                $downloads_lids[]                                   = (int)$tempDownloadObj->getVar('lid');
            }

            // Do an intersection of the found lids if the operator is AND
            if ('AND' === $andor) {
                if (null === $downloads_lids) {
                    $downloads_lids[] = '';
                }
                if (null === $downloads_intersect) {
                    $downloads_intersect = $downloads_lids;
                } // first time through initialize the array with all the found files
                $downloads_intersect = array_intersect($downloads_intersect, $downloads_lids);
                unset($downloads_lids);
            }
            unset($tempDownloadObjs);
        } // end of for loop through query terms
    } // end of if there are query terms

    // If an AND operator was used, cull the $downloadObjs array based on the intersection found
    if ('AND' === $andor) {
        foreach ($downloadObjs as $lid => $downloadObj) {
            if (!in_array($lid, $downloads_intersect)) {
                unset($downloadObjs[$lid]);
            }
        }
        $limitOffsetIndex = $downloads_intersect;
    } else {
        $limitOffsetIndex = $downloads_lids;
    }

    $ret        = [];
    $i          = 0;
    $storedLids = [];

    // foreach (array_keys($downloadObjs) as $i)
    if (is_array($limitOffsetIndex)) {
        $counter = count($limitOffsetIndex);
        for ($x = $offset; $i < $limit && $x < $counter; ++$x) {
            $lid = $limitOffsetIndex[$x];
            $obj = $downloadObjs[$lid];
            if (is_object($obj) && !isset($storedLids[$lid])) {
                $storedLids[$lid] = true;
                $ret[$i]['image'] = 'assets/images/size2.gif';
                $ret[$i]['link']  = "singlefile.php?cid={$obj->getVar('cid')}&amp;lid={$lid}";
                $ret[$i]['title'] = $obj->getVar('title');
                $ret[$i]['time']  = $obj->getVar('published');
                $ret[$i]['uid']   = $obj->getVar('submitter');
                ++$i;
            }
        }
    }

    /*
        // Swish-e support EXPERIMENTAL
        if (($helper->getConfig('enable_swishe') === true) && Utility::checkSwishe() === true) {
    // IN PROGRESS
            $swisheCriteria = new \CriteriaCompo(new \Criteria('cid', '(' . implode(',', $allowedDownCategoriesIds) . ')', 'IN'));
            if ($userId != 0) {
                $swisheCriteria->add(new \Criteria('submitter', (int) $userId));
            }
            if ($andor = 'AND') {
                $swisheQueryWords = implode (' AND ', $queryArray);
            } elseif ($andor = 'OR') {
                $swisheQueryWords = implode (' OR ', $queryArray);
            } else {
                $swisheQueryWords = '';
            }
            if (strlen($swisheQueryWords) > 0) {
                $swisheSearchResults = Utility::searchSwishe($swisheQueryWords);
                foreach ($swisheSearchResults as $swisheSearchResult) {
                    $tempSwisheCriteria = clone($swisheCriteria);
                    $tempSwisheCriteria->add(new \Criteria('filename', $swisheSearchResult['file_path']));
                    $tempDownloadObjs = $helper->getHandler('Download')->getActiveDownloads($tempSwisheCriteria);
                    $tempDownloadObj = $tempDownloadObjs[0];
                    if (is_object($tempDownloadObj)) {
                        $tempRet['image'] = "assets/images/size2.gif";
                        $tempRet['link'] = "singlefile.php?cid={$tempDownloadObj->getVar('cid')}&amp;lid={$tempDownloadObj->getVar('lid')}";
                        $tempRet['title'] = $tempDownloadObj->getVar('title');
                        $tempRet['time'] = $tempDownloadObj->getVar('published');
                        $tempRet['uid'] = $tempDownloadObj->getVar('submitter');
    // IN PROGRESS
                    }
                }
            }
        }
        // Swish-e support EXPERIMENTAL
    */

    return $ret;
}
