<?php

use MediaWiki\MediaWikiServices;

class SkinLift extends SkinMustache {

	private $additionalTemplateData = [];

	/**
	* 
	* See User contributed notes:https://www.php.net/manual/en/function.array-merge-recursive.php
	* 
    * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
    * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
    */
    private function array_merge_recursive_distinct( array &$array1, array &$array2 ) {
      $merged = $array1;
    
      foreach ( $array2 ?? [] as $key => &$value )
      {
        if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
        {
          $merged [$key] = $this->array_merge_recursive_distinct ( $merged [$key], $value );
        }
        else
        {
          $merged [$key] = $value;
        }
      }
    
      return $merged;
    }

	public function setTemplateVariable( $value ) {
		$this->additionalTemplateData = $this->array_merge_recursive_distinct($this->additionalTemplateData, $value);
	}
	
	/**
	 * Extends getPortletData function
	 */
	protected function getPortletData( $label, array $urls = [] ) {
		$data = parent::getPortletData( $label, $urls );
#        unset($data['html-items']);

		// Sanitize and standardize links
		foreach ( $urls ?? [] as $key => $item ) {
		    
		    $item['text'] ??= (!is_int($key) ? wfMessage( $key )->text() : '');
            $className = $item['class'] ?? [];
            $isSelected = is_array( $className ) ? in_array( 'selected', $className )
                : $className === 'selected';

		    if ( $isSelected ) {
                if ( is_array( $className ) ) {
                    $className[] = 'active';
                } else {
                    $className .= ' active';
                }
            }
            $item['class'] = $className;
		    
            $links = $item['links'] ?? null;
		    if ($links) {
		        $item = $item['links'][0];
		        unset($item['links']);
		    }
		
			$data['array-links'][] = [is_int($key) ? $item['text'] : $key => $item];
		}

		return $data;
	}


	
    /**
     * Extends the getTemplateData function
     */
    public function getTemplateData() {

        // Get from parent
        $data = parent::getTemplateData();
        
        // Delete data-user-menu (duplicated with data-personal)
        unset($data['data-portlets']['data-user-menu']);

        // Promote toolbox to it's own data-toolbox portlet and promote the rest to a data-navigation portlet
        if ($data['data-portlets-sidebar']['data-portlets-first']['id'] == 'p-tb') {
            
            $data['data-portlets']['data-toolbox'] = $data['data-portlets-sidebar']['data-portlets-first'];
            
        } else {

            $data['data-portlets']['data-toolbox'] = array_pop($data['data-portlets-sidebar']['array-portlets-rest']);
            
            $data['data-portlets']['data-navigation']['label'] = 'Site navigation';
            $data['data-portlets']['data-navigation']['array-sections'][] = $data['data-portlets-sidebar']['data-portlets-first'];
            
            foreach ($data['data-portlets-sidebar']['array-portlets-rest'] ?? [] as &$portlet) {
                $data['data-portlets']['data-navigation']['array-sections'][] = $portlet;
            } unset($portlet);
            
            foreach ($data['data-portlets']['data-navigation']['array-sections'] ?? [] as &$section) {
                foreach ($section['array-links'] ?? [] as $key => &$link) {
                    $section['array-links'][$key] = $link[key($link)];
                } unset($link);
            } unset($section);
            
        }
        unset($data['data-portlets-sidebar']);
        // Remove toolbox from navigation
        unset($data['data-portlets']['data-navigation']['array-links']);
        
        // Move edit to actions portlet
        foreach ($data['data-portlets']['data-views']['array-links'] ?? [] as $key => $link) {
            if (in_array(key($data['data-portlets']['data-views']['array-links'][$key]), ['ve-edit', 'edit'])) {
                array_unshift($data['data-portlets']['data-actions']['array-links'], $link);
                unset($data['data-portlets']['data-views']['array-links'][$key]);
            }
        } unset($key); unset($link);
        // Undo the key order effects of the above unset on the key
        $data['data-portlets']['data-views']['array-links'] = array_values($data['data-portlets']['data-views']['array-links'] ?? []);

        // Move login/logout to own portlet
        $data['data-portlets']['data-login'] = [
            'id' => "p-login",
            'class' => "mw-portlet mw-portlet-login",
            'html-tooltip' => "",
            'html-after-portal' => "",
            'array-links' => []
        ];
        foreach ($data['data-portlets']['data-personal']['array-links'] ?? [] as $key => $link) {
            if (in_array(key($data['data-portlets']['data-personal']['array-links'][$key]), ['login', 'logout'])) {
                array_unshift($data['data-portlets']['data-login']['array-links'], $link);
                unset($data['data-portlets']['data-personal']['array-links'][$key]);
            }
        } unset($key); unset($link);
        // Undo the key order effects of the above unset on the key
        $data['data-portlets']['data-personal']['array-links'] = array_values($data['data-portlets']['data-personal']['array-links'] ?? []);

        // Clean out all empty data-portlets
        foreach ($data['data-portlets'] ?? [] as $key => $portlet) {
            if (empty($portlet['array-links']) && empty($portlet['array-sections']) ) {
                unset($data['data-portlets'][$key]);
            }
        } unset($portlet);
        
        $data += $this->additionalTemplateData;
        return $data;
    }

	/**
	 * Extends getCategories function
	 */
	public function getCategories() {
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		$showHiddenCats = $userOptionsLookup->getBoolOption( $this->getUser(), 'showhiddencats' );

        $cats = $this->getOutput()->getCategories();
        $catLinks = [];
        foreach ($cats ?? [] as $cat) {
            $catTitle = Title::makeTitleSafe( NS_CATEGORY, $cat );
            $catLinks['array-links'][] = [
                'text' => $catTitle->getText(),
                'href' => $catTitle->isKnown() ? $catTitle->getLinkURL() : $catTitle->getEditURL(),
                'exists' => $catTitle->isKnown()
            ];

        };

		return $catLinks;
	}
	
}
