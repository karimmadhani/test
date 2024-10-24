<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once(APPPATH . 'modules/bridging/controllers/antrean/Config.php');

class M_tindak_lanjut extends CI_Model
{
  var $table, $pk_id;

  function __construct()
  {
    parent::__construct();
    // @params
    $this->table = '';
    $this->pk_id = '';

    $this->AntreanBpjs = new Config();

    // @model
    $this->load->model('farmasi/m_resep');
    $this->load->model('pendaftaran/m_pendaftaran');
  }

  /*
  Tindak Lanjut
  */

  public function get_data($pelayanan_id = null)
  {
    $sql = "SELECT a.*, aa.*, b.tindaklanjut_nm, c.keadaanpasien_nm, d.lokasi_nm, e.fasyankes_nm  
            FROM dat_tindaklanjut a 
            LEFT JOIN 
            (
              SELECT 
                a.pelayanan_id, a.konsul_id, a.lokasi_id, b.lokasi_nm AS lokasi_nm_ranap, a.order_st, a.inden_st, a.ranaputama_st  
              FROM dat_pelayanan a
              LEFT JOIN mst_lokasi b ON a.lokasi_id = b.lokasi_id
              WHERE a.konsul_id = '$pelayanan_id' AND b.lokasi_map = 'BANGSAL'
            ) aa ON a.pelayanan_id = aa.konsul_id 
            LEFT JOIN mst_tindaklanjut b ON a.tindaklanjut_cd = b.tindaklanjut_cd
            LEFT JOIN mst_keadaan_pasien c ON a.keadaanpasien_id = c.keadaanpasien_id 
            LEFT JOIN mst_lokasi d ON a.lokasi_id = d.lokasi_id 
            LEFT JOIN mst_fasyankes e ON a.fasyankes_id = e.fasyankes_id 
            WHERE a.pelayanan_id=?";
    return DB::raw('row_array', $sql, $pelayanan_id);
  }


  public function get_lokasi_sebelumnya($pelayanan_id = null)
  {
    $sql = "SELECT 
              b.lokasi_nm, 
              c.kamar_nm, 
              d.kamar_no   
            FROM dat_pelayanan a 
            INNER JOIN mst_lokasi b ON a.lokasi_id = b.lokasi_id
            LEFT JOIN mst_kamar c ON a.kamar_awal_id = c.kamar_id
            LEFT JOIN mst_kamar_no d ON a.bed_awal_id = d.kamarno_id 
            WHERE a.konsul_id=? AND b.lokasi_map = 'BANGSAL'";
    return DB::raw('row_array', $sql, $pelayanan_id);
  }

