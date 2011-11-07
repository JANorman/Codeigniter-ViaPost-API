<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Welcome extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -  
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in 
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{
		$this->load->library('Viapost');
		
		if($this->viapost->login())
		{
			
			if($this->viapost->create_letter('./reminder.pdf', date('H:i:s')))
			{
				$this->viapost->to('Your Name', 'JNorman', '13 Church Hill', 'Purley', 'Purley', 'Purley', 'CR8 3QP');
				
				$this->viapost->send(2, 2, true, false);

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