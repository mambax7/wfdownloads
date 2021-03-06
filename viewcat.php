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

use Xmf\Request;
use XoopsModules\Wfdownloads\{Common,
    Common\LetterChoice,
    DownloadHandler,
    Helper,
    Utility,
    ObjectTree
};

/** @var Helper $helper */
/** @var Utility $utility */

$currentFile = basename(__FILE__);
require_once __DIR__ . '/header.php';

/** @var \XoopsGroupPermHandler $grouppermHandler */
$grouppermHandler = xoops_getHandler('groupperm');

$cid   = Request::getInt('cid', 0);
$start = Request::getInt('start', 0);
//$list = Request::getString('letter', '', 'GET');
$list = Request::getString('list', '');
//$orderby = Request::getString('orderby', null);
$orderby = isset($_GET['orderby']) ? Utility::convertorderbyin($_GET['orderby']) : $helper->getConfig('filexorder');

$groups = is_object($GLOBALS['xoopsUser']) ? $GLOBALS['xoopsUser']->getGroups() : [0 => XOOPS_GROUP_ANONYMOUS];

// Check permissions
if (in_array(XOOPS_GROUP_ANONYMOUS, $groups)) {
    if (!$grouppermHandler->checkRight('WFDownCatPerm', $cid, $groups, $helper->getModule()->mid())) {
        redirect_header(XOOPS_URL . '/user.php', 3, _MD_WFDOWNLOADS_NEEDLOGINVIEW);
    }
} else {
    if (!$grouppermHandler->checkRight('WFDownCatPerm', $cid, $groups, $helper->getModule()->mid())) {
        redirect_header('index.php', 3, _NOPERM);
    }
}

// Check if submission is allowed
$isSubmissionAllowed = false;
if (is_object($GLOBALS['xoopsUser'])
    && (_WFDOWNLOADS_SUBMISSIONS_DOWNLOAD == $helper->getConfig('submissions')
        || _WFDOWNLOADS_SUBMISSIONS_BOTH == $helper->getConfig('submissions'))) {
    // if user is a registered user
    $groups = $GLOBALS['xoopsUser']->getGroups();
    if (count(array_intersect($helper->getConfig('submitarts'), $groups)) > 0) {
        $isSubmissionAllowed = true;
    }
} else {
    // if user is anonymous
    if (_WFDOWNLOADS_ANONPOST_DOWNLOAD == $helper->getConfig('anonpost')
        || _WFDOWNLOADS_ANONPOST_BOTH == $helper->getConfig('anonpost')) {
        $isSubmissionAllowed = true;
    }
}

// Get category object
$categoryObj = $helper->getHandler('Category')->get($cid);
if (null === $categoryObj) {
    redirect_header('index.php', 3, _CO_WFDOWNLOADS_ERROR_NOCATEGORY);
}

// Get download/upload permissions
$allowedDownCategoriesIds = $grouppermHandler->getItemIds('WFDownCatPerm', $groups, $helper->getModule()->mid());
$allowedUpCategoriesIds   = $grouppermHandler->getItemIds('WFUpCatPerm', $groups, $helper->getModule()->mid());

//$GLOBALS['xoopsOption']['template_main'] = "{$helper->getModule()->dirname()}_viewcat.tpl";
$GLOBALS['xoopsOption']['template_main'] = $helper->getDirname() . '_display_' . $helper->getConfig('idxcat_items_display_type') . '.tpl';
require_once XOOPS_ROOT_PATH . '/header.php';

$xoTheme->addScript(XOOPS_URL . '/browse.php?Frameworks/jquery/jquery.js');
$xoTheme->addScript(WFDOWNLOADS_URL . '/assets/js/magnific/jquery.magnific-popup.min.js');
$xoTheme->addStylesheet(WFDOWNLOADS_URL . '/assets/js/magnific/magnific-popup.css');
$xoTheme->addStylesheet(WFDOWNLOADS_URL . '/assets/css/module.css');

$xoopsTpl->assign('wfdownloads_url', WFDOWNLOADS_URL . '/');

