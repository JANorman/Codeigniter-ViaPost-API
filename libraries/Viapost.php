<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ViaPost Class
 *
 * Allows you to send PDF files through ViaPost's API.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		James Norman - NSAWD
 * @link		http://nsawd.com
 */
class Viapost {
	
	/**
	 * Codeigniter instance
	 * @var object $CI
	 */
	var $CI;
	
	
	/**
	 * WSDL URL
	 * @var string $wsdl
	 */
	var $wsdl;

	
	/**
	 * Debug Mode (prints Exceptions)
	 * @var bool $debug
	 */
	var $debug;
	
	
	/**
	 * ViaPost login details
	 * @var array $account_details
	 */
	var $account_details;
	
	
	/**
	 * PHP Soap Client
	 * @var SoapClient $client
	 */
	var $client;
	
	
	/**
	 * Login Token returned by ViaPost used for subsequent calls.
	 * @var string $token
	 */
	var $token;
	
	
	/**
	 * Letter ID of the last uploaded letter
	 * @var integer $letter_id
	 */
	var $letter_id;
	
	
	/**
	 * Recipients of the letter
	 * @var array $recipients
	 */
	var $recipients;
	
	
	/**
	 * Email Notifications of Sent letters
	 * @var bool $email_notifications
	 */
	var $email_notifications;
	
	/**
	 * Config
	 * @var array $config
	 */
	var $config;


	function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->config('viapost');
		$this->config = $this->CI->config->item('viapost');
		$this->wsdl = $this->config['wsdl'];
		$this->debug = $this->config['debug_mode'];
		$this->token = null;
		$this->letter_id = null;
		$this->recipients = null;
		$this->email_notifications = true;
		
