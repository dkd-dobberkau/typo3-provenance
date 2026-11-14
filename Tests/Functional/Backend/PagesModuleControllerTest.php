<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Backend;

use CmsProvenance\Provenance\Backend\Controller\PagesModuleController;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PagesModuleControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PageOverview.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
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
    public function withoutAPageItAsksForOne(): void
    {
        self::assertStringContainsString('Select a page in the page tree.', $this->render([]));
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
