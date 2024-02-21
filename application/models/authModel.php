<?php

class authModel extends CI_Model
{

    public function data_login($username, $password)
    {
        $data = $this->db->where([
            'username' => $username,
            'password' => $password
        ]);

        if (!empty($data)) {
            return $this->db->get('tb_user')->row();
        } else {
            return false;
        }
    }
}
