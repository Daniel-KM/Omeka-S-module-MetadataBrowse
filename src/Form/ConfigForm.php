<?php

namespace MetadataBrowse\Form;

use Laminas\Form\Form;

class ConfigForm extends Form
{
    protected $globalSettings;

    public function init()
    {
        $this->add([
            'type' => 'checkbox',
            'name' => 'metadata_browse_use_globals',
            'options' => [
                        'label' => 'Use global configuration on admin side', // @translate
                    ],
            'attributes' => [
                'checked' => $this->globalSettings->get('metadata_browse_use_globals') ? 'checked' : '',
                'id' => 'metadata-browse-use-globals',
            ],
        ]);

        $this->add([
            'type' => 'checkbox',
            'name' => 'metadata_browse_direct_links',
            'options' => [
                'label' => 'Direct Links', // @translate
                'info' => 'Instead of printing a separate link for each value, apply the link to the value itself.', // @translate
            ],
            'attributes' => [
                'checked' => $this->globalSettings->get('metadata_browse_direct_links') ? 'checked' : '',
                'id' => 'metadata_browse_direct_links',
            ],
        ]);
        $this->add([
            'type' => 'checkbox',
            'name' => 'metadata_browse_fulltext_search',
            'options' => [
                'label' => 'Use full text search', // @translate
                'info' => 'Redirect to the site wide search page instead of the resource search page.', // @translate
            ],
            'attributes' => [
                'checked' => $this->globalSettings->get('metadata_browse_fulltext_search') ? 'checked' : '',
                'id' => 'metadata_browse_fulltext_search',
            ],
        ]);
    }

    public function setGlobalSettings($globalSettings)
    {
        $this->globalSettings = $globalSettings;
    }
}