$xoopsTpl->assign('cid', $cid); // this definition is not removed for backward compatibility issues
$xoopsTpl->assign('category_id', $cid); // this definition is not removed for backward compatibility issues
$xoopsTpl->assign('category_cid', $cid);

// Retreiving the top parent category
if (empty($list) && !isset($_GET['selectdate'])) {
    $categoriesTopParentByCid = $helper->getHandler('Category')->getAllSubcatsTopParentCid();
    $topCategoryObj           = $helper->getHandler('Category')->get(@$categoriesTopParentByCid[$cid]);

    $xoopsTpl->assign('topcategory_title', $topCategoryObj->getVar('title'));
    $xoopsTpl->assign('topcategory_image', $topCategoryObj->getVar('imgurl')); // this definition is not removed for backward compatibility issues
    $xoopsTpl->assign('topcategory_image_URL', $topCategoryObj->getVar('imgurl'));
    $xoopsTpl->assign('topcategory_cid', $topCategoryObj->getVar('cid'));
}

// Formulize module support (2006/05/04) jpc - start
if (Utility::checkModule('formulize')) {
    $formulize_fid = $categoryObj->getVar('formulize_fid');
    if ($formulize_fid) {
        $xoopsTpl->assign('custom_form', true);
    } else {
        $xoopsTpl->assign('custom_form', false);
    }
}
// Formulize module support (2006/05/04) jpc - end

// Generate Header

$showAlphabet = $helper->getConfig('showAlphabet');

if ($showAlphabet) {
    $helper->loadLanguage('common');
    $xoopsTpl->assign('letterChoiceTitle', constant('CO_' . $moduleDirNameUpper . '_' . 'BROWSETOTOPIC'));

    // ------------------- Letter Choice Start ---------------------------------------

    $catArray['imageheader'] = Utility::headerImage();
    //$catArray['letters']     = Utility::lettersChoice();
    $db                  = XoopsDatabaseFactory::getDatabaseConnection();
    $objHandler          = new DownloadHandler($db);
    $choicebyletter      = new LetterChoice($objHandler, null, null, range('a', 'z'), 'letter');
    $catarray['letters'] = $choicebyletter->render();
    $xoopsTpl->assign('catarray', $catarray);

    //$catArray['toolbar'] = Utility::toolbar();
    //$xoopsTpl->assign('catarray', $catArray);

    // ------------------- Letter Choice End ------------------------------------
}

$xoopsTpl->assign('categoryPath', $helper->getHandler('Category')->getNicePath($cid)); // this definition is not removed for backward compatibility issues
$xoopsTpl->assign('module_home', Utility::moduleHome(true)); // this definition is not removed for backward compatibility issues

// Get categories tree
$criteria = new CriteriaCompo();
$criteria->setSort('weight ASC, title');
$categoryObjs = $helper->getHandler('Category')->getObjects($criteria, true);
require_once XOOPS_ROOT_PATH . '/class/tree.php';
$categoryObjsTree = new ObjectTree($categoryObjs, 'cid', 'pid');

// Breadcrumb
$breadcrumb = new Common\Breadcrumb();
$breadcrumb->addLink($helper->getModule()->getVar('name'), WFDOWNLOADS_URL);
foreach (array_reverse($categoryObjsTree->getAllParent($cid)) as $parentCategoryObj) {
    $breadcrumb->addLink($parentCategoryObj->getVar('title'), 'viewcat.php?cid=' . $parentCategoryObj->getVar('cid'));
}
if ('' != $categoryObj->getVar('title')) {
    $breadcrumb->addLink($categoryObj->getVar('title'), '');
}
if (!empty($list)) {
    $breadcrumb->addLink($list, '');
}
$xoopsTpl->assign('wfdownloads_breadcrumb', $breadcrumb->render());

// Display Subcategories for selected Category
$allSubCategoryObjs = $categoryObjsTree->getFirstChild($cid);

