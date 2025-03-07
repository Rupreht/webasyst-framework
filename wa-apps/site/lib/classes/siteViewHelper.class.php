<?php

class siteViewHelper extends waAppViewHelper
{
    public function pages($parent_id = 0, $with_params = true)
    {
        if (is_bool($parent_id)) {
            $with_params = $parent_id;
            $parent_id = 0;
        }
        try {
            $domain_model = new siteDomainModel();
            $domain = $domain_model->getByName(waSystem::getInstance()->getRouting()->getDomain(null, true));

            $page_model = new sitePageModel();
            $sql = "SELECT id, parent_id, name, title, full_url, url, create_datetime, update_datetime FROM ".$page_model->getTableName().'
                    WHERE domain_id = i:domain_id AND route = s:route AND status = 1 ORDER BY sort';

            if (wa()->getApp() == 'site') {
                $route = wa()->getRouting()->getRoute('url');
                if (wa()->getEnv() !== 'frontend' || waRequest::param('page')) {
                    $url = $this->wa()->getConfig()->getRootUrl(false);
                    $url .= wa()->getRouting()->getRootUrl();
                    if (waRequest::param('page')) {
                        $route = '*';
                    }
                } else {
                    $url = $this->wa()->getAppUrl(null, true);
                }
            } else {
                $routes = wa()->getRouting()->getByApp('site', $domain['name']);
                if ($routes) {
                    $route = current($routes);
                    $route = $route['url'];
                    $url = wa()->getRootUrl(false, true).waRouting::clearUrl($route);
                } else {
                    return array();
                }
            }

            $pages = $page_model->query($sql, array(
                'domain_id' => $domain['id'],
                'route' => $route)
            )->fetchAll('id');

            if ($with_params) {
                $page_params_model = new sitePageParamsModel();
                $data = $page_params_model->getByField('page_id', array_keys($pages), true);
                foreach ($data as $row) {
                    $pages[$row['page_id']][$row['name']] = $row['value'];
                }
            }
            foreach ($pages as &$page) {
                $page['url'] = $url.$page['full_url'];
                if (!isset($page['title']) || !$page['title']) {
                    $page['title'] = $page['name'];
                }
                foreach ($page as $k => $v) {
                    if ($k != 'content') {
                        $page[$k] = htmlspecialchars((string)$v);
                    }
                }
            }
            unset($page);
            // make tree
            foreach ($pages as $page_id => $page) {
                if ($page['parent_id'] && isset($pages[$page['parent_id']])) {
                    $pages[$page['parent_id']]['childs'][] = &$pages[$page_id];
                }
            }
            if ($parent_id) {
                return isset($pages[$parent_id]['childs']) ? $pages[$parent_id]['childs'] : array();
            }
            foreach ($pages as $page_id => $page) {
                if ($page['parent_id'] && $page_id != $parent_id) {
                    unset($pages[$page_id]);
                }
            }

            /**
             * Event for {$wa->site->pages()}
             * @since 2.6.0
             * @param array $pages
             *
             * @event view_pages
             */

            $this->wa()->event('view_pages', $pages);

            return $pages;
        } catch (Exception $e) {
            return array();
        }
    }

    public function getThemeFileTemplate($template_name = 'header.html', $app_id = 'site', $theme_id = null, $vars = [])
    {
        try {
            if (!$theme_id) {
                $theme_id = waRequest::getTheme();
            }
            $theme = new waTheme($theme_id, $app_id);
            $view = new siteEditorView(wa($app_id));
            if(!$view->setThemeTemplate($theme, $template_name)) {
                return '';
            }
            if ($vars) {
                $view->assign($vars);
            }
            return $view->fetch($template_name);

        } catch (Exception $e) {

            if (waSystemConfig::isDebug() && wa()->getUser()->get('is_user') > 0) {
                return $e->getMessage()."\n<br><br>\n<pre>".$e."</pre>";
            }
        }
    }
}