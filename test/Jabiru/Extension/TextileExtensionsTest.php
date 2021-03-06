<?php

namespace Scribe\Jabiru\Tests\Jabiru\Extension;

use Scribe\Jabiru\Jabiru;
use Scribe\Jabiru\Extension\Textile\CommentExtension;
use Scribe\Jabiru\Extension\Textile\DefinitionListExtension;
use Scribe\Jabiru\Extension\Textile\HeaderExtension;
use Symfony\Component\Finder\Finder;

/**
 * Tests Scribe\Jabiru\Extensions\Textile\*
 *
 * @group Textile
 */
class TextileExtensionsTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider textileProvider
     */
    public function testTextilePatterns($name, $textile, $expected)
    {
        $jabiru = new Jabiru();
        $jabiru->addExtensions([
            new HeaderExtension(),
            new DefinitionListExtension(),
            new CommentExtension()
        ]);

        $expected = str_replace("\r\n", "\n", $expected);
        $expected = str_replace("\r", "\n", $expected);
        $html     = $jabiru->render($textile);

        $this->assertEquals($expected, $html, sprintf('%s failed', $name));
    }

    /**
     * @return array
     */
    public function textileProvider()
    {
        $finder = Finder::create()
            ->in(__DIR__.'/../Resources/textile')
            ->files()
            ->name('*.textile');

        return $this->processPatterns($finder);
    }

    /**
     * @param Finder|\Symfony\Component\Finder\SplFileInfo[] $finder
     *
     * @return array
     */
    protected function processPatterns(Finder $finder)
    {
        $patterns = [];

        foreach ($finder as $file) {
            $name       = preg_replace('/\.(textile|out)$/', '', $file->getFilename());
            $expected   = trim(file_get_contents(preg_replace('/\.textile$/', '.out', $file->getPathname())));
            $expected   = str_replace("\r\n", "\n", $expected);
            $expected   = str_replace("\r", "\n", $expected);
            $patterns[] = [$name, $file->getContents(), $expected];
        }

        return $patterns;
    }

}