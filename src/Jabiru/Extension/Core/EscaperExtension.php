<?php

namespace Scribe\Jabiru\Extension\Core;

use Scribe\Jabiru\Component\Element\ElementLiteral;
use Scribe\Jabiru\Extension\ExtensionInterface;
use Scribe\Jabiru\Markdown;

/**
 * Escape special characters
 *
 * Original source code from Markdown.pl
 *
 * > Copyright (c) 2004 John Gruber
 * > <http://daringfireball.net/projects/markdown/>
 */
class EscaperExtension implements ExtensionInterface
{

    /**
     * @var array
     */
    private $hashes = array();

    /**
     * {@inheritdoc}
     */
    public function register(Markdown $markdown)
    {
        $this->hashes = $this->generateSpecialCharsHash();

        $markdown->on('escape.special_chars', array($this, 'escapeSpecialChars'));
        $markdown->on('inline', array($this, 'escapeSpecialChars'), 20);
        $markdown->on('inline', array($this, 'escapeAmpsAndBrackets'), 60);
        $markdown->on('finalize', array($this, 'unescapeSpecialChars'), 20);
    }

    /**
     * @param ElementLiteral $text
     */
    public function escapeSpecialChars(ElementLiteral $text)
    {
        foreach ($this->hashes as $char => $hash) {
            $text->replaceString('\\'.$char, $hash);
        }
    }

    /**
     * @param ElementLiteral $text
     */
    public function escapeAmpsAndBrackets(ElementLiteral $text)
    {
        if ($text->contains('&')) {
            $text->replace('/&(?!#?[xX]?(?:[0-9a-fA-F]+|\w+);)/', '&amp;');
        }

        if ($text->contains('<')) {
            $text->replace('{<(?![a-z/?\$!])}i', '&lt;');
        }
    }

    /**
     * @param ElementLiteral $text
     */
    public function unescapeSpecialChars(ElementLiteral $text)
    {
        foreach ($this->hashes as $char => $hash) {
            $text->replaceString($hash, $char);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'escaper';
    }

    /**
     * @return array
     */
    protected function generateSpecialCharsHash()
    {
        $hashes = array();
        $chars  = array('\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!');

        foreach ($chars as $char) {
            $hashes[$char] = sprintf('{escape:md5(%s)}', md5($char));
        }

        return $hashes;
    }

}