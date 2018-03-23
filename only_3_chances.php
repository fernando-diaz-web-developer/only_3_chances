<?php 
/**
 * Only 3 Chances
 *
 * @package     only_3_chances
 * @author      Jesus Azocar
 * @copyright   2018 Jesus Azocar
 * @license     GPL
 *
 * @wordpress-plugin
 * Plugin Name: Only 3 Chances
 * Plugin URI:  none
 * Description: A plugin for allowing a user a maximum amount of 3 attempts before disabling the user. It additionally renders a Google Captcha before the login.
 * Version:     0.5
 * Author:      Jesus Azocar
 * Author URI:  azocar.com
 * Text Domain: only_3_chances
 * License:    GPL
 */
class Only3Chances{

	public function add_custom_columns(){
		global $wpdb;

		$row = $wpdb->get_results(  "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
		WHERE table_name = '{$this->users_table}' AND column_name = 'attempts'"  );

		if(empty($row)){ 
		   $wpdb->query("ALTER TABLE {$this->users_table} ADD attempts INT(11) NOT NULL DEFAULT 0");
		}

		$row = $wpdb->get_results(  "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
		WHERE table_name = '{$this->users_table}' AND column_name = 'last_time_locked'"  );

		if(empty($row)){ 
		   $wpdb->query("ALTER TABLE {$this->users_table} ADD last_time_locked TIMESTAMP DEFAULT 0");
		}
	}

	public  function remove_custom_columns(){
		global $wpdb;

		$row = $wpdb->get_results(  "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
		WHERE table_name = '{$this->users_table}' AND column_name = 'attempts'"  );

		if(!empty($row)){
		   $wpdb->query("ALTER TABLE {$this->users_table} DROP COLUMN attempts");
		}

		$row = $wpdb->get_results(  "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
		WHERE table_name = '{$this->users_table}' AND column_name = 'last_time_locked'"  );

		if(!empty($row)){
		   $wpdb->query("ALTER TABLE {$this->users_table} DROP COLUMN last_time_locked");
		}
		
	}

	public function check_attempts($user,$password){
		global $wpdb;  

		if(get_class($user)==='WP_Error'){
			return $user;
		}

		$secret = get_option("o3c_apikey",'6Ldvj00UAAAAAEEGq6G8tZgNuujEIVukmk3xvNSW'); 

		if(get_option("o3c_recaptcha",false)  ){
			$url = 'https://www.google.com/recaptcha/api/siteverify';
			$data = array('secret' => $secret, 'response' => $_REQUEST['g-recaptcha-response']);

			$options = array(
			    'http' => array(
			        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
			        'method'  => 'POST',
			        'content' => http_build_query($data)
			    )
			);
			$context  = stream_context_create($options);
			$result = file_get_contents($url, false, $context);
			if ($result === FALSE) {  }
			$payload = json_decode($result);
			if(!$payload->success){
				return new WP_Error('bad_attempt',__("The ReCAPTCHA authentication failed."));
			}
		}

		$attempts = (int) $user->data->attempts;
		$minutes = get_option("o3c_minutes",10); 
		
		$result = $wpdb->get_results("SELECT TIMESTAMPDIFF(SECOND,last_time_locked,current_timestamp) total_time
						FROM {$this->users_table} WHERE ID = ".$user->data->ID);

		if((int) $result[0]->total_time <= 60 * $minutes){
			return new WP_Error('too_attempts',__("You must wait a while for attempting to login again."));
		}

		$result = wp_check_password($password, $user->data->user_pass, $user->data->ID);
		
		if(!$result){
			$wpdb->query("UPDATE {$this->users_table} SET attempts = attempts + 1 WHERE ID = ".$user->data->ID);
			$row = $wpdb->get_results(  "SELECT attempts FROM {$this->users_table}
			WHERE ID = ".$user->data->ID );
			$attempts = (int) $row[0]->attempts;
			if($attempts === 3){
				$wpdb->query("UPDATE {$this->users_table} SET last_time_locked = current_timestamp WHERE ID = ".$user->data->ID);
			return new WP_Error('limit_reached',__("You've reached the amount of failed attempts in a row. You must wait $minutes minutes for trying again. "));
			}
			else{
			return new WP_Error('bad_attempt',__("The password is incorrect. If you reach 3 failed attempts, your account will be locked. Remaining attempts: ").(3 - $attempts ));
			}
		}
		else{
			$wpdb->query("UPDATE {$this->users_table} SET attempts = 0 WHERE ID = ".$user->data->ID);
		}
		
		return $user;
	}

	public function settings_init() { 
   		 register_setting( 'o3c_settings', 'o3c_minutes' );
   		 register_setting( 'o3c_settings', 'o3c_recaptcha' );
   		 register_setting( 'o3c_settings', 'o3c_apikey' );

		 add_settings_section( 'main_settings', 'Settings',[$this,'plugin_section_text'],'o3c_settings');

		 add_settings_field( 'o3c_minutes', __('Minutes of delay'),[$this,'display_minutes'],'o3c_settings','main_settings');
		 add_settings_field( 'o3c_recaptcha', __('Use ReAPTCHA?'),[$this,'display_recaptcha'],'o3c_settings','main_settings');
		 add_settings_field( 'o3c_apikey', __('API Key'),[$this,'display_apikey'],'o3c_settings','main_settings');
	}

	public function enqueue_recaptcha() { 
		wp_enqueue_script( 'google_recaptcha', 'https://www.google.com/recaptcha/api.js' ); 
	}

	public function plugin_section_text(){
		echo '';
	}

	public function display_minutes(){
		$minutes = get_option("o3c_minutes",'10');
		echo '<input type="number" min="2" value="'.$minutes.'" name="o3c_minutes"/>';
	}

	public function display_recaptcha(){
		 if( get_option("o3c_recaptcha",false) ){
			echo '<input type="checkbox" checked name="o3c_recaptcha"/>';
		}
		else{
			echo '<input type="checkbox" name="o3c_recaptcha"/>';
		}
	}

	public function display_apikey(){
		$disabled = get_option("o3c_recaptcha",false) ? '' : 'disabled';
		$apikey = get_option("o3c_apikey",'');
		echo '<input type="text" value="'.$apikey.'" name="o3c_apikey" '.$disabled.'/>';
	}

	public function echo_div_recaptcha(){
		 if( get_option("o3c_recaptcha",false) ){
			echo '<div class="g-recaptcha" data-sitekey="6Ldvj00UAAAAANdhszxLx_v6yVjS8E8GWr2VyZ4y"></div>';
		}
	}

	public function settings_page(){
		require_once 'templates/settings.php';
	}

	public function add_settings_menu(){
		add_options_page( 'Only 3 Attempts', __('Settings'), 'manage_options', 'oc3_settings', array($this,'settings_page'));
	}

	public function __construct(){
		global $wpdb;
		$this->users_table = $wpdb->prefix.'users';

 		register_activation_hook(__FILE__,array($this,'add_custom_columns'));
 		register_deactivation_hook(__FILE__,array($this,'remove_custom_columns'));
 		add_action( 'admin_init', array($this,'settings_init' ));
 		add_action('init',array($this,'enqueue_recaptcha'));
 		add_action('admin_menu',array($this,'add_settings_menu'));
 		add_action('login_form',array($this,'echo_div_recaptcha'));
 		add_filter('wp_authenticate_user',array($this,'check_attempts'),10,2);
	}

}

new Only3Chances();