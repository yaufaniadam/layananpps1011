<?php defined('BASEPATH') or exit('No direct script access allowed');
class Surat extends Admin_Controller
{

	public function __construct()
	{
		parent::__construct();
		$this->load->model('mahasiswa/surat_model', 'surat_model');
		$this->load->model('notif/Notif_model', 'notif_model');
		$this->load->library('mailer');
		$this->load->model('survey/survey_model', 'survey_model');
		$this->load->model('admin/template_model', 'template_model');
	}

	public function index($role = 0)
	{
		$data['query'] = $this->surat_model->get_surat($role);
		$data['role'] = $role;
		$data['title'] = 'Pengajuan';
		$data['view'] = 'surat/index';
		$this->load->view('layout/layout', $data);
	}

	public function getsurat_json($role = 0)
	{

		$records['data'] = $this->surat_model->get_surat(1);
		$data = array();

		$i = 0;
		foreach ($records['data']  as $surat) {
			$data[] = array(
				$surat['id_surat'],
				$surat['kategori_surat'],
				$surat['status'],
				$surat['fullname'],
				$surat['prodi'],
				$surat['date_full'],

				($surat['id_status'] != 20)  ?
					'<a class=" btn btn-sm  btn-circle btn-success" href="' . base_url('admin/surat/detail/' . encrypt_url($surat['id_surat'])) . '"><i class="fas fa-search"></i></a></a>
				<a href="" style="color:#fff;" title="Hapus"
				class="delete btn btn-sm  btn-circle btn-danger"
				data-href="' . base_url('admin/surat/hapus/d/' . $surat['id_kategori_surat'] . '/' . encrypt_url($surat['id_surat'])) . '"
				data-toggle="modal" data-target="#confirm-delete"> <i class="fa fa-trash-alt"></i></a>' :
					'<a href="' . base_url('admin/surat/hapus/r/' . $surat['id_kategori_surat'] . '/' . encrypt_url($surat['id_surat'])) . '" style="color:#fff;" title="Kembalikan"
				class="restore btn btn-sm  btn-circle btn-success"> <i
					class="fa fa-undo"></i></a>'
			);
		}
		$records['data'] = $data;
		echo json_encode($records);
	}

	public function detail($id_surat = 0)
	{
		$id_surat = decrypt_url($id_surat);

		if ($id_surat) {

			$data['status'] = $this->surat_model->get_surat_status($id_surat);
			$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
			$data['timeline'] = $this->surat_model->get_timeline($id_surat);
			$data['fields'] = $this->surat_model->get_fields_by_id_kat_surat($data['surat']['id_kategori_surat']);
			$data['template'] = $this->template_model->get_template_bykat($data['surat']['id_kategori_surat']);

			if ($data['surat']['id_status'] == 8 || $data['surat']['id_status'] == 9 || $data['surat']['id_status'] == 10) {

				$data['no_surat_data'] = $this->surat_model->get_no_surat($id_surat);

				if ($data['surat']['id'] < 785 && (!$data['no_surat_data'])) {
					// isi table no_surat untuk surat2 lama supaya bisa dipakai di sistem baru
					$this->db->insert('no_surat', ['id_surat' => $id_surat, 'hal' => $data['surat']['kategori_surat']]);

					// echo "surat lama yg baru diproses no suratnya";

					redirect(base_url('admin/surat/detail/' . encrypt_url($id_surat)));
				}
			}

			//cek apakah admin atau pengguna prodi ( admin prodi, tu, kaprodi, kecuali mhs)
			if (($data['surat']['id_prodi'] == $this->session->userdata('id_prodi') && $this->session->userdata('role') !== 1) ||
				$this->session->userdata('role') == 1 || $this->session->userdata('role') == 5
			) {

				if ($data['surat']['id_status'] == 10) {

					//cek apakah sudah mengisi survey
					$survey = $this->survey_model->get_survey($id_surat, $data['surat']['id_mahasiswa']);
					if ($survey) {
						$data['sudah_survey'] = 1;
						$data['hasil_survey'] = $survey;
					} else {
						$data['sudah_survey'] = 0;
					}
				}
				$data['title'] = 'Detail Surat';
				$data['view'] = 'surat/detail';
			} else {
				$data['title'] = 'Forbidden';
				$data['view'] = 'restricted';
			}
		} else {
			$data['title'] = 'Halaman tidak ditemukan';
			$data['view'] = 'error404';
		}

		$this->load->view('layout/layout', $data);
	}

