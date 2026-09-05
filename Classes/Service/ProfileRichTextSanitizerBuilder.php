<?php

declare(strict_types=1);

namespace FGTCLB\AcademicPersonsEdit\Service;

use TYPO3\HtmlSanitizer\Behavior;
use TYPO3\HtmlSanitizer\Behavior\Attr\UriAttrValueBuilder;
use TYPO3\HtmlSanitizer\Behavior\CdataSection;
use TYPO3\HtmlSanitizer\Behavior\Comment;
use TYPO3\HtmlSanitizer\Behavior\Tag;
use TYPO3\HtmlSanitizer\Builder\CommonBuilder;

final class ProfileRichTextSanitizerBuilder extends CommonBuilder
{
    protected function createBehavior(): Behavior
    {
        return (new Behavior())
            ->withFlags(
                Behavior::REMOVE_UNEXPECTED_CHILDREN
                | Behavior::ENCODE_INVALID_PROCESSING_INSTRUCTION,
            )
            ->withName('academic-persons-profile-rich-text')
            ->withoutNodes(new Comment())
            ->withoutNodes(new CdataSection())
            ->withTags(
                new Tag('br'),
                new Tag('p', Tag::ALLOW_CHILDREN),
                new Tag('strong', Tag::ALLOW_CHILDREN),
                new Tag('em', Tag::ALLOW_CHILDREN),
                new Tag('ul', Tag::ALLOW_CHILDREN),
                new Tag('ol', Tag::ALLOW_CHILDREN),
                new Tag('li', Tag::ALLOW_CHILDREN),
                (new Tag('a', Tag::ALLOW_CHILDREN))->addAttrs($this->hrefAttr),
            );
    }

    /**
     * @return array{href: UriAttrValueBuilder}
     */
    protected function createUriAttrValueBuilders(): array
    {
        return [
            'href' => (new UriAttrValueBuilder())
                ->allowLocal(true)
                ->allowSchemes('http', 'https', 'mailto', 'tel'),
        ];
    }
}
