<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Backend\Controller;

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

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($GLOBALS['LANG']->sL($module->getTitle()), (string)($pageRecord['title'] ?? ''));
        $view->makeDocHeaderModuleMenu(['id' => $pageId]);
        self::setPageInformation($view, $pageRecord);
        $view->assignMultiple([
            'pageId' => $pageId,
            'pageSelected' => $pageRecord !== [],
            'depth' => $depth,
            'depths' => self::DEPTHS,
            'inWorkspace' => $user->workspace !== 0,
            'rows' => $pageRecord !== [] ? $this->withEditLinks($this->pageOverview->rows($pageId, $depth, $user), $user, $request) : [],
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
    private function withEditLinks(array $rows, BackendUserAuthentication $user, ServerRequestInterface $request): array
    {
        $returnUrl = (string)$request->getUri();
        foreach ($rows as &$row) {
            $page = BackendUtility::getRecord('pages', $row['uid']) ?? [];
            $row['editUrl'] = $user->check('tables_modify', 'pages') && $user->doesUserHaveAccess($page, Permission::PAGE_EDIT)
                ? (string)$this->uriBuilder->buildUriFromRoute('record_edit', ['edit' => ['pages' => [$row['uid'] => 'edit']], 'returnUrl' => $returnUrl])
                : '';
        }

        return $rows;
    }

    private function backendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
