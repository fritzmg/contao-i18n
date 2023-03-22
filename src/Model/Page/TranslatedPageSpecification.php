<?php

declare(strict_types=1);

namespace Netzmacht\Contao\I18n\Model\Page;

use Contao\Model;
use Netzmacht\Contao\Toolkit\Data\Model\Specification;
use RuntimeException;

class TranslatedPageSpecification implements Specification
{
    /**
     * The language.
     */
    private string $language;

    /**
     * Page id of the page in the main language.
     */
    private int $mainLanguage;

    /**
     * @param int    $mainLanguage Page id of the page in the main language.
     * @param string $language     The current language.
     */
    public function __construct(int $mainLanguage, string $language)
    {
        $this->mainLanguage = $mainLanguage;
        $this->language     = $language;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException Method is not implemented yet.
     */
    public function isSatisfiedBy(Model $model): bool
    {
        throw new RuntimeException('isSatisfiedBy not implemented yet.');
    }

    /**
     * {@inheritdoc}
     */
    public function buildQuery(array &$columns, array &$values)
    {
        $columns[] = '.languageMain = ?';
        $columns[] = '(SELECT count(id) FROM tl_page r WHERE r.id=.hofff_root_page_id AND r.language=?) > 0';
        $values[]  = $this->mainLanguage;
        $values[]  = $this->language;
    }
}
