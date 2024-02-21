<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model('authModel');
    }

    public function index()
    {
        $this->load->view('layouts/header');
        $this->load->view('auth/login');
        $this->load->view('layouts/footer');
    }

    public function login()
    {
        if (!empty($this->input->post('username') && $this->input->post('password'))) {

            $verifikasi = $this->authModel->data_login($this->input->post('username'), $this->input->post('password'));

            if ($verifikasi == true) {
                if ($verifikasi->is_active == 'active') {
                    redirect('admin');
                } else {
                    $this->session->set_flashdata('gagal', '<div class="alert alert-danger alert-dismissible fade show text-center" role="alert">
											Akses Belum Disetujui!</div>');
                    redirect('/');
                }
            } else {
                $this->session->set_flashdata('gagal', '<div class="alert alert-danger alert-dismissible fade show text-center" role="alert">
											Username atau Password salah!</div>');
                redirect('/');
            }
        } else {
            redirect("/");
        }
    }

    public function register()
    {
        $this->load->view('layouts/header');
        $this->load->view('auth/register');
        $this->load->view('layouts/footer');
    }

    public function proses_registrasi()
    {
        $data = [
            'username'    => $this->input->post('username'),
            'password'    => $this->input->post('password'),
            'fullname'    => $this->input->post('fullname'),
            'is_active'   => 'nonactive'
        ];

        $this->db->insert('tb_user', $data);
        redirect('/');
    }

    public function logout()
    {
        session_destroy();
        redirect('/');
    }
}
