<?php

use MediaWiki\MediaWikiServices;

/**
 * @file
 */
class LiftHooks {

    /**
     * Get "time ago"
     * 
     * Modified from: https://www.php.net/manual/en/dateinterval.format.php
    */
    public function formatDateDiff($start, $end=null) {
        if(!($start instanceof DateTime)) {
            $start = new DateTime($start);
        }
       
        if($end === null) {
            $end = new DateTime();
        }
       
        if(!($end instanceof DateTime)) {
            $end = new DateTime($start);
        }

        $interval = $end->diff($start);
        $suffix = ( $interval->invert ? ' ago' : '' );
      
        $doPlural = function($nb,$str){return $nb>1?$str.'s':$str;}; // adds plurals
       
        $format = [];
        if($interval->y !== 0) {
            $format[] = "%y ".$doPlural($interval->y, "year");
        }
        if($interval->m !== 0) {
            $format[] = "%m ".$doPlural($interval->m, "month");
        }
        if($interval->d !== 0) {
            $format[] = "%d ".$doPlural($interval->d, "day");
        }
        if($interval->h !== 0) {
            $format[] = "%h ".$doPlural($interval->h, "hour");
        }
        if($interval->i !== 0) {
            $format[] = "%i ".$doPlural($interval->i, "minute");
        }
        if($interval->s !== 0) {
            $format[] = "%s ".$doPlural($interval->s, "second");
        }
        if($interval->y === 0 && $interval->m === 0 && $interval->d === 0 && $interval->h === 0 && $interval->i === 0 && $interval->s === 0 && $interval->f !== 0) {
            return 'less than 1 second ago';
        }

        // We use the two biggest parts
        if(count($format) > 1) {
            $format = array_shift($format).", ".array_shift($format);
        } else {
            $format = array_pop($format);
        }
       
        // Prepend 'since ' or whatever you like
        return $interval->format($format) . $suffix;
    }

	/**
	 * Delete old users online
	*/ 
    private static function deleteOldVisitors() {
        global $wgUsersOnlineTimeout;

		$timeout = 3600;
		if ( is_numeric( $wgUsersOnlineTimeout ) ) {
			$timeout = $wgUsersOnlineTimeout;
		}
		$nowdatetime = date("Y-m-d H:i:s");
        $now = strtotime($nowdatetime);
        $old = $now - $timeout;
        $olddatetime = date("Y-m-d H:i:s", $old);

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $db = $lb->getConnectionRef( DB_PRIMARY  );

		$db->delete( 'users_online', [ 'user_id' => 0, 'end_session < "' . $olddatetime . '"' ], __METHOD__ );
        
    }

	/**
	 * Count anonymous users online
	*/ 
	private static function countAnonsOnline() {

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $db = $lb->getConnectionRef( DB_REPLICA  );

		$row = $db->selectRow(
			'users_online',
			'COUNT(*) AS cnt',
			'user_id = 0 AND end_session >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)',
			__METHOD__,
			'GROUP BY ip_address'
		);
		$anons = (int)$row->cnt;

		return $anons;
	}
	
	/**
	 * Count users online
	*/ 
	private static function countUsersOnline() {

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $db = $lb->getConnectionRef( DB_REPLICA  );

		$row = $db->selectRow(
			'users_online',
			'COUNT(*) AS cnt',
			'user_id != 0 AND end_session >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)',
			__METHOD__,
			'GROUP BY ip_address'
		);
		$users = (int)$row->cnt;
		
		return $users;
	}

