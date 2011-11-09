<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Welcome extends CI_Controller {

	public function index()
	{
		// Load the library
		$this->load->library('Viapost');
		
		// Attempt login
		if($this->viapost->login())
		{
			// Upload a letter
			if($this->viapost->create_letter('./reminder.pdf', date('H:i:s')))
			{
				$this->viapost->to('Your Name', 'Your Org', 'First Line', 'Second Line', 'Third Line', 'Fourth Line', 'POSTCODE');
				
				if($this->viapost->send(1, 1, true, false))
					echo 'Message Sent!';
				else
					echo 'Message Failed.';

			}
			else
			{
				echo 'Failed to add letter';
			}
		}
		else
		{
			echo 'Failed To Login';
		}		
	}
	
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */