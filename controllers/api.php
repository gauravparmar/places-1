<?php defined('BASEPATH') OR exit('No direct script access allowed');
/* 
 * Places API : App : Social-Igniter
 *
 */
class Api extends Oauth_Controller
{
    function __construct()
    {
        parent::__construct();

        $this->load->library('places_igniter');
        
    	$this->form_validation->set_error_delimiters('', '');        
	}

    /* Install App */
	function install_get()
	{
		// Load
		$this->load->library('installer');
		$this->load->config('install');
		$this->load->dbforge();

		// Create Places Table
		$this->dbforge->add_key('place_id', TRUE);
		$this->dbforge->add_field(array(
			'place_id' => array(
				'type' 					=> 'INT',
				'constraint' 			=> 16,
				'unsigned' 				=> TRUE,
				'auto_increment'		=> TRUE
			),
			'content_id' => array(
				'type' 					=> 'INT',
				'constraint' 			=> '11',
				'null'					=> TRUE
			),
			'address' => array(
				'type'					=> 'VARCHAR',
				'constraint'			=> 128,
				'null'					=> TRUE
			),
			'district' => array(
				'type'					=> 'VARCHAR',
				'constraint'			=> 128,
				'null'					=> TRUE
			),
			'locality' => array(
				'type'					=> 'VARCHAR',
				'constraint'			=> 128,
				'null'					=> TRUE
			),
			'region' => array(
				'type'					=> 'VARCHAR',
				'constraint'			=> 128,
				'null'					=> TRUE
			),
			'country' => array(
				'type'					=> 'VARCHAR',
				'constraint'			=> 128,
				'null'					=> TRUE
			),
			'postal' => array(
				'type'					=> 'VARCHAR',
				'constraint'			=> 32,
				'null'					=> TRUE
			)										
		));

		$this->dbforge->create_table('places');


		// Settings & Create Folders
		$settings = $this->installer->install_settings('places', config_item('places_settings'));
	
		if ($settings == TRUE)
		{
            $message = array('status' => 'success', 'message' => 'Yay, the Places App was installed');
        }
        else
        {
            $message = array('status' => 'error', 'message' => 'Dang Places App could not be uninstalled');
        }		
		
		$this->response($message, 200);
	} 
	

    function all_get()
    {
        if ($places = $this->places_igniter->get_places_view('type', 'place', FALSE, 'all'))
        {
            $message = array('status' => 'success', 'data' => $places);
        }
        else
        {
            $message = array('status' => 'error', 'message' => 'Could not find any locations');
        }

        $this->response($message, 200);
    }

