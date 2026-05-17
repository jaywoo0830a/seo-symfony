<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Author;
use App\Entity\ContentNode;
use App\Entity\Enum\ContentStatus;
use App\Entity\Region;
use App\Entity\Theme;
use App\Service\NodeNavigator;
use App\Service\PreviewNavigator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * PreviewNavigator는 NodeNavigator(final)와 duck-typed로 같은 nav 글로벌
 * 자리에서 작동해야 한다. 시그니처가 어긋나면 Twig에서 조용히 빈 결과를
 *반환하거나 PHP가 런타임에 깨지므로, 컨트랙트를 reflection으로 강제한다.
 */
final class PreviewNavigatorTest extends TestCase
{
    /**
     * NodeNavigator의 모든 public 메서드(생성자/매직 메서드 제외)가
     * PreviewNavigator에도 같은 시그니처로 존재.
     *
     * @return iterable<string, array{string}>
     */
    public static function navigatorMethods(): iterable
    {
        foreach ((new \ReflectionClass(NodeNavigator::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isStatic()) {
                continue;
            }
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }
            yield $method->getName() => [$method->getName()];
        }
    }

    #[DataProvider('navigatorMethods')]
    public function testPreviewNavigatorHasSameMethodSignature(string $method): void
    {
        $this->assertTrue(
            method_exists(PreviewNavigator::class, $method),
            sprintf('PreviewNavigator is missing %s() — would break templates calling nav.%s', $method, $method),
        );

        $real = new ReflectionMethod(NodeNavigator::class, $method);
        $stub = new ReflectionMethod(PreviewNavigator::class, $method);

        $this->assertSame(
            $real->getNumberOfParameters(),
            $stub->getNumberOfParameters(),
            sprintf('Parameter count mismatch on %s()', $method),
        );

        foreach ($real->getParameters() as $i => $realParam) {
            $stubParam = $stub->getParameters()[$i];
            $this->assertSame(
                $this->typeName($realParam->getType()),
                $this->typeName($stubParam->getType()),
                sprintf('Parameter %d type mismatch on %s()', $i, $method),
            );
            $this->assertSame(
                $realParam->isOptional(),
                $stubParam->isOptional(),
                sprintf('Parameter %d optionality mismatch on %s()', $i, $method),
            );
        }

        $this->assertSame(
            $this->typeName($real->getReturnType()),
            $this->typeName($stub->getReturnType()),
            sprintf('Return type mismatch on %s()', $method),
        );
    }

    public function testEmptyStubReturnsEmptyEverywhere(): void
    {
        $nav = new PreviewNavigator();
        $node = $this->fakeNode();

        $this->assertNull($nav->parent($node));
        $this->assertSame([], $nav->children($node));
        $this->assertSame([], $nav->children($node, 'theme'));
        $this->assertSame([], $nav->siblings($node));
        $this->assertSame([], $nav->siblings($node, 'theme'));
        $this->assertSame([], $nav->ancestors($node));
        $this->assertSame([], $nav->uncles($node));
        $this->assertSame(0, $nav->countChildren($node));
        $this->assertSame([], $nav->themeChain($node));
        $this->assertSame([], $nav->regionChain($node));
    }

    public function testStubReturnsConfiguredFakesPerAxis(): void
    {
        $regionChild = $this->fakeNode();
        $themeChild = $this->fakeNode();
        $regionSibling = $this->fakeNode();
        $themeSibling = $this->fakeNode();
        $ancestor = $this->fakeNode();
        $parent = $this->fakeNode();

        $nav = new PreviewNavigator(
            childrenByAxis: ['region' => [$regionChild], 'theme' => [$themeChild]],
            siblingsByAxis: ['region' => [$regionSibling], 'theme' => [$themeSibling]],
            ancestors: [$ancestor],
            parent: $parent,
        );

        $node = $this->fakeNode();

        $this->assertSame([$regionChild], $nav->children($node, 'region'));
        $this->assertSame([$themeChild], $nav->children($node, 'theme'));
        $this->assertSame([$regionSibling], $nav->siblings($node, 'region'));
        $this->assertSame([$themeSibling], $nav->siblings($node, 'theme'));
        $this->assertSame([$ancestor], $nav->ancestors($node));
        $this->assertSame($parent, $nav->parent($node));
        $this->assertSame(1, $nav->countChildren($node, 'region'));
        $this->assertSame(1, $nav->countChildren($node, 'theme'));
    }

    public function testStubIsFinalSoCallersCannotSilentlySubclassAroundDrift(): void
    {
        $this->assertTrue(
            (new \ReflectionClass(PreviewNavigator::class))->isFinal(),
            'PreviewNavigator must stay final — subclassing would let drift creep back in.',
        );
    }

    private function typeName(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }
        if ($type instanceof ReflectionNamedType) {
            return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
        }
        return (string) $type;
    }

    private function fakeNode(): ContentNode
    {
        $theme = (new Theme())->setSlug('preview')->setName('preview')->setDepth(0);
        $region = (new Region())->setSlug('kr')->setName('대한민국')->setDepth(0);
        $author = (new Author())->setSlug('preview-author')->setRealName('preview');

        return (new ContentNode())
            ->setTheme($theme)
            ->setRegion($region)
            ->setAuthor($author)
            ->setStatus(ContentStatus::Live);
    }
}
