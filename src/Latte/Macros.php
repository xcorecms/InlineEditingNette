<?php
declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Latte;

use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;
use Nette\Http\IRequest;

/**
 * @author Jakub Janata <jakubjanata@gmail.com>
 */
final class Macros extends MacroSet
{
    /** @var bool */
    private $useTranslator;

    /** @var IRequest */
    private $request;

    /**
     * @param Compiler $compiler
     * @param IRequest $request
     * @param bool $useTranslator
     */
    public function __construct(Compiler $compiler, IRequest $request, bool $useTranslator)
    {
        parent::__construct($compiler);
        $this->request = $request;
        $this->useTranslator = $useTranslator;

        $this->addMacro('inline', null, [$this, 'macroInline']);
        $this->addMacro('inlineNamespace', null, [$this, 'macroNamespace']);
        $this->addMacro('inlineEntityBlock', null, [$this, 'macroEntityBlock']);
        $this->addMacro('inlineEntity', null, [$this, 'macroEntity']);
        $this->addMacro('inlineEntityHtml', null, [$this, 'macroEntityHtml']);
        $this->addMacro('inlineField', null, [$this, 'macroEntityField']);
        $this->addMacro('inlineFieldHtml', null, [$this, 'macroEntityFieldHtml']);
        $this->addMacro('inlineSource', [$this, 'macroSource']);
    }

    /**
     * @param Compiler $compiler
     * @param IRequest $request
     * @param bool $useTranslator
     * @return Macros
     */
    public static function install(Compiler $compiler, IRequest $request, bool $useTranslator = false): Macros
    {
        return new self($compiler, $request, $useTranslator);
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
            'echo "data-inline-type=\"simple\" ";' .
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
        $node->openingCode = $writer->write('<?php $inlineNamespaceStack[] = %node.word; ?>');
        $node->closingCode = '<?php array_pop($inlineNamespaceStack); ?>';
    }

    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     */
    public function macroEntityField(MacroNode $node, PhpWriter $writer): void
    {
        $code = '$_inline_entity = end($inlineEntityStack); $_inline_property = %node.word;';
        $this->prepareEntityMacro($node, $writer, $code, 'entity-specific');
    }

    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     */
    public function macroEntity(MacroNode $node, PhpWriter $writer): void
    {
        $code = '[$_inline_entity, $_inline_property] = [%node.args];';
        $this->prepareEntityMacro($node, $writer, $code, 'entity-specific');
    }

    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     */
    public function macroEntityFieldHtml(MacroNode $node, PhpWriter $writer): void
    {
        $code = '$_inline_entity = end($inlineEntityStack); $_inline_property = %node.word;';
        $this->prepareEntityMacro($node, $writer, $code, 'entity');
    }

    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     */
    public function macroEntityHtml(MacroNode $node, PhpWriter $writer): void
    {
        $code = '[$_inline_entity, $_inline_property] = [%node.args];';
        $this->prepareEntityMacro($node, $writer, $code, 'entity');
    }

    /**
     * Base method for entity macros
     * @param MacroNode $node
     * @param PhpWriter $writer
     * @param string $code
     * @param string $type
     */
    protected function prepareEntityMacro(MacroNode $node, PhpWriter $writer, string $code, string $type)
    {
        $node->openingCode = $writer->write('<?php ' .
            $code .
            '$_inline_class = get_class($_inline_entity);' .
            '$_inline_id = $_inline_entity->id;' .
            '$_inline_content = $_inline_entity->{$_inline_property};?>');

        $node->attrCode .= ' <?php if($this->global->inlinePermissionChecker' .
            '->isEntityEditationAllowed($_inline_entity)) {' .
            'echo "id=\"inline_{$_inline_class}_{$_inline_id}_{$_inline_property}\" ";' .
            'echo "data-inline-type=\"' . $type . '\" ";' .
            'echo "data-inline-entity=\"$_inline_class\" ";' .
            'echo "data-inline-id=\"$_inline_id\" ";' .
            'echo "data-inline-property=\"$_inline_property\" "; ' .
            '} ?>';

        $node->innerContent = $writer->write('<?php echo %modify($_inline_content); ?>');
    }

    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     */
    public function macroEntityBlock(MacroNode $node, PhpWriter $writer): void
    {
        $entity = $writer->formatArgs();
        $node->openingCode = "<?php \$inlineEntityStack[] = $entity; ?>";
        $node->closingCode = '<?php array_pop($inlineEntityStack); ?>';
    }

    /**
     * @return string
     */
    public function macroSource(): string
    {
        $baseUrl = rtrim($this->request->getUrl()->getBaseUrl(), '/');

        return 'if($this->global->inlinePermissionChecker->isGlobalEditationAllowed()) {
                    echo \'<script src="' . $baseUrl . '/inline/inline.js" id="inline-editing-source"
                    data-source-css="' . $baseUrl . '/inline/inline.css"
                    data-source-tinymce-js="' . $baseUrl . '/inline/tinymce/tinymce.min.js"
                    data-source-gateway-url="' . $baseUrl . '/inline-editing"></script>\';}';
    }
}