	public function hapus($kode, $id_kat, $id_surat)
	{

		$id_surat = decrypt_url($id_surat);
		if ($id_surat) {

			if ($kode == 'd') {

				$hapus_exist = $this->db->get_where('surat_status', ['id_surat' => $id_surat, 'id_status' => 20])->num_rows();

				if ($hapus_exist < 1) {

					$hapus = $this->db->set('id_status', '20')
						->set('date', 'NOW()', FALSE)
						->set('id_surat', $id_surat)
						->set('pic', $_SESSION['user_id'])
						->insert('surat_status');
				} else {
					redirect(base_url('admin/surat/index'));
				}

				//hapus notif yg berkaitan
				$this->db->where(['id_surat' => $id_surat]);
				$hapus = $this->db->delete('notif');

				//hapus yudisium yg berkaitan jika berhubungan drn kategori surat yudisium
				if ($id_kat == 6) {
					$this->db->where(['id_surat' => $id_surat]);
					$hapus = $this->db->update('yudisium', ['aktif' => 'd']);
				}
			} else if ($kode == 'r') {
				$this->db->where(['id_surat' => $id_surat, 'id_status' => '20']);
				$this->db->delete('surat_status');

				//kembalikan peserta di tabel yudisium yjika berhubungan drn kategori surat yudisium
				if ($id_kat == 6) {
					$this->db->where(['id_surat' => $id_surat]);
					$hapus = $this->db->update('yudisium', ['aktif' => '']);
				}
			}

			$this->session->set_flashdata('msg', 'Surat berhasil dihapus!');
			redirect(base_url('admin/surat/index'));
		} else {
			$data['title'] = 'Halaman tidak ditemukan';
			$data['view'] = 'error404';
			$this->load->view('layout/layout', $data);
		}
	}
	public function proses_surat($id_surat = 0)
	{
		$this->db->set('id_status', 2)
			->set('date', 'NOW()', FALSE)
			->set('id_surat', $id_surat)
			->insert('surat_status');

		redirect(base_url('admin/surat/detail/' . $id_surat));
	}


	public function verifikasi()
	{
		if ($this->input->post('submit')) {

			$verifikasi = $this->input->post('verifikasi'); //ambil nilai 
			$id_surat = $this->input->post('id_surat');
			$id_notif = $this->input->post('id_notif');
			//set status
			$this->db->set('id_status', $this->input->post('rev2'))
				->set('pic', $this->session->userdata('user_id'))
				->set('date', 'NOW()', FALSE)
				->set('id_surat', $id_surat)
				->set('catatan', $this->input->post('catatan'))
				->insert('surat_status');

			foreach ($verifikasi as $id => $value_verifikasi) {

				$this->db->where(array('id_kat_keterangan_surat' => $id, 'id_surat' => $id_surat))
					->update(
						'keterangan_surat',
						array(
							'verifikasi' =>  $value_verifikasi,
						)
					);
			}

			if ($this->input->post('rev2') == 6) {
				$role = array(3, 2);
			} else if ($this->input->post('rev2') == 4) {
				$role = array(3, 2);
			} else if ($this->input->post('rev2') == 7) {
				$role = array(3, 6);
			}

			// buat notifikasi
			$data_notif = array(
				'id_surat' => $id_surat,
				'id_status' => $this->input->post('rev2'),
				'kepada' => $this->input->post('user_id'),
				'role' => $role
			);

			//sendmail & notif
			$this->mailer->send_mail($data_notif);

			//remove notif yg berkaitan sama surat ini
			$set_notif = $this->db->update('notif', ['dibaca' => date('Y-m-d H:i:s'), 'status' => 1], ['id_surat' => $id_surat, 'role' => $this->session->userdata('role')]);

			if ($set_notif) {
				$this->session->set_flashdata('msg', 'Surat sudah diperiksa oleh TU!');
				redirect(base_url('admin/surat/detail/' . encrypt_url($id_surat)));
			}
		} else {
			$data['title'] = 'Forbidden';
			$data['view'] = 'restricted';
			$this->load->view('layout/layout', $data);
		}
	}

	public function disetujui()
	{
		if ($this->input->post('submit')) {

			if ($this->session->userdata('role') == 1) { // tu pasca
				$id_surat = $this->input->post('id_surat');
				$id_mahasiswa = $this->surat_model->get_detail_surat($id_surat)['id_mahasiswa'];
				$result = $this->db->set('id_status', 9)
					->set('date', 'NOW()', FALSE)
					->set('id_surat', $id_surat)
					->set('pic', $this->session->userdata('user_id'))
					->insert('surat_status');


				if ($result) {
					$data_notif = array(
						'id_surat' => $id_surat,
						'id_status' => 9,
						'kepada' => $id_mahasiswa,
						'role' => array(3, 5)
					);

					//sendmail & notif
					$this->mailer->send_mail($data_notif);

					//remove notif yg berkaitan sama surat ini
					$this->db->update('notif', ['dibaca' => date('Y-m-d H:i:s'), 'status' => 1], ['id_surat' => $id_surat, 'role' => $this->session->userdata('role')]);

					$this->session->set_flashdata('msg', 'Surat berhasil dikirim untuk diACC oleh Direktur Pascasarjana!');
					redirect(base_url('admin/surat/detail/' . encrypt_url($id_surat)));
				}
			} elseif ($this->session->userdata('role') == 6 && $this->session->userdata('id_prodi') == $this->input->post('prodi')) { // kaprodi

				$id_surat = $this->input->post('id_surat');

				$surat = $this->surat_model->get_detail_surat($id_surat);

				echo '<pre>';
				print_r($this->input->post());
				echo '</pre>';

				$result = $this->db->set('id_status', 8)
					->set('date', 'NOW()', FALSE)
					->set('id_surat', $id_surat)
					->set('pic', $this->session->userdata('user_id'))
					->insert('surat_status');

				if ($result) {
					$data_notif = array(
						'id_surat' => $id_surat,
						'id_status' => 8,
						'kepada' => $this->input->post('user_id'),
						'role' => array(3, 1)
					);

					//sendmail & notif
					$this->mailer->send_mail($data_notif);

					// setelah diacc kaprodi, isi tbl no_surat
					$this->db->insert('no_surat', ['id_surat' => $id_surat, 'hal' => $surat['kategori_surat']]);

					//remove notif yg berkaitan sama surat ini
					$this->db->update('notif', ['dibaca' => date('Y-m-d H:i:s'), 'status' => 1], ['id_surat' => $id_surat, 'role' => $this->session->userdata('role')]);

					$this->session->set_flashdata('msg', 'Surat sudah diberi persetujuan oleh Kaprodi!');
					redirect(base_url('admin/surat/detail/' . encrypt_url($id_surat)));
				}
			}
		}
	}

