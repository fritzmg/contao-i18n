<?php

/**
 * Contao I18n provides some i18n structures for easily l10n websites.
 *
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2015-2018 netzmacht David Molineus
 * @license    LGPL-3.0-or-later
 * @filesource
 *
 */

declare(strict_types=1);

namespace Netzmacht\Contao\I18n\EventListener;

use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\Database;
use Contao\Date;
use Contao\FaqCategoryModel;
use Contao\FaqModel;
use Netzmacht\Contao\I18n\Model\Page\I18nPageRepository;
use Netzmacht\Contao\Toolkit\Data\Model\Repository;
use Netzmacht\Contao\Toolkit\Data\Model\RepositoryManager;

/**
 * Class SearchableI18nFaqUrlsListener
 *
 * @package Netzmacht\Contao\I18n\EventListener
 */
class SearchableI18nFaqUrlsListener extends AbstractSearchableUrlsListener
{
    /**
     * Model repository manager.
     *
     * @var RepositoryManager
     */
    private $repositoryManager;

    /**
     * I18n page repository.
     *
     * @var I18nPageRepository
     */
    private $i18nPageRepository;

    /**
     * Legacy contao database connection.
     *
     * @var Database
     */
    private $database;

    /**
     * Contao config adapter.
     *
     * @var Config|Adapter
     */
    private $config;

    /**
     * SearchableI18nNewsUrlsListener constructor.
     *
     * @param RepositoryManager  $repositoryManager  Model repository manager.
     * @param I18nPageRepository $i18nPageRepository I18n page repository.
     * @param Database           $database           Legacy contao database connection.
     * @param Config|Adapter     $config             Contao config adapter.
     */
    public function __construct(RepositoryManager $repositoryManager, I18nPageRepository $i18nPageRepository, Database $database, $config)
    {
        $this->repositoryManager  = $repositoryManager;
        $this->i18nPageRepository = $i18nPageRepository;
        $this->database           = $database;
        $this->config             = $config;
    }

    /**
     * {@inheritdoc}
     */
    protected function collectPages($pid = 0, string $domain = '', bool $isSitemap = false): array
    {
        $pages     = [];
        $root      = [];
        $processed = [];
        $time      = Date::floorToMinute();

        if ($pid > 0) {
            $root = $this->database->getChildRecords($pid, 'tl_page');
        }

        // Get all categories
        /** @var Repository|FaqCategoryModel $categoryRepository */
        $categoryRepository = $this->repositoryManager->getRepository(FaqCategoryModel::class);

        /** @var FaqModel|Repository $faqRepository */
        $faqRepository = $this->repositoryManager->getRepository(FaqModel::class);
        $collection    = $categoryRepository->findAll();

        // Walk through each category
        if ($collection !== null) {
            while ($collection->next()) {
                // Skip FAQs without target page
                if (!$collection->jumpTo) {
                    continue;
                }

                $translations = $this->i18nPageRepository->getPageTranslations($collection->jumpTo);

                foreach ($translations as $translation) {
                    // Skip FAQs outside the root nodes
                    if (!empty($root) && !\in_array($translation->id, $root)) {
                        continue;
                    }

                    // Get the URL of the jumpTo page
                    if (!isset($processed[$collection->jumpTo][$translation->id])) {
                        // The target page has not been published (see #5520)
                        if (!$translation->published
                            || ($translation->start != '' && $translation->start > $time)
                            || ($translation->stop != '' && $translation->stop <= ($time + 60))
                        ) {
                            continue;
                        }

                        if ($isSitemap) {
                            // The target page is protected (see #8416)
                            if ($translation->protected) {
                                continue;
                            }

                            // The target page is exempt from the sitemap (see #6418)
                            if ($translation->sitemap == 'map_never') {
                                continue;
                            }
                        }

                        // Generate the URL
                        $processed[$collection->jumpTo][$translation->id] = $translation->getAbsoluteUrl(
                            $this->config->get('useAutoItem') ? '/%s' : '/items/%s'
                        );
                    }

                    $strUrl = $processed[$collection->jumpTo][$translation->id];

                    // Get the items
                    $objItems = $faqRepository->findPublishedByPid($collection->id);

                    if ($objItems !== null) {
                        while ($objItems->next()) {
                            $pages[] = sprintf(
                                preg_replace('/%(?!s)/', '%%', $strUrl),
                                ($objItems->alias ?: $objItems->id)
                            );
                        }
                    }
                }
            }
        }

        return $pages;
    }
}
