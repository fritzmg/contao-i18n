<?php

/**
 * Contao I18n provides some i18n structures for easily l10n websites.
 *
 * @package    dev
 * @author     David Molineus <david.molineus@netzmacht.de>
 * @copyright  2015 netzmacht creative David Molineus
 * @license    LGPL 3.0
 * @filesource
 *
 */

namespace Netzmacht\Contao\I18n\Page;

use Contao\Module;
use Contao\PageModel;
use Contao\PageRegular;
use Netzmacht\Contao\I18n\I18nTrait;


/**
 * Class Regular
 * 
 * @package Netzmacht\Contao\I18n\Page
 */
class Regular extends PageRegular
{
    use I18nTrait;

    /**
     * @inheritDoc
     */
    public static function getFrontendModule($moduleId, $column = 'main')
    {
        if (!is_object($moduleId) && !strlen($moduleId)) {
            return '';
        }

        $currentPage = static::getServiceContainer()->getPageProvider()->getPage();
        $i18n        = static::getI18n();

        if (!$i18n->isI18nPage($currentPage->type)) {
            return parent::getFrontendModule($moduleId, $column);
        }

        $basePage = $i18n->getBasePage($currentPage);
        if (!$basePage) {
            return '';
        }

        if ($moduleId == 0) {
            // Articles
            return self::getArticles($basePage, $column);
        } else {
            return self::generateFrontendModul($moduleId, $basePage, $column);
        }
    }

    /**
     * Get the articles of a page.
     *
     * @param PageModel $basePage Base page.
     * @param string    $column   Article column.
     *
     * @return string
     */
    private static function getArticles($basePage, $column = 'main')
    {
        // Show a particular article only
        if ($basePage->type == 'regular' && \Input::get('articles')) {
            list($section, $article) = explode(':', \Input::get('articles'));

            if ($article === null) {
                $article = $section;
                $section = 'main';
            }

            if ($section == $column) {
                $articleModel = \ArticleModel::findByIdOrAliasAndPid($article, $basePage->id);

                // Send a 404 header if the article does not exist
                if ($articleModel === null) {
                    // Do not index the page
                    $basePage->noSearch = 1;
                    $basePage->cache    = 0;

                    header('HTTP/1.1 404 Not Found');

                    $translator = static::getServiceContainer()->getTranslator();

                    return '<p class="error">' . $translator->translate('invalidPage', 'MSC', [$article]) . '</p>';
                }

                // Add the "first" and "last" classes (see #2583)
                $articleModel->classes = array('first', 'last');

                return static::getArticle($articleModel);
            }
        }

        // HOOK: trigger the article_raster_designer extension
        if (in_array('article_raster_designer', \ModuleLoader::getActive())) {
            return \RasterDesigner::load($basePage->id, $column);
        }

        // Show all articles (no else block here, see #4740)
        $articles = \ArticleModel::findPublishedByPidAndColumn($basePage->id, $column);

        if ($articles === null) {
            return '';
        }

        $return    = '';
        $count     = 0;
        $multiMode = ($articles->count() > 1);
        $last      = $articles->count() - 1;

        while ($articles->next()) {
            $articleModel = $articles->current();

            // Add the "first" and "last" classes (see #2583)
            if ($count == 0 || $count == $last) {
                $arrCss = array();

                if ($count == 0) {
                    $arrCss[] = 'first';
                }

                if ($count == $last) {
                    $arrCss[] = 'last';
                }

                $articleModel->classes = $arrCss;
            }

            $return .= static::getArticle($articleModel, $multiMode, false, $column);
            ++$count;
        }

        return $return;
    }

    private static function generateFrontendModul($moduleId, $basePage, $column)
    {
        // Other modules
        if (is_object($moduleId)) {
            $moduleModel = $moduleId;
        } else {
            $moduleModel = \ModuleModel::findByPk($moduleId);

            if ($moduleModel === null) {
                return '';
            }
        }

        // Check the visibility (see #6311)
        if (!static::isVisibleElement($moduleModel)) {
            return '';
        }

        $moduleClass = \Module::findClass($moduleModel->type);

        // Return if the class does not exist
        if (!class_exists($moduleClass)) {
            static::log(
                sprintf('Module class "%s" (module "%s") does not exist', $moduleClass, $moduleModel->type),
                __METHOD__,
                TL_ERROR
            );
            return '';
        }

        $moduleModel->typePrefix = 'mod_';
        /** @var Module $module */
        $module = new $moduleClass($moduleModel, $column);
        $buffer = $module->generate();

        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['getFrontendModule']) && is_array($GLOBALS['TL_HOOKS']['getFrontendModule'])) {
            foreach ($GLOBALS['TL_HOOKS']['getFrontendModule'] as $callback) {
                $buffer = static::importStatic($callback[0])->$callback[1]($moduleModel, $buffer, $module);
            }
        }

        // Disable indexing if protected
        if ($module->protected && !preg_match('/^\s*<!-- indexer::stop/', $buffer)) {
            $buffer = "\n<!-- indexer::stop -->". $buffer ."<!-- indexer::continue -->\n";
        }

        return $buffer;
    }
}