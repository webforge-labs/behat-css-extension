<?php
namespace Webforge\Behat;

use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\Node\FunctionNode;
use Symfony\Component\CssSelector\XPath\Extension\AbstractExtension;
use Symfony\Component\CssSelector\XPath\Translator;
use Symfony\Component\CssSelector\XPath\XPathExpr;

class SymfonyCssExtension extends AbstractExtension
{

    /**
     * SymfonyCssExtension constructor.
     */
    public function __construct($translator)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctionTranslators()
    {
        return array(
            'has' => array($this, 'translateHas')
        );
    }

    /**
     * @throws ExpressionErrorException
     */
    public function translateHas(XPathExpr $xpath, FunctionNode $function): XPathExpr
    {
        throw new \InvalidArgumentException('not yet');

        $arguments = $function->getArguments();
        foreach ($arguments as $token) {
            if (!($token->isString() || $token->isIdentifier())) {
                throw new ExpressionErrorException(
                    'Expected a single string or identifier for :contains(), got '
                    .implode(', ', $arguments)
                );
            }
        }

        return $xpath->addCondition(sprintf(
            'contains(string(.), %s)',
            Translator::getXpathLiteral($arguments[0]->getValue())
        ));
    }

    /**
     * Returns extension name.
     *
     * @return string
     */
    public function getName()
    {
        return CssElement::MINK_SELECTOR;
    }
}