 	function create_authd_post()
	{
		// Validation Rules
	   	$this->form_validation->set_rules('address', 'Address', 'required');
	   	$this->form_validation->set_rules('title', 'Title', 'required');
	   	$this->form_validation->set_rules('locality', 'City', 'required');

		// Passes Validation
	    if ($this->form_validation->run() == true)
	    {
	    	if ($this->input->post('site_id')) $site_id = $this->input->post('site_id');
	    	else $site_id = config_item('site_id');
	    	
	    	$content_data = array(
	    		'site_id'			=> $site_id,
				'parent_id'			=> $this->input->post('parent_id'),
				'category_id'		=> $this->input->post('category_id'),
				'module'			=> 'places',
				'type'				=> 'place',
				'source'			=> $this->input->post('source'),
				'order'				=> 0,
	    		'user_id'			=> $this->oauth_user_id,
				'title'				=> $this->input->post('title'),
				'title_url'			=> form_title_url($this->input->post('title'), $this->input->post('title_url')),
				'content'			=> $this->input->post('content'),
				'details'			=> $this->input->post('details'),
				'canonical'			=> $this->input->post('canonical'),
				'access'			=> $this->input->post('access'),
				'comments_allow'	=> config_item('places_comments_allow'),
				'geo_lat'			=> $this->input->post('geo_lat'),
				'geo_long'			=> $this->input->post('geo_long'),
				'viewed'			=> 'Y',
				'approval'			=> 'Y',
				'status'			=> form_submit_publish($this->input->post('status'))  			
	    	);

			$activity_data = array(
				'title'			=> $this->input->post('title'),
				'address'		=> $this->input->post('address'),
				'locality'		=> $this->input->post('locality'),
				'region'		=> $this->input->post('region'),
				'geo_lat'		=> $this->input->post('geo_lat'),
				'geo_long'		=> $this->input->post('geo_long')
			);

			// Insert
			$result = $this->social_igniter->add_content($content_data, $activity_data);    	

	    	if ($result)
		    {
		    	// Process Tags
				if ($this->input->post('tags')) $this->places_igniter->process_tags($this->input->post('tags'), $result['content']->content_id);				
				
				// Add Place
				$place_data = array(
					'content_id'	=> $result['content']->content_id,
					'address'		=> $this->input->post('address'),
					'district'		=> $this->input->post('district'),
					'locality'		=> $this->input->post('locality'),
					'region'		=> $this->input->post('region'),
					'country'		=> $this->input->post('country'),
					'postal'		=> $this->input->post('postal')
				);
				
				$place = $this->places_igniter->add_place($place_data);
				
	        	$message = array('status' => 'success', 'message' => 'Awesome we created your place', 'data' => $result['content'], 'activity' => $result['activity'], 'place' => $place);
	        }
	        else
	        {
		        $message = array('status' => 'error', 'message' => 'Oops we were unable to create your place');
	        }	
		}
		else 
		{
	        $message = array('status' => 'error', 'message' => validation_errors());
		}

	    $this->response($message, 200);
	}
         
    
    function modify_authd_post()
    { 
		// Validation Rules
	   	$this->form_validation->set_rules('address', 'Address', 'required');
	   	$this->form_validation->set_rules('title', 'Title', 'required');
	   	$this->form_validation->set_rules('locality', 'City', 'required');

		// Passes Validation
	    if ($this->form_validation->run() == true)
	    {
	    	$content = $this->social_igniter->get_content($this->get('id'));
	    
			// Access Rules
		   	//$this->social_auth->has_access_to_modify($this->input->post('type'), $this->get('id') $this->oauth_user_id);
	   
	    	$content_data = array(
	    		'content_id'		=> $this->get('id'),
				'parent_id'			=> $this->input->post('parent_id'),
				'category_id'		=> $this->input->post('category_id'),
				'order'				=> $this->input->post('order'),
				'title'				=> $this->input->post('title'),
				'title_url'			=> form_title_url($this->input->post('title'), $this->input->post('title_url'), $content->title_url),
				'content'			=> $this->input->post('content'),
				'details'			=> $this->input->post('details'),
				'canonical'			=> $this->input->post('canonical'),
				'access'			=> $this->input->post('access'),
				'comments_allow'	=> $this->input->post('comments_allow'),
				'geo_lat'			=> $this->input->post('geo_lat'),
				'geo_long'			=> $this->input->post('geo_long'),
				'viewed'			=> 'Y',
				'approval'			=> 'Y',
				'status'			=> form_submit_publish($this->input->post('status'))
	    	);

			$activity_data = array(
				'title'			=> $this->input->post('title'),
				'address'		=> $this->input->post('address'),
				'locality'		=> $this->input->post('locality'),
				'region'		=> $this->input->post('region'),
				'geo_lat'		=> $this->input->post('geo_lat'),
				'geo_long'		=> $this->input->post('geo_long')
			);	    	
	    									
			// Update
			$update = $this->social_igniter->update_content($content_data, $this->oauth_user_id, $activity_data); 
	        
		    if ($update)
		    {
				// Process Tags    
				if ($this->input->post('tags')) $this->places_igniter->process_tags($this->input->post('tags'), $content->content_id);
								
				// Add Place
				$place_data = array(
					'content_id'	=> $content->content_id,
					'address'		=> $this->input->post('address'),
					'district'		=> $this->input->post('district'),
					'locality'		=> $this->input->post('locality'),
					'region'		=> $this->input->post('region'),
					'country'		=> $this->input->post('country'),
					'postal'		=> $this->input->post('postal')
				);
				
				$place = $this->places_igniter->update_place($place_data);			
				
	        	$message = array('status' => 'success', 'message' => 'Awesome, we updated your place', 'data' => $update, 'place' => $place_data);
	        }
	        else
	        {
		        $message = array('status' => 'error', 'message' => 'Oops, we were unable to update this place');
	        }	
		}
		else 
		{
	        $message = array('status' => 'error', 'message' => validation_errors());
		}
        
        $this->response($message, 200);
    }
    
    function destroy_get()
    {
        $message 	= array('id' => $this->get('id'), 'message' => 'DELETED!');
        $response	= 200;
        
        $this->response($message, $response);
    }
    
	

}