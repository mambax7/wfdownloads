<?php

namespace XoopsModules\Wfdownloads;

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

require_once \dirname(__DIR__) . '/include/common.php';

/**
 * Class DownloadHandler
 */
class DownloadHandler extends \XoopsPersistableObjectHandler
{
    /**
     * @access public
     */
    public $helper;

    /**
     * @param null|\XoopsDatabase $db
     */
    public function __construct(\XoopsDatabase $db = null)
    {
        parent::__construct($db, 'wfdownloads_downloads', Download::class, 'lid', 'title');
        /** @var \XoopsModules\Wfdownloads\Helper $this ->helper */
        $this->helper = Helper::getInstance();
    }

    /**
     * Get maximum published date from a criteria
     *
     * @param \CriteriaElement|null $criteria
     *
     * @return mixed
     */
    public function getMaxPublishdate(\CriteriaElement $criteria = null)
    {
        $field = '';
        $groupby = false;
        if (null !== $criteria && $criteria instanceof \CriteriaElement) {
            if ('' != $criteria->groupby) {
                $groupby = true;
                $field   = $criteria->groupby . ', '; //Not entirely secure unless you KNOW that no criteria's groupby clause is going to be mis-used
            }
        }
        $sql = 'SELECT ' . $field . 'MAX(published) FROM ' . $this->table;
        if (\is_object($criteria)) {
            $sql .= ' ' . $criteria->renderWhere();
            if ('' != $criteria->groupby) {
                $sql .= $criteria->getGroupby();
            }
        }
        $result = $this->db->query($sql);
        if (!$result) {
            return 0;
        }
        if (!$groupby) {
            [$count] = $this->db->fetchRow($result);

            return $count;
        }
        $ret = [];
        while (list($id, $count) = $this->db->fetchRow($result)) {
            $ret[$id] = $count;
        }

        return $ret;
    }

    /**
     * Get criteria for active downloads
     *
     * @return \CriteriaCompo
     */
    public function getActiveCriteria()
    {
        /** @var \XoopsGroupPermHandler $grouppermHandler */
        $grouppermHandler = \xoops_getHandler('groupperm');

        $criteria = new \CriteriaCompo(new \Criteria('offline', false));
        $criteria->add(new \Criteria('published', 0, '>'));
        $criteria->add(new \Criteria('published', \time(), '<='));
        $expiredCriteria = new \CriteriaCompo(new \Criteria('expired', 0));
        $expiredCriteria->add(new \Criteria('expired', \time(), '>='), 'OR');
        $criteria->add($expiredCriteria);
        // add criteria for categories that the user has permissions for
        $groups                   = \is_object($GLOBALS['xoopsUser']) ? $GLOBALS['xoopsUser']->getGroups() : [0 => XOOPS_GROUP_ANONYMOUS];
        $allowedDownCategoriesIds = $grouppermHandler->getItemIds('WFDownCatPerm', $groups, $this->helper->getModule()->mid());
        $criteria->add(new \Criteria('cid', '(' . \implode(',', $allowedDownCategoriesIds) . ')', 'IN'));

        return $criteria;
    }

    /**
     * Get array of active downloads with optional additional criteria
     *
     * @param \CriteriaCompo|null $crit Additional criteria
     *
     * @return array
     */
    public function getActiveDownloads(\CriteriaCompo $crit = null)
    {
        if (\is_object($crit)) {
            $criteria = $crit;
        } else {
            $criteria = new \CriteriaCompo();
        }
        $active_crit = $this->getActiveCriteria();
        $criteria->add($active_crit);

        return $this->getObjects($criteria);
    }

    /**
     * Get count of active downloads
     *
     * @param \CriteriaElement|null $crit Additional criteria
     *
     * @return int /int
     */
    public function getActiveCount(\CriteriaElement $crit = null)
    {
        $criteria = $this->getActiveCriteria();
        if (\is_object($crit)) {
            $criteria->add($crit);
        }

        return $this->getCount($criteria);
    }

    /**
     * Increment hit counter for a download
     *
     * @param int $lid
     *
     * @return bool
     */
    public function incrementHits($lid)
    {
        $sql = 'UPDATE ' . $this->table . " SET hits=hits+1 WHERE lid='" . (int)$lid . "'";

        return $this->db->queryF($sql);
    }

    /**
     * @param \XoopsObject $download
     * @param bool         $force
     *
     * @return bool
     */
    public function delete(\XoopsObject $download, $force = false)
    {
        if (parent::delete($download, $force)) {
            $criteria = new \Criteria('lid', (int)$download->getVar('lid'));
            $this->helper->getHandler('Rating')->deleteAll($criteria);
            $this->helper->getHandler('Mirror')->deleteAll($criteria);
            $this->helper->getHandler('Review')->deleteAll($criteria);
            $this->helper->getHandler('Report')->deleteAll($criteria);
            // delete comments
            \xoops_comment_delete((int)$this->helper->getModule()->mid(), (int)$download->getVar('lid'));

            // Formulize module support (2006/05/04) jpc - start
            if (Utility::checkModule('formulize')) {
                if (\is_file(XOOPS_ROOT_PATH . '/modules/formulize/include/functions.php') && $download->getVar('formulize_idreq') > 0) {
                    require_once XOOPS_ROOT_PATH . '/modules/formulize/include/functions.php';
                    //deleteFormEntries(array($download->getVar('formulize_idreq')));
                    $category = $this->helper->getHandler('Category')->get($download->getVar('cid'));
                    deleteFormEntries([$download->getVar('formulize_idreq')], $category->getVar('formulize_fid'));
                }
            }

            // Formulize module support (2006/05/04) jpc - end
            return true;
        }

        return false;
    }
}