  public function save_tindak_lanjut($pelayanan_id = null, $controller = null)
  {
    $d = _post();
    // dd($d);
    // @get
    $pelayanan = DB::get('dat_pelayanan', ['pelayanan_id' => $pelayanan_id]);
    $registrasi = DB::get('dat_registrasi', ['registrasi_id' => $pelayanan['registrasi_id']]);
    $layanan_ke = DB::raw('row_array', "SELECT a.layanan_ke FROM dat_pelayanan a WHERE a.registrasi_id = '" . @$pelayanan['registrasi_id'] . "' ORDER BY a.pelayanan_id DESC LIMIT 1");

    // @process
    if (@$pelayanan['dilayani_selesai_tgl'] == '') {
      // @update dat_pelayanan
      if (@$pelayanan['dilayani_mulai_tgl'] == '') {

        $random_second = rand(540, 600);

        $udpelayanan = array(
          'dilayani_mulai_tgl' => date('Y-m-d H:i:s', strtotime('-' . $random_second . ' seconds', strtotime(to_date(@$d['tindaklanjut_tgl'], '', 'full_date')))),
          'dilayani_st' => 2,
          'dilayani_selesai_tgl' => to_date(@$d['tindaklanjut_tgl'], '', 'full_date'),
        );

        DB::update('dat_pelayanan', $udpelayanan, ['pelayanan_id' => @$pelayanan_id]);


        if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
          // Kirim task id 
          $this->AntreanBpjs->processTaskIdAntreanBPJS(@$pelayanan['registrasi_id'], '4');

          $check_masuk_rs_tgl = DB::raw('row_array', "SELECT metadata_code_1,metadata_code_2,metadata_code_3 FROM log_update_antrean WHERE registrasi_id = '" . @$pelayanan['registrasi_id'] . "'");

          if ($check_masuk_rs_tgl['metadata_code_3'] == null || @$check_masuk_rs_tgl['metadata_code_3'] == '') {
            $this->AntreanBpjs->processTaskIdAntreanBPJS(@$pelayanan['registrasi_id'], '3');
          }


          if (@$registrasi['statuspasien_id'] == 'B') {
            // $this->AntreanBpjs->processTaskIdAntreanBPJS(@$pelayanan['registrasi_id'], '3');
            $this->AntreanBpjs->processTaskIdAntreanBPJS(@$pelayanan['registrasi_id'], '1');
            $this->AntreanBpjs->processTaskIdAntreanBPJS(@$pelayanan['registrasi_id'], '2');
            $this->AntreanBpjs->processTaskIdAntreanBPJS(@$pelayanan['registrasi_id'], '3');
          }
        }
      } else {
        $udpelayanan = array(
          'dilayani_st' => 2,
          'dilayani_selesai_tgl' => to_date(@$d['tindaklanjut_tgl'], '', 'full_date'),
        );
      }
      DB::update('dat_pelayanan', $udpelayanan, ['pelayanan_id' => @$pelayanan_id]);
    } else {
      if (@$pelayanan['dilayani_mulai_tgl'] == '') {
        if (get_parameter('antrean_task_id')['parameter_cd'] == 'true') {
          // Kirim task id 
          $this->AntreanBpjs->processTaskIdAntreanBPJS(@$pelayanan['registrasi_id'], '4');
        }
        $random_second = rand(540, 600);
        $udpelayanan = array(
          'dilayani_mulai_tgl' => date('Y-m-d H:i:s', strtotime('-' . $random_second . ' seconds', strtotime(to_date(@$d['tindaklanjut_tgl'], '', 'full_date')))),
        );
        DB::update('dat_pelayanan', $udpelayanan, ['pelayanan_id' => @$pelayanan_id]);
      }
    }

    // @insert dat_pelayanan (kondisi : Rujuk Rawat Inap)
    $t_lokasi_id = null;
    if (@$d['tindaklanjut_cd'] == '03') {
      $cek_ranap = DB::raw('row_array', "SELECT * FROM dat_tindaklanjut WHERE pelayanan_id=? LIMIT 1", ['pelayanan_id' => @$pelayanan['pelayanan_id']]);
      // @kondisi tindak lanjut : Rujuk Rawat Inap
      if (@$d['lokasi_id'] != '') {
        $t_lokasi_id = @$d['lokasi_id'];
      }
      $idpelayanan = array(
        'pelayanan_id' => DB::get_id('dat_pelayanan'),
        'registrasi_id' => @$pelayanan['registrasi_id'],
        'pasien_id' => @$pelayanan['pasien_id'],
        'kelas_hak_id' => @$pelayanan['kelas_hak_id'],
        'kelas_layanan_id' => @$pelayanan['kelas_layanan_id'],
        'lokasi_asal_id' => @$pelayanan['lokasi_id'],
        'lokasi_id' => @$t_lokasi_id,
        'dokter_asal_id' => @$pelayanan['dokter_id'],
        'dokter_id' => @$d['dpjp_id'],
        'registrasi_ke' => '2',
        'layanan_ke' => (@$layanan_ke['layanan_ke'] + 1),
        'konsul_id' => @$pelayanan_id,
        'order_st' => 0,
        'order_tgl' => to_date(@$d['tindaklanjut_tgl'], '', 'full_date'),
        'keterangan_klinis' => null,
        'tipeorder_id' => null,
        'note_ruang_ranap' => @$d['note_ruang_ranap'],
      );

      if (@$cek_ranap == '' || @$cek_ranap == null) {
        $save = DB::insert('dat_pelayanan', $idpelayanan);
        DB::update_id('dat_pelayanan', @$idpelayanan['pelayanan_id']);
        if ($save) {
          $pelayanan_id_tujuan = @$idpelayanan['pelayanan_id'];
        } else {
          $pelayanan_id_tujuan = null;
        }
      } else {
        $pelayanan_id_tujuan = null;
      }
    } elseif (@$d['tindaklanjut_cd'] == '04') {

      $jumlah_surat = DB::raw('row_array', "SELECT COUNT(*) AS jumlah FROM dat_surat_rujukan_keluar WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)");
      // $d['nomor_surat'] = '4451/' . ($jumlah_surat['jumlah'] + 1) . '/R/' . bulanToRomawi(date('n')) . '/' . date('Y');

      $rujukan_keluar = array(
        'suratrujukankeluar_id' => DB::get_id('dat_surat_rujukan_keluar'),
        'pelayanan_id' => @$pelayanan_id,
        'registrasi_id' => @$d['registrasi_id'],
        'lokasi_id' => @$pelayanan['lokasi_id'],
        'pasien_id' => @$pelayanan['pasien_id'],
        'dokter_id' => @$pelayanan['dokter_id'],
        'ppa_id' => _ses_get('pegawai_id'),
        'suratrujukankeluar_tgl' => to_date(@$d['tindaklanjut_tgl'], '', 'full_date'),
        // 'nomor_surat' => $d['nomor_surat'],
        'pengirim_id' => @$pelayanan['dokter_id'],
        'rumah_sakit' => @$d['rumah_sakit'],
        'bagian' => @$d['bagian'],
        'di' => '',
        'gejala' => @$d['gejala'],
        'diagnosa' => @$d['diagRujukan'],
        'kondisi_pasien' => @$d['kondisi_pasien'],
        'pertolongan_darurat_pertama' => @$d['pertolongan_darurat_pertama']
      );

      DB::insert('dat_surat_rujukan_keluar', $rujukan_keluar);
      DB::update_id('dat_surat_rujukan_keluar', $rujukan_keluar['suratrujukankeluar_id']);
    } elseif (@$d['tindaklanjut_cd'] == '08') {
      // @kondisi tindak lanjut : Selesai Konsul
    } elseif (@$d['tindaklanjut_cd'] == '09') {
      // @kondisi tindak lanjut : Selesai Operasi
      // @update dat_pelayanan lokasi IBS where pelayanan_id
      $dpelayananIBS1 = array(
        'dilayani_st' => 2,
        'dilayani_selesai_tgl' => to_date(@$d['tindaklanjut_tgl'], '', 'full_date'),
        'order_st' => 1,
      );
      DB::update('dat_pelayanan', $dpelayananIBS1, ['pelayanan_id' => @$pelayanan_id]);
      // @update dat_pelayanan lokasi IBS where pasien_id & antrean_st 2 / update yang Kunjungan Berikutnya
      $dpelayananIBS2 = array(
        'dilayani_st' => 2,
        'dilayani_selesai_tgl' => to_date(@$d['tindaklanjut_tgl'], '', 'full_date'),
        'order_st' => 1,
      );
      DB::update('dat_pelayanan', $dpelayananIBS2, ['pasien_id' => @$pelayanan['pasien_id'], 'antrean_st' => 2, 'antrean_tgl' => @$pelayanan['antrean_tgl']]);
    } else {
      if (@$controller != 'penunjang') {
        // @update dat_registrasi
        $dr = array(
          'keluar_rs_st' => 1,
          'keluar_rs_tgl' => to_date(@$d['tindaklanjut_tgl'], '', 'full_date'),
        );
        DB::update('dat_registrasi', $dr, ['registrasi_id' => @$pelayanan['registrasi_id']]);
      }
    }

