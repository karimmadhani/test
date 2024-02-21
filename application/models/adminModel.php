<?php

class adminModel extends CI_Model
{

    public function dataUser()
    {
        return $this->db->get('tb_user')->result();
    }

    public function dataJenisBarang($id = null)
    {
        $this->db->select('tb_jenis_barang.*')->from('tb_jenis_barang');
        if ($id != null) {
            return $this->db->where('id_jenis_barang', $id)->get()->row();
        } else {
            return $this->db->get()->result();
        }
    }

    public function dataBarang($id = null)
    {
        $this->db->select('tb_barang.*, tb_jenis_barang.nama_jenis_barang as jenis_barang')->from('tb_barang');
        if ($id != null) {
            return $this->db->where('id_barang', $id)->get()->row();
        } else {
            return $this->db->join('tb_jenis_barang', 'tb_jenis_barang.id_jenis_barang = tb_barang.id_jenis_barang')->get()->result();
        }
    }
}
