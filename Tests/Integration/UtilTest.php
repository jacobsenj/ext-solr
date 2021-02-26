<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Core\Context\Context;

class UtilTest extends IntegrationTest
{
    public function setUp()
    {
        parent::setUp();

        if (Util::getIsTYPO3VersionBelow10()) {

            /* @var CacheManager|ObjectProphecy $cacheManager */
            $cacheManager = $this->prophesize(CacheManager::class);
            /* @var VariableFrontend|ObjectProphecy $frontendCache */
            $frontendCache = $this->prophesize(VariableFrontend::class);
            $cacheManager
                ->getCache('cache_pages')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('cache_runtime')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('cache_hash')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('cache_core')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('cache_rootline')
                ->willReturn($frontendCache->reveal());
            $cacheManager
                ->getCache('tx_solr_configuration')
                ->willReturn($frontendCache->reveal());
            GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManager->reveal());
        }
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = [];

    }

    public function tearDown()
    {
        GeneralUtility::resetSingletonInstances([]);
        unset($GLOBALS['TSFE']);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getConfigurationFromPageIdReturnsEmptyConfigurationForPageIdZero()
    {
        $configuration = Util::getConfigurationFromPageId(0, 'plugin.tx_solr', false, 0, false);
        $this->assertInstanceOf(TypoScriptConfiguration::class, $configuration);
    }

    /**
     * @test
     */
    public function getConfigurationFromPageIdInitializesTsfeOnCacheCall()
    {
        $path = '';
        $language = 0;
        $initializeTsfe = true;

        // prepare first call

        /** @var TwoLevelCache|ObjectProphecy $twoLevelCache */
        $twoLevelCache = $this->prophesize(TwoLevelCache::class);
        $twoLevelCache
            ->get(\Prophecy\Argument::cetera())
            ->willReturn([]);
        $twoLevelCache
            ->set(\Prophecy\Argument::cetera())
            ->shouldBeCalled();
        GeneralUtility::addInstance(TwoLevelCache::class, $twoLevelCache->reveal());


        // Change TSFE->id to 12 ($pageId) and create new cache
        $this->buildTestCaseForTsfe(34, 1);


        Util::getConfigurationFromPageId(
            34,
            $path,
            $initializeTsfe,
            $language,
            true
        );
        $this->assertSame(
            34,
            $GLOBALS['TSFE']->id
        );

        // Change TSFE->id to 23 and create new cache

        $this->buildTestCaseForTsfe(56, 8);
        Util::getConfigurationFromPageId(
            56,
            $path,
            $initializeTsfe,
            $language,
            true
        );
        $this->assertSame(
            56,
            $GLOBALS['TSFE']->id
        );


        // prepare second/cached call
        // TSFE->id has to be changed back to 12 $pageId
        Util::getConfigurationFromPageId(
            34,
            $path,
            $initializeTsfe,
            $language,
            true
        );

        $this->assertSame(
            34,
            $GLOBALS['TSFE']->id
        );

    }

    protected function buildTestCaseForTsfe(int $pageId, int $rootPageId)
    {
        /** @var PageRepository|ObjectProphecy $pageRepository */
        $pageRepository = $this->prophesize(PageRepository::class);
        GeneralUtility::addInstance(PageRepository::class, $pageRepository->reveal());

        /** @var ExtendedTemplateService|ObjectProphecy $extendedTemplateService */
        $extendedTemplateService = $this->prophesize(ExtendedTemplateService::class);
        GeneralUtility::addInstance(ExtendedTemplateService::class, $extendedTemplateService->reveal());

        $siteLanguage = $this->prophesize(SiteLanguage::class);


        $site = $this->prophesize(Site::class);
        $site->getLanguageById(0)
            ->shouldBeCalled()
            ->willReturn($siteLanguage->reveal());
        $siteFinder = $this->prophesize(SiteFinder::class);
        $siteFinder->getSiteByPageId($pageId)
            ->shouldBeCalled()
            ->willReturn($site->reveal());

        $site->getConfiguration()
            ->willReturn(['settings' => []]);

        GeneralUtility::addInstance(SiteFinder::class, $siteFinder->reveal());

        $tsfeProphecy = $this->prophesize(TypoScriptFrontendController::class);
        if (Util::getIsTYPO3VersionBelow10()) {
            $tsfeProphecy->willBeConstructedWith([null, $pageId, 0]);
        } else {
            $siteLanguage->getTypo3Language()->shouldBeCalled()->willReturn(0);

            $rootLineUtility = $this->prophesize(RootlineUtility::class);
            $rootLineUtility->get()->shouldBeCalledOnce()->willReturn([]);
            GeneralUtility::addInstance(RootlineUtility::class, $rootLineUtility->reveal());

            /* @var ObjectProphecy|UserAspect $frontendUserAspect */
            $frontendUserAspect = $this->prophesize(UserAspect::class);
            $frontendUserAspect->isLoggedIn()->willReturn(false);
            $frontendUserAspect->get('isLoggedIn')->willReturn(false);
            $frontendUserAspect->get('id')->shouldBeCalled()->willReturn('UtilTest_TSFEUser');
            $frontendUserAspect->getGroupIds()->shouldBeCalled()->willReturn([0, -1]);
            $frontendUserAspect->get('groupIds')->shouldBeCalled()->willReturn([0, -1]);
            $backendUserAspect = $this->prophesize(UserAspect::class);
            $workspaceAspect =  $this->prophesize(WorkspaceAspect::class);

            $context = $this->prophesize(Context::class);
            $context->hasAspect('frontend.preview')->shouldBeCalled()->willReturn(false);
            $context->setAspect('frontend.preview', Argument::any())->shouldBeCalled();
            $context->hasAspect('frontend.user')->shouldBeCalled()->willReturn(false);
            $context->hasAspect('language')->shouldBeCalled()->willReturn(true);
            $context->getPropertyFromAspect('language', 'id')->shouldBeCalled()->willReturn(0);
            $context->getPropertyFromAspect('language', 'id', 0)->shouldBeCalled()->willReturn(0);
            $context->getPropertyFromAspect('language', 'contentId')->shouldBeCalled()->willReturn(0);
            $context->getAspect('frontend.user')->shouldBeCalled()->willReturn($frontendUserAspect->reveal());
            $context->getAspect('backend.user')->shouldBeCalled()->willReturn($backendUserAspect->reveal());
            $context->getAspect('workspace')->shouldBeCalled()->willReturn($workspaceAspect->reveal());
            $context->getPropertyFromAspect('visibility', 'includeHiddenContent', false)->shouldBeCalled();
            $context->getPropertyFromAspect('backend.user', 'isLoggedIn', false)->shouldBeCalled();
            $context->setAspect('frontend.user', Argument::any())->shouldBeCalled();
            $context->getPropertyFromAspect('workspace', 'id')->shouldBeCalled()->willReturn(0);
            $context->getPropertyFromAspect('date', 'accessTime', 0)->willReturn(0);
            $context->getPropertyFromAspect('typoscript', 'forcedTemplateParsing')->willReturn(false);
            $context->getPropertyFromAspect('visibility', 'includeHiddenPages')->shouldBeCalled()->willReturn(false);
            $context->setAspect('typoscript', Argument::any())->shouldBeCalled();
            GeneralUtility::setSingletonInstance(Context::class, $context->reveal());
            $GLOBALS['TYPO3_REQUEST'] = GeneralUtility::makeInstance(ServerRequest::class);
            $tsfeProphecy->willBeConstructedWith([$context->reveal(), $site->reveal(), $siteLanguage->reveal()]);
            $tsfeProphecy->getSite()->shouldBeCalled()->willReturn($site);
            $tsfeProphecy->getPageAndRootlineWithDomain($pageId, Argument::type(ServerRequest::class))->shouldBeCalled();
            $tsfeProphecy->getConfigArray()->shouldBeCalled();
            $tsfeProphecy->settingLanguage()->shouldBeCalled();
            $tsfeProphecy->newCObj()->shouldBeCalled();
            $tsfeProphecy->calculateLinkVars([])->shouldBeCalled();
            $tsfeProphecy->settingLocale()->shouldBeCalled();

        }

        $tsfe = $tsfeProphecy->reveal();

        $tsfe->tmpl = new \TYPO3\CMS\Core\TypoScript\TemplateService();
        GeneralUtility::addInstance(TypoScriptFrontendController::class, $tsfe);
    }
}