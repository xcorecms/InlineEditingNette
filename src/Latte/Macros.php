<?php
declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Latte;

use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
final class Macros extends MacroSet
{
    /** @var bool */
    private $useTranslator;

    /**
     * @param Compiler $compiler
     * @param bool $useTranslator
     */
    public function __construct(Compiler $compiler, bool $useTranslator)
    {
        parent::__construct($compiler);
        $this->useTranslator = $useTranslator;

        $this->addMacro('inline', null, [$this, 'macroInline']);
        $this->addMacro('inlineNamespace', null, [$this, 'macroNamespace']);
        $this->addMacro('inlineSource', [$this, 'macroSource']);
    }

    /**
     * @param Compiler $compiler
     * @param bool $useTranslator
     * @return Macros
     */
    public static function install(Compiler $compiler, bool $useTranslator = false): Macros
    {
        return new self($compiler, $useTranslator);
    }

    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     */
    public function macroInline(MacroNode $node, PhpWriter $writer): void
    {
        $args = explode(',', $node->args);

        $name = "\"$args[0]\"";
        $namespace = 'isset($inlineNamespaceStack) ? end($inlineNamespaceStack) : ""';
        $locale = $this->useTranslator ? '$this->global->inlineTranslatorProvider->getLocale()' : '""';

        // parse params
        foreach ($args as $arg) {
            $item = explode('=>', $arg);

            if (count($item) === 2) {
                switch (trim($item[0])) {
                    case 'namespace':
                        $namespace = '"' . trim($item[1]) . '"';
                        break;
                    case 'locale':
                        $locale = '"' . trim($item[1]) . '"';
                        break;
                }
            }
        }

        $node->openingCode = "<?php \$_inline_namespace=$namespace;\$_inline_locale=$locale;\$_inline_name=$name;?>";

        $node->attrCode .= ' <?php if($this->global->inlinePermissionChecker' .
            '->isItemEditationAllowed($_inline_namespace, $_inline_locale, $_inline_name)) {' .
            'echo "data-inline-name=\"$_inline_name\" ";' .
            'echo "data-inline-namespace=\"$_inline_namespace\" "; ' .
            'echo "data-inline-locale=\"$_inline_locale\" ";' .
            '} ?>';

        $node->innerContent = $writer->write(
            '<?php echo %modify(call_user_func_array($this->filters->inlineEditingContent, ' .
            '[$_inline_namespace, $_inline_locale, $_inline_name])); ?>'
        );
    }

    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     */
    public function macroNamespace(MacroNode $node, PhpWriter $writer): void
    {
        $namespace = $writer->formatArgs();
        $node->openingCode = '<?php $inlineNamespaceStack[] = "' . trim($namespace, "'") . '"; ?>';
        $node->closingCode = '<?php array_pop($inlineNamespaceStack); ?>';
    }

    /**
     * @return string
     */
    public function macroSource(): string
    {
        return 'if($this->global->inlinePermissionChecker->isGlobalEditationAllowed()) {
                    echo \'<script src="/inline/inline.js" id="inline-editing-source"
                    data-source-css="/inline/inline.css"
                    data-source-tinymce-js="/inline/tinymce/tinymce.min.js"
                    data-source-gateway-url="/inline-editing"></script>\';}';
    }
}
