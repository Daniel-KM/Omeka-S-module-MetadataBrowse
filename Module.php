<?php
namespace MetadataBrowse;

use MetadataBrowse\Form\ConfigForm;
use Omeka\Module\AbstractModule;
use Omeka\Entity\Job;
use Omeka\Entity\Value;
use Omeka\Event\Event;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\View\Renderer\PhpRenderer;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;

class Module extends AbstractModule
{

    public function upgrade($oldVersion, $newVersion,
        ServiceLocatorInterface $serviceLocator
    )
    {
        if (version_compare($newVersion, '0.1.1-alpha', '>')) {
            // $settings = $serviceLocator->get('Omeka\Settings');
            // $settings->delete('metadata_browse_properties');
        }
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }


    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
                'Omeka\Api\Representation\ValueRepresentation',
                Event::REP_VALUE_HTML,
                array($this, 'repValueHtml' )
                );
        
        $sharedEventManager->attach(
                array(
                'Omeka\Controller\Admin\Item',
                'Omeka\Controller\Admin\ItemSet',
                'Omeka\Controller\Site\Item',
                'Omeka\Controller\Site\ItemSet',
                ),
                Event::VIEW_SHOW_AFTER,
                array($this, 'addCSS')
                );
        
        $sharedEventManager->attach(
                array(
                'Omeka\Controller\Admin\Item',
                'Omeka\Controller\Admin\ItemSet',
                'Omeka\Controller\Site\Item',
                'Omeka\Controller\Site\ItemSet',
                ),
                Event::VIEW_BROWSE_AFTER,
                array($this, 'addCSS')
                );
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $params = $controller->params()->fromPost();
        if (isset($params['propertyIds'])) {
            $propertyIds = json_encode($params['propertyIds']);
        } else {
            $propertyIds = json_encode(array());
        }
        $globalSettings = $this->getServiceLocator()->get('Omeka\Settings');
        $globalSettings->set('metadata_browse_properties', $propertyIds);
        $globalSettings->set('metadata_browse_use_globals', $params['metadata_browse_use_globals']);
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $globalSettings = $this->getServiceLocator()->get('Omeka\Settings');
        $filteredPropertyIds = $globalSettings->get('metadata_browse_properties');
        $escape = $renderer->plugin('escapeHtml');
        $translator = $this->getServiceLocator()->get('MvcTranslator');
        $html = '';
        $html .= "<script type='text/javascript'>
        var filteredPropertyIds = $filteredPropertyIds;
        </script>
        ";
        $formElementManager = $this->getServiceLocator()->get('formElementManager');
        $form = $formElementManager->get(ConfigForm::class, array());
        $html .= $renderer->formCollection($form, false);
        $html .= "<div id='properties'><p>" . $escape($translator->translate("Choose properties from the sidebar to be searchable on the admin side.")) . "</p></div>";
        $html .= $renderer->partial('metadata-browse/property-template', array('escape' => $escape, 'translator' => $translator));
        $renderer->headScript()->appendFile($renderer->assetUrl('js/metadata-browse.js', 'MetadataBrowse'));
        $renderer->headLink()->appendStylesheet($renderer->assetUrl('css/metadata-browse.css', 'MetadataBrowse'));
        $renderer->htmlElement('body')->appendAttribute('class', 'sidebar-open');
        $selectorHtml = $renderer->propertySelector('Select properties to be searchable');
        $html .= "<div class='sidebar active'>$selectorHtml</div>";
        
        return $html;
    }
    
    public function addCSS($event)
    {
        $view = $event->getTarget();
        $view->headLink()->appendStylesheet($view->assetUrl('css/metadata-browse.css', 'MetadataBrowse'));
    }

    public function repValueHtml($event)
    {

        
        $target = $event->getTarget();
        $propertyId = $target->property()->id();

        $routeMatch = $this->getServiceLocator()->get('Application')
                        ->getMvcEvent()->getRouteMatch();
        $routeMatchParams = $routeMatch->getParams();
        //setup the route params to pass to the Url helper. Both the route name and its parameters go here
        $routeParams = [
                'action' => 'browse',
        ];
        if ($routeMatch->getParam('__ADMIN__')) {
            $globalSettings = $this->getServiceLocator()->get('Omeka\Settings');
            if($globalSettings->get('metadata_browse_use_globals')) {
                $filteredPropertyIds = json_decode($globalSettings->get('metadata_browse_properties'));
            } else {
                $api = $this->getServiceLocator()->get('Omeka\ApiManager');
                $sites = $api->search('sites', array())->getContent();
                $siteSettings = $this->getServiceLocator()->get('Omeka\SiteSettings');
                $filteredPropertyIds = [];
                foreach($sites as $site) {
                    $siteSettings->setSite($site);
                    $currentSettings = json_decode($siteSettings->get('metadata_browse_properties'));
                    $filteredPropertyIds = array_merge($currentSettings, $filteredPropertyIds);
                }
            }

            $routeParams['route'] = 'admin/default';
        } else {
            $filteredPropertyIds = json_decode($siteSettings->get('metadata_browse_properties'));
            $siteSlug = $routeMatch->getParam('site-slug');
            $routeParams['route'] = 'site';
            $routeParams['site-slug'] = $siteSlug . '/' . $target->resource()->getControllerName();
        }
        
        $url = $this->getServiceLocator()->get('ViewHelperManager')->get('Url');
        if (in_array($propertyId, $filteredPropertyIds)) {
            $controllerName = $target->resource()->getControllerName();
            $routeParams['controller'] = $controllerName;
            
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            $params = $event->getParams();
            $html = $params['html'];
            switch ($target->type()) {
                case 'resource':
                    $searchTarget = $target->valueResource()->id();
                    $searchUrl = $this->resourceSearchUrl($url, $routeParams, $propertyId, $searchTarget);
                    break;
                case 'uri':
                    $searchTarget = $target->uri();
                    $searchUrl = $this->uriSearchUrl($url, $routeParams, $propertyId, $searchTarget);
                    break;
                case 'literal':
                    $searchTarget = $html;
                    $searchUrl = $this->literalSearchUrl($url, $routeParams, $propertyId, $searchTarget);
                    break;
                default:
                    $resource = $target->valueResource();
                    $uri = $target->uri();
                    if ($resource) {
                        $searchTarget = $target->valueResource()->id();
                        $searchUrl = $this->resourceSearchUrl($url, $routeParams, $propertyId, $searchTarget);
                    } else if ($uri) {
                        $searchUrl = $this->uriSearchUrl($url, $routeParams, $propertyId, $uri);
                    } else {
                        $searchTarget = $html;
                        $searchUrl = $this->literalSearchUrl($url, $routeParams, $propertyId, $searchTarget);
                    }
            }

            switch ($controllerName) {
                case 'item':
                    $controllerLabel = 'items';
                break;
                case 'item-set':
                    $controllerLabel = 'item sets';
                break;
                default:
                    $controllerLabel = $controllerName;
                break;
            }
            $text = $translator->translate(sprintf("See all %s with this value", $controllerLabel));
            $link = "<a class='metadata-browse-link' href='$searchUrl'>$text</a>";
            $event->setParam('html', "$html $link");
        }
    }

    protected function literalSearchUrl($url, $routeParams, $propertyId, $searchTarget)
    {
        $searchUrl = $url($routeParams['route'],
              $routeParams,
              array('query' => array('Search' => '',
                                     "property[$propertyId][eq][]" => $searchTarget
                               )
                    )
          );
        return $searchUrl;
    }

    protected function uriSearchUrl($url, $routeParams, $propertyId, $searchTarget)
    {
        $searchUrl = $url($routeParams['route'],
              $routeParams,
              array('query' => array('Search' => '',
                                     "property[$propertyId][eq][]" => $searchTarget
                               )
                    )
          );
        return $searchUrl;
    }

    protected function resourceSearchUrl($url, $routeParams, $propertyId, $searchTarget)
    {
        $searchUrl = $url($routeParams['route'],
              $routeParams,
              array('query' => array('Search' => '',
                                     "property[$propertyId][res][]" => $searchTarget
                               )
                    )
          );
        return $searchUrl;
    }
}
