<?php

namespace Scribe\Jabiru\Extension\Core;

use Scribe\Jabiru\Component\Collection\Collection;
use Scribe\Jabiru\Component\Element\ElementLiteral;
use Scribe\Jabiru\Exception\SyntaxError;
use Scribe\Jabiru\Extension\ExtensionInterface;
use Scribe\Jabiru\Renderer\RendererAwareInterface;
use Scribe\Jabiru\Renderer\RendererAwareTrait;
use Scribe\Jabiru\Markdown;

/**
 * Original source code from Markdown.pl
 *
 * > Copyright (c) 2004 John Gruber
 * > <http://daringfireball.net/projects/markdown/>
 */
class LinkExtension implements ExtensionInterface, RendererAwareInterface
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

        $markdown->on('initialize', array($this, 'initialize'), 30);
        $markdown->on('inline', array($this, 'processReferencedLink'), 40);
        $markdown->on('inline', array($this, 'processInlineLink'), 40);
        $markdown->on('inline', array($this, 'processAutoLink'), 50);
    }

    /**
     * Strips link definitions from text, stores the URLs and titles in hash references.
     *
     * @param Text  $text
     * @param array $options
     */
    public function initialize(ElementLiteral $text, array $options = array())
    {
        /** @noinspection PhpUnusedParameterInspection */
        $text->replace('{
            ^[ ]{0,' . $options['tabWidth'] . '}\[(.+)\]:   # id = $1
              [ \t]*
              \n?               # maybe *one* newline
              [ \t]*
            <?(\S+?)>?          # url = $2
              [ \t]*
              \n?               # maybe one newline
              [ \t]*
            (?:
                (?<=\s)         # lookbehind for whitespace
                ["\'(]
                (.+?)           # title = $3
                ["\')]
                [ \t]*
            )?  # title is optional
            (?:\n+|\Z)
        }xm', function (ElementLiteral $whole, ElementLiteral $id, ElementLiteral $url, ElementLiteral $title = null) {
            $id->lower();
            $this->markdown->emit('escape.special_chars', [$url->replace('/(?<!\\\\)_/', '\\\\_')]);
            $this->markdown->getUrlRegistry()->set($id, htmlspecialchars($url, ENT_QUOTES, 'UTF-8', false));

            if ($title) {
                $this->markdown->getTitleRegistry()->set($id, preg_replace('/"/', '&quot;', $title));
            }

            return '';
        });
    }

    /**
     * Handle reference-style links: [link text] [id]
     *
     * @param Text  $text
     * @param array $options
     */
    public function processReferencedLink(ElementLiteral $text, array $options = array())
    {
        if (!$text->contains('[')) {
            return;
        }

        /** @noinspection PhpUnusedParameterInspection */
        $text->replace('{
            #(                   # wrap whole match in $1
              \[
                (' . $this->getNestedBrackets() . ')    # link text = $2
              \]

              [ ]?              # one optional space
              (?:\n[ ]*)?       # one optional newline followed by spaces

              \[
                (.*?)       # id = $3
              \]
            #)
        }xs', function (ElementLiteral $whole, ElementLiteral $linkText, ElementLiteral $id = null) use ($options) {
            if (is_null($id) || (string) $id == '') {
                $id = new ElementLiteral($linkText);
            }

            $id->lower();

            if ($this->markdown->getUrlRegistry()->exists($id)) {
                $url = new ElementLiteral($this->markdown->getUrlRegistry()->get($id));
                $url->escapeHtml()->replace('/(?<!\\\\)_/', '\\\\_');
                $this->markdown->emit('escape.special_chars', [$url]);

                $linkOptions = [
                    'href' => $url->getString(),
                ];

                if ($this->markdown->getTitleRegistry()->exists($id)) {
                    $title = new ElementLiteral($this->markdown->getTitleRegistry()->get($id));
                    $linkOptions['title'] = $title->escapeHtml()->getString();
                }

                return $this->getRenderer()->renderLink($linkText->getString(), $linkOptions);
            } else {
                if ($options['strict']) {
                    throw new SyntaxError(
                        sprintf('Unable to find id "%s" in Reference-style link', $id),
                        $this, $whole, $this->markdown
                    );
                }

                return $whole;
            }
        });
    }

    /**
     * Inline-style links: [link text](url "optional title")
     *
     * @param ElementLiteral $text
     */
    public function processInlineLink(ElementLiteral $text)
    {
        if (!$text->contains('[')) {
            return;
        }

        /** @noinspection PhpUnusedParameterInspection */
        $text->replace('{
            #(               # wrap whole match in $1
              \[
                (' . $this->getNestedBrackets() . ')    # link text = $2
              \]
              \(            # literal paren
                [ \t]*
                <?(.*?)>?   # href = $3
                [ \t]*
                (           # $4
                  ([\'"])   # quote char = $5
                  (.*?)     # Title = $6
                  \4        # matching quote
                )?          # title is optional
              \)
            #)
        }xs', function (ElementLiteral $whole, ElementLiteral $linkText, ElementLiteral $url, ElementLiteral $a = null, ElementLiteral $q = null, ElementLiteral $title = null) {
            $url->escapeHtml()->replace('/(?<!\\\\)_/', '\\\\_');
            $this->markdown->emit('escape.special_chars', [$url]);

            $linkOptions = [
                'href' => $url->getString(),
            ];

            if ($title) {
                $linkOptions['title'] = $title->replace('/"/', '&quot;')->escapeHtml()->getString();
            }

            return $this->getRenderer()->renderLink($linkText, $linkOptions);
        });
    }

    /**
     * Make links out of things like `<http://example.com/>`
     *
     * @param ElementLiteral $text
     */
    public function processAutoLink(ElementLiteral $text)
    {
        if (!$text->contains('<')) {
            return;
        }

        $text->replace('{<((?:https?|ftp):[^\'">\s]+)>}', function (ElementLiteral $w, ElementLiteral $url) {
            $this->markdown->emit('escape.special_chars', [$url->replace('/(?<!\\\\)_/', '\\\\_')]);

            return $this->getRenderer()->renderLink($url, [
                'href' => $url->getString()
            ]);
        });

        /** @noinspection PhpUnusedParameterInspection */
        $text->replace('{
            <
            (?:mailto:)?
            (
                [-.\w]+
                \@
                [-a-z0-9]+(\.[-a-z0-9]+)*\.[a-z]+
            )
            >
        }ix', function (ElementLiteral $w, ElementLiteral $address) {
            $address  = "mailto:" . $address;

            $encode = array(
                function ($char) { return '&#' . ord($char) . ';'; },
                function ($char) { return '&#x' . dechex(ord($char)) . ';'; },
                function ($char) { return $char; }
            );

            $chars = new Collection(str_split($address));

            $chars->apply(function ($char) use ($encode) {
                if ($char == '@') {
                    return $encode[rand(0, 1)]($char);
                } elseif ($char != ':') {
                    $rand = rand(0, 100);
                    $key = ($rand > 90) ? 2 : ($rand < 45 ? 0 : 1);

                    return $encode[$key]($char);
                }

                return $char;
            });

            $address = $chars->join();
            $text = $chars->slice(7)->join();

            return $this->getRenderer()->renderLink($text, ['href' => $address]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'link';
    }

    /**
     * @return string
     */
    protected function getNestedBrackets()
    {
        return str_repeat('(?>[^\[\]]+|\[', 7) . str_repeat('\])*', 7);
    }

}
