<?php

declare(strict_types=1);

namespace CmsProvenance\Provenance\Tests\Unit\Service;

use CmsProvenance\Provenance\Service\DocumentIdentifierGenerator;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DocumentIdentifierGeneratorTest extends UnitTestCase
{
    #[Test]
    public function generateReturnsUrnWithVersion4Uuid(): void
    {
        $identifier = (new DocumentIdentifierGenerator())->generate();

        self::assertMatchesRegularExpression(
            '/^urn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $identifier
        );
    }

    #[Test]
    public function generateReturnsDifferentIdentifiersOnEachCall(): void
    {
        $generator = new DocumentIdentifierGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }
}
