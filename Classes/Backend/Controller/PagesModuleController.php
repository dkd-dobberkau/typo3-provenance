<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Backend\Controller;

use CmsProvenance\Provenance\Backend\ExtraColumns;
use CmsProvenance\Provenance\Backend\PageOverview;
use CmsProvenance\Provenance\Backend\PageSubtree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * Submodule "Pages" of the Provenance module: records and levels of the
 * pages below the selected one. Read-only.
 */
#[AsController]
final readonly class PagesModuleController
{
    public const DEPTHS = [0, 1, 2, 3, 4, PageSubtree::ALL];

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PageOverview $pageOverview,
        private UriBuilder $uriBuilder,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->backendUser();
        $module = $request->getAttribute('module');
        $pageId = (int)($request->getQueryParams()['id'] ?? 0);
        $depth = self::depth($request, $user);
        // For admins, page 0 reads as the root level; the overview needs a real page.
        $pageRecord = $pageId > 0 ? (BackendUtility::readPageAccess($pageId, $user->getPagePermsClause(Permission::PAGE_SHOW)) ?: []) : [];

        $mayView = $user->check('tables_select', 'pages');
        $overview = ExtraColumns::normalize($pageRecord !== [] && $mayView ? $this->withEditLinks($this->pageOverview->rows($pageId, $depth, $user), $request) : []);

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($GLOBALS['LANG']->sL($module->getTitle()), (string)($pageRecord['title'] ?? ''));
        $view->makeDocHeaderModuleMenu(['id' => $pageId]);
        self::setPageInformation($view, $pageRecord);
        $view->assignMultiple([
            'pageId' => $pageId,
            'pageSelected' => $pageRecord !== [],
            'mayView' => $mayView,
            'depth' => $depth,
            'depths' => self::DEPTHS,
            'inWorkspace' => $user->workspace !== 0,
            'rows' => $overview['rows'],
            'extraColumns' => $overview['columns'],
            'moduleUrl' => (string)$this->uriBuilder->buildUriFromRoute($module->getIdentifier()),
        ]);

        return $view->renderResponse('PagesModule');
    }

    /**
     * The depth from the request, else the one stored for the user.
     */
    public static function depth(ServerRequestInterface $request, BackendUserAuthentication $user): int
    {
        $moduleData = $request->getAttribute('moduleData');
        $requested = $request->getQueryParams()['depth'] ?? null;
        if ($requested !== null && in_array((int)$requested, self::DEPTHS, true)) {
            $moduleData->set('depth', (int)$requested);
            $user->pushModuleData($request->getAttribute('module')->getIdentifier(), $moduleData->toArray());
        }
        $depth = (int)$moduleData->get('depth', 1);

        return in_array($depth, self::DEPTHS, true) ? $depth : 1;
    }

    /**
     * Page path in the doc header; the API changed in TYPO3 14.
     *
     * @param array<string, mixed> $pageRecord
     */
    public static function setPageInformation(ModuleTemplate $view, array $pageRecord): void
    {
        if ($pageRecord === []) {
            return;
        }
        $docHeader = $view->getDocHeaderComponent();
        if (method_exists($docHeader, 'setPageBreadcrumb')) {
            $docHeader->setPageBreadcrumb($pageRecord);
        } else {
            $docHeader->setMetaInformation($pageRecord);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withEditLinks(array $rows, ServerRequestInterface $request): array
    {
        $returnUrl = (string)$request->getUri();
        $mayEditMetadata = $this->backendUser()->check('tables_modify', 'sys_file_metadata');
        foreach ($rows as &$row) {
            $row['editUrl'] = $row['mayEdit']
                ? (string)$this->uriBuilder->buildUriFromRoute('record_edit', ['edit' => ['pages' => [$row['uid'] => 'edit']], 'returnUrl' => $returnUrl])
                : '';
            foreach ($row['media']['incomplete'] as &$file) {
                $file['editUrl'] = $mayEditMetadata && $file['metadataUid'] > 0
                    ? (string)$this->uriBuilder->buildUriFromRoute('record_edit', ['edit' => ['sys_file_metadata' => [$file['metadataUid'] => 'edit']], 'returnUrl' => $returnUrl])
                    : '';
                $file['needsBackfill'] = array_intersect($file['lacks'], ['identifier', 'hash']) !== [];
            }
            unset($file);
        }

        return $rows;
    }

    private function backendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