	public function pratinjau()
	{
		if ($this->input->post('submit')) {
			$id_surat = $this->input->post('id_surat');

			$this->form_validation->set_rules(
				'no_surat',
				'Nomor Surat',
				'trim|required',
				array('required' => '%s wajib diisi.')
			);
			$this->form_validation->set_rules(
				'kat_tujuan_surat',
				'Kategori Tujuan Surat',
				'trim|required',
				array('required' => '%s wajib diisi.')
			);
			$this->form_validation->set_rules(
				'tujuan_surat',
				'Tujuan Surat',
				'trim|required',
				array('required' => '%s wajib diisi.')
			);
			$this->form_validation->set_rules(
				'urusan_surat',
				'Urusan Surat',
				'trim|required',
				array('required' => '%s wajib diisi.')
			);
			$this->form_validation->set_rules(
				'template_surat',
				'Template Surat',
				'required',
				array('required' => '%s wajib diisi.')
			);
			$this->form_validation->set_rules(
				'instansi',
				'Instansi',
				'trim|required',
				array('required' => '%s wajib diisi.')
			);
			$this->form_validation->set_rules(
				'hal',
				'hal',
				'trim|required',
				array('required' => '%s wajib diisi.')
			);

			if ($this->form_validation->run() == FALSE) {
				$data['status'] = $this->surat_model->get_surat_status($id_surat);
				$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
				$data['timeline'] = $this->surat_model->get_timeline($id_surat);
				$data['fields'] = $this->surat_model->get_fields_by_id_kat_surat($data['surat']['id_kategori_surat']);
				$data['template'] = $this->template_model->get_template_bykat($data['surat']['id_kategori_surat']);
				$data['header'] = 'header';

				$data['title'] = 'Detail Surat';
				$data['view'] = 'surat/detail';
				$this->load->view('layout/layout', $data);
			} else {

				$stempel_basah = $this->input->post('stempel_basah');
				$id_kategori_surat = $this->input->post('id_kategori_surat');
				$no_surat = $this->input->post('no_surat');
				$kat_tujuan_surat = $this->input->post('kat_tujuan_surat');
				$tujuan_surat = $this->input->post('tujuan_surat');
				$template_surat = $this->input->post('template_surat');
				$urusan_surat = $this->input->post('urusan_surat');
				$date = date('Y-m-d');
				$tanggal_surat = tgl_indo(date("Y-m-j", strtotime($date)));
				$no_surat =	 $this->surat_model->generate_no_surat($no_surat, $kat_tujuan_surat, $tujuan_surat, $urusan_surat, $date);

				$pratinjau = array(
					'stempel_basah' => $stempel_basah,
					'template_surat' => $template_surat,
					'id_user' => $this->input->post('user_id'),
					'id_prodi' => $this->input->post('id_prodi'),
					'id_kategori_surat' => $id_kategori_surat,
					'no_surat' => $no_surat,
					'kat_tujuan_surat' => $kat_tujuan_surat,
					'tujuan_surat' => $tujuan_surat,
					'urusan_surat' => $urusan_surat,
					'tembusan' => $this->input->post('tembusan'),
					'instansi' => $this->input->post('instansi'),
					'lamp' => $this->input->post('lamp'),
					'hal' => $this->input->post('hal'),
					'tanggal_terbit' => $date,
					'no_lengkap' => $no_surat,
				);

				$update = $this->db->update('no_surat', $pratinjau, array('id_surat' => $id_surat));

				$data['pratinjau'] = $pratinjau;
				$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
				$data['no_surat'] = $no_surat;
				$data['tanggal_surat'] = $tanggal_surat;
				$data['fields'] = $this->surat_model->get_fields_by_id_kat_surat($data['surat']['id_kategori_surat']);
				$data['template_surat'] = $this->template_model->get_template_byid($template_surat);
				$data['header'] = 'header';


				if ($update) {

					$data['title'] = 'Pratinjau Surat';
					$data['view'] = 'surat/pratinjau_surat';
				} else {
					$data['title'] = 'Terjadi kesalahan';
					$data['view'] = 'surat/pratinjau_surat_error';
				}

				$this->load->view('layout/layout', $data);
			}
		}
	}

