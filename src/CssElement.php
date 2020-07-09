<?php
namespace Webforge\Behat;

use Behat\Mink\Element\ElementInterface;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use Behat\Mink\WebAssert;
use Hamcrest\Matcher;
use Hamcrest\MatcherAssert;
use Hamcrest\Matchers;
use LogicException;
use RuntimeException;

class CssElement
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

    /**b
     * @var array|string
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
     * @var CssElement
     */
    protected $context;

    /**
     * @var null
     */
    private $element;

    /**
     * Creates a new css assertion chain
     *
     * @param string|array $selector the css selector to start with
     * @param WebAssert    $assertSession
     * @param Session      $session
     * @param CssElement   $context
     */
    public function __construct($selector, WebAssert $assertSession, Session $session, CssElement $context = NULL)
    {
        $this->assert = $assertSession;
        $this->session = $session;
        $this->context = $context;

        $this->props = new \stdClass;

        if (is_array($selector)) {
            list ($this->subExpression, $this->element) = $selector;
            $this->subSelector = FALSE;
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
    public function cssSelector()
    {
        $selector = $this->context ? $this->context->cssSelector() . ' ' : '';

        if ($this->subSelector === FALSE) {
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
    public function expression()
    {
        $expression = $this->context ? $this->context->expression() : '';
        $expression .= $this->subExpression;

        return $expression;
    }


    /**
     * @return NodeElement|NULL
     */
    public function getElement($refresh = false)
    {
        if (!isset($this->element) || $refresh) {
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

        return $element;
    }

    /**
      * @return NodeElement[]
      */
    public function getElements()
    {
        if ($this->subSelector === FALSE) {
            throw new LogicException('Cannot get elements() from this selector, because it was not used purely with css selectors. This expression cannot be resolved to elements: ' . $this->expression());
        }

        return $this->contextElement()->findAll(self::MINK_SELECTOR, $this->subSelector);
    }


    /**
     * @return NodeElement|NULL
     */
    protected function contextElement()
    {
        return $this->context ? $this->context->ensureElement() : $this->session->getPage();
    }
    /**
     * @return CssElement[]
     */
    public function all()
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
    public function eq($index)
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
     * @param string|array $selector the css selector that will find a child/children of the current element
     *
     * @return CssElement
     */
    public function css($selector)
    {
        return new self($selector, $this->assert, $this->session, $this);
    }

    /**
     * Returns the last call to css() again
     *
     * note this is NOT the same as calling parent() !
     *
     * @return CssElement|NULL
     */
    public function end()
    {
        return $this->context;
    }

    /**
     * @return CssElement
     */
    public function parent()
    {
        $parentElement = $this->ensureElement()->find('xpath', '..');

        return $this->css(array('.parent()', $parentElement));
    }


    /**
     * Like the jquery closest, finds the first matching parent element by $filter (css selector)
     *
     * @param string $filter in css format
     *
     * @return CssElement
     */
    public function closest($filter)
    {
        $xpath = $this->session->getSelectorsHandler()
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
    public function exists()
    {
        $this->ensureElement();

        return $this;
    }

    /**
     * asserts that the element exists and is visible
     *
     * @return CssElement
     */
    public function isVisible()
    {
        $this->assertThat(
            $this->generateMessage('%s should be visible'),
            $this->ensureElement()->isVisible(),
            Matchers::equalTo(true)
        );

        return $this;
    }

    /**
     * @return CssElement
     */
    public function isNotVisible()
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
    public function count($numberOfElements)
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

    /**
     * @return int
     */
    public function getCount()
    {
        return count($this->getElements());
    }

    /**
     * Asserts that the selector matches 0 elements
     *
     * @return CssElement
     */
    public function notExists()
    {
        return $this->count(0);
    }

    /**
     * Clicks on the element
     *
     * @return CssElement
     */
    public function click()
    {
        try {
            $this->ensureElement()->click();
        } catch (\WebDriver\Exception\ElementNotVisible $e) {
            throw new \LogicException($this->generateMessage('tried to click on element, but it was not visible: %s'), 0, $e);
        }

        return $this;
    }

    /**
     * @param $locator
     * @param $value
     *
     * @return CssElement
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function fillField($locator, $value)
    {
        $this->ensureElement()->fillField($locator, $value);

        return $this;
    }

    /**
     * @param $locator
     *
     * @return CssElement
     *
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function checkField($locator)
    {
        $this->ensureElement()->checkField($locator);

        return $this;
    }

    /**
     * Get an HTML Attribute from the current element
     *
     * @param string $name of the attribute
     * @return string
     */
    public function getAttribute($name)
    {
        return $this->ensureElement()->getAttribute($name);
    }

    /**
     * Asserts that a certain html attribute has the value
     * @param string $name
     * @param string|Matcher $value to be matched
     * @return null|string
     */
    public function hasAttribute($name, $value)
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
    public function hasClass($className) : CssElement
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
    public function getHtml()
    {
        return $this->ensureElement()->getHtml();
    }

    /**
     * Returns the -inner- html of the matched element
     *
     * @return string
     */
    public function getOuterHtml()
    {
        return $this->ensureElement()->getOuterHtml();
    }

    /**
     * Returns the inner text of the matched element
     *
     * @return string
     */
    public function getText()
    {
        return $this->ensureElement()->getText();
    }

    /**
     * Returns the inner text of all matched elements
     *
     * @return string[]
     */
    public function getTexts()
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
    public function containsText($substr)
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
    public function containsNotText($substr)
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
     */
    public function hasText($value)
    {
        $this->assertThat(
            $this->generateMessage('%s.text()'),
            $this->getText(),
            is_string($value) ? Matchers::equalTo($value) : $value
        );
    }

    /**
     * Asserts that the (inner?) html equals the value
     *
     * @param string|Matcher $value
     */
    public function hasHtml($value, $inner = true)
    {
        $this->assertThat(
            $this->generateMessage($inner ? '%s.innerHtml()' : '%s.outerHtml()'),
            $inner ? $this->getHtml() : $this->getOuterHtml(),
            is_string($value) ? Matchers::equalTo($value) : $value
        );
    }

    /**
     * Waits for the element with jquery and returns the waitedForElement
     *
     * note: needs @javascript in scenario
     *
     * @param  int $time in ms
     *
     * @return CssElement
     */
    public function waitForExists($time = NULL): CssElement
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

    public function waitForExist($time = NULL): CssElement
    {
        return $this->waitForExists($time);
    }

    /**
     * Waits for the element to be removed with jquery
     *
     * note: needs @javascript in scenario
     *
     * @param  int $time in ms
     *
     * @return CssElement
     */
    public function waitForNotExists($time = NULL): CssElement
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
     * @param $time
     *
     * @return CssElement
     */
    public function waitForVisible($time = NULL): CssElement
    {
        $time = $time ?: $this->defaultTimeout;
        list($result, $actualTime, $maxTime) = $this->waitFor(function () {
            $element = $this->getElement(true);

            if (!$element) {
                return false;
            }

            $result = $element->isVisible();

            return $result;
        }, $time);

        if ($result !== true) {
            throw new \LogicException(sprintf('Waiting for visibility of element >> %s << timed out. Waited for %.2f / %.2f seconds.', $this->expression(),$actualTime / 1000, $maxTime / 1000));
        }

        return $this;
    }

    public function waitForNotVisible($time = NULL, $notExistingIsOkay = false): CssElement
    {
        $time = $time ?: $this->defaultTimeout;
        list($result, $actualTime, $maxTime) = $this->waitFor(function () use ($notExistingIsOkay) {
            $element = $this->getElement(true);

            if (!$element) {
                return $notExistingIsOkay; // false: continue waiting
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
     * @param $format
     * @return string
     */
    private function generateMessage($format)
    {
        $args = array_slice(func_get_args(), 1);
        array_unshift($args, $this->expression());
        return vsprintf($format, $args);
    }

    /**
     * Waits for $condition to become true
     *
     * @param \Closure $condition
     * @param null $time
     * @return array list($result, float, float) the result, the actualTime waited in milliseconds, the maxTime to wait in milliseconds
     */
    public function waitFor(\Closure $condition, $time = NULL)
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
        return $this->session->getDriver()->getWebDriverSession();
    }

    private function assertThat($messagePart, $value, $matcher)
    {
        MatcherAssert::assertThat(
            $messagePart,
            $value,
            $matcher
        );
    }

    /**
     * @return CssElement
     */
    public function mouseOver()
    {
        $this->ensureElement()->mouseOver();

        return $this;
    }

    /**
     * Asserts that substr is in the value of the element
     *
     * @param string $substr
     */
    public function containsValue($substr) : CssElement
    {
        $this->assertThat(
            $this->generateMessage('%s.val().contains()'),
            $this->getValue(),
            Matchers::containsString($substr)
        );

        return $this;
    }

    public function getValue()
    {
        return $this->ensureElement()->getValue();
    }

    /**
     * @param $value
     *
     * @return CssElement
     */
    public function setValue($value)
    {
        $this->ensureElement()->setValue($value);
        return $this;
    }

    /**
     * @param string|Matcher $value
     *
     * @return CssElement
     */
    public function hasValue($value)
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
    public function evaluatejQueryExpression($expression)
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

    public function scrollIntoView($alignToTop)
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

    protected function executeAsync($script, array $args = [])
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

    protected function defineWithJQuery()
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
