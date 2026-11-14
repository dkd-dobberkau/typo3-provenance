<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Backend;

use CmsProvenance\Provenance\Backend\Controller\PagesModuleController;
use CmsProvenance\Provenance\Event\ModifyPageOverviewRowEvent;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PagesModuleControllerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['filemetadata'];
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PageOverview.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PagesModuleEditors.csv');
        $this->logIn(1);
    }

    #[Test]
    public function listsThePagesOfTheSelectedPage(): void
    {
        $body = $this->render(['id' => 1, 'depth' => 1]);

        self::assertStringContainsString('Incomplete', $body);
        self::assertStringContainsString('L2', $body);
        self::assertStringContainsString('Author &quot;Grace Hopper&quot; has no profile URI.', $body);
    }

    #[Test]
    public function incompleteMediaLinkToTheirMetadataAndNameTheBackfill(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PageOverviewMedia.csv');

        $body = $this->render(['id' => 1, 'depth' => 0]);

        self::assertStringContainsString('2 of 3 incomplete', $body);
        self::assertStringContainsString('legacy.png', $body);
        self::assertStringContainsString('edit%5Bsys_file_metadata%5D%5B2%5D=edit', $body);
        self::assertStringContainsString('provenance:backfill-media', $body);
    }

    #[Test]
    public function withoutAPageItAsksForOne(): void
    {
        self::assertStringContainsString('Select a page in the page tree.', $this->render([]));
    }

    #[Test]
    public function userWithoutAccessToPagesSeesNone(): void
    {
        $this->logIn(3);
        $body = $this->render(['id' => 1, 'depth' => 1]);

        self::assertStringContainsString('You may not view pages.', $body);
        self::assertStringNotContainsString('Incomplete', $body);
        self::assertStringNotContainsString('Select a page', $body);
    }

    #[Test]
    public function listenersCanAddLinkColumns(): void
    {
        $this->getContainer()->set('provenance.test.link-listener', new class () {
            public function __invoke(ModifyPageOverviewRowEvent $event): void
            {
                $row = $event->getRow();
                $row['extra']['Action'] = ['label' => 'Check', 'url' => '/typo3/check?id=' . $event->getPageUid()];
                $event->setRow($row);
            }
        });
        $this->get(ListenerProvider::class)->addListener(ModifyPageOverviewRowEvent::class, 'provenance.test.link-listener');

        $body = $this->render(['id' => 1, 'depth' => 1]);

        self::assertStringContainsString('<th>Action</th>', $body);
        self::assertStringContainsString('href="/typo3/check?id=2"', $body);
    }

    private function logIn(int $userUid): void
    {
        $backendUser = $this->setUpBackendUser($userUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @param array<string, int> $query
     */
    private function render(array $query): string
    {
        $module = $this->get(ModuleProvider::class)->getModule('provenance_pages', $GLOBALS['BE_USER']);
        $request = (new ServerRequest('https://example.org/typo3/module/web/provenance/pages', 'GET'))
            ->withQueryParams($query)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('module', $module)
            ->withAttribute('moduleData', ModuleData::createFromModule($module, []))
            ->withAttribute('route', new Route('/module/web/provenance/pages', ['packageName' => 'cms-provenance/provenance', '_identifier' => 'provenance_pages']));
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $response = $this->get(PagesModuleController::class)->handleRequest($request);
        self::assertSame(200, $response->getStatusCode());

        return (string)$response->getBody();
    }
}
