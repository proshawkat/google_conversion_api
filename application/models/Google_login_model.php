<?php
class Google_login_model extends CI_Model
{

	function Insert_user_data($data)
	{
		$this->db->insert('users', $data);
	}
}
?>
