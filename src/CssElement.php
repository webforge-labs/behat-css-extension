<?php
namespace Webforge\Behat;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use Behat\Mink\WebAssert;
use Hamcrest\Matcher;
use Hamcrest\MatcherAssert;
use Hamcrest\Matchers;
use LogicException;
use RuntimeException;

final class CssElement
{
    /**
     * Used in find() for all selectors
     *
     * we use our own implementation of the css selector here
     * @var string a selector name used by mink
     */
    const MINK_SELECTOR = 'webforge-css';

    /**
     * Used to store some test-vars
     *
     * @var \stdClass
     */
    public $props;

    /**
     * @var WebAssert
     */
    protected $assert;

    /**
     * @var int in milliseconds
     */
    private $defaultTimeout = 5000;

    /**
     * @var string|false
     */
    protected $subSelector;

    /**
     * @var string
     */
    protected $subExpression;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var CssElement|null
     */
    protected $context;

    /**
     * @var null|NodeElement|DocumentElement
     */
    private $element;

    /**
     * Creates a new css assertion chain
     *
     * @param string|array{string, NodeElement|DocumentElement|null} $selector the css selector to start with
     */
    public function __construct($selector, WebAssert $assertSession, Session $session, ?CssElement $context = null)
    {
        $this->assert = $assertSession;
        $this->session = $session;
        $this->context = $context;

        $this->props = new \stdClass;

        if (is_array($selector)) {
            list ($this->subExpression, $this->element) = $selector;
            $this->subSelector = false;
        } else {
            $this->element = NULL; // will be set with find(), then
            $this->subSelector = $selector;
            $this->subExpression = $context ? sprintf(".find('%s')", $selector) : sprintf("jQuery('%s')", $selector);
        }
    }

    /**
     * Returns (if possible) a complete css selector describing the element
     *
     * @return string
     */
    public function cssSelector(): string
    {
        $selector = $this->context ? $this->context->cssSelector() . ' ' : '';

        if ($this->subSelector === false) {
            return '<< no valid css selector >>';
        } else {
            $selector .= $this->subSelector;
        }

        return $selector;
    }

    /**
     * Returns a jquery expression describing the element
     *
     * @return string
     */
    public function expression(): string
    {
        $expression = $this->context ? $this->context->expression() : '';
        $expression .= $this->subExpression;

        return $expression;
    }


    /**
     * @param bool $refresh
     *@return NodeElement|DocumentElement|null
     */
    public function getElement(bool $refresh = false)
    {
        if (!isset($this->element) || $refresh) {
            assert(is_string($this->subSelector), 'you cannot get an element if the subSelector is not set');
            $this->element = $this->contextElement()->find(self::MINK_SELECTOR, $this->subSelector);
        }

        return $this->element;
    }

    /**
     * This is nearly the same as exist(), but isnt chainable
     *
     * @return NodeElement
     * @throws RuntimeException
     */
    protected function ensureElement()
    {
        $element = $this->getElement();

        if (!$element) {
            throw new RuntimeException(sprintf("The element %s cannot be found", $this->expression()));
        }

        if ($element instanceof DocumentElement) {
            throw new \RuntimeException('The document does not support this method');
        }

        return $element;
    }

    /**
      * @return NodeElement[]
      */
    public function getElements(): array
    {
        if ($this->subSelector === false) {
            throw new LogicException('Cannot get elements() from this selector, because it was not used purely with css selectors. This expression cannot be resolved to elements: ' . $this->expression());
        }

        return $this->contextElement()->findAll(self::MINK_SELECTOR, $this->subSelector);
    }


    /**
     * @return NodeElement|DocumentElement
     */
    protected function contextElement()
    {
        return $this->context ? $this->context->ensureElement() : $this->session->getPage();
    }

    /**
     * @return CssElement[]
     */
    public function all(): array
    {
        // wrap all found elements in cssElements with a custom expression
        return array_map(
            function ($element, $key) {
                return $this->css(['.eq(' . $key . ')', $element]);
            },
            $elements = $this->getElements(),
            array_keys($elements)
        );
    }

