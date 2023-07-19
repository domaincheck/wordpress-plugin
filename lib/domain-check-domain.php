<?php
/**
 * Created by IntelliJ IDEA.
 * User: joey
 * Date: 3/4/17
 * Time: 12:27 PM
 */

class DomainCheckDomain {



	public $domain_id = null;
	public $domain_url = null;
	public $user_id = 0;
	public $status = 0;
	public $data_added = 0;
	public $search_data = 0;
	public $domain_watch = 0;
	public $domain_last_check = 0;
	public $domain_next_check = 0;
	public $domain_created = 0;
	public $domain_expires = 0;
	public $owner = '';
	public $domain_settings = null;
	public $cache = null;

	public function __construct( $data = array() ) {

	}

}