<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Config extends MY_Controller
{
  public function __construct()
  {
    parent::__construct();
    // @model
    _models(['bridging/antrean/m_config']);
    // @table
    $this->table = $this->m_config->table;
    $this->pk_id = $this->m_config->pk_id;
    // @params
    $this->template = 'bridging/antrean/config/';
    // @encrypt
    $this->load->library('encryption');
    $this->encryption->initialize(
      array(
        'cipher' => 'aes-256',
        'mode' => 'ctr',
        'key' => $this->config->item('encryption_key'),
      )
    );
  }

  public function index()
  {
    $d['id'] = "1";
    $d['main'] = DB::get($this->table, [$this->pk_id => @$d['id']]);
    foreach ($d['main'] as $k => $v) {
      $d['main'][$k] = $this->encryption->decrypt($d['main'][$k]);
    }
    $d['form_act'] = $this->uri . '/save/' . @$d['id'];
    $this->render($this->template . 'index', $d);
  }

  public function save($id = null)
  {
    $d = _post();
    foreach ($d as $k => $v) {
      $d[$k] = $this->encryption->encrypt($d[$k]);
    }
    $w = ($id != '' ? [$this->pk_id => $id] : null);
    if ($id == null) {
      if (DB::valid_id($this->table, @$this->pk_id, @$d[@$this->pk_id]) == true) {
        _json(_response('20', $this->uri));
      } else {
        DB::insert($this->table, $d, $w);
        _json(_response('01', $this->uri));
      }
    } else {
      DB::update($this->table, $d, $w);
      _json(_response('02', $this->uri));
    }
  }

  function processAntreanBPJS($kodebooking = null)
  {
    date_default_timezone_set("Asia/Jakarta");
    $str_region = 'Asia/Jakarta';

    if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
      $antrean = new Nsulistiyawan\Bpjs\Antrean\Antrean(antrean_config());
      $referensi = new Nsulistiyawan\Bpjs\Antrean\Referensi(antrean_config());

      $sep = new Nsulistiyawan\Bpjs\VClaim\Sep(vclaim_config());
      $peserta = new Nsulistiyawan\Bpjs\VClaim\Peserta(vclaim_config());
    }

    // $kirim_st = 0;
    // if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
    $kirim_st = 1;
    // }

    date_default_timezone_set($str_region);

    $query = "SELECT 
                a.registrasi_id, a.registrasi_tgl, a.statuspasien_id, a.masuk_rs_tgl, a.dpjp_id, 
                b.pelayanan_id, b.antrean_no, b.antrean_cd, b.lokasi_id, b.dokter_id, b.dilayani_mulai_tgl, b.dilayani_selesai_tgl, b.lokasi_id, 
                c.bpjs_kode, 
                c.pegawai_nm AS dpjp_nm, 
                d.pasien_id, d.rm_no, d.nik, d.telp_no, d.hp_no, 
                e.kartu_no, 
                f.bpjs_st, f.penjamin_nm, 
                g.bpjs_cd, 
                g.lokasi_nm 
              FROM dat_registrasi a 
              INNER JOIN dat_pelayanan b ON a.registrasi_id=b.registrasi_id
              INNER JOIN mst_pegawai c ON a.dpjp_id=c.pegawai_id 
              INNER JOIN mst_pasien d ON a.pasien_id=d.pasien_id 
              LEFT JOIN mst_pasien_penjamin e ON a.pasien_id=e.pasien_id AND a.penjamin_id=e.penjamin_id 
              LEFT JOIN mst_penjamin f ON a.penjamin_id = f.penjamin_id
              LEFT JOIN mst_lokasi g ON b.lokasi_id = g.lokasi_id
              WHERE a.registrasi_id = '" . @$kodebooking . "' AND b.registrasi_ke = '1' AND b.lokasi_id NOT IN ('01.01', '01.10', '01.12', '01.15', '01.17')";
    $row = DB::raw('row_array', $query);

    $cek_log = DB::raw('row_array', "SELECT * FROM log_tambah_antrean WHERE kodebooking = '" . @$kodebooking . "'");

    if (@$cek_log == null || @$cek_log['metadata_code'] != 200) {
      $estimasi_dilayani = @$row['masuk_rs_tgl'];
      $estimasi_dilayani = strtotime($estimasi_dilayani);
      $increment = @$row['antrean_no'] * 10;
      $estimasi_dilayani = strtotime('+' . $increment . ' minutes', $estimasi_dilayani);
      $estimasi_dilayani = $estimasi_dilayani * 1000;

      $hari = get_hari_jadwal_dokter(date('D', strtotime(to_date(to_date(@$row['masuk_rs_tgl'], '', 'date'), '', 'date'))));

      $cek_jadwal = DB::raw('row_array', "SELECT a.*,b.bpjs_cd FROM mst_jadwal_dokter a INNER JOIN mst_lokasi b ON a.lokasi_id = b.lokasi_id WHERE a.dokter_id = '" . @$row['dpjp_id'] . "' AND a.lokasi_id = '" . @$row['lokasi_id'] . "' AND a.hari = '" . @$hari . "'");

      $kuota_jkn = (@$cek_jadwal['max_kuota_jkn'] == '') ? 40 : @$cek_jadwal['max_kuota_jkn'];
      $kuota_nonjkn =  (@$cek_jadwal['max_kuota_online'] == '') ? 60 : @$cek_jadwal['max_kuota_online'];

      $jadwal_terpilih = null;
      if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
        $jadwal_dokter = $referensi->jadwalDokter(@$cek_jadwal['bpjs_cd'], to_date(to_date(@$row['masuk_rs_tgl'], '', 'date'), '', 'date'));
        if (@$jadwal_dokter['metadata']['code'] == 200) {
          $list_jadwal = @$jadwal_dokter['response'];
          $dtr = '';
          foreach ($list_jadwal as $jadwal) {
            if (@$jadwal['kodedokter'] == $row['bpjs_kode']) {
              $jadwal_terpilih = $jadwal;
              break;
            } else {
              $jadwal_terpilih = $jadwal;
            }
          }
        }
      }

      $jeniskunjungan = 1;
      if (@$row['jeniskunjungan_jkn'] == 1) {
        $jeniskunjungan = 1;
      } else if (@$row['jeniskunjungan_jkn'] == 2) {
        $jeniskunjungan = 3;
      } else {
        $jeniskunjungan = 1;
      }

      $nik = null;
      if (@$row['nik']) {
        if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
          $pesertaRow = $peserta->getByNIK($row['nik'], @$row['masuk_rs_tgl']);
          if (@$pesertaRow['metaData']['code'] == "200") {
            $row['kartu_no'] = $pesertaRow['response']['peserta']['noKartu'];
            $nik = $pesertaRow['response']['peserta']['nik'];
            $nohpPeserta = $pesertaRow['response']['peserta']['mr']['noTelepon'];
          }
        }
      } else {
        if (@$row['kartu_no']) {
          if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
            $pesertaRow = $peserta->getByNoKartu($row['kartu_no'], @$row['masuk_rs_tgl']);
            if (@$pesertaRow['metaData']['code'] == "200") {
              $nik = $pesertaRow['response']['peserta']['nik'];
              $nohpPeserta = $pesertaRow['response']['peserta']['mr']['noTelepon'];
            }
          }
        }
      }


      $nohp = @$nohpPeserta;
      if ($nohp == "" || $nohp == null) {
        if (@$row['telp_no'] != '') {
          $nohp = substr(@$row['telp_no'], 0, 12);
        } else {
          $nohp = substr(@$row['hp_no'], 0, 12);
        }
      }

      if ($nohp == "" || $nohp == null) {
        $nohp = "0271716646";
      }

      if ($nik == "" || $nik == null) {
        $nik = "3300000000000001";
      }

      if (strpos(@$row['nomorreferensi_jkn'], 'INT') === FALSE) {

        if (@$row['bpjs_st'] == '1') {
          $kontrol = $this->get_control($row['masuk_rs_tgl'], $row['kartu_no']);
          if (!@$kontrol['response']) {
            $rujukan = $this->get_rujukan($row['kartu_no']);
            if (!@$rujukan['response']['rujukan']) {
              $rujukan = $this->get_rujukan($row['kartu_no'], 'RS');
              foreach (@$rujukan['response']['rujukan'] as $key => $v) {
                $row['nomorreferensi_jkn'] = $v['noKunjungan'];
                $jeniskunjungan = '4';
              }
            } else {
              foreach (@$rujukan['response']['rujukan'] as $key => $v) {
                if ($v['poliRujukan']['kode'] == $row['bpjs_cd']) {
                  $row['nomorreferensi_jkn'] = $v['noKunjungan'];
                  $jeniskunjungan = '1';
                  break;
                }
              }
            }
          } else {
            $change = 0;
            foreach ($kontrol['response']['list'] as $key => $value) {
              if ($value['terbitSEP'] == 'Belum' && $value['poliAsal'] == $row['bpjs_cd']) {
                $change = 1;
                $row['nomorreferensi_jkn'] = $value['noSuratKontrol'];
                $jeniskunjungan = '3';
                break;
              }
            }
            if ($change = 0) {
              $rujukan = $this->get_rujukan($row['kartu_no']);
              if (!@$rujukan['response']['rujukan']) {
                $rujukan = $this->get_rujukan($row['kartu_no'], 'RS');
                foreach ($rujukan['response']['rujukan'] as $key => $v) {
                  $row['nomorreferensi_jkn'] = $v['noKunjungan'];
                  $jeniskunjungan = '4';
                }
              } else {
                foreach ($rujukan['response']['rujukan'] as $key => $v) {
                  if ($v['poliRujukan']['kode'] == $row['bpjs_cd']) {
                    $row['nomorreferensi_jkn'] = $v['noKunjungan'];
                    $jeniskunjungan = '1';
                    break;
                  }
                }
              }
            }
          }
          // echo json_encode($rujukan);
          // die;
        }
      }

      if (@$row['bpjs_st'] == '1' &&  !@$row['nomorreferensi_jkn']) {
        $no_internal = substr("INT" . $row['registrasi_id'] . $row['rm_no'], 0, 19);
        $row['nomorreferensi_jkn'] = $no_internal;
        $jeniskunjungan = '2';
      }

      if (@$jadwal_terpilih['namapoli'] != '') {
        $namapoli = @$jadwal_terpilih['namapoli'];
      } else {
        $namapoli = @$row['lokasi_nm'];
      }

      if (@$jadwal_terpilih['namadokter'] != '') {
        $namadokter = @$jadwal_terpilih['namadokter'];
      } else {
        $namadokter = @$row['dpjp_nm'];
      }

      if (@$jadwal_terpilih['jadwal'] != '') {
        $jadwalpraktek = @$jadwal_terpilih['jadwal'];
      } else {
        $jadwalpraktek = get_hour_minute_from_time($cek_jadwal['jam_awal']) . '-' . get_hour_minute_from_time(@$cek_jadwal['jam_akhir']);
      }

      if (@$row['bpjs_st'] == '1') {
        $kartu_no = @$row['kartu_no'];
      } else {
        $kartu_no = '';
      }

      $request = array(
        "kodebooking" => @$row['registrasi_id'],
        "jenispasien" => @$row['bpjs_st'] == '1' ? 'JKN' : 'NON JKN',
        "nomorkartu" => @$kartu_no,
        "nik" => ($nik != null) ? $nik : @$row['nik'],
        "nohp" => $nohp,
        "kodepoli" => @$row['bpjs_cd'],
        "namapoli" => @$namapoli,
        "pasienbaru" => (@$row['statuspasien_id'] == 'B') ? 1 : 0,
        "norm" => @$row['rm_no'],
        "tanggalperiksa" => to_date(to_date(@$row['masuk_rs_tgl'], '', 'date'), '', 'date'),
        "kodedokter" => intval(@$row['bpjs_kode']),
        "namadokter" => @$namadokter,
        "jampraktek" => @$jadwalpraktek,
        "jeniskunjungan" => $jeniskunjungan,
        "nomorreferensi" => @$row['bpjs_st'] == '1' ? @$row['nomorreferensi_jkn'] : '',
        "nomorantrean" => @$row['antrean_cd'] . '.' . @$row['antrean_no'],
        "angkaantrean" => intval(@$row['antrean_no']),
        "estimasidilayani" => intval(@$estimasi_dilayani),
        "sisakuotajkn" => @$kuota_jkn - @$row['antrean_no'],
        "kuotajkn" => @$kuota_jkn,
        "sisakuotanonjkn" => @$kuota_nonjkn - @$row['antrean_no'],
        "kuotanonjkn" => @$kuota_nonjkn,
        "keterangan" => "Peserta harap 30 menit lebih awal guna pencatatan administrasi."
      );
      // echo json_encode($request);
      // die;

      // sleep(2); //delay 2 second

      if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
        // if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
        $result = $antrean->tambahAntrean($request);

        $no_internal = substr("INT" . $row['registrasi_id'] . $row['rm_no'], 0, 19);
        if (@$result['metadata']['code'] != 200 && !strpos(@$result['metadata']['message'], 'masa berlaku habis') === FALSE) {
          $request['nomorreferensi'] = $no_internal;
          $request['jeniskunjungan'] = 2;
          $result = $antrean->tambahAntrean($request);
        }
        if (@$result['metadata']['code'] != 200 && !strpos(@$result['metadata']['message'], 'data nomorreferensi belum sesuai') === FALSE) {
          $request['nomorreferensi'] = $no_internal;
          $request['jeniskunjungan'] = 2;
          $result = $antrean->tambahAntrean($request);
        }
        if (@$result['metadata']['code'] != 200 && !strpos(@$result['metadata']['message'], 'sudah terbit SEP') === FALSE) {
          $request['nomorreferensi'] = $no_internal;
          $request['jeniskunjungan'] = 2;
          $result = $antrean->tambahAntrean($request);
        }
        // if ($result['metadata']['code'] != 200 && !strpos($result['metadata']['message'], 'Service Expired') === FALSE) {
        //   die;
        // }
        // } else {
        //   $result = '';
        // }
      }
    } else {
      $result = '';
    }

    if (@$cek_log == null) {
      // insert
      // echo "Insert data\n";
      $bpjsLogTambahAntrean = array(
        'metadata_code' => @$result['metadata']['code'],
        'metadata_message' => @$result['metadata']['message'],
        'kodebooking' => @$request['kodebooking'],
        'pasien_id' => @$row['pasien_id'],
        'registrasi_id' => @$row['registrasi_id'],
        'pelayanan_id' => @$row['pelayanan_id'],
        'lokasi_id' => @$row['lokasi_id'],
        'request' => json_encode(@$request),
        'response' => json_encode(@$result),
        'kirim_st' => @$kirim_st,
      );
      $bpjsLogTambahAntrean['id'] = DB::get_id('log_tambah_antrean');
      DB::insert('log_tambah_antrean', $bpjsLogTambahAntrean);
      DB::update_id('log_tambah_antrean', $bpjsLogTambahAntrean['id']);
    } else {
      if (@$result) {
        // echo "Update data\n";
        $bpjsLogTambahAntrean = array(
          'metadata_code' => @$result['metadata']['code'],
          'metadata_message' => @$result['metadata']['message'],
          'kodebooking' => @$request['kodebooking'],
          'pasien_id' => @$row['pasien_id'],
          'registrasi_id' => @$row['registrasi_id'],
          'pelayanan_id' => @$row['pelayanan_id'],
          'lokasi_id' => @$row['lokasi_id'],
          'request' => json_encode(@$request),
          'response' => json_encode(@$result),
          'kirim_st' => @$kirim_st,
        );

        if (@$result['metadata']['code'] != '208') {
          if (@$result['metadata']['code'] == '201') {
            $message = @$result['metadata']['message'];
            $cek_sep = strpos(@$message, 'Sudah terbit SEP');

            if ($cek_sep === false) {
              DB::update('log_tambah_antrean', $bpjsLogTambahAntrean, ['id' => @$cek_log['id']]);
            }
          } else {
            DB::update('log_tambah_antrean', $bpjsLogTambahAntrean, ['id' => @$cek_log['id']]);
          }
        }
      } else {
        // echo @$cek_log['metadata_code'];
        // echo "Skip data \n";
      }
      // $new->update('bpjs_log_tambah_antrean', $bpjsLogTambahAntrean, "id=%s",  @$cek_log['id']);
      // if (@$cek_log['metadata_code'] != @$result['metadata']['code'] || @$cek_log['metadata_message'] != @$result['metadata']['message']) {
      // }
    }
  }

  function processTaskIdAntreanBPJS($kodebooking = null, $task_id = null)
  {
    date_default_timezone_set("Asia/Jakarta");
    $str_region = 'Asia/Jakarta';

    if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
      $antrean = new Nsulistiyawan\Bpjs\Antrean\Antrean(antrean_config());
      $referensi = new Nsulistiyawan\Bpjs\Antrean\Referensi(antrean_config());

      $sep = new Nsulistiyawan\Bpjs\VClaim\Sep(vclaim_config());
      $peserta = new Nsulistiyawan\Bpjs\VClaim\Peserta(vclaim_config());
    }

    $kirim_st = 0;
    if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
      $kirim_st = 1;
    }

    date_default_timezone_set($str_region);

    $query = "SELECT 
                a.registrasi_id, a.statuspasien_id,  a.masuk_rs_tgl, a.batal_tgl, a.dpjp_id, b.pelayanan_id, b.antrean_no, b.antrean_cd, b.lokasi_id, b.dokter_id, b.dilayani_mulai_tgl, b.dilayani_selesai_tgl, c.bpjs_kode, d.pasien_id, d.rm_no, d.nik, d.telp_no, d.hp_no, e.kartu_no, f.bpjs_st, f.penjamin_nm, g.bpjs_cd, h.tindaklanjut_tgl
              FROM dat_registrasi a 
              INNER JOIN dat_pelayanan b ON a.registrasi_id=b.registrasi_id
              INNER JOIN mst_pegawai c ON a.dpjp_id=c.pegawai_id 
              INNER JOIN mst_pasien d ON a.pasien_id=d.pasien_id 
              LEFT JOIN mst_pasien_penjamin e ON a.pasien_id=e.pasien_id AND a.penjamin_id=e.penjamin_id 
              LEFT JOIN mst_penjamin f ON a.penjamin_id = f.penjamin_id
              LEFT JOIN mst_lokasi g ON b.lokasi_id = g.lokasi_id
              LEFT JOIN dat_tindaklanjut h ON a.pasien_id=h.pasien_id AND a.registrasi_id=h.registrasi_id AND b.pelayanan_id=h.pelayanan_id 
              WHERE a.registrasi_id = '" . @$kodebooking . "' AND b.registrasi_ke = '1' AND b.lokasi_id NOT IN ('01.01', '01.10', '01.12', '01.15', '01.17')";
    $cek_reg = DB::raw('row_array', $query);


    $cek_log = DB::raw('row_array', "SELECT * FROM log_update_antrean WHERE kodebooking = '" . $kodebooking . "' ");

    if ($cek_log == null) {
      $bpjsLogUpdateAntrean = array(
        'kodebooking' => @$kodebooking,
        'pasien_id' => @$cek_reg['pasien_id'],
        'lokasi_id' => @$cek_reg['lokasi_id'],
        'pelayanan_id' => @$cek_reg['pelayanan_id'],
        'registrasi_id' => @$cek_reg['registrasi_id'],
      );
      $bpjsLogUpdateAntrean['id'] = DB::get_id('log_update_antrean');
      DB::insert('log_update_antrean', $bpjsLogUpdateAntrean);
      DB::update_id('log_update_antrean', $bpjsLogUpdateAntrean['id']);
    } else {
      $bpjsLogUpdateAntrean = array(
        'pasien_id' => @$cek_reg['pasien_id'],
        'lokasi_id' => @$cek_reg['lokasi_id'],
        'pelayanan_id' => @$cek_reg['pelayanan_id'],
        'registrasi_id' => @$cek_reg['registrasi_id'],
      );
      DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['kodebooking' => $kodebooking]);
    }

    $cek_log = DB::raw('row_array', "SELECT * FROM log_update_antrean WHERE kodebooking = '" . $kodebooking . "' ");

    if ($task_id == '1') {
      // Task ID 1 (mulai waktu tunggu admisi)
      // echo "Task ID 1 \n";
      if(@$cek_reg['dilayani_mulai_tgl'] != '' || @$cek_reg['dilayani_mulai_tgl'] != null) {
        @$tgl_task_id_1 = @$cek_reg['dilayani_mulai_tgl'];
      } else {
        @$tgl_task_id_1 = @$cek_reg['masuk_rs_tgl'];
      }
      $random_minute = rand(5, 10);
      $start_waktu_tunggu = strtotime('-' . $random_minute . ' minutes', strtotime(@$tgl_task_id_1)) * 1000;
      $tgl_1 = date("Y-m-d H:i:s", ($start_waktu_tunggu / 1000));
      $request = array(
        "kodebooking" => $kodebooking,
        "taskid" => 1,
        "waktu" => $start_waktu_tunggu
      );

      if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
        if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
          $result = $antrean->updatewaktuantrean($request);
        } else {
          $result = '';
        }
      }

      if (@$cek_log['metadata_code_1'] != '200') {
        $bpjsLogUpdateAntrean = array(
          'taskid_1' => (@$result['metadata']['code'] == '200') ? 1 : 0,
          'metadata_code_1' => @$result['metadata']['code'],
          'metadata_message_1' => @$result['metadata']['message'],
          'request_1' => json_encode(@$request),
          'response_1' => json_encode(@$result),
          'tgl_catat_1' => $tgl_1,
          'kirim_st_1' => @$kirim_st,
        );
        DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['id' => @$cek_log['id']]);
      }
      // End Task ID 1 (mulai waktu tunggu admisi)
    }

    if ($task_id == '2') {
      // Task ID 2 (akhir waktu tunggu admisi/mulai waktu layan admisi)
      // echo "Task ID 2 \n";
      $random_minute = rand(1, 4);
      
      if(@$cek_reg['dilayani_mulai_tgl'] != '' || @$cek_reg['dilayani_mulai_tgl'] != null) {
        @$tgl_task_id_2 = @$cek_reg['dilayani_mulai_tgl'];
      } else {
        @$tgl_task_id_2 = @$cek_reg['masuk_rs_tgl'];
      }

      $end_waktu_tunggu = strtotime('-' . $random_minute . ' minutes', strtotime(@$tgl_task_id_2)) * 1000;
      $tgl_2 = date("Y-m-d H:i:s", ($end_waktu_tunggu / 1000));
      $request = array(
        "kodebooking" => $kodebooking,
        "taskid" => 2,
        "waktu" => $end_waktu_tunggu
      );

      if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
        if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
          $result = $antrean->updatewaktuantrean($request);
        } else {
          $result = '';
        }
      }

      if (@$cek_log['metadata_code_2'] != '200') {
        $bpjsLogUpdateAntrean = array(
          'taskid_2' => (@$result['metadata']['code'] == '200') ? 1 : 0,
          'metadata_code_2' => @$result['metadata']['code'],
          'metadata_message_2' => @$result['metadata']['message'],
          'request_2' => json_encode(@$request),
          'response_2' => json_encode(@$result),
          'tgl_catat_2' => $tgl_2,
          'kirim_st_2' => @$kirim_st,
        );
        DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['id' => @$cek_log['id']]);
      }
      // End Task ID 2 (akhir waktu tunggu admisi/mulai waktu layan admisi)
    }

    // FUNGSI DIATAS DISABLED KARENA ANTRIAN ONLINE

    if ($task_id == '3') {
      // Task ID 3 (akhir waktu layan admisi/mulai waktu tunggu poli), 
      // echo "Task ID 3\n";
      // date_default_timezone_set($str_region);
      // $end_akhir_layan_admisi = strtotime(@$cek_reg['masuk_rs_tgl']) * 1000;
      // $tgl_3 = date("Y-m-d H:i:s", ($end_akhir_layan_admisi / 1000));
      // $request = array(
      //   "kodebooking" => $kodebooking,
      //   "taskid" => 3,
      //   "waktu" => $end_akhir_layan_admisi
      // );

      // if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
      //   if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
      //     $result = $antrean->updatewaktuantrean($request);
      //   } else {
      //     $result = '';
      //   }
      // }

      // if (@$cek_log['metadata_code_3'] != '200') {
      //   $bpjsLogUpdateAntrean = array(
      //     'taskid_3' => (@$result['metadata']['code'] == '200') ? 1 : 0,
      //     'metadata_code_3' => @$result['metadata']['code'],
      //     'metadata_message_3' => @$result['metadata']['message'],
      //     'request_3' => json_encode(@$request),
      //     'response_3' => json_encode(@$result),
      //     'tgl_catat_3' => $tgl_3,
      //     'kirim_st_3' => @$kirim_st,
      //   );
      //   DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['id' => @$cek_log['id']]);
      // }
      // End Task ID 3 (akhir waktu layan admisi/mulai waktu tunggu poli), 

      // baru

      if (@$cek_reg['dilayani_mulai_tgl'] != '' || @$cek_reg['dilayani_mulai_tgl'] != null) {

        date_default_timezone_set($str_region);
        // $start_masuk_layan_admisi = strtotime(@$cek_reg['dilayani_mulai_tgl']) * 1000;
        // $tgl_3 = date("Y-m-d H:i:s", ($starlt_masuk_layan_admisi / 1000));
        $random_minute = [1200, 1500, 1560, 1620, 1800, 1920, 2400, 2700];

        $randomIndex = array_rand($random_minute);

        $randomm_ = $random_minute[$randomIndex];
        
        // $random_minute = rand(750, 1800, 2000, 2100);

        $masuk_rs_tgl_strtime = strtotime('-' . $randomm_ . ' seconds', strtotime(@$cek_reg['dilayani_mulai_tgl'])) * 1000;



        $masuk_rs_tgl = date("Y-m-d H:i:s", ($masuk_rs_tgl_strtime / 1000));


        $request = array(
          "kodebooking" => $kodebooking,
          "taskid" => 3,
          "waktu" => $masuk_rs_tgl_strtime
        );

        if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
          if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
            $result = $antrean->updatewaktuantrean($request);
          } else {
            $result = '';
          }
        }

        if (@$cek_log['metadata_code_3'] != '200') {
          $bpjsLogUpdateAntrean = array(
            'taskid_3' => (@$result['metadata']['code'] == '200') ? 1 : 0,
            'metadata_code_3' => @$result['metadata']['code'],
            'metadata_message_3' => @$result['metadata']['message'],
            'request_3' => json_encode(@$request),
            'response_3' => json_encode(@$result),
            'tgl_catat_3' => $masuk_rs_tgl,
            'kirim_st_3' => @$kirim_st,
          );
          DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['id' => @$cek_log['id']]);
        }

        // $d_update_masuk_rs_tgl = array(
        //   'masuk_rs_tgl' => @$masuk_rs_tgl,
        // );

        // DB::update('dat_registrasi', $d_update_masuk_rs_tgl, ['registrasi_id' => @$cek_reg['registrasi_id']]);
      }
    }

    if ($task_id == '4') {
      // Task ID 4 (akhir waktu tunggu poli/mulai waktu layan poli), 
      // echo "Task ID 4\n";
      date_default_timezone_set($str_region);

      $start_layan_poli = strtotime(@$cek_reg['dilayani_mulai_tgl']) * 1000;

      $tgl_4 = date("Y-m-d H:i:s", ($start_layan_poli / 1000));
      $request = array(
        "kodebooking" => $kodebooking,
        "taskid" => 4,
        "waktu" => $start_layan_poli
      );

      if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
        if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
          $result = $antrean->updatewaktuantrean($request);
        } else {
          $result = '';
        }
      }

      if (@$cek_log['metadata_code_4'] != '200') {
        $bpjsLogUpdateAntrean = array(
          'taskid_4' => (@$result['metadata']['code'] == '200') ? 1 : 0,
          'metadata_code_4' => @$result['metadata']['code'],
          'metadata_message_4' => @$result['metadata']['message'],
          'request_4' => json_encode(@$request),
          'response_4' => json_encode(@$result),
          'tgl_catat_4' => $tgl_4,
          'kirim_st_4' => @$kirim_st,
        );
        DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['id' => @$cek_log['id']]);
      }
      // End Task ID 4 (akhir waktu tunggu poli/mulai waktu layan poli), 
    }

    $resep = DB::raw('row_array', "SELECT * FROM dat_resep WHERE registrasi_id = '" . $kodebooking . "' ");

    if ($task_id == '5') {
      // Task ID 5 (akhir waktu layan poli/mulai waktu tunggu farmasi), 
      // echo "Task ID 5\n";
      date_default_timezone_set($str_region);

      if (@$cek_reg['tindaklanjut_tgl'] != null) {
        $end_layan_poli = strtotime(@$cek_reg['tindaklanjut_tgl']) * 1000;
      } else {
        $end_layan_poli = strtotime(@$cek_reg['dilayani_selesai_tgl']) * 1000;
      }

      $tgl_5 = date("Y-m-d H:i:s", ($end_layan_poli / 1000));
      // kondisi jenisresep
      if (@$resep != null) {
        $jenisresep = 'Non racikan';
      } else {
        $jenisresep = 'Tidak ada';
      }
      $request = array(
        "kodebooking" => $kodebooking,
        "taskid" => 5,
        "waktu" => $end_layan_poli,
        "jenisresep" => $jenisresep,
      );

      if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
        if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
          $result = $antrean->updatewaktuantrean($request);
        } else {
          $result = '';
        }
      }

      if (@$cek_log['metadata_code_5'] != '200') {
        $bpjsLogUpdateAntrean = array(
          'taskid_5' => (@$result['metadata']['code'] == '200') ? 1 : 0,
          'metadata_code_5' => @$result['metadata']['code'],
          'metadata_message_5' => @$result['metadata']['message'],
          'request_5' => json_encode(@$request),
          'response_5' => json_encode(@$result),
          'tgl_catat_5' => $tgl_5,
          'kirim_st_5' => @$kirim_st,
        );
        DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['id' => @$cek_log['id']]);
      }
      // End Task ID 5 (akhir waktu layan poli/mulai waktu tunggu farmasi), 
    }

    if ($resep != null) {
      if ($task_id == '6') {
        // Tambah Antrean Farmasi
        $cek_log_farmasi = DB::raw('row_array', "SELECT * FROM log_tambah_antrean_farmasi WHERE kodebooking = '" . @$kodebooking . "'");
        if (@$cek_log_farmasi == null || @$cek_log_farmasi['metadata_code'] != 200) {
          date_default_timezone_set($str_region);
          $request = array(
            "kodebooking" => @$kodebooking,
            "jenisresep" => 'non racikan',
            "nomorantrean" => intval(substr($resep['resep_id'], 8, 99)),
            "keterangan" => "non racikan"
          );
          if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
            // if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
            $result = $antrean->tambahAntreanFarmasi($request);
            // } else {
            // $result = '';
            // }
          }
        } else {
          $result = '';
        }

        if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
          if (@$cek_log_farmasi == null) {
            // insert
            // echo "Insert data\n";
            $bpjsLogTambahAntreanFarmasi = array(
              'metadata_code' => @$result['metadata']['code'],
              'metadata_message' => @$result['metadata']['message'],
              'kodebooking' => @$request['kodebooking'],
              'pasien_id' => @$cek_reg['pasien_id'],
              'registrasi_id' => @$cek_reg['registrasi_id'],
              'pelayanan_id' => @$cek_reg['pelayanan_id'],
              'lokasi_id' => @$cek_reg['lokasi_id'],
              'request' => json_encode(@$request),
              'response' => json_encode(@$result),
              'kirim_st' => @$kirim_st,
            );
            $bpjsLogTambahAntreanFarmasi['id'] = DB::get_id('log_tambah_antrean_farmasi');
            DB::insert('log_tambah_antrean_farmasi', $bpjsLogTambahAntreanFarmasi);
            DB::update_id('log_tambah_antrean_farmasi', $bpjsLogTambahAntreanFarmasi['id']);
          } else {
            if (@$result) {
              $bpjsLogTambahAntreanFarmasi = array(
                'metadata_code' => @$result['metadata']['code'],
                'metadata_message' => @$result['metadata']['message'],
                'kodebooking' => @$request['kodebooking'],
                'pasien_id' => @$cek_reg['pasien_id'],
                'registrasi_id' => @$cek_reg['registrasi_id'],
                'pelayanan_id' => @$cek_reg['pelayanan_id'],
                'lokasi_id' => @$cek_reg['lokasi_id'],
                'request' => json_encode(@$request),
                'response' => json_encode(@$result),
                'kirim_st' => @$kirim_st,
              );

              if (@$result['metadata']['code'] != '208') {
                if (@$result['metadata']['code'] == '201') {
                  $message = @$result['metadata']['message'];
                  $cek_sep = strpos(@$message, 'Sudah terbit SEP');

                  if ($cek_sep === false) {
                    DB::update('log_tambah_antrean_farmasi', $bpjsLogTambahAntreanFarmasi, ['id' => @$cek_log_farmasi['id']]);
                  }
                } else {
                  DB::update('log_tambah_antrean_farmasi', $bpjsLogTambahAntreanFarmasi, ['id' => @$cek_log_farmasi['id']]);
                }
              }
            }
          }
        }
        // END Tambah Antrean Farmasi
        // Task ID 6 (akhir waktu tunggu farmasi/mulai waktu layan farmasi membuat obat), 
        // echo "Task ID 6\n";
        date_default_timezone_set($str_region);

        if (@$resep['dilayani_mulai_tgl'] != null) {
          $start_layan_farmasi = strtotime(@$resep['dilayani_mulai_tgl']) * 1000;
        } else if (@$cek_reg['tindaklanjut_tgl'] != null) {
          $start_layan_farmasi = strtotime(@$cek_reg['tindaklanjut_tgl']) * 1000;
        } else {
          $start_layan_farmasi = strtotime(@$resep['resep_tgl']) * 1000;
        }

        $tgl_6 = date("Y-m-d H:i:s", ($start_layan_farmasi / 1000));
        $request = array(
          "kodebooking" => $kodebooking,
          "taskid" => 6,
          "waktu" => $start_layan_farmasi
        );

        if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
          if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
            $result = $antrean->updatewaktuantrean($request);
          } else {
            $result = '';
          }
        }

        if (@$cek_log['metadata_code_6'] != '200') {
          $bpjsLogUpdateAntrean = array(
            'taskid_6' => (@$result['metadata']['code'] == '200') ? 1 : 0,
            'metadata_code_6' => @$result['metadata']['code'],
            'metadata_message_6' => @$result['metadata']['message'],
            'request_6' => json_encode(@$request),
            'response_6' => json_encode(@$result),
            'tgl_catat_6' => $tgl_6,
            'kirim_st_6' => @$kirim_st,
          );
          DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['id' => @$cek_log['id']]);
        }
        // Task ID 6 (akhir waktu tunggu farmasi/mulai waktu layan farmasi membuat obat),
      }

      if ($resep['resep_st'] == 2 || $resep['resep_st'] == 3) {
        if ($task_id == '7') {
          // Task ID 7 (akhir waktu obat selesai dibuat),
          // echo "Task ID 7\n";
          date_default_timezone_set($str_region);

          if ($resep['dilayani_selesai_tgl'] != null) {
            $end_layan_farmasi = strtotime(@$resep['dilayani_selesai_tgl']) * 1000;
          } else {
            $end_layan_farmasi = strtotime(@$resep['resep_tgl_selesai']) * 1000;
          }

          $tgl_7 = date("Y-m-d H:i:s", ($end_layan_farmasi / 1000));
          $request = array(
            "kodebooking" => $kodebooking,
            "taskid" => 7,
            "waktu" => $end_layan_farmasi
          );

          if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
            if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
              $result = $antrean->updatewaktuantrean($request);
            } else {
              $result = '';
            }
          }

          if ($cek_log['metadata_code_7'] != '200') {
            $bpjsLogUpdateAntrean = array(
              'taskid_7' => (@$result['metadata']['code'] == '200') ? 1 : 0,
              'metadata_code_7' => @$result['metadata']['code'],
              'metadata_message_7' => @$result['metadata']['message'],
              'request_7' => json_encode(@$request),
              'response_7' => json_encode(@$result),
              'tgl_catat_7' => $tgl_7,
              'kirim_st_7' => @$kirim_st,
            );
            DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['id' => @$cek_log['id']]);
          }
          // End Task ID 7 (akhir waktu obat selesai dibuat),
        }
      }
    }

    if ($task_id == '99') {
      // Task ID 99  (Batal Periksa), 
      // echo "Task ID 99 \n";
      $waktu_batal = strtotime(@$cek_reg['batal_tgl']) * 1000;
      $tgl_99 = date("Y-m-d H:i:s", ($waktu_batal / 1000));
      $request = array(
        "kodebooking" => $kodebooking,
        "taskid" => 99,
        "waktu" => $waktu_batal
      );

      if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
        if (get_parameter('send_task_id_bpjs')['parameter_cd'] == 'true') {
          $result = $antrean->updatewaktuantrean($request);
        } else {
          $result = '';
        }
      }

      if (@$cek_log['metadata_code_3'] != '200') {
        $bpjsLogUpdateAntrean = array(
          'taskid_99' => (@$result['metadata']['code'] == '200' || @$result['metadata']['code'] == '208') ? 1 : 0,
          'metadata_code_99' => @$result['metadata']['code'],
          'metadata_message_99' => @$result['metadata']['message'],
          'request_99' => json_encode(@$request),
          'response_99' => json_encode(@$result),
          'tgl_catat_99' => $tgl_99,
          'kirim_st_99' => @$kirim_st,
        );
        DB::update('log_update_antrean', $bpjsLogUpdateAntrean, ['id' => @$cek_log['id']]);
      }
      // End Task ID 99 (Batal Periksa), 
    }
  }

  function get_rujukan($no = '', $src = '')
  {
    $antrean = new Nsulistiyawan\Bpjs\VClaim\Rujukan(vclaim_config());
    $rujukan = $antrean->cariByNoKartu($src, $no, true);
    return $rujukan;
  }

  function get_control($tgl, $no)
  {
    $tgl = explode('-', $tgl);
    $kontrol = new Nsulistiyawan\Bpjs\VClaim\RencanaKontrol(vclaim_config());
    $data = $kontrol->listSuratKontrolBerdasarkanNoKartu($tgl[1], $tgl[0], $no, '2');
    return $data;
  }
}