    /**
     * Returns the xth element matching (as match)
     *
     * @param int $index 0-based integer indicating 0the position of the element in the match
     *
     * @return CssElement
     */
    public function eq(int $index): CssElement
    {
        $elements = $this->getElements();

        $this->assertThat(
            $this->generateMessage('%s.eq('.$index.')'),
            $elements,
            Matchers::hasKeyInArray($index)
        );

        return $this->css(['.eq('.$index.')', $elements[$index]]);
    }


    /**
     * @param string|array{string, NodeElement|DocumentElement|null} $selector the css selector that will find a child/children of the current element
     *
     * @return CssElement
     */
    public function css($selector): CssElement
    {
        return new self($selector, $this->assert, $this->session, $this);
    }

    /**
     * Returns the last call to css() again
     *
     * note this is NOT the same as calling parent() !
     *
     * @return CssElement
     */
    public function end(): CssElement
    {
        if (!isset($this->context)) {
            throw new \LogicException('End of the context: no end() to be returned');
        }

        return $this->context;
    }

    public function parent(): CssElement
    {
        $parentElement = $this->ensureElement()->find('xpath', '..');

        return $this->css(['.parent()', $parentElement]);
    }


    /**
     * Like the jquery closest, finds the first matching parent element by $filter (css selector)
     *
     * @param string $filter in css format
     *
     * @return CssElement
     */
    public function closest(string $filter): CssElement
    {
        $xpath = $this->session->getSelectorsHandler() // @phpstan-ignore-line we hacked the second parameter here as optional
            ->getSelector(self::MINK_SELECTOR)
                ->translateToXPath($filter, 'ancestor::');

        $closestElement = $this->ensureElement()->find('xpath', $xpath);

        return $this->css([
            sprintf(".closest('%s')", $filter),
            $closestElement
        ]);
    }

    /**
     * Asserts that the selector resolves to a set of exactly one element
     *
     * @return CssElement
     */
    public function exists(): CssElement
    {
        $this->ensureElement();

        return $this;
    }

