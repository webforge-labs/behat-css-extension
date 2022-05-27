# Behat css Extension

(not yet an extension, but usable)  
It extends the mink extension to actual usuable, chained css expressions

You need:
 - behat
 - behat/mink-extension
 - behat/mink-selenium2-driver
 - symfony/css-selector
 - hamcrestphp

## installation

```
compose require --dev webforge/behat-css-extension
```

you need to install (additional to your normal behat setup):

```
"behat/behat": "^3.5",
"behat/mink-extension": "^2.3",
"behat/mink-selenium2-driver": "^1.3",
```

use those dependencies in your dev-dependencies.


## usage

Use in your BehatFeatureContext:

```php
use Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Context\Context;
use Webforge\Behat\CssUtilitiesTrait;

class MyFeatureContext extends MinkContext implements Context
{
    use CssUtilitiesTrait;

    /**
     * @Given /^I wait for the page to load$/
     */
    public function iWaitForThePageToLoad()
    {
        $this->context = $this->css('.v-dialog--active')->exists();
        $this->context('.container .headline:contains("Wähle dein Fotobuch-Format")')->waitForVisible(5000);
    }


   /**
     * @When /^I click on the position button$/
     */
    public function iClickOnThePositionButton()
    {
        $this->css('.v-btn:contains("Position")')->exists()->click();
    }

    /**
     * @Given /^I click on the undock headline icon$/
     */
    public function iClickOnTheUndockHeadlineIcon()
    {
        $this->context('.v-btn:contains("Überschrift ablösen")')->exists()->click();
    }
    
    /**
     * @Given /^the headline is displayed in text$/
     */
    public function theHeadlineIsDisplayedInText()
    {
        $this->css('.pb-container.headline')->waitForExist() // selector is:  .pb-container.headline
            ->css('.headline.visible')->notExists()->end() // selector is: .pb-container.headline .headline.visible 
            ->css('.headline h1')->isNotVisible(); // selector is now: .pb-container.headline .headline h1 
    }
}
```

 - use $this->css($selector) to start from document, $selector is a valid css3 selector (supported by `symfony/css-selector`) 
 - set $this->context to whatever dialog/page/subarea you want to select elements
 - use $this->context() to start from context
 - use $this->resetContext() to reset context to document
 - use wait*, exists, isVisible, isNotVisible to make assertions against your selected element
 - move down with ->css() to find sub-selectors
 - move with ->end() back up to the chain, to the last css() call
 - use all(), getElements(), get* to retrieve elements, attributes or others an break the chain