<?php 

class siteRoutingAction extends waViewAction
{   
    
    public function execute()
    {   
        $this->setLayout(new siteDesignLayout());
        
        $route_id = waRequest::get('route');
        $routes = wa()->getRouting()->getRoutes(siteHelper::getDomain());
        if (!isset($routes[$route_id])) {
            throw new waException('Route not found', 404);
        }
        $route = $routes[$route_id];
        
		$app_id = $routes[$route_id]['app'];        
		$path = $this->getConfig()->getAppsPath($app_id, 'lib/config/site.php');
		$app = wa()->getAppInfo($app_id);
        if (file_exists($path)) {
		    $app['site'] = include($path);
		}
        if (isset($app['site']['params'])) {
            $params = $this->getParams($route_id, $app['site']['params'], $route);
        } else {
            $params = array();
        }
        $themes = siteHelper::getThemes($app_id);
        if (!isset($route['theme']) && $themes) {
            $route['theme'] = 'default';
        }
        if (!isset($route['theme_mobile']) && $themes) {
        	$route['theme_mobile'] = $route['theme'];
        }
        
        if (!isset($route['locale'])) {
        	$route['locale'] = '';
        }        
        
        if (!isset($route['_name'])) {
            if ($app_id == 'site') {
                if ($title = siteHelper::getDomain('title')) {
                    $route['_name'] = $title;
                } else {
                    $app_settings_model = new waAppSettingsModel();
                    $route['_name'] = $app_settings_model->get('webasyst', 'name', 'Webasyst');
                }
            } else {
                $route['_name'] = $app['name'];
            }
        }

        $this->view->assign('route_id', $route_id);
        $this->view->assign('route', $route);
        $this->view->assign('params', $params);
        $this->view->assign('app_id', $app_id);
        $this->view->assign('app', $app);
        $this->view->assign('domain_id', siteHelper::getDomainId());
        $this->view->assign('domain', siteHelper::getDomain());
        $this->view->assign('locales', array('' => _w('Auto')) + waLocale::getAll('name'));
    }
    
    protected function getParams($route_id, $config, $values)
    {
        $result = array();
        foreach ($config as $id => $info) {
            $info['id'] = $id;
            $result[$id] = array(    
                'name' => $info['name'],
                'value' => $this->getHTML($route_id, $info, isset($values[$id]) ? $values[$id]: null)
            );
        }
        return $result;
    }
    
    protected function getHTML($route_id, $info, $value) 
    {
        $html = '';
        switch ($info['type']) {
            case 'select':
                $html = '<select name="params['.$info['id'].']">';
                foreach ($info['items'] as $k => $v) {
                    $html .= '<option '.($k == $value ? 'selected="selected" ' : '').'value="'.$k.'">'.htmlspecialchars($v).'</option>';
                } 
                $html .= '</select>';
                return $html;
            case 'radio':
                foreach ($info['items'] as $k => $v) {
                    $html .= '<label class="s-label-with-check">'.
                             	'<input type="radio" name="params['.$info['id'].']" value="'.$k.'" />'.htmlspecialchars($v).'</label>';
                }
                return $html;
            case 'checkbox':
                foreach ($info['items'] as $k => $v) {
                    $html .= '<label class="s-label-with-check">'.
                                '<input type="checkbox" name="params['.$info['id'].'][]" value="'.$k.'" />'.htmlspecialchars($v).'</label>';
                }
                return $html;
            case 'radio_select':
                $html = '<div id="s-radio-select-'.$route_id.'">';
                foreach ($info['items'] as $k => $v) {
                    $html .= '<label class="s-label-with-check">'.
                             	'<input type="radio" '.
                                    ($value == $k || isset($v['items'][$value]) ? 'checked="checked"' : '').
                                    ' name="params['.$info['id'].']" value="'.$k.'" />'.
                                htmlspecialchars($v['name']).' <span class="hint">'.$v['description'].'</span>'.
                             '</label>';
                    if (isset($v['items'])) {
                        $html .= '<select '.($value == $k || isset($v['items'][$value]) ? '' : 'disabled="disabled"').' name="params['.$info['id'].']">';
                        foreach ($v['items'] as $k => $v) {
                            $html .= '<option '.($k == $value ? 'selected="selected" ' : '').'value="'.$k.'">'.htmlspecialchars($v).'</option>';
                        } 
                        $html .= '</select>';
                    }
                }
                $html .= '</div>';
                $html .= '<script type="text/javascript">
                $("#s-radio-select-'.$route_id.' input[type=radio]").change(function () {
                	if ($(this).is(":checked")) {
                		$("#s-radio-select-'.$route_id.' select").attr("disabled", "disabled");
                		$(this).parent().next("select").removeAttr("disabled");
                	}
                });
                </script>';
                return $html;
            default:
                return '';
        }    
    }    
}