	public function pratinjau_direktur($id_surat)
	{
		$id_surat = decrypt_url($id_surat);

		if ($id_surat) {
			$surat_terbit = $this->surat_model->get_no_surat($id_surat);
			$data['pratinjau'] = $surat_terbit;
			$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
			$tgl_surat = date("Y-m-j", strtotime($surat_terbit['tanggal_terbit']));
			$data['tanggal_surat'] = tgl_indo($tgl_surat);
			$data['template_surat'] = $this->template_model->get_template_byid($data['pratinjau']['template_surat']);
			$data['fields'] = $this->surat_model->get_fields_by_id_kat_surat($data['surat']['id_kategori_surat']);
			$data['header'] = 'header';
			//qrcode
			$this->load->library('ciqrcode');
			$params['data'] = base_url('validasi/cekvalidasi/' . encrypt_url($id_surat));
			$params['level'] = 'L';
			$params['size'] = 2;
			$params['savename'] = FCPATH . "public/documents/tmp/" . $id_surat . '-qr.png';
			$this->ciqrcode->generate($params);

			if ($data['surat']['kode'] == 'SU') {
				$kategori = $surat_terbit['hal'];
			} else {
				$kategori = $data['surat']['kategori_surat'];
			}

			$nim = $data['surat']['username'];

			$filename = strtolower(str_replace(' ', '-', $kategori) . '-' . $nim . '-' . date('Y-m-j') . '-' . $id_surat);

			$edit_nosurat = array(
				'file' => $filename . '.pdf',
			);
			$this->db->update('no_surat', $edit_nosurat, array('id' => $surat_terbit['id']));

			$now = new DateTime(null, new DateTimeZone('Asia/Jakarta'));
			$now->setTimezone(new DateTimeZone('Asia/Jakarta'));    // Another way

			// $view = $this->load->view('surat/tampil_surat', $data, TRUE);
			$this->load->view('surat/tampil_surat', $data);
		}
	}

	public function terbitkan_surat()
	{

		if ($this->input->post('submit')) {
			$id_surat = $this->input->post('id_surat');
			$no_surat = $this->surat_model->get_no_surat($id_surat);

			$data['pratinjau'] = $no_surat;

			$this->db->set('id_status', 10)
				->set('date', 'NOW()', FALSE)
				->set('id_surat', $id_surat)
				->set('pic', $data['pratinjau']['id_user'])
				->insert('surat_status');

			$data_notif = array(
				'id_surat' => $id_surat,
				'id_status' => 10,
				'kepada' => $data['pratinjau']['id_user'],
				'role' => array(3)
			);

			//sendmail & notif
			$this->mailer->send_mail($data_notif);

			//remove notif yg berkaitan sama surat ini
			$set_notif = $this->db->update('notif', ['dibaca' => date('Y-m-d H:i:s'), 'status' => 1], ['id_surat' => $id_surat, 'role' => $this->session->userdata('role')]);


			$this->session->set_flashdata('msg', 'Surat berhasil diterbitkan!');
			redirect(base_url('admin/surat/detail/' . encrypt_url($id_surat)));
		}
	}