		try
		{
			// Instantiate new Soap Client
			$this->client = new SoapClient($this->wsdl, array('trace' => true, 'soap_version' => SOAP_1_2));
			log_message('debug', "ViaPost Class Initialized");
		}
		catch (SoapFault $ex)
		{
			log_message('error', "ViaPost Class Constructor Fault: $ex->faultstring");
			if($this->debug)
			{
				die('ViaPost Exception: '.$ex->faultstring);
			}
			return false;
		}
	}
	
	/**
	 * Login to the ViaPost API. Required before calling any other method.
	 * @access	public
	 * @param	username	Your ViaPost Username
	 * @param	password	Your ViaPost Password
	 * @return	bool
	 */	
	public function login($username = null, $password = null)
	{
		if($username == null && $password == null)
		{
			$this->account_details['username'] = $this->config['username'];
			$this->account_details['password'] = $this->config['password'];
			
		}
		else
		{
			$this->account_details['username'] = $username;
			$this->account_details['password'] = $password;
		}
		
		try
		{
			$result = $this->client->SignIn(array(
				'sUserName' => $this->account_details['username'],
				'sPassword' => $this->account_details['password']
			));
			
			if($result->SignInResult)
			{
				$this->token = $result->sLoginToken;
				log_message('info', 'ViaPost Class - User ('.$username.') Logged In Successfully');
				return true;
			}
			log_message('error', 'ViaPost Class - User ('.$username.') Failed To Login');
			return false;
		} 
		catch (SoapFault $fault)
		{
			log_message('error', 'ViaPost Class - Exception: '.$ex->faultstring);
			if($this->debug)
			{
				die('ViaPost Exception: '.$ex->faultstring);
			}
			return false;
		}
	}
	
	/**
	 * Uploads a letter to ViaPost
	 * @access	public
	 * @param	file	Relative URI to the file
	 * @param	name	Name of the file
	 * @param	description	The file's description
	 * @param	isXML	Sets whether the file is a PDF or Word XML
	 * @param 	shareLetter	Sets whether the file will be shared with other ViaPost users in your organisation.
	 * @return	integer|false	Letter ID of uploaded letter or false on failure.
	 */
	public function create_letter($file, $name, $description = '', $isXML = false, $shareLetter = false)
	{
		if(!$this->is_logged_in())
			return false;
			
		try
		{
			$result = $this->client->CreateLetter(array(
								'loginToken' => $this->token,
								'name' => $name,
								'description' => $description,
								'FileContents' => $this->get_file($file),
								'dynamic' => $isXML,
								'shareLetterWithGroup' => $shareLetter,
								'letterID' => 3
			));
			
			if($result->CreateLetterResult)
			{
				log_message('info', 'ViaPost Class - Successfully Added Letter');
				$this->letter_id = $result->letterID;
				return $result->letterID;
			}
			return false;
		}
		catch (SoapFault $ex)
		{
			log_message('error', 'ViaPost Class - Exception: '.$ex->faultstring);
			if($this->debug)
			{
				die('ViaPost Exception: '.$ex->faultstring);
			}
			return false;
		}
	}
	
	/**
	 * Adds a recepient
	 * @access	public
	 * @param 	name	Person's name
	 * @param	organisation	Organisation
	 * @param	street1	Street Line 1
	 * @param	street2	Street Line 2
	 * @param	street3	Street Line 3
	 * @param	posttown	Post Town
	 * @param	postcode	Postcode
	 * @return	true
	 */
	public function to($name, $org, $street1, $street2, $street3, $posttown, $postcode)
	{
		if($this->recipients == null)
		{
			$this->recipients = array(array(
						'name' => $name,
						'organisation' => $org,
						'street1' => $street1,
						'street2' => $street2,
						'street3' => $street3,
						'posttown' => $posttown,
						'postcode' => $postcode		
			));
		}
		else
		{
			$new = array(
						'name' => $name,
						'organisation' => $org,
						'street1' => $street1,
						'street2' => $street2,
						'street3' => $street3,
						'posttown' => $posttown,
						'postcode' => $postcode		
			);
			array_push($this->recipients, $new); 
		}
		log_message('info', 'ViaPost Class - Added Recipient: '.$name);
		return true;
	}
	
	/**
	 * Sends & Prints a letter
	 * @access	public
	 * @param	letter_id	The Letter ID produced from calling create_letter
	 * @param	nbr_pages	Number of pages to print
	 * @param	nbr_sheets	Number of sheets to print on
	 * @param	is_simplex	Simplex or Duplex printing.
	 * @param	is_color	Is this document to be printed in colour?
	 * @param	send_now	Send the letter now?
	 * @param	send_date	Timestamp to send the letter on, if not now. 
	 * @return 	bool
	 */
	public function send($nbr_pages, $nbr_sheets, $is_simplex, $is_color, $send_now = true, $send_date = null)
	{
		if(!$this->is_logged_in())
			return false;
			
		if($send_now != true)
			$send_now = false;
		
		if($send_date == null)
			$send_date = date(DATE_ATOM);
		else
			$send_date = date(DATE_ATOM, $send_date);
			
		if($this->recipients == null)
			return false;
		
		try
		{
			$data = array(
								'loginToken' => new SoapVar($this->token, XSD_STRING, 's:string'),
								'letterID' => new SoapVar($this->letter_id, XSD_LONG, 's:long'),
								'colour' => new SoapVar((bool) $is_color, XSD_BOOLEAN, 's:boolean'),
								'simplex' => new SoapVar((bool) $is_simplex, XSD_BOOLEAN, 's:boolean'),
								
								'sendNow' => new SoapVar($send_now, XSD_BOOLEAN, 's:boolean'),
								'dateToSend' => new SoapVar($send_date, XSD_DATETIME, 's:dateTime'),
								
								'emailNotification' => new SoapVar($this->email_notifications, XSD_BOOLEAN, 's:boolean'),
								
								'costOfMailing' => new SoapVar(1, XSD_DECIMAL, 's:decimal'),
								
								'numberOfPages' => new SoapVar($nbr_pages, XSD_LONG, 's:long'),
								'numberOfSheets' => new SoapVar($nbr_sheets, XSD_LONG, 's:long'),
			);

			for($i=0; $i<count($this->recipients); $i++)
			{
				$data['name'] = new SoapVar($this->recipients[$i]['name'], XSD_STRING, 's:string');
				$data['organisation'] = new SoapVar($this->recipients[$i]['organisation'], XSD_STRING, 's:string');
				$data['street1'] = new SoapVar($this->recipients[$i]['street1'], XSD_STRING, 's:string');
				$data['street2'] = new SoapVar($this->recipients[$i]['street2'], XSD_STRING, 's:string');
				$data['street3'] = new SoapVar($this->recipients[$i]['street3'], XSD_STRING, 's:string');
				$data['posttown'] = new SoapVar($this->recipients[$i]['posttown'], XSD_STRING, 's:string');
				$data['postcode'] = new SoapVar($this->recipients[$i]['postcode'], XSD_STRING, 's:string');
				
				$result = $this->client->SendSimpleMailingToSingleAddress($data);
				if(!$result->SendSimpleMailingToSingleAddressResult)
					log_message('error', 'ViaPost Class - Failed Sending To '.$data['name'].', Letter ID: '.$letter_id);
				else
					log_message('info', 'ViaPost Class - Sent Letter');
			}
			return true;
						
		}
		catch (SoapFault $ex)
		{
			log_message('error', 'ViaPost Class - Exception: '.$ex->faultstring);
			if($this->debug)
			{				
				die('ViaPost Exception: '.$ex->faultstring);
			}
			return false;
		}
	}
	
	
	/**
	 * Calculate the total cost
	 * @access	public
	 * @param	letter_id	The Letter ID produced from calling create_letter
	 * @param	nbr_pages	Number of pages to print
	 * @param	nbr_sheets	Number of sheets to print on
	 * @param	is_simplex	Simplex or Duplex printing.
	 * @param	is_color	Is this document to be printed in colour?
	 * @return	float
	 */
	public function get_cost($nbr_pages, $nbr_sheets, $is_simplex, $is_color)
	{
		if(!$this->is_logged_in())
			return false;
			
		if(count($this->recipients) == 0)
			return 0;
		
		try
		{
			$total = 0;
			$data = array(
						'loginToken' => $this->token,
						'documentType' => 'SimpleMailing',
						'documentID' => $this->letter_id,
						'colour' => $is_color,
						'simplex' => $is_simplex,
						'numberOfPages' => $nbr_pages,
						'numberOfSheets' => $nbr_sheets,
						'costOfMailing' => 0
			);
			for($i=0; $i<count($this->recipients); $i++)
			{						
				$data['name'] = $this->recipients[$i]['name'];
				$data['organisation'] = $this->recipients[$i]['organisation'];
				$data['street1'] = $this->recipients[$i]['street1'];
				$data['street2'] = $this->recipients[$i]['street2'];
				$data['street3'] = $this->recipients[$i]['street3'];
				$data['posttown'] = $this->recipients[$i]['posttown'];
				$data['postcode'] = $this->recipients[$i]['postcode'];
				
				$result = $this->client->CalculateCostOfSingleAddressMailing($data);
				if($result->CalculateCostOfSingleAddressMailingResult)
					$total += $result->costOfMailing;
			}
			return $total;
		}
		catch (SoapFault $ex)
		{
			log_message('error', 'ViaPost Class - Exception: '.$ex->faultstring);
			if($this->debug)
			{				
				die('ViaPost Exception: '.$ex->faultstring);
			}
			return -1;
		}
		
	}
	
	/**
	 * Get the Estimated Delivery Date if sent now, or on a given date
	 * @access	public
	 * @param	send_date	Timestamp to send letter on.
	 * @return	integer|false	Timestamp of delivery date.
	 */ 
	public function get_delivery_date_from_send_date($send_date = null)
	{
		if(!$this->is_logged_in())
			return false;
		
		if($send_date == null)
			$send_date = date(DATE_ATOM);
		else
			$send_date = date(DATE_ATOM, $send_date);
		try
		{
			$result = $this->client->CalculateEstimatedDeliveryDate(array(
									'sLoginToken' => $this->token,
									'SendDateTime' => $send_date,
									'DeliveryDate' => date(DATE_ATOM),
									'sReturnMessage' => ''								
			));
			
			if($result->CalculateEstimatedDeliveryDateResult)
			{
				return strtotime($result->DeliveryDate);
			}
			else
			{
				return false;
			}
		}
		catch (SoapFault $ex)
		{
			log_message('error', 'ViaPost Class - Exception: '.$ex->faultstring);
			if($this->debug)
			{				
				die('ViaPost Exception: '.$ex->faultstring);
			}
			return -1;
		}
	}
	
	/**
	 * Get the earliest send date for it to arrive on the delivery date
	 * @access	public
	 * @param	delivery_date	Timestamp to send letter on.
	 * @param	both	Do you require both the earliest and lastst send dates? (in an array)
	 * @return	integer	Timestamp of send date.
	 */ 
	public function get_send_date_from_delivery_date($delivery_date)
	{
		if(!$this->is_logged_in())
			return false;
		
		$max_days = 4;
		$day = strtotime('-' . $max_days .' days', $delivery_date);
		if(date('N', $day) == 6)
			return strtotime('-1 day', $day);
		else if(date('N', $day) == 7)
			return strtotime('-2 day', $day);
		else
			return $day;
		
	}
	
	
	public function get_letter_status($letter_id)
	{
		try
		{
			$result = $this->client->GetLetterStatusByLetterID(array(
												'sLoginToken' => $this->token,
												'LetterID' => $letter_id,
												'sReturnMessage' => ''								
			));
			
			print_r($result);
			if($result->GetLetterStatusByLetterIDResult)
			{
				return $result->sReturnMessage;
			}
			else
			{
				return false;
			}
		}
		catch (SoapFault $ex)
		{
			log_message('error', 'ViaPost Class - Exception: '.$ex->faultstring);
			if($this->debug)
			{				
				die('ViaPost Exception: '.$ex->faultstring);
			}
			return -1;
		}
	}
	
	/**
	 * Gets the Contents of the given file in Base64Binary.
	 * @access private
	 * @param filename	URI to file
	 * @return base64binary
	 */
	private function get_file($filename)
	{
		if($filename)
		{
			return file_get_contents($filename);
		}
	}
	
	
	/**
	 * Get last Soap request
	 * @access public
	 * @return xml
	 */
	public function get_last_request()
	{
		return $this->client->__getLastRequest();
	}
	
	
	/**
	 * Clear the list of recipients
	 * @access public
	 * @return true
	 */
	public function clear_recipients()
	{
		$this->recipients = null;
		return true;
	}
	
	/**
	 * Clear the last used letter
	 * @access public
	 * @return true
	 */
	public function clear_letter()
	{
		$this->letter_id = null;
		return true;
	}
		
	/**
	 * Check if user is logged in.
	 * @access public
	 * @return bool
	 */
	public function is_logged_in()
	{
		if($this->token == null)
			return false;
		else
			return true;
	}
}
