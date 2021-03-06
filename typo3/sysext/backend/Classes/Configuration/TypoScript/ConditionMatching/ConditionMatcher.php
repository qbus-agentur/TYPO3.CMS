<?php
namespace TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\TypoScript\ConditionMatching\AbstractConditionMatcher;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Matching TypoScript conditions for backend disposal.
 *
 * Used with the TypoScript parser.
 * Matches browserinfo, IPnumbers for use with templates
 */
class ConditionMatcher extends AbstractConditionMatcher
{
    /**
     * @var Context
     */
    protected $context;

    public function __construct(Context $context = null)
    {
        $pageId = $this->pageId ?? $this->determinePageId();

        $this->context = $context ?? GeneralUtility::makeInstance(Context::class);
        $this->rootline = BackendUtility::BEgetRootLine($pageId, '', true) ?? [];
        $treeLevel = $this->rootline ? count($this->rootline) - 1 : 0;
        $tree = new \stdClass();
        $tree->level = $treeLevel;
        $tree->rootLine = $this->rootline;
        $tree->rootLineIds = array_column($this->rootline, 'uid');

        $backendUserAspect = $this->context->getAspect('backend.user');
        $backend = new \stdClass();
        $backend->user = new \stdClass();
        $backend->user->isAdmin = $backendUserAspect->get('isAdmin') ?? false;
        $backend->user->isLoggedIn = $backendUserAspect->get('isLoggedIn') ?? false;
        $backend->user->userId = $backendUserAspect->get('id') ?? 0;
        $backend->user->userGroupList = implode(',', $backendUserAspect->get('groupIds'));

        $this->expressionLanguageResolver = GeneralUtility::makeInstance(
            Resolver::class,
            'typoscript',
            [
                'tree' => $tree,
                'backend' => $backend,
                'page' => BackendUtility::getRecord('pages', $pageId) ?? [],
            ]
        );
    }

    /**
     * Tries to determine the ID of the page currently processed.
     * When User/Group TS-Config is parsed when no specific page is handled
     * (i.e. in the Extension Manager, etc.) this function will return "0", so that
     * the accordant conditions (e.g. PIDinRootline) will return "FALSE"
     *
     * @return int The determined page id or otherwise 0
     * @deprecated since TYPO3 v9.4, will be removed in TYPO3 v10.0.
     */
    protected function determinePageId()
    {
        $pageId = 0;
        $editStatement = GeneralUtility::_GP('edit');
        $commandStatement = GeneralUtility::_GP('cmd');
        // Determine id from module that was called with an id:
        if ($id = (int)GeneralUtility::_GP('id')) {
            $pageId = $id;
        } elseif (is_array($editStatement)) {
            $table = key($editStatement);
            $uidAndAction = current($editStatement);
            $uid = key($uidAndAction);
            $action = current($uidAndAction);
            if ($action === 'edit') {
                $pageId = $this->getPageIdByRecord($table, $uid);
            } elseif ($action === 'new') {
                $pageId = $this->getPageIdByRecord($table, $uid, true);
            }
        } elseif (is_array($commandStatement)) {
            $table = key($commandStatement);
            $uidActionAndTarget = current($commandStatement);
            $uid = key($uidActionAndTarget);
            $actionAndTarget = current($uidActionAndTarget);
            $action = key($actionAndTarget);
            $target = current($actionAndTarget);
            if ($action === 'delete') {
                $pageId = $this->getPageIdByRecord($table, $uid);
            } elseif ($action === 'copy' || $action === 'move') {
                $pageId = $this->getPageIdByRecord($table, $target, true);
            }
        }
        return $pageId;
    }

    /**
     * Gets the page id by a record.
     *
     * @param string $table Name of the table
     * @param int $id Id of the accordant record
     * @param bool $ignoreTable Whether to ignore the page, if TRUE a positive
     * @return int Id of the page the record is persisted on
     * @deprecated since TYPO3 v9.4, will be removed in TYPO3 v10.0.
     */
    protected function getPageIdByRecord($table, $id, $ignoreTable = false)
    {
        $pageId = 0;
        $id = (int)$id;
        if ($table && $id) {
            if (($ignoreTable || $table === 'pages') && $id >= 0) {
                $pageId = $id;
            } else {
                $record = BackendUtility::getRecordWSOL($table, abs($id), '*', '', false);
                $pageId = $record['pid'];
            }
        }
        return $pageId;
    }
}