	public function cetak_surat($id_surat, $header)
	{
		$id_surat = decrypt_url($id_surat);

		if ($id_surat) {
			$surat_terbit = $this->surat_model->get_no_surat($id_surat);
			$data['pratinjau'] = $surat_terbit;
			$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
			$tgl_surat = date("Y-m-j", strtotime($surat_terbit['tanggal_terbit']));
			$data['tanggal_surat'] = tgl_indo($tgl_surat);

			$data['template_surat'] = $this->template_model->get_template_byid($data['pratinjau']['template_surat']);
			$data['fields'] = $this->surat_model->get_fields_by_id_kat_surat($data['surat']['id_kategori_surat']);
			$data['header'] = $header;

			//qrcode
			$this->load->library('ciqrcode');
			$params['data'] = base_url('validasi/cekvalidasi/' . encrypt_url($id_surat));
			$params['level'] = 'L';
			$params['size'] = 2;
			$params['savename'] = FCPATH . "public/documents/tmp/" . $id_surat . '-qr.png';
			$this->ciqrcode->generate($params);

			if ($data['surat']['kode'] == 'SU') {
				$kategori = $surat_terbit['hal'];
			} else {
				$kategori = $data['surat']['kategori_surat'];
			}

			$nim = $data['surat']['username'];

			$filename = strtolower(str_replace(' ', '-', $kategori) . '-' . $nim . '-' . date('Y-m-j') . '-' . $id_surat);

			$edit_nosurat = array(
				'file' => $filename . '.pdf',
			);
			$this->db->update('no_surat', $edit_nosurat, array('id' => $surat_terbit['id']));

			$now = new DateTime(null, new DateTimeZone('Asia/Jakarta'));
			$now->setTimezone(new DateTimeZone('Asia/Jakarta'));    // Another way

			$view = $this->load->view('surat/tampil_surat', $data, TRUE);
			// $this->load->view('surat/tampil_surat', $data);

			if ($header == 'header') {
				$mpdf = new \Mpdf\Mpdf([
					'tempDir' => 'public/documents/pdfdata',
					'mode' => 'utf-8',
					'format' => 'A4',
					'margin_left' => 0,
					'margin_right' => 0,
					'margin_footer' => 4,
					'margin_bottom' => 40,
					'margin_top' => 0,
					'float' => 'left',
					'setAutoTopMargin' => 'stretch'
				]);


				$mpdf->SetHTMLHeader('
				<div style="text-align: left; margin-left:85px;margin-bottom:10px;">
						<img width="390" height="" src="' . base_url() . '/public/dist/img/logokop-pasca.jpg" />
				</div>');

				$mpdf->SetHTMLFooter('		
				<div class="futer">
				<p>Digenerate oleh Sistem Layanan Pascasarjana UMY pada tanggal ' . $now->format("d-m-Y H:i") . '</p>				
				</div>');


				$mpdf->WriteHTML($view);

				$mpdf->Output($filename . '.pdf', 'D');
			} else {

				$mpdf2 = new \Mpdf\Mpdf([
					'tempDir' => 'public/documents/pdfdata',
					'mode' => 'utf-8',
					// 'format' => [24, 24],
					'format' => 'A4',
					'margin_left' => 0,
					'margin_right' => 0,
					'margin_footer' => 3,
					'margin_bottom' => 40,
					'margin_top' => 40,
					'float' => 'left',
					'setAutoTopMargin' => 'stretch'
				]);

				$mpdf2->SetHTMLHeader('');
				$mpdf2->SetHTMLFooter('
		
				<div class="futers" stlye="padding-left:20px;">
					<table style="width:90%; margin:0 auto;">
						<tr>
							<td style="width:85%; vertical-align:bottom; padding-bottom:9px;"><p style="text-align:left; font-size:8pt;font-style:italic;">Digenerate oleh Sistem Layanan Pascasarjana UMY pada tanggal ' . $now->format("d-m-Y H:i") . '</p></td>
							<td style="text-align:right;padding-bottom:40px;"><img src="' . base_url('public/documents/tmp/') . $id_surat . '-qr.png" /></td>
						</tr>
					</table>					
				</div>');


				$mpdf2->WriteHTML($view);

				$mpdf2->Output($filename . '-nh.pdf', 'D');
			}
		}
	}

	public function cetak_surat_lama($id_surat, $header)
	{

		$id_surat = decrypt_url($id_surat);

		if ($id_surat) {

			$nmr_surat = $this->surat_model->get_no_surat($id_surat);

			if ($nmr_surat) {

				$this->load->library('ciqrcode');

				$params['data'] = base_url('validasi/cekvalidasi/' . encrypt_url($id_surat));
				$params['level'] = 'L';
				$params['size'] = 2;
				$params['savename'] = FCPATH . "public/documents/tmp/" . $id_surat . '-qr.png';
				$this->ciqrcode->generate($params);

				$data['pratinjau'] = $nmr_surat;
				$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
				$data['no_surat'] = $nmr_surat['no_lengkap'];

				$tgl_surat = date("Y-m-d", strtotime($nmr_surat['tanggal_terbit']));
				$data['tanggal_surat'] = tgl_indo($tgl_surat);

				if ($data['surat']['kode'] == 'SU') {
					$kategori = $nmr_surat['hal'];
				} else {
					$kategori = $data['surat']['kategori_surat'];
				}

				$nim = $data['surat']['username'];

				$filename = strtolower(str_replace(' ', '-', $kategori) . '-' . $nim . '-' . date('Y-m-d') . '-' . $id_surat);

				$edit_nosurat = array(
					'file' => $filename . '.pdf',
				);
				$this->db->update('no_surat', $edit_nosurat, array('id' => $nmr_surat['id']));

				$view = $this->load->view('surat/tampil_surat_lama', $data, TRUE);
				$data['view'] = 'surat/tampil_surat_lama';

				$now = new DateTime(null, new DateTimeZone('Asia/Jakarta'));
				$now->setTimezone(new DateTimeZone('Asia/Jakarta'));    // Another way


				$mpdf = new \Mpdf\Mpdf([
					'tempDir' => 'public/documents/pdfdata',
					'mode' => 'utf-8',
					// 'format' => [24, 24],
					'format' => 'A4',
					'margin_left' => 0,
					'margin_right' => 0,
					'margin_footer' => 3,
					'margin_bottom' => 40,
					'margin_top' => 20,
					'float' => 'left',
					'setAutoTopMargin' => 'stretch'
				]);


				$mpdf->SetHTMLHeader('
				<div style="text-align: left; margin-left:85px;margin-bottom:20px;">
						<img width="390" height="" src="' . base_url() . '/public/dist/img/logokop-pasca.jpg" />
				</div>');
				$mpdf->SetHTMLFooter('
		
				<div class="futer">
					<table style="width:100%">
						<tr>
							<td style="width:85%; vertical-align:bottom; padding-bottom:9px;"><p style="text-align:left; font-size:7pt;font-style:italic;">Digenerate oleh Sistem Layanan Pascasarjana UMY pada tanggal ' . $now->format("d-m-Y H:i") . '</p></td>
							<td style="text-align:right;padding-bottom:40px;"><img src="' . base_url('public/documents/tmp/') . $id_surat . '-qr.png" /></td>
						</tr>
					</table>					
				</div>');

				$mpdf->WriteHTML($view);

				$mpdf->Output($filename . '.pdf', 'D');
			}
		}
	}


	public function get_tujuan_surat()
	{
		$kat_tujuan = $this->input->post('kat_tujuan_surat');
		if ($kat_tujuan) {
			$data = $this->db->query("SELECT * FROM tujuan_surat WHERE id_kat_tujuan_surat = $kat_tujuan")->result_array();
			echo json_encode($data);
		}
	}


	/* 
	Pengajuan Susrat oleh Admin Pasca (surat internal) */

	public function internal($role = 0)
	{
		$role = $_SESSION['role'];
		$data['query'] = $this->surat_model->get_surat_internal($role);
		$data['title'] = 'Pengajuan Saya';
		$data['view'] = 'surat/internal';
		$this->load->view('layout/layout', $data);
	}


	public function ajukan($id_kategori = 0)
	{
		$data['kategori_surat'] = $this->surat_model->get_kategori_surat('p');
		$data['title'] = 'Buat Surat';
		$data['view'] = 'surat/ajukan';
		$this->load->view('layout/layout', $data);
	}

	public function buat_surat($id)
	{
		$data = array(
			'id_kategori_surat' => $id,
			'id_mahasiswa' => $this->session->userdata('user_id'),
		);

		$data = $this->security->xss_clean($data);
		$result = $this->surat_model->tambah($data);
		//ambil last id surat yg baru diinsert
		$insert_id = $this->db->insert_id();
		// set status surat
		$status_surat = array(
			'id_surat' => $insert_id,
			'id_status' => 1,
			'pic' => $this->session->userdata('user_id'),
			'date' => date('Y-m-d H:i:s'),
		);
		$status_surat = $this->security->xss_clean($status_surat);
		$this->db->insert('surat_status', $status_surat);

		//ambil id surat berdasarkan last id status surat
		$insert_id2 = $this->db->select('id_surat')->from('surat_status')->where('id=', $this->db->insert_id())->get()->row_array();
		// ambil keterangan surat berdasar kategori surat
		$kat_surat = $this->db->select('*')->from('kat_keterangan_surat')->where('id_kategori_surat=', $id)->get()->result_array();


		if ($kat_surat) {

			foreach ($kat_surat as $row) {

				$keterangan_surat = array(
					'value' => '',
					'id_surat' =>  $insert_id2['id_surat'],
					'id_kat_keterangan_surat' => $row['id'],
				);

				$keterangan_surat = $this->security->xss_clean($keterangan_surat);

				$this->db->insert('keterangan_surat', $keterangan_surat);
			}
		}


		$data_notif = array(
			'id_surat' => $insert_id2['id_surat'],
			'id_status' => 1,
			'kepada' => $_SESSION['user_id'],
			'role' => array(3)
		);

		$results = $this->notif_model->send_notif($data_notif);

		if ($results) {
			$this->session->set_flashdata('msg', 'Berhasil!');
			redirect(base_url('admin/surat/tambah/' . encrypt_url($insert_id)));
		}
	}

	public function tambah($id_surat = 0)
	{
		$id_surat = decrypt_url($id_surat);

		$id_notif = $this->input->post('id_notif');

		if ($this->input->post('submit')) {

			echo '<pre>';
			print_r($this->input->post('dokumen'));
			echo '</pre>';

			// validasi form, form ini digenerate secara otomatis
			foreach ($this->input->post('dokumen') as $id => $dokumen) {
				$this->form_validation->set_rules(
					'dokumen[' . $id . ']',
					kat_keterangan_surat($id)['kat_keterangan_surat'],
					'trim|required',
					array('required' => '%s wajib diisi.')
				);
			}

			if ($this->form_validation->run() == FALSE) {
				$data['kategori_surat'] = $this->surat_model->get_kategori_surat('p');
				$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
				$data['fields'] = $this->surat_model->get_fields_by_id_kat_surat($data['surat']['id_kategori_surat']);
				$data['timeline'] = $this->surat_model->get_timeline($id_surat);

				$data['title'] = 'Ajukan Surat';
				$data['view'] = 'surat/tambah';
				$this->load->view('layout/layout', $data);
			} else {

				//cek dulu apakah ini surat baru atau surat revisi
				if ($this->input->post('revisi')) {
					$id_status = 5;
				} else {
					$id_status = 8;
				}

				//tambah status ke tb surat_status
				$insert = $this->db->set('id_surat', $id_surat)
					->set('id_status', $id_status) //baru
					->set('pic', $this->session->userdata('user_id'))
					->set('date', 'NOW()', FALSE)
					->insert('surat_status');

				//insert field ke tabel keterangan_surat
				if ($insert) {
					foreach ($this->input->post('dokumen') as $id => $dokumen) {
						$this->db->where(array('id_kat_keterangan_surat' => $id, 'id_surat' => $id_surat));
						$this->db->update(
							'keterangan_surat',
							array(
								'value' => $dokumen
							)
						);
					}

					// kirim notifikasi
					$data_notif = array(
						'id_surat' => $id_surat,
						'id_status' => 8,
						'kepada' => $_SESSION['user_id'],
						'role' => array(1) // harus dalam bentuk array
					);

					//sendmail & notif
					$this->mailer->send_mail($data_notif);

					// setelah diacc kaprodi, isi tbl no_surat
					$this->db->insert('no_surat', ['id_surat' => $id_surat]);

					// hapus notifikasi "Lengkapi dokumen"
					$set_status = $this->db->set('status', 1)
						->set('dibaca', 'NOW()', FALSE)
						->where(array('id' => $id_notif, 'status' => 0))
						->update('notif');

					if ($set_status) {
						redirect(base_url('admin/surat/tambah/' . encrypt_url($id_surat)));
					}
				}
			}
		} else {

			if ($id_surat) {
				$data['kategori_surat'] = $this->surat_model->get_kategori_surat('p');
				$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
				$data['fields'] = $this->surat_model->get_fields_by_id_kat_surat($data['surat']['id_kategori_surat']);
				$data['timeline'] = $this->surat_model->get_timeline($id_surat);

				if ($data['surat']['id_status'] == 10) {
					$data['no_surat_final'] = $this->surat_model->get_no_surat($id_surat);
				}

				if ($data['surat']['id_mahasiswa'] == $this->session->userdata('user_id')) {
					$data['title'] = 'Ajukan Surat';
					$data['view'] = 'surat/tambah';
				} else {
					$data['title'] = 'Forbidden';
					$data['view'] = 'restricted';
				}
			} else {
				$data['title'] = 'Halaman tidak ditemukan';
				$data['view'] = 'error404';
			}

			$this->load->view('layout/layout', $data);
		}
	}

	//---------------------------------------------------
	// Advanced Search Example
	public function arsip($klien = null)
	{
		$data['klien'] =  $klien;

		$data['kategori_surat'] =  $this->surat_model->get_kategori_surat($klien);
		$prodi = $this->db->select('*')->from('prodi')->get()->result_array();
		$data['prodi'] =  $prodi;

		$this->session->unset_userdata('kategori_surat');
		$this->session->unset_userdata('prodi');
		$this->session->unset_userdata('klien');

		$data['title'] = 'Arsip Surat Keluar';
		$data['view'] = 'surat/arsip2';
		$this->load->view('layout/layout', $data);
	}

	//-------------------------------------------------------
	function search()
	{

		$kategori_surat = $this->input->post('kategori_surat');
		// $klien = $this->input->post('klien');

		$this->session->set_userdata('kategori_surat', $this->input->post('kategori_surat'));
		$this->session->set_userdata('prodi', $this->input->post('prodi'));
		// $this->session->set_userdata('arsip_search_from',$this->input->post('arsip_search_from'));
		// $this->session->set_userdata('arsip_search_to',$this->input->post('arsip_search_to'));

		// echo "sesi";
		echo json_encode($kategori_surat);
	}

	function sesi()
	{
		echo '<pre>';
		print_r($_SESSION);
		echo '</pre>';;

		echo '<pre>';
		print_r($this->surat_model->get_surat_arsip(''));
		echo '</pre>';
	}

	//---------------------------------------------------
	// Server-side processing Datatable Example with Advance Search
	public function arsip_json($klien = null)
	{

		$records['data'] = $this->surat_model->get_surat_arsip($klien);
		$data = array();
		foreach ($records['data']  as $row) {
			$data[] = array(
				$row['no_lengkap'],
				$row['tanggal_terbit'],
				$row['fullname'],
				$row['kategori_surat'],
				$row['prodi'],
				$row['instansi'],
				$row['hal'],

				'<a class="btn btn-sm btn-success" href="' . base_url('/admin/surat/cetak_surat/' . encrypt_url($row['id_surat'])) . '/header"><i class="fas fa-file-pdf"></i></a>',
			);
		}
		$records['data'] = $data;
		echo json_encode($records);
	}

	public function get_kategori_surat_by_klien()
	{
		$klien = $this->session->userdata('klien');
		$data = $this->db->query("SELECT id, kategori_surat FROM kategori_surat WHERE klien = '$klien'")->result_array();
		echo json_encode($data);
	}

	public function persetujuan_kaprodi()
	{
		$id_surat = $this->input->post('id_surat');
		$id_mhs = $this->input->post('id_mhs');
		//set status
		$this->db->set('id_status', '8')
			->set('pic', $this->session->userdata('user_id'))
			->set('date', 'NOW()', FALSE)
			->set('id_surat', $id_surat)
			->set('catatan', '')
			->insert('surat_status');

		//hapus notif yg berkaitan

		//remove notif yg berkaitan sama surat ini
		$set_notif = $this->db->update('notif', ['dibaca' => date('Y-m-d H:i:s'), 'status' => 1], ['id_surat' => $id_surat, 'role' => 6]);


		redirect(base_url('admin/surat/detail/' . encrypt_url($id_surat) . '/' . $id_mhs));
	}

	public function persetujuan_kaprodi_yudisium()
	{
		$id_surat = $this->input->post('id_surat');
		$id_mhs = $this->input->post('id_mhs');
		//set status
		$this->db->set('id_status', '11')
			->set('pic', $this->session->userdata('user_id'))
			->set('date', 'NOW()', FALSE)
			->set('id_surat', $id_surat)
			->set('catatan', '')
			->insert('surat_status');

		//hapus notif yg berkaitan

		//remove notif yg berkaitan sama surat ini
		$set_notif = $this->db->update('notif', ['dibaca' => date('Y-m-d H:i:s'), 'status' => 1], ['id_surat' => $id_surat, 'role' => 6]);

		//set Yudisium
		$this->db->set('user_id', $id_mhs)
			->set('id_surat', $id_surat)
			->insert('yudisium');


		redirect(base_url('admin/surat/detail/' . encrypt_url($id_surat) . '/' . $id_mhs));
	}

	public function persetujuan_direktur()
	{
		$id_surat = $this->input->post('id_surat');
		$id_mhs = $this->input->post('id_mhs');
		//set status
		$this->db->set('id_status', '9')
			->set('pic', $this->session->userdata('user_id'))
			->set('date', 'NOW()', FALSE)
			->set('id_surat', $id_surat)
			->set('catatan', '')
			->insert('surat_status');

		//hapus notif yg berkaitan

		//remove notif yg berkaitan sama surat ini
		$set_notif = $this->db->update('notif', ['dibaca' => date('Y-m-d H:i:s'), 'status' => 1], ['id_surat' => $id_surat, 'role' => 8]);

		redirect(base_url('admin/surat/detail/' . encrypt_url($id_surat) . '/' . $id_mhs));
	}

	public function yudisium()
	{
		$data['query'] = $this->surat_model->get_surat_yudisium();
		$data['title'] = 'Surat Pendaftaran Yudisium';
		$data['view'] = 'surat/index_yudisium';
		$this->load->view('layout/layout', $data);
	}

	public function acc_yudisium()
	{

		if ($this->input->post('submit')) {

			$verifikasi = $this->input->post('verifikasi'); //ambil nilai 
			$id_surat = $this->input->post('id_surat');
			$id_notif = $this->input->post('id_notif');

			//set status
			$this->db->set('id_status', $this->input->post('rev2'))
				->set('pic', $this->session->userdata('user_id'))
				->set('date', 'NOW()', FALSE)
				->set('id_surat', $id_surat)
				->set('catatan', $this->input->post('catatan'))
				->insert('surat_status');

			foreach ($verifikasi as $id => $value_verifikasi) {

				$this->db->where(array('id_kat_keterangan_surat' => $id, 'id_surat' => $id_surat))
					->update(
						'keterangan_surat',
						array(
							'verifikasi' =>  $value_verifikasi,
						)
					);
			}

			if ($this->input->post('rev2') === '6') { // ditolak
				$role = array(3);
				$id_status = 6;
			} else if ($this->input->post('rev2') == 4) { //revisi
				$role = array(3);
				$id_status = 4;
			} else if ($this->input->post('rev2') == 7) { // acc kaprodi
				$role = array(3);		
				$id_status = 7;		
		
			} else if ($this->input->post('rev2') == 12) { // acc yudisium
				$role = array(3);		
				$id_status = 12;		
						//set Yudisium
						$yudisium = $this->db->set('user_id', $this->input->post('user_id'))
						->insert('yudisium');
			}

			
			// buat notifikasi
			$data_notif = array(
				'id_surat' => $id_surat,
				'id_status' => $id_status,
				'kepada' => $this->input->post('user_id'),
				'role' => $role
			);

			//sendmail & notif
			$this->mailer->send_mail($data_notif);

			// remove notif yg berkaitan sama surat ini
			$set_notif = $this->db->update('notif', ['dibaca' => date('Y-m-d H:i:s'), 'status' => 1], ['id_surat' => $id_surat, 'role' => $this->session->userdata('role')]);

		if ($set_notif) {
				$this->session->set_flashdata('msg', 'Pendaftaran Yudisium selesai diverifikasi!');
				redirect(base_url('admin/surat/detail/' . encrypt_url($id_surat)));
			}
		} else {
			$data['title'] = 'Forbidden';
			$data['view'] = 'restricted';
			$this->load->view('layout/layout', $data);
		}
	}

	public function editfield()
	{

		$id = 	$this->input->post('id');
		$pengajuan_id = 	$this->input->post('pengajuan_id');


		$update_field = $this->db->where(array('id_kat_keterangan_surat' => $id, 'id_surat' => $pengajuan_id))
			->update(
				'keterangan_surat',
				array(
					'value' =>  $this->input->post('valfield'),
					// 'tanggal_edit' => date('Y-m-d h:m:s'),
					'diedit_oleh' =>  $this->session->userdata('user_id'),
				)
			);
		if ($update_field) {
			$data = [
				'status' => 'sukses',
				'id' => $this->input->post('id'),
				'pengajuan_id' => $this->input->post('pengajuan_id'),
				'value' => $this->input->post('valfield'),
			];
		}

		echo json_encode($data);
	}
}
