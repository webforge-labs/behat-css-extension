<?php

namespace Webforge\Behat;

use Behat\Mink\Selector\SelectorInterface;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;
use Symfony\Component\CssSelector\Parser\Shortcut\ClassParser;
use Symfony\Component\CssSelector\Parser\Shortcut\ElementParser;
use Symfony\Component\CssSelector\Parser\Shortcut\EmptyStringParser;
use Symfony\Component\CssSelector\Parser\Shortcut\HashParser;
use Symfony\Component\CssSelector\XPath\Extension\HtmlExtension;
use Symfony\Component\CssSelector\XPath\Translator;

/**
  * An Extension to support prefixing xpath expressions
  */
class SymfonyCssSelector implements SelectorInterface
{
    /**
     * @var Translator
     */
    private $translator;

    protected function getTranslator() : Translator
    {
        if (!isset($this->translator)) {
            $this->translator = new Translator();

            $this->translator->registerExtension(new HtmlExtension($this->translator));
            $this->translator->registerExtension(new SymfonyCssExtension($this->translator));

            $this->translator
                    ->registerParserShortcut(new EmptyStringParser())
                    ->registerParserShortcut(new ElementParser())
                    ->registerParserShortcut(new ClassParser())
                    ->registerParserShortcut(new HashParser())
            ;
        }

        return $this->translator;
    }

    /**
     * Translates CSS into XPath.
     *
     * @param string|array $locator current selector locator
     * @param string $prefix xpath prefix
     *
     * @return string
     */
    public function translateToXPath($locator)
    {
        if (!is_string($locator)) {
            throw new \InvalidArgumentException('The CssSelector expects to get a string as locator');
        }

        $args = func_get_args();

        $prefix = 'descendant-or-self::';
        if (count($args) == 2) {
            $prefix = $args[1];
        }

        try {
            return $this->getTranslator()->cssToXPath($locator, $prefix);
        } catch (SyntaxErrorException $e) {
            throw new \RuntimeException('Failed to convert locator: '.$locator.' to xpath: '.$e->getMessage(), 0, $e);
        }
    }
}