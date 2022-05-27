<?php
namespace Webforge\Behat;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;

trait CssUtilitiesTrait
{
    /**
     * @BeforeScenario
     * @param BeforeScenarioScope $scope
     */
    public function injectJQueryCssSelector(BeforeScenarioScope $scope)
    {
        $this->getSession()->getSelectorsHandler()->registerSelector(CssElement::MINK_SELECTOR, new SymfonyCssSelector());
    }
    /**
     * @var CssElement
     */
    protected $context = NULL;

    /**
     * Sets the context to the main element (body)
     */
    protected function resetContext()
    {
        $this->context = NULL;
    }

    /**
     * @return CssElement
     */
    protected function getContext() : CssElement
    {
        if (!isset($this->context)) {
            $this->context = $this->css('body');
        }

        return $this->context;
    }

    /**
     * Creates a CssElement in context of the page
     *
     * can be used to find children, etc
     *
     * $this->css('body')->exists()
     *    ->css('nav a:contains("something")')->click();
     *
     * @param string $selector
     * @return CssElement
     */
    public function css($selector) : CssElement
    {
        return new CssElement($selector, $this->assertSession(), $this->getSession(), NULL);
    }

    /**
     * Like css() but executed on this->getContext() which might return body or the currently set context
     *
     * @param string $selector  a valid css selector
     * @return CssElement
     */
    public function context($selector) : CssElement
    {
        return $this->getContext()->css($selector);
    }
}