if (is_array($allSubCategoryObjs) > 0 && !$list && !isset($_GET['selectdate'])) {
    $listings = Utility::getTotalDownloads($allowedDownCategoriesIds);
    $scount   = 1;
    foreach ($allSubCategoryObjs as $subCategoryObj) {
        $download_count = 0;
        // Check if subcategory is allowed
        if (!in_array($subCategoryObj->getVar('cid'), $allowedDownCategoriesIds)) {
            continue;
        }

        $infercategories    = [];
        $catdowncount       = $listings['count'][$subCategoryObj->getVar('cid')] ?? 0;
        $subsubCategoryObjs = $categoryObjsTree->getAllChild($subCategoryObj->getVar('cid'));

        // ----- added for subcat images -----
        if (('' !== $subCategoryObj->getVar('imgurl')) && is_file(XOOPS_ROOT_PATH . '/' . $helper->getConfig('catimage') . '/' . $subCategoryObj->getVar('imgurl'))) {
            if ($helper->getConfig('usethumbs') && function_exists('gd_info')) {
                $imageURL = Utility::createThumb(
                    $subCategoryObj->getVar('imgurl'),
                    $helper->getConfig('catimage'),
                    'thumbs',
                    $helper->getConfig('cat_imgwidth'),
                    $helper->getConfig('cat_imgheight'),
                    $helper->getConfig('imagequality'),
                    $helper->getConfig('updatethumbs'),
                    $helper->getConfig('keepaspect')
                );
            } else {
                $imageURL = XOOPS_URL . '/' . $helper->getConfig('catimage') . '/' . $subCategoryObj->getVar('imgurl');
            }
        } else {
            $imageURL = ''; //XOOPS_URL . '/' . $helper->getConfig('catimage') . '/blank.png';
        }
        // ----- end subcat images -----

        if (count($subsubCategoryObjs) > 0) {
            foreach ($subsubCategoryObjs as $subsubCategoryObj) {
                if (in_array($subsubCategoryObj->getVar('cid'), $allowedDownCategoriesIds)) {
                    $download_count    += $listings['count'][$subsubCategoryObj->getVar('cid')] ?? 0;
                    $infercategories[] = [
                        'cid'             => $subsubCategoryObj->getVar('cid'),
                        'id'              => $subsubCategoryObj->getVar('cid'), // this definition is not removed for backward compatibility issues
                        'title'           => $subsubCategoryObj->getVar('title'),
                        'image'           => $imageURL,
                        'image_URL'       => $imageURL,
                        'count'           => $download_count, // this definition is not removed for backward compatibility issues
                        'downloads_count' => $download_count,
                    ];
                }
            }
        } else {
            $download_count  = 0;
            $infercategories = [];
        }
        $catdowncount   += $download_count;
        $download_count = 0;

        $xoopsTpl->append(
            'subcategories',
            [
                'title'               => $subCategoryObj->getVar('title'),
                'image'               => $imageURL, // this definition is not removed for backward compatibility issues
                'image_URL'           => $imageURL,
                'id'                  => $subCategoryObj->getVar('cid'), // this definition is not removed for backward compatibility issues
                'cid'                 => $subCategoryObj->getVar('cid'),
                'allowed_download'    => in_array($subCategoryObj->getVar('cid'), $allowedDownCategoriesIds),
                'allowed_upload'      => $isSubmissionAllowed && in_array($subCategoryObj->getVar('cid'), $allowedUpCategoriesIds),
                'summary'             => $subCategoryObj->getVar('summary'),
                'infercategories'     => $infercategories,
                'subcategories'       => $infercategories,
                'totallinks'          => $catdowncount, // this definition is not removed for backward compatibility issues
                'downloads_count'     => $catdowncount,
                'count'               => $scount, // this definition is not removed for backward compatibility issues
                'subcategories_count' => $catdowncount,
            ]
        );
        ++$scount;
    }
}
if (isset($cid) && $cid > 0 && isset($categoryObjs[$cid])) {
    $xoopsTpl->assign('category_title', $categoryObjs[$cid]->getVar('title'));
    $xoopsTpl->assign('description', $categoryObjs[$cid]->getVar('description'));
    $xoopsTpl->assign('category_description', $categoryObjs[$cid]->getVar('description'));
    $xoopsTpl->assign('category_allowed_download', $isSubmissionAllowed && in_array($cid, $allowedDownCategoriesIds));
    $xoopsTpl->assign('category_allowed_upload', in_array($cid, $allowedUpCategoriesIds));

    // Making the category image and title available in the template
    if (('' !== $categoryObjs[$cid]->getVar('imgurl')) && is_file(XOOPS_ROOT_PATH . '/' . $helper->getConfig('catimage') . '/' . $categoryObjs[$cid]->getVar('imgurl'))) {
        if ($helper->getConfig('usethumbs') && function_exists('gd_info')) {
            $imageURL = Utility::createThumb(
                $categoryObjs[$cid]->getVar('imgurl'),
                $helper->getConfig('catimage'),
                'thumbs',
                $helper->getConfig('cat_imgwidth'),
                $helper->getConfig('cat_imgheight'),
                $helper->getConfig('imagequality'),
                $helper->getConfig('updatethumbs'),
                $helper->getConfig('keepaspect')
            );
        } else {
            $imageURL = XOOPS_URL . '/' . $helper->getConfig('catimage') . '/' . $categoryObjs[$cid]->getVar('imgurl');
        }
    } else {
        $imageURL = '';
    }

    if ($helper->getConfig('shortTitles')) {
        $xoopsTpl->assign('xoops_pagetitle', $categoryObjs[$cid]->getVar('title'));
    } else {
        $xoopsTpl->assign('xoops_pagetitle', $categoryObjs[$cid]->getVar('title') . ' | ' . $helper->getModule()->name());
    }
    $xoopsTpl->assign('category_image', $imageURL); // this definition is not removed for backward compatibility issues
    $xoopsTpl->assign('category_image_URL', $imageURL);
}