    /**
     * asserts that the element exists and is visible
     *
     * @return CssElement
     */
    public function isVisible(): CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s should be visible'),
            $this->ensureElement()->isVisible(),
            Matchers::equalTo(true)
        );

        return $this;
    }

    public function isNotVisible(): CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s should NOT be visible'),
            $this->ensureElement()->isVisible(),
            Matchers::equalTo(false)
        );

        return $this;
    }

    /**
     * Asserts that the selector matches $numberOfElements elements
     *
     * @param int|Matcher $numberOfElements
     *
     * @return CssElement
     */
    public function count($numberOfElements): CssElement
    {
        if (!($numberOfElements instanceof Matcher)) {
            $numberOfElements = Matchers::equalTo($numberOfElements);
        }

        $this->assertThat(
            $this->generateMessage('%s.length()'),
            count($this->getElements()),
            $numberOfElements
        );

        return $this;
    }

    public function getCount(): int
    {
        return count($this->getElements());
    }

    /**
     * Asserts that the selector matches 0 elements
     *
     * @return CssElement
     */
    public function notExists(): CssElement
    {
        return $this->count(0);
    }

    /**
     * Clicks on the element
     *
     * @return CssElement
     */
    public function click(): CssElement
    {
        try {
            $this->ensureElement()->click();
        } catch (\WebDriver\Exception\ElementNotVisible $e) {
            throw new \LogicException($this->generateMessage('tried to click on element, but it was not visible: %s'), 0, $e);
        }

        return $this;
    }

    /**
     * @param string $locator
     * @param string $value
     *
     * @return CssElement
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function fillField(string $locator, string $value): CssElement
    {
        $this->ensureElement()->fillField($locator, $value);

        return $this;
    }

    /**
     * @param string $locator
     *
     * @return CssElement
     *
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function checkField(string $locator): CssElement
    {
        $this->ensureElement()->checkField($locator);

        return $this;
    }

    /**
     * Get an HTML Attribute from the current element
     *
     * @param string $name of the attribute
     */
    public function getAttribute(string $name): ?string
    {
        return $this->ensureElement()->getAttribute($name);
    }

    /**
     * Asserts that a certain html attribute has the value
     * @param string $name
     * @param string|Matcher $value to be matched
     * @return CssElement
     */
    public function hasAttribute(string $name, $value): CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s ->hasAttribute("%s", %s)', $name, json_encode($value)),
            $this->ensureElement()->getAttribute($name),
            is_string($value) ? Matchers::equalTo($value) : $value
        );

        return $this;
    }

    /**
     * Checks whether an element has a named CSS class.
     *
     * @param string $className Name of the css-class
     *
     * @return CssElement
     */
    public function hasClass(string $className) : CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s ->hasClass("%s")', $className),
            $this->ensureElement()->hasClass($className),
            Matchers::equalTo(true)
        );

        return $this;
    }

    /**
     * Returns the -inner- html of the matched element
     *
     * @return string
     */
    public function getHtml(): string
    {
        return $this->ensureElement()->getHtml();
    }

    /**
     * Returns the -inner- html of the matched element
     *
     * @return string
     */
    public function getOuterHtml(): string
    {
        return $this->ensureElement()->getOuterHtml();
    }

    /**
     * Returns the inner text of the matched element
     *
     * @return string
     */
    public function getText(): string
    {
        return $this->ensureElement()->getText();
    }

    /**
     * Returns the inner text of all matched elements
     *
     * @return string[]
     */
    public function getTexts(): array
    {
        $texts = [];
        foreach ($this->all() as $element) {
            $texts[] = $element->getText();
        }
        return $texts;
    }

    /**
     * Asserts that substr is in the text value
     *
     * @param string $substr
     * @return CssElement
     */
    public function containsText(string $substr): CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s:contains()'),
            $this->getText(),
            Matchers::containsString($substr)
        );

        return $this;
    }


    /**
     * Asserts that substr is in the text value
     *
     * @param string $substr
     * @return CssElement
     */
    public function containsNotText(string $substr): CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s:not(contains())'),
            $this->getText(),
            Matchers::not(Matchers::containsString($substr))
        );

        return $this;
    }


    /**
     * Asserts that the inner text equals the value
     *
     * @param string|Matcher $value
     * @return CssElement
     */
    public function hasText($value): CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s.text()'),
            $this->getText(),
            is_string($value) ? Matchers::equalTo($value) : $value
        );

        return $this;
    }

    /**
     * Asserts that the (inner?) html equals the value
     *
     * @param string|Matcher $value
     * @return CssElement
     */
    public function hasHtml($value, bool $inner = true): CssElement
    {
        $this->assertThat(
            $this->generateMessage($inner ? '%s.innerHtml()' : '%s.outerHtml()'),
            $inner ? $this->getHtml() : $this->getOuterHtml(),
            is_string($value) ? Matchers::equalTo($value) : $value
        );
        return $this;
    }

    /**
     * Waits for the element with jquery and returns the waitedForElement
     *
     * note: needs @javascript in scenario
     *
     * @param int|null $time in ms
     *
     * @return CssElement
     */
    public function waitForExists(?int $time = NULL): CssElement
    {
        $time = $time ?: $this->defaultTimeout;

        list($result, $actualTime, $maxTime) = $this->waitFor(function () {
            return (bool) $this->getElement(true);
        }, $time);

        if ($result !== true) {
            throw new \LogicException(sprintf('Waiting for existence of element >> %s << timed out. Waited for %.2f / %.2f seconds.', $this->expression(),$actualTime / 1000, $maxTime / 1000));
        }

        return $this->exists();
    }

    /**
     * @param int|null $time
     */
    public function waitForExist(?int $time = NULL): CssElement
    {
        return $this->waitForExists($time);
    }

    /**
     * Waits for the element to be removed with jquery
     *
     * note: needs @javascript in scenario
     *
     * @param int|null $time in ms
     *
     * @return CssElement
     */
    public function waitForNotExists(?int $time = NULL): CssElement
    {
        list($result, $actualTime, $maxTime) = $this->waitFor(function () {
            return !$this->getElement(true);
        }, $time);

        if ($result !== true) {
            throw new \LogicException(sprintf('Waiting for element to NOT exist >> %s << timed out. Waited for %.2f / %.2f seconds.', $this->expression(),$actualTime / 1000, $maxTime / 1000));
        }

        return $this;
    }

    /**
     * Waits for an element to become visible
     *
     * @param int|null $time
     *
     * @return CssElement
     */
    public function waitForVisible(?int $time = NULL): CssElement
    {
        $time = $time ?: $this->defaultTimeout;
        list($result, $actualTime, $maxTime) = $this->waitFor(function () {
            $element = $this->getElement(true);

            if (!$element) {
                return false;
            }

            if ($element instanceof DocumentElement) {
                throw new \RuntimeException('The document does not support isVisible()');
            }

            $result = $element->isVisible();

            return $result;
        }, $time);

        if ($result !== true) {
            throw new \LogicException(sprintf('Waiting for visibility of element >> %s << timed out. Waited for %.2f / %.2f seconds.', $this->expression(),$actualTime / 1000, $maxTime / 1000));
        }

        return $this;
    }

    /**
     * @param int|null $time
     * @param bool $notExistingIsOkay
     */
    public function waitForNotVisible(?int $time = NULL, bool $notExistingIsOkay = false): CssElement
    {
        $time = $time ?: $this->defaultTimeout;
        list($result, $actualTime, $maxTime) = $this->waitFor(function () use ($notExistingIsOkay) {
            $element = $this->getElement(true);

            if (!$element) {
                return $notExistingIsOkay; // false: continue waiting
            }

            if ($element instanceof DocumentElement) {
                throw new \RuntimeException('The document does not support isVisible()');
            }

            return !$element->isVisible();
        }, $time);

        if ($result === false) {
            throw new \LogicException(sprintf('Waiting for element to disappear >> %s << timed out. Waited for %.2f/%.2f seconds.', $this->expression(),$actualTime / 1000, $maxTime / 1000));
        }

        return $this;
    }

    /**
     * Generates a message where the first string (%s) is a jquery-expression for the element itself
     *
     * @param string $format
     */
    private function generateMessage(string $format): string
    {
        $args = array_slice(func_get_args(), 1);
        array_unshift($args, $this->expression());
        return vsprintf($format, $args);
    }

    /**
     * Waits for $condition to become true
     *
     * @param \Closure $condition
     * @param int|null $time
     * @return array{mixed, float, float} the result, the actualTime waited in milliseconds, the maxTime to wait in milliseconds
     */
    public function waitFor(\Closure $condition, ?int $time = null): array
    {
        $time = $time ?: $this->defaultTimeout;

        $start = microtime(true);
        $end = $start + $time / 1000.0;

        do {
            $result = $condition();

            if (!$result) {
                usleep(100000); // 100 ms
            }
        } while (($now = microtime(true)) < $end && !$result);

        $actualTime = $now - $start;

        return [$result, $actualTime * 1000, $time];
    }

    private function getWebDriverSession(): \WebDriver\Session
    {
        $driver = $this->session->getDriver();

        if (!method_exists($driver, 'getWebDriverSession')) {
            throw new \RuntimeException('Driver does not support getWebDriverSession() - requires Selenium2Driver');
        }

        return $driver->getWebDriverSession();
    }

    /**
     * @param string $messagePart
     * @param mixed $value
     * @param Matcher $matcher
     * @return void
     */
    private function assertThat(string $messagePart, $value, Matcher $matcher)
    {
        MatcherAssert::assertThat(
            $messagePart,
            $value,
            $matcher
        );
    }

    public function mouseOver(): CssElement
    {
        $this->ensureElement()->mouseOver();

        return $this;
    }

    /**
     * Asserts that substr is in the value of the element
     *
     * @param string $substr
     */
    public function containsValue(string $substr) : CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s.val().contains()'),
            $this->getValue(),
            Matchers::containsString($substr)
        );

        return $this;
    }

    /**
     * @return array<int|string, mixed>|bool|string|null
     */
    public function getValue()
    {
        return $this->ensureElement()->getValue();
    }

    /**
     * @param string|bool|array<int|string, mixed> $value
     */
    public function setValue($value): CssElement
    {
        $this->ensureElement()->setValue($value);
        return $this;
    }

    /**
     * @param string|Matcher $value
     * @return CssElement
     */
    public function hasValue($value): CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s.val()'),
            $this->getValue(),
            is_string($value) ? Matchers::equalTo($value) : $value
        );

        return $this;
    }

    /**
     * use  jQery-Hack if nothing other works
     *
     * @param string $expression the part coming after the jQuery selector for this element
     * @return mixed
     */
    public function evaluatejQueryExpression(string $expression)
    {
        return $this->executeAsync(
            $this->defineWithJQuery()."\n".
            "\nwithJQuery(function(jQuery) {\n" .
                "done(".$this->expression() . $expression.");\n" .
            "\n})"
        );
    }


    /**
     * Runs asynchron code with jQuery
     * $code will be called with the first parameter as a jquery instance of the element() and a second with a callback done
     * if an argument is passed to done it will be return by this method
     * @param string $code
     * @return mixed
     */
    public function evaluateWithjQuery(string $code)
    {
        return $this->executeAsync(
            $this->defineWithJQuery()."\n".
            "\nwithJQuery(function(jQuery) {\n" .
                "    var todo = $code;\n".
                "    var jqueryElement = ".$this->expression()."\n\n".
                "    todo(jqueryElement, done);\n" .
            "\n})"
        );
    }

    public function scrollIntoView(bool $alignToTop): CssElement
    {
        $this->executeAsync(
            $this->defineWithJQuery()."\n".
            "\nwithJQuery(function(jQuery) {\n" .
                $this->expression().".get(0).scrollIntoView(".($alignToTop ? 'true' : 'false').")\n".
                "done();\n" .
            "\n})"
        );

        return $this;
    }

    /**
     * @param string[] $args
     * @return mixed
     * @throws \WebDriver\Exception
     */
    protected function executeAsync(string $script, array $args = [])
    {
        $script = 'var done = arguments['.count($args).']; ' . $script;

        $wdSession = $this->getWebDriverSession();

        try {
            return $wdSession->execute_async(['script' => $script, 'args' => $args]);
        } catch (\WebDriver\Exception $e) {
            if ($e->getCode() === \WebDriver\Exception::JAVASCRIPT_ERROR) {
                $e = new \RuntimeException("Failed to execute Javascript: \n\n".$script.' '.$e->getMessage(), 0, $e);
            }

            throw $e;
        }
    }

    protected function defineWithJQuery(): string
    {
        return /** @lang JavaScript */ <<<'JS'
            var withJQuery = function(callback) {
                if (!window.jQuery) {
                    if (typeof define === "function" && define.amd) {
                        require(['jquery'], callback)
                    } else {
                      
                        var headID = window.document.getElementsByTagName("head")[0]
                        var newScript = window.document.createElement('script')
                        newScript.type = 'text/javascript'
                        newScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js'
                        headID.appendChild(newScript)
                        
                        newScript.onload = newScript.onreadystatechange = function() {
                            if (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete') {
                                newScript.onload = newScript.onreadystatechange = null
                                callback(window.jQuery)
                            }
                        }
                    }
                } else {
                    callback(window.jQuery)
                }
            }
JS
            ;
    }
}