    // @insert dat_tindaklanjut
    $dt = array(
      'pelayanan_id' => @$pelayanan['pelayanan_id'],
      'registrasi_id' => @$pelayanan['registrasi_id'],
      'pasien_id' => @$pelayanan['pasien_id'],
      'tindaklanjut_tgl' => to_date(@$d['tindaklanjut_tgl'], '', 'full_date'),
      'lokasi_asal_id' => @$pelayanan['lokasi_id'],
      'lokasi_id' => @$t_lokasi_id,
      'dokter_id' => @$pelayanan['dokter_id'],
      'penjamin_id' => @$registrasi['penjamin_id'],
      'kelas_hak_id' => @$pelayanan['kelas_hak_id'],
      'kelas_layanan_id' => @$pelayanan['kelas_layanan_id'],
      'tindaklanjut_cd' => @$d['tindaklanjut_cd'],
      'keadaanpasien_id' => @$d['keadaanpasien_id'],
      'kontrol_tgl' => to_date(@$d['kontrol_tgl'], '', 'date'),
      'fasyankes_id' => @$d['rumah_sakit'],
      'catatan_tindak_lanjut' => @$d['catatan_tindak_lanjut'],
      'catatan_emergency' => @$d['catatan_emergency'],
      'catatan_dokter' => @$d['catatan_dokter'],
      'catatan_perawat' => @$d['catatan_perawat'],
      'catatan_farmasi' => @$d['catatan_farmasi'],
      'catatan_gizi' => @$d['catatan_gizi'],
      'ket_kondisi' => @$d['ket_kondisi']
      // 'pelayanan_id_tujuan' => @$pelayanan_id_tujuan,
    );

    if (@$pelayanan_id_tujuan != null || @$pelayanan_id_tujuan != '') {
      @$dt['pelayanan_id_tujuan'] = @$pelayanan_id_tujuan;
    }

    $tindaklanjut = DB::raw('row_array', "SELECT a.tindaklanjut_id FROM dat_tindaklanjut a WHERE a.pelayanan_id = '" . @$pelayanan['pelayanan_id'] . "' LIMIT 1");
    if (@$tindaklanjut['tindaklanjut_id'] != '' || @$tindaklanjut['tindaklanjut_id'] != null) {
      DB::update('dat_tindaklanjut', $dt, ['tindaklanjut_id' => @$tindaklanjut['tindaklanjut_id']]);
    } else {
      $dt['tindaklanjut_id'] = DB::get_id('dat_tindaklanjut');
      DB::insert('dat_tindaklanjut', $dt);
      DB::update_id('dat_tindaklanjut', $dt['tindaklanjut_id']);
    }

    // Generate Antrean Resep Rajal
    $resepData = DB::raw('result_array', "SELECT * FROM dat_resep WHERE pelayanan_id = '" . @$pelayanan['pelayanan_id'] . "' AND registrasi_id = '" . @$pelayanan['registrasi_id'] . "' ORDER BY resep_id ASC");
    if (count($resepData) > 0) { // Cek jika ada resep
      foreach ($resepData as $key => $value) {
        if (@$value['lokasidepo_id'] == '06.03') { //Khusus Depo Farmasi Rajal Pembuatan Antrean Farmasi Ketika Tindak Lanjut
          if (@$value['urut_no'] == '' || @$value['urut_no'] == NULL) { // Jika belum ada nomor urut
            $dresepAntrean = array(
              'antrean_st' => 1,
              'antrean_tgl' => date('Y-m-d'),
            );
            $antrean = $this->m_resep->get_antrean_no($value['resep_id'], @$value['lokasidepo_id'], date('Y-m-d'));

            $dresepAntrean['antrean_cd'] = $antrean['antrean_cd'];
            $dresepAntrean['antrean_no'] = $antrean['antrean_no'];
            $dresepAntrean['urut_no'] = $antrean['urut_no'];
            DB::update('dat_resep', $dresepAntrean, ['resep_id' => @$value['resep_id']]);
          }
        } else {
          if (@$value['urut_no'] == '' || @$value['urut_no'] == NULL) { // Jika belum ada nomor urut
            $dresepAntrean = array(
              'antrean_st' => 1,
              'antrean_tgl' => date('Y-m-d'),
            );
            $antrean = $this->m_resep->get_antrean_no($value['resep_id'], @$value['lokasidepo_id'], date('Y-m-d'));

            $dresepAntrean['antrean_cd'] = $antrean['antrean_cd'];
            $dresepAntrean['antrean_no'] = $antrean['antrean_no'];
            $dresepAntrean['urut_no'] = $antrean['urut_no'];
            DB::update('dat_resep', $dresepAntrean, ['resep_id' => @$value['resep_id']]);
          }
        }
      }
    }

    //@save cppt terintegrasi
    $this->save_cppt_terintegrasi($pelayanan_id);

    // update kamar
    if (@$d['tindaklanjut_cd'] == '00' || @$d['tindaklanjut_cd'] == '01' || @$d['tindaklanjut_cd'] == '02' || @$d['tindaklanjut_cd'] == '04' || @$d['tindaklanjut_cd'] == '05' || @$d['tindaklanjut_cd'] == '06' || @$d['tindaklanjut_cd'] == '07' || @$d['tindaklanjut_cd'] == '10' || @$d['tindaklanjut_cd'] == '11') {
      $lokasi = DB::get('mst_lokasi', ['lokasi_id' => @$pelayanan['lokasi_id']]);
      if (@$lokasi['jenisregistrasi_id'] == '2') {
        // @update mst_kamar : PENAMBAHAN
        $hitung_kamar = hitung_kamar(@$pelayanan['kamar_awal_id'], 'L', 'penambahan');
        $sebmk = array(
          'terisi_bed_lk' => @$hitung_kamar['terisi_bed_lk'],
          'terisi_bed_pr' => @$hitung_kamar['terisi_bed_pr'],
          'terisi_bed_total' => @$hitung_kamar['terisi_bed_total'],
          'sisa_bed_lk' => @$hitung_kamar['sisa_bed_lk'],
          'sisa_bed_pr' => @$hitung_kamar['sisa_bed_pr'],
          'sisa_bed_total' => @$hitung_kamar['sisa_bed_total'],
        );
        DB::update('mst_kamar', $sebmk, ['kamar_id' => @$pelayanan['kamar_awal_id'], 'lokasi_id' => @$pelayanan['lokasi_id']]);

        // @update mst_kamar_no
        $mkno = array(
          'tersedia_st' => 1,
          'terakhirdipakai_tgl' => @$registrasi['registrasi_tgl'],
          'terakhirkembali_tgl' => date('Y-m-d H:i:s')
        );
        DB::update('mst_kamar_no', $mkno, ['kamarno_id' => @$pelayanan['bed_awal_id']]);

        // @update dat_pelayanan_kamar : kamar_keluar_tgl, hari, kamar_keluar_st = 1
        $pelayanan_kamar = DB::get('dat_pelayanan_kamar', ['pelayanan_id' => @$pelayanan_id, 'registrasi_id' => @$pelayanan['registrasi_id'], 'kelas_layanan_id' => @$pelayanan['kelas_layanan_id'], 'lokasi_id' => @$pelayanan['lokasi_id'], 'kamar_id' => @$pelayanan['kamar_awal_id'], 'bed_id' => @$pelayanan['bed_awal_id']]);
        $udpk = array(
          'kamar_keluar_tgl' => date('Y-m-d'),
          'hari' => hitung_hari(date('Y-m-d'), @$pelayanan_kamar['kamar_masuk_tgl']),
          'kamar_keluar_st' => 1,
        );
        DB::update('dat_pelayanan_kamar', $udpk, ['pelayanankamar_id' => @$pelayanan_kamar['pelayanankamar_id'], 'pelayanan_id' => @$pelayanan_kamar['pelayanan_id']]);
      }
    }
    // END update kamar
  }

  public function batal_tindak_lanjut($pelayanan_id = null, $registrasi_id = null)
  {
    $pelayanan = DB::get('dat_pelayanan', ['pelayanan_id' => $pelayanan_id]);
    $tindak_lanjut = DB::get('dat_tindaklanjut', ['pelayanan_id' => $pelayanan_id]);
    $sp_dirawat = DB::get('dat_surat_perintah_dirawat', ['pelayanan_id' => $pelayanan_id]);

    if (@$tindak_lanjut['tindaklanjut_id'] != '') {
      if (@$tindak_lanjut['tindaklanjut_cd'] == '03') {
        // @kondisi tindak lanjut : Rujuk Rawat Inap
        // $pelayanan_tindaklanjut = DB::get('dat_pelayanan', ['konsul_id' => $pelayanan_id]);
        $pelayanan_tindaklanjut = DB::raw(
          'row_array',
          "SELECT 
            a.* 
          FROM dat_pelayanan a
          LEFT JOIN mst_lokasi b ON a.lokasi_id = b.lokasi_id
          WHERE a.konsul_id = '" . $pelayanan_id . "' AND (b.jenisregistrasi_id='2' OR a.lokasi_id IS NULL) AND (a.ranaputama_st = 1 OR a.order_st = 0)"
        );
        if (@$pelayanan_tindaklanjut['pelayanan_id'] != '') {
          // @delete dat_pelayanan yang rujuk rawat inap
          DB::delete('dat_pelayanan', ['pelayanan_id' => @$pelayanan_tindaklanjut['pelayanan_id']]);
        }

        if (@$sp_dirawat != '') {
          DB::delete('dat_surat_perintah_dirawat', ['surat_perintah_dirawat_id' => $sp_dirawat['surat_perintah_dirawat_id']]);
        }
      } elseif (@$tindak_lanjut['tindaklanjut_cd'] == '08') {
        // @kondisi tindak lanjut : Selesai Konsul
      } elseif (@$tindak_lanjut['tindaklanjut_cd'] == '09') {
        // @kondisi tindak lanjut : Selesai Operasi
        // @update dat_pelayanan lokasi IBS where pelayanan_id
        $dpelayananIBS1 = array(
          'dilayani_st' => 1,
          'dilayani_selesai_tgl' => NULL,
          'order_st' => 0,
        );
        DB::update('dat_pelayanan', $dpelayananIBS1, ['pelayanan_id' => @$pelayanan_id]);
        // @update dat_pelayanan lokasi IBS where pasien_id & antrean_st 2 / update yang Kunjungan Berikutnya
        $dpelayananIBS2 = array(
          'dilayani_st' => 1,
          'dilayani_selesai_tgl' => NULL,
          'order_st' => 0,
        );
        DB::update('dat_pelayanan', $dpelayananIBS2, ['pasien_id' => @$pelayanan['pasien_id'], 'antrean_st' => 2]);
      } else {
        // @update dat_registrasi
        $dr = array(
          'keluar_rs_st' => 0,
          'keluar_rs_tgl' => NULL,
        );
        DB::update('dat_registrasi', $dr, ['registrasi_id' => @$registrasi_id]);
      }
      // @delete dat_tindaklanjut
      DB::delete('dat_tindaklanjut', ['tindaklanjut_id' => @$tindak_lanjut['tindaklanjut_id']]);

      $lokasi = DB::get('mst_lokasi', ['lokasi_id' => @$pelayanan['lokasi_id']]);
      if (@$lokasi['jenisregistrasi_id'] == '2') {
        // @update mst_kamar : PENGURANGAN
        $hitung_kamar = hitung_kamar(@$pelayanan['kamar_awal_id'], 'L', 'pengurangan');
        $sebmk = array(
          'terisi_bed_lk' => @$hitung_kamar['terisi_bed_lk'],
          'terisi_bed_pr' => @$hitung_kamar['terisi_bed_pr'],
          'terisi_bed_total' => @$hitung_kamar['terisi_bed_total'],
          'sisa_bed_lk' => @$hitung_kamar['sisa_bed_lk'],
          'sisa_bed_pr' => @$hitung_kamar['sisa_bed_pr'],
          'sisa_bed_total' => @$hitung_kamar['sisa_bed_total'],
        );
        DB::update('mst_kamar', $sebmk, ['kamar_id' => @$pelayanan['kamar_awal_id'], 'lokasi_id' => @$pelayanan['lokasi_id']]);

        // @update mst_kamar_no
        $mkno = array(
          'tersedia_st' => 0,
        );
        DB::update('mst_kamar_no', $mkno, ['kamarno_id' => @$pelayanan['bed_awal_id']]);
      }
    }
  }

  function save_cppt_terintegrasi($pelayanan_id)
  {
    // $pelayanan = DB::get('dat_pelayanan', ['pelayanan_id' => $pelayanan_id]);
    $pelayanan = DB::raw(
      'row_array',
      "SELECT 
        a.*, b.jenisregistrasi_id 
      FROM dat_pelayanan a 
      LEFT JOIN mst_lokasi b ON a.lokasi_id = b.lokasi_id 
      WHERE a.pelayanan_id = ?",
      $pelayanan_id
    );
    if (@$pelayanan['jenisregistrasi_id'] == '1') {
      // jenisregistrasi_id : 1 -> RAWAT JALAN
      $anamnesis = DB::get('dat_anamnesis', ['pelayanan_id' => $pelayanan_id]);
      //@save subjective
      $s = '';
      if (@$anamnesis['anamnesa'] != '') {
        $s .= @$anamnesis['anamnesa'] . "<br>";
      }
      if (@$anamnesis['alergi'] != '') {
        $s .= "Alergi : " . @$anamnesis['alergi'] . "<br>";
      }
      if (isset($anamnesis['sumber_informasi'])) {
        if (is_array($anamnesis['sumber_informasi'])) {
          $s .= "Sumber Informasi : " . hastag_to_comma($anamnesis['sumber_informasi']) . "<br>";
        }
      }
      if (isset($anamnesis['riwayat_penyakit_terdahulu'])) {
        if (is_array($anamnesis['riwayat_penyakit_terdahulu'])) {
          $s .= "Riwayat Penyakit Terdahulu : " . hastag_to_comma($anamnesis['riwayat_penyakit_terdahulu']) . "<br>";
        }
      }
      if (isset($anamnesis['riwayat_penyakit_keluarga'])) {
        if (is_array($anamnesis['riwayat_penyakit_keluarga'])) {
          $s .= "Riwayat Penyakit Keluarga : " . hastag_to_comma($anamnesis['riwayat_penyakit_keluarga']) . "<br>";
        }
      }
      //@save Objective
      $o = '';
      $o .= '<b>GCS</b> : <b>E</b> :&nbsp; <b>M</b> :&nbsp; <b>V</b> :&nbsp; <br>';
      $o .= '<b>TEKANAN DARAH</b> : ' . ((@$anamnesis['systole'] != '') ? @$anamnesis['systole'] : "&nbsp;") . '/' . ((@$anamnesis['diastole'] != '') ? @$anamnesis['diastole'] : "&nbsp;") . ' mmHg <br>';
      $o .= '<b>NADI</b> :' . ((@$anamnesis['nadi'] != '') ? @$anamnesis['nadi'] : "&nbsp;") . ' x/Menit <br>';
      $o .= '<b>RESPIRASI</b> :&nbsp; x/Menit <br>';
      $o .= '<b>SUHU</b> :' . ((@$anamnesis['suhu'] != '') ? @$anamnesis['suhu'] : "&nbsp;") . ' &#8451; <br>';
      $o .= '<b>BB</b> :' . ((@$anamnesis['berat_badan'] != '') ? @$anamnesis['berat_badan'] : "&nbsp;") . ' kg <br>';
      $o .= '<b>TB</b> :' . ((@$anamnesis['tinggi_badan'] != '') ? @$anamnesis['tinggi_badan'] : "&nbsp;") . ' cm <br>';
      $o .= '<b>SPO2 SKALA 1</b> :' . ((@$anamnesis['spo2'] != '') ? @$anamnesis['spo2'] : "&nbsp;") . ' % <br>';
      $o .= '<b>SPO2 SKALA 2</b> :&nbsp; % <br>';
      $o .= '<br><b>HASIL PEMERIKSAAN FISIK </b><br>';
      $o .= nl2br(htmlentities(@$anamnesis['objective_uraian'], ENT_QUOTES, 'UTF-8')) . '<br>';
      //@save asessment
      $diagnosis = DB::raw(
        'result_array',
        "SELECT 
        a.*, 
        b.icd10_nm 
      FROM dat_diagnosis a 
      LEFT JOIN mst_icd10 b ON a.icd10_id=b.icd10_id
      WHERE a.pelayanan_id = ?
      ORDER BY a.diagnosis_id ASC",
        $pelayanan_id
      );
      $a = '';
      if (@$anamnesis['diagnosis_primer'] != '') {
        $a .= "Dx Primer : " . @$anamnesis['diagnosis_primer'] . "<br>";
      }
      if (@$anamnesis['diagnosis_sekunder'] != '') {
        $a .= "Dx Sekunder : " . @$anamnesis['diagnosis_sekunder'] . "<br>";
      }
      foreach ($diagnosis as $key => $diag) {
        $a .= $diag['icd10_id'] . ':' . $diag['icd10_nm'] . ' ' . '(' . @$diag['diagnosis_klinis'] . ')' . ', ';
      }
      //@save planning
      $tindak_lanjut = DB::raw(
        'result_array',
        "SELECT 
        a.* 
      FROM dat_tindaklanjut a 
      WHERE a.pelayanan_id = ?",
        $pelayanan_id
      );
      $p = '';
      if (@$tindak_lanjut['catatan_tindak_lanjut'] != '') {
        $p .= "Catatan Tindak Lanjut : " . @$tindak_lanjut['catatan_tindak_lanjut'] . "<br>";
      }
      if (@$anamnesis['planning'] != '') {
        $p .= @$anamnesis['planning'] . "<br>";
      }
      if (@$anamnesis['keterangan'] != '') {
        $p .= "Keterangan : " . @$anamnesis['keterangan'] . "<br>";
      }

      if (@$tindak_lanjut['tindaklanjut_tgl'] != '') {
        $cppt_tgl = @$tindak_lanjut['tindaklanjut_tgl'];
      } else {
        $cppt_tgl = date('Y-m-d H:i:s');
      }

      $cppt = array(
        'pelayanan_id' => @$pelayanan['pelayanan_id'],
        'registrasi_id' => @$pelayanan['registrasi_id'],
        'pasien_id' => @$pelayanan['pasien_id'],
        'cppt_tgl' => @$cppt_tgl,
        'lokasi_id' => @$pelayanan['lokasi_id'],
        'profesicppt_id' => '02', // Dokter
        'jenisverifikasi_cd' => '01',
        'ppa_id' => _ses_get('pegawai_id'),
        's' => @$s,
        'o' => @$o,
        'a' => @$a,
        'p' => @$p,
      );


      if (_ses_get('dokter_st') == '1') {
        $profesicppt_id = '02';
      } else {
        $profesicppt_id = '03';
      }

      if ($profesicppt_id == '02') {
        $cek_cppt = DB::get('dat_cppt', [
          'pelayanan_id' => $pelayanan_id,
          'ppa_id' => _ses_get('pegawai_id'),
          'profesicppt_id' => @$profesicppt_id,
        ]);

        if (@$cek_cppt['cppt_id'] != '') {
          DB::update('dat_cppt', $cppt, ['cppt_id' => @$cek_cppt['cppt_id']]);
        } else {
          $cppt['cppt_id'] = DB::get_id('dat_cppt');
          DB::insert('dat_cppt', $cppt);
          // DB::update_id('dat_cppt', $cppt['cppt_id']);
        }
      }
    }
  }

  public function all_diagnosis($registrasi_id = null, $pelayanan_id = null)
  {
    $sql = "SELECT 
              a.diagnosis_id, a.icd10_id, a.diagnosis_klinis, a.ihs_id,
              b.icd10_nm,
              c.parameter_cd AS diagnosiskelompok_nm,
              d.parameter_cd AS diagnosisjenis_nm,
              e.parameter_cd AS diagnosiskasus_nm  
            FROM dat_diagnosis a 
            LEFT JOIN mst_icd10 b ON a.icd10_id = b.icd10_id
            LEFT JOIN mst_parameter c ON c.parameter_val = a.diagnosiskelompok_cd AND c.parameter_field = 'diagnosiskelompok_cd'
            LEFT JOIN mst_parameter d ON d.parameter_val = a.diagnosisjenis_cd AND d.parameter_field = 'diagnosisjenis_cd'
            LEFT JOIN mst_parameter e ON e.parameter_val = a.diagnosiskasus_cd AND e.parameter_field = 'diagnosiskasus_cd'
            WHERE a.registrasi_id = '" . @$registrasi_id . "' AND a.pelayanan_id = '" . @$pelayanan_id . "'
            ORDER BY a.diagnosis_id ASC";
    $res = DB::raw('result_array', $sql);
    return $res;
  }
}