// Extract Download information from database
$xoopsTpl->assign('show_category_title', false);

if (Request::hasVar('selectdate', 'GET')) {
    $criteria->add(new Criteria('', 'TO_DAYS(FROM_UNIXTIME(' . Request::getInt('selectdate', 0, 'GET') . '))', '=', '', 'TO_DAYS(FROM_UNIXTIME(published))'));
    $xoopsTpl->assign('show_categort_title', true);
} elseif (!empty($list)) {
    $criteria->setSort("{$orderby}, title");
    $criteria->add(new Criteria('title', $myts->addSlashes($list) . '%', 'LIKE'));
    $xoopsTpl->assign('categoryPath', sprintf(_MD_WFDOWNLOADS_DOWNLOADS_LIST, htmlspecialchars($list, ENT_QUOTES | ENT_HTML5)));
    $xoopsTpl->assign('show_categort_title', true);
} else {
    $criteria->setSort("{$orderby}, title");
    $criteria->add(new Criteria('cid', $cid));
}

$downloads_count = $helper->getHandler('Download')->getActiveCount($criteria);
$criteria->setLimit($helper->getConfig('perpage'));
$criteria->setStart($start);
$downloadObjs = $helper->getHandler('Download')->getActiveDownloads($criteria);

// Show Downloads by file
if ($downloads_count > 0) {
    foreach ($downloadObjs as $downloadObj) {
        $downloadInfo = $downloadObj->getDownloadInfo();
        $xoopsTpl->assign('lang_dltimes', sprintf(_MD_WFDOWNLOADS_DLTIMES, $downloadInfo['hits']));
        $xoopsTpl->assign('lang_subdate', $downloadInfo['is_updated']);
        $xoopsTpl->append('file', $downloadInfo); // this definition is not removed for backward compatibility issues
        $xoopsTpl->append('downloads', $downloadInfo);
    }

    // Show order box
    $xoopsTpl->assign('show_links', false);
    if ($downloads_count > 1 && 0 != $cid) {
        $xoopsTpl->assign('show_links', true);
        $orderbyTrans = Utility::convertOrderByTrans($orderby);
        $xoopsTpl->assign('orderby', Utility::convertorderbyout($orderby));
        $xoopsTpl->assign('lang_cursortedby', sprintf(_MD_WFDOWNLOADS_CURSORTBY, Utility::convertOrderByTrans($orderby)));
        $orderby = Utility::convertorderbyout($orderby);
    }
    // Screenshots display
    $xoopsTpl->assign('show_screenshot', false);
    if (1 == $helper->getConfig('screenshot')) {
        $xoopsTpl->assign('shots_dir', $helper->getConfig('screenshots'));
        $xoopsTpl->assign('shotwidth', $helper->getConfig('shotwidth'));
        $xoopsTpl->assign('shotheight', $helper->getConfig('shotheight'));
        $xoopsTpl->assign('viewcat', true);
        $xoopsTpl->assign('show_screenshot', true);
    }

    // Nav page render
    require_once XOOPS_ROOT_PATH . '/class/pagenav.php';
    if (Request::hasVar('selectdate', 'GET')) {
        $pagenav = new XoopsPageNav($downloads_count, $helper->getConfig('perpage'), $start, 'start', 'list=' . urlencode($_GET['selectdate']));
    } elseif (!empty($list)) {
        $pagenav = new XoopsPageNav($downloads_count, $helper->getConfig('perpage'), $start, 'start', 'list=' . urlencode($list));
    } else {
        $pagenav = new XoopsPageNav($downloads_count, $helper->getConfig('perpage'), $start, 'start', 'cid=' . $cid);
    }
    $page_nav = $pagenav->renderNav();
    $xoopsTpl->assign('page_nav', isset($page_nav) && !empty($page_nav)); // this definition is not removed for backward compatibility issues
    $xoopsTpl->assign('pagenav', $pagenav->renderNav());
}

