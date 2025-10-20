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

or (to use selenium 4)
```
behat/behat
behat/mink
friends-of-behat/mink-extension
friends-of-behat/symfony-extension
lullabot/mink-selenium2-driver
```

use those dependencies in your dev-dependencies.

## docker

```yaml
services:
    selenium:
        image: selenium/hub:4.27
        environment:
            - TZ=Europe/Berlin

    selenium_chrome:
        image: selenium/node-chrome:4.27
        shm_size: 2gb
        volumes:
            - /dev/shm:/dev/shm
        depends_on:
            - selenium
        environment:
            - SE_EVENT_BUS_HOST=selenium
            - SE_EVENT_BUS_PUBLISH_PORT=4442
            - SE_EVENT_BUS_SUBSCRIBE_PORT=4443
            - NODE_MAX_INSTANCES=3
            - NODE_MAX_SESSION=6
            - TZ=Europe/Berlin
            - LANG_WHICH=de
            - LANG_WHERE=DE
            - LANGUAGE=de_DE.UTF-8
            - LANG=de_DE.UTF-8
```

## behat.yaml

```yaml
default:
    extensions:
        FriendsOfBehat\SymfonyExtension:
            kernel:
                class: \Kernel
                environment: test

        Behat\MinkExtension:
            base_url: http://web.local.testing
            sessions:
                selenium:
                    selenium2:
                        browser: chrome
                        wd_host: "selenium:4444/wd/hub"
                        capabilities:
                            extra_capabilities:
                                acceptSslCerts: true
                                acceptInsecureCerts: true
                            chrome:
                                switches: [ "window-size=1280,900", "ignore-certificate-errors", "no-sandbox" ]
                                prefs:
                                    intl:

```

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
