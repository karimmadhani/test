<?php
defined('BASEPATH') or exit('No direct script access allowed');


/**
 *
 * Controller AdminController
 *
 * This controller for ...
 *
 * @package   CodeIgniter
 * @category  Controller CI
 * @author    Setiawan Jodi <jodisetiawan@fisip-untirta.ac.id>
 * @author    Raul Guerrero <r.g.c@me.com>
 * @link      https://github.com/setdjod/myci-extension/
 * @param     ...
 * @return    ...
 *
 */

class admin extends CI_Controller
{

  public function __construct()
  {
    parent::__construct();
    $this->load->model('adminModel');
  }

  public function index()
  {
    $data['user'] = $this->db->get('tb_user')->num_rows();
    $data['jenisBarang'] = $this->db->get('tb_jenis_barang')->num_rows();
    $data['barang'] = $this->db->get('tb_barang')->num_rows();

    $this->load->view('layouts/header');
    $this->load->view('admin/dist/index', $data);
    $this->load->view('layouts/header');
  }

  public function user()
  {
    $data['no'] = 1;
    $data['dataUser'] = $this->adminModel->dataUser();

    $this->load->view('layouts/header');
    $this->load->view('admin/dist/user', $data);
    $this->load->view('layouts/header');
  }

  public function statusUserActive()
  {
    $id = $this->input->post('id');

    $data = [
      'is_active' => 'active'
    ];
    $this->db->where('id_user', $id)->update('tb_user', $data);
    echo json_encode(['status' => 'success']);
  }

  public function statusUserNonActive()
  {
    $id = $this->input->post('id');

    $data = [
      'is_active' => 'nonactive'
    ];
    $this->db->where('id_user', $id)->update('tb_user', $data);
    echo json_encode(['status' => 'success']);
  }

  public function jenisBarang()
  {
    $data['no'] = 1;
    $data['jenisBarang'] = $this->adminModel->dataJenisBarang();
    $this->load->view('layouts/header');
    $this->load->view('admin/dist/jenis_barang', $data);
    $this->load->view('layouts/header');
  }

  public function jenisBarangForEdit($id)
  {
    echo json_encode($this->adminModel->dataJenisBarang($id));
  }

  public function insertOrUpdateJenisBarang()
  {
    if (!empty($this->input->post('id'))) {
      $data = [
        'nama_jenis_barang'          => ucfirst($this->input->post('nama')),
      ];

      $this->db->where('id_jenis_barang', $this->input->post('id'))->update('tb_jenis_barang', $data);
      redirect('admin/jenisBarang');
    } else {
      $data = [
        'nama_jenis_barang'          => ucfirst($this->input->post('nama')),
      ];
      $this->db->insert('tb_jenis_barang', $data);
      redirect('admin/jenisBarang');
    }
  }

  public function deleteJenisBarang($id)
  {
    $this->db->where('id_jenis_barang', $id)->delete('tb_jenis_barang');
    redirect('admin/jenisBarang');
  }

  public function barang()
  {
    $data['no'] = 1;
    $data['barang'] = $this->adminModel->dataBarang();
    $data['jenis_barang'] = $this->adminModel->dataJenisBarang();
    $this->load->view('layouts/header');
    $this->load->view('admin/dist/barang', $data);
    $this->load->view('layouts/header');
  }

  public function barangForEdit($id)
  {
    echo json_encode($this->adminModel->dataBarang($id));
  }

  public function insterOrUpdateBarang()
  {
    if (!empty($this->input->post('id'))) {
      $data = [
        'nama_jenis_barang'          => ucfirst($this->input->post('nama')),
      ];

      $this->db->where('id_barang', $this->input->post('id'))->update('tb_barang', $data);
      redirect('admin/barang');
    } else {
      $data = [
        'id_jenis_barang'     => $this->input->post('jenis_barang'),
        'nama_barang'   => ucfirst($this->input->post('nama')),
        'harga'               => $this->input->post('harga'),
        'stok'                => $this->input->post('stok'),
      ];
      $this->db->insert('tb_barang', $data);
      redirect('admin/barang');
    }
  }

  public function deleteBarang($id)
  {
    $this->db->where('id_barang', $id)->delete('tb_barang');
    redirect('admin/barang');
  }
}


/* End of file AdminController.php */
/* Location: ./application/controllers/AdminController.php */