<?php
namespace Webforge\Behat;

use Symfony\Component\CssSelector\XPath\Extension\AbstractExtension;

class SymfonyCssExtension extends AbstractExtension
{

    /**
     * SymfonyCssExtension constructor.
     */
    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctionTranslators(): array
    {
        return [
            'has' => [$this, 'translateHas']
        ];
    }

    /**
     * Returns extension name.
     *
     * @return string
     */
    public function getName(): string
    {
        return CssElement::MINK_SELECTOR;
    }
}