$xoopsTpl->assign('use_mirrors', $helper->getConfig('enable_mirrors'));
$xoopsTpl->assign('use_ratings', $helper->getConfig('enable_ratings'));
$xoopsTpl->assign('use_reviews', $helper->getConfig('enable_reviews'));
$xoopsTpl->assign('use_rss', $helper->getConfig('enablerss'));

if ((true == $helper->getConfig('enablerss')) && $downloads_count > 0) {
    $rsslink_URL = WFDOWNLOADS_URL . "/rss.php?cid={$cid}";
    $xoopsTpl->assign('category_rssfeed_URL', $rsslink_URL);
    $rsslink = "<a href='"
               . $rsslink_URL
               . "' title='"
               . _MD_WFDOWNLOADS_LEGENDTEXTCATRSS
               . "'><img src='"
               . XOOPS_URL
               . '/modules/'
               . $helper->getModule()->getVar('dirname')
               . "/assets/images/icon/rss.gif' border='0' alt='"
               . _MD_WFDOWNLOADS_LEGENDTEXTCATRSS
               . "' title='"
               . _MD_WFDOWNLOADS_LEGENDTEXTCATRSS
               . "'></a>";
    $xoopsTpl->assign('cat_rssfeed_link', $rsslink); // this definition is not removed for backward compatibility issues
}

require_once __DIR__ . '/footer.php';

?>
<script type="text/javascript">

    $('.magnific_zoom').magnificPopup({
        type: 'image',
        image: {
            cursor: 'mfp-zoom-out-cur',
            titleSrc: "title",
            verticalFit: true,
            tError: 'The image could not be loaded.' // Error message
        },
        iframe: {
            patterns: {
                youtube: {
                    index: 'youtube.com/',
                    id: 'v=',
                    src: '//www.youtube.com/embed/%id%?autoplay=1'
                }, vimeo: {
                    index: 'vimeo.com/',
                    id: '/',
                    src: '//player.vimeo.com/video/%id%?autoplay=1'
                }, gmaps: {
                    index: '//maps.google.',
                    src: '%id%&output=embed'
                }
            }
        },
        preloader: true,
        showCloseBtn: true,
        closeBtnInside: false,
        closeOnContentClick: true,
        closeOnBgClick: true,
        enableEscapeKey: true,
        modal: false,
        alignTop: false,
        mainClass: 'mfp-img-mobile mfp-fade',
        zoom: {
            enabled: true,
            duration: 300,
            easing: 'ease-in-out'
        },
        removalDelay: 200
    });
</script>