	/**
	 * Get users online
	*/ 
	private static function getUsersOnline() {

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $db = $lb->getConnectionRef( DB_REPLICA  );

		$users = [
		    'array-currently' => [],
		    'array-recently' => []
		    ];

		$currently = $db->select(
        	[ 'users_online', 'user' ],
        	[ 'wiki_users_online.user_id', 'wiki_users_online.lastLinkURL', 'wiki_users_online.lastPageTitle', 'wiki_users_online.start_session', 'wiki_users_online.end_session', 'wiki_user.user_name' ],
        	[
        		'wiki_users_online.user_id != 0 AND wiki_users_online.end_session >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)'
        	],
        	__METHOD__,
        	['ORDER BY' => 'wiki_users_online.end_session DESC'],
        	[
        		'user' => [ 'INNER JOIN', [ 'wiki_user.user_id=wiki_users_online.user_id' ] ]
        	]
		);
		
		foreach ($currently as $r) {
		    $user = $r;
		    $user->ago = LiftHooks::formatDateDiff($user->end_session);
		    $user->online_since = LiftHooks::formatDateDiff($user->start_session);
		    $user->user_page = MediaWikiServices::getInstance()->getUserFactory()->newFromId( (int)$user->user_id )->getUserPage()->getLocalURL();
		    $users['array-currently'][] = (array) $user;
		}

		$currently = $db->select(
        	[ 'users_online', 'user' ],
        	[ 'wiki_users_online.user_id', 'wiki_users_online.lastLinkURL', 'wiki_users_online.lastPageTitle', 'wiki_users_online.start_session', 'wiki_users_online.end_session', 'wiki_user.user_name' ],
        	[
        		'wiki_users_online.user_id != 0 AND wiki_users_online.end_session <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE) AND wiki_users_online.end_session >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)',
        	],
        	__METHOD__,
        	['ORDER BY' => 'wiki_users_online.end_session DESC'],
        	[
        		'user' => [ 'INNER JOIN', [ 'wiki_user.user_id=wiki_users_online.user_id' ] ]
        	]
		);
		
		foreach ($recently as $r) {
		    $user = $r;
		    $user->ago = LiftHooks::formatDateDiff($user->end_session);
		    $user->offline_since = LiftHooks::formatDateDiff($user->end_session);
		    $user->user_page = MediaWikiServices::getInstance()->getUserFactory()->newFromId( (int)$user->user_id )->getUserPage()->getLocalURL();
		    $users['array-recently'][] = (array) $user;
		}

		return $users;
	}

	/**
	 * Update users online data
	*/ 
	public static function onBeforeInitialize( \Title &$title, $unused, \OutputPage $output, \User $user, \WebRequest $request, \MediaWiki $mediaWiki ) {
        
		// Delete old visitors
		LiftHooks::deleteOldVisitors();

        // Get previous and current sessions
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $db = $lb->getConnectionRef( DB_PRIMARY );

        $current_session = $request->getSessionId()->getId();
        
	    $last_session = null;
		if ($user->getId() != 0) {

            // Get user's last session_id
    		$row = $db->selectRow(
    			'users_online',
    			['session_id', 'start_session', 'end_session', 'prev_end_session'],
    			'user_id = '. $user->getId(),
    			__METHOD__,
    			''
    		);
    		
    		$last_session = $row->session_id;
		}
		
		$same_session = $last_session == $current_session;
		
		// row to insert to table
		$row = [
			'user_id' => $user->getId(),
			'session_id' => $request->getSessionId()->getId(),
			'ip_address' => $user->getName(),
			'lastLinkURL' => $title->getLinkURL(),
			'lastPageTitle' => $title->getText(),
			'start_session' => $same_session ? $row->start_session : date("Y-m-d H:i:s"),
			'end_session' => date("Y-m-d H:i:s"),
			'prev_end_session' => $same_session ? $row->prev_end_session : $row->end_session
		];
		$method = __METHOD__;
		$db->onTransactionIdle( function() use ( $db, $method, $row ) {
			$db->upsert(
				'users_online',
				$row,
				[ 'user_id', 'ip_address' ],
				[
				    'session_id' => $row['session_id'],
				    'lastLinkURL' => $row['lastLinkURL'],
				    'lastPageTitle' => $row['lastPageTitle'],
				    'start_session' => $row['start_session'],
				    'end_session' => $row['end_session'],
				    'prev_end_session' => $row['prev_end_session']
				],
				$method
			);
		});

	}

	/**
	 * Pass users online data to skin
	 */
	public static function onSkinTemplateNavigation_Universal( $skin, &$links ) {
	    
		if (method_exists($skin, 'setTemplateVariable')) {

            // Online
    		$portlet['data-extension-portlets']['data-online'] = [];
    		
		    $portlet['data-extension-portlets']['data-online'] = [
		        'id' => 'p-online',
        		'text'  => 'Online',
        		'href'  => '/w/Main page',
        		'title' => 'Online',
        		'id'    => 'n-online',
        		'online' => LiftHooks::countUsersOnline(),
        		'guests' => LiftHooks::countAnonsOnline(),
        		'members' => LiftHooks::getUsersOnline()
	        ];

            $skin->setTemplateVariable($portlet);
		}
        
	}
    
}