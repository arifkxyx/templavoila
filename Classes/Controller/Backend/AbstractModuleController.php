<?php
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

namespace Schnitzler\Templavoila\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Schnitzler\Templavoila\Traits\BackendUser;
use Schnitzler\Templavoila\Traits\DatabaseConnection;
use Schnitzler\Templavoila\Traits\LanguageService;
use Schnitzler\Templavoila\Utility\PermissionUtility;
use TYPO3\CMS\Backend\Module\AbstractModule;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Class Schnitzler\Templavoila\Controller\Backend\AbstractModuleController
 */
abstract class AbstractModuleController extends AbstractModule
{
    const ACCESS_READ = 1;

    /**
     * @var StandaloneView
     */
    protected $view;

    /**
     * @var int
     */
    private $id;

    /**
     * @var array
     */
    private $settings;

    /**
     * @var array
     */
    private $typoScriptSetupCache = [];

    use BackendUser;
    use DatabaseConnection;
    use LanguageService;

    /**
     * Central Request Dispatcher
     *
     * @param ServerRequestInterface $request PSR7 Request Object
     * @param ResponseInterface $response PSR7 Response Object
     *
     * @return ResponseInterface
     *
     * @throws \InvalidArgumentException In case an action is not callable
     */
    public function processRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->id = (int) $request->getQueryParams()['id'];

        if ($request->getQueryParams()['updatePageTree']) {
            BackendUtility::setUpdateSignal('updatePageTree');
        }

        if ($this instanceof Configurable) {
            $defaultSettings = $this->getDefaultSettings();
            $userSettings = BackendUtility::getModuleData(
                $defaultSettings,
                $request->getQueryParams()['SET'] ?: [],
                static::getModuleName()
            );

            ArrayUtility::mergeRecursiveWithOverrule($defaultSettings, $userSettings);
            $this->settings = $defaultSettings;
        }

        return parent::processRequest($request, $response);
    }

    /**
     * @param int $flag
     *
     * @return bool
     */
    public function hasAccess()
    {
        if ($this->getId() === 0) {
            return false;
        }

        $pageRecord = BackendUtility::getRecordWSOL('pages', $this->getId());

        if (!is_array($pageRecord)) {
            return false;
        }

        return PermissionUtility::hasBasicEditRights('pages', $pageRecord);
    }

    /**
     * @param string $methodName
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function forward($methodName, ServerRequestInterface $request, ResponseInterface $response)
    {
        if (!is_callable([$this, $methodName])) {
            throw new \InvalidArgumentException(
                'The method "' . $methodName . '" is not callable within "' . get_class($this) . '".',
                1442736343
            );
        }
        return $this->{$methodName}($request, $response);
    }

    /**
     * @param ServerRequest $request
     * @param Response $response
     *
     * @return ResponseInterface
     */
    abstract public function index(ServerRequest $request, Response $response);

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getSetting($key)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : null;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function updateSetting($key, $value)
    {
        if ($this instanceof Configurable) {
            $this->settings = BackendUtility::getModuleData(
                $this->getDefaultSettings(),
                [$key => $value],
                static::getModuleName()
            );
        }
    }

    /**
     * @return array
     */
    protected function getCurrentUrlQueryParts()
    {
        $queries = [];
        parse_str(parse_url(GeneralUtility::getIndpEnv('REQUEST_URI'), PHP_URL_QUERY), $queries);

        return $queries;
    }

    /**
     * @param $templateName
     *
     * @return StandaloneView
     *
     * @throws \TYPO3\CMS\Fluid\View\Exception\InvalidTemplateResourceException
     * @throws \BadFunctionCallException
     * @throws \InvalidArgumentException
     */
    public function getStandaloneView($templateName)
    {
        $setup = $this->getTypoScriptSetup();

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths($setup['module.']['tx_templavoila.']['view.']['layoutRootPaths.']);
        $view->setTemplateRootPaths($setup['module.']['tx_templavoila.']['view.']['templateRootPaths.']);
        $view->setPartialRootPaths($setup['module.']['tx_templavoila.']['view.']['partialRootPaths.']);
        $view->setTemplate($templateName);
        $view->assign('settings', $this->settings);

        return $view;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return (int)$this->id;
    }

    /**
     * @return ModuleTemplate
     */
    public function getModuleTemplate()
    {
        return $this->moduleTemplate;
    }

    /**
     * @return array
     */
    public function getTypoScriptSetup()
    {
        if (!isset($this->typoScriptSetupCache[$this->getId()])) {
            $rootline = [];
            if ($this->getId() > 0) {
                /** @var $sysPage PageRepository */
                $sysPage = GeneralUtility::makeInstance(PageRepository::class);
                $rootline = $sysPage->getRootLine($this->getId(), '', true);
            }

            /** @var $template TemplateService */
            $template = GeneralUtility::makeInstance(TemplateService::class);
            $template->tt_track = 0;
            $template->setProcessExtensionStatics(true);
            $template->init();
            $template->runThroughTemplates($rootline, 0);
            $template->generateConfig();

            $this->typoScriptSetupCache[$this->getId()] = $template->setup;
        }

        return $this->typoScriptSetupCache[$this->getId()];
    }
}
