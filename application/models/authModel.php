<?php

class authModel extends CI_Model
{

    public function data_login($username, $password)
    {
        $data = $this->db->where([
            'username' => $username,
            'password' => $password
        ])
            ->get('tb_user')
            ->row();

        if (!empty($data)) {
            return true;
        } else {
            return false;
        }
    }
}
