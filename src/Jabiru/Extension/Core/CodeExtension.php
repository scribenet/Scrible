<?php

namespace Scribe\Jabiru\Extension\Core;

use Scribe\Jabiru\Component\Element\ElementLiteral;
use Scribe\Jabiru\Extension\ExtensionInterface;
use Scribe\Jabiru\Renderer\RendererAwareInterface;
use Scribe\Jabiru\Renderer\RendererAwareTrait;
use Scribe\Jabiru\Markdown;

/**
 * Converts block and line code
 *
 * Original source code from Markdown.pl
 *
 * > Copyright (c) 2004 John Gruber
 * > <http://daringfireball.net/projects/markdown/>
 */
class CodeExtension implements ExtensionInterface, RendererAwareInterface
{

    use RendererAwareTrait;

    /**
     * @var Markdown
     */
    private $markdown;

    /**
     * {@inheritdoc}
     */
    public function register(Markdown $markdown)
    {
        $this->markdown = $markdown;

        $markdown->on('block', array($this, 'processCodeBlock'), 40);
        $markdown->on('inline', array($this, 'processCodeSpan'), 10);
    }

    /**
     * @param Text  $text
     * @param array $options
     */
    public function processCodeBlock(ElementLiteral $text, array $options = array())
    {
        /** @noinspection PhpUnusedParameterInspection */
        $text->replace('{
            (?:\n\n|\A)
            (                                               # $1 = the code block -- one or more lines, starting with a space/tab
              (?:
                (?:[ ]{' . $options['tabWidth'] . '} | \t)  # Lines must start with a tab or a tab-width of spaces
                .*\n+
              )+
            )
            (?:(?=^[ ]{0,' . $options['tabWidth'] . '}\S)|\Z) # Lookahead for non-space at line-start, or end of doc
        }mx', function (ElementLiteral $whole, ElementLiteral $code) {
            $this->markdown->emit('outdent', array($code));
            $code->escapeHtml(ENT_NOQUOTES);
            $this->markdown->emit('detab', array($code));
            $code->replace('/\A\n+/', '');
            $code->replace('/\s+\z/', '');

            return "\n\n" . $this->getRenderer()->renderCodeBlock($code) . "\n\n";
        });
    }

    /**
     * @param ElementLiteral $text
     */
    public function processCodeSpan(ElementLiteral $text)
    {
        if (!$text->contains('`')) {
            return;
        }

        $chars = ['\\\\', '`', '\*', '_', '{', '}', '\[', '\]', '\(', '\)', '>', '#', '\+', '\-', '\.', '!'];
        $chars = implode('|', $chars);

        /** @noinspection PhpUnusedParameterInspection */
        $text->replace('{
            (`+)        # $1 = Opening run of `
            (.+?)       # $2 = The code block
            (?<!`)
            \1          # Matching closer
            (?!`)
        }x', function (ElementLiteral $w, ElementLiteral $b, ElementLiteral $code) use ($chars) {
            $code->trim()->escapeHtml(ENT_NOQUOTES);
            $code->replace(sprintf('/(?<!\\\\)(%s)/', $chars), '\\\\${1}');

            return $this->getRenderer()->renderCodeSpan($code);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'code';
    }

}
