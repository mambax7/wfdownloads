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
use XoopsModules\Wfdownloads\Helper;

defined('XOOPS_ROOT_PATH') || exit('XOOPS root path not defined');
require_once __DIR__ . '/common.php';

// comment callback functions

/**
 * @param $downloadId
 * @param $commentCount
 */
function wfdownloads_com_update($downloadId, $commentCount)
{
    $helper = Helper::getInstance();
    $helper->getHandler('Download')->updateAll('comments', (int)$commentCount, new Criteria('lid', (int)$downloadId));
}

/**
 * @param $comment
 */
function wfdownloads_com_approve(&$comment)
{
    // notification mail here
}
