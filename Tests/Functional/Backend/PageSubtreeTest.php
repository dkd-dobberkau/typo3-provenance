<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Functional\Backend;

use CmsProvenance\Provenance\Backend\PageSubtree;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PageSubtreeTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PageTree.csv');
    }

    #[Test]
    public function depthZeroIsThePageItself(): void
    {
        self::assertSame([1], $this->pageIds(1, 0, 1));
    }

    #[Test]
    public function depthOneAddsLiveDefaultLanguageChildrenIncludingHiddenOnes(): void
    {
        self::assertSame([1, 2, 3, 9], $this->pageIds(1, 1, 1));
    }

    #[Test]
    public function allReachesTheDeepestPageInTreeOrder(): void
    {
        self::assertSame([1, 2, 5, 6, 3, 9, 10], $this->pageIds(1, PageSubtree::ALL, 1));
    }

    #[Test]
    public function pageOutsideTheEditorsMountGivesNothing(): void
    {
        self::assertSame([], $this->pageIds(1, PageSubtree::ALL, 2));
    }

    #[Test]
    public function editorSeesTheReadablePagesOfTheMount(): void
    {
        self::assertSame([2, 5, 6], $this->pageIds(2, PageSubtree::ALL, 2));
    }

    #[Test]
    public function unreadablePageGivesNothing(): void
    {
        self::assertSame([], $this->pageIds(9, PageSubtree::ALL, 2));
    }

    /**
     * @return list<int>
     */
    private function pageIds(int $root, int $depth, int $userUid): array
    {
        return $this->get(PageSubtree::class)->pageIds($root, $depth, $this->setUpBackendUser($userUid));
    }
}
