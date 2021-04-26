<?php defined('BASEPATH') or exit('No direct script access allowed');
class Surat extends Mahasiswa_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('mailer');
		$this->load->model('surat_model', 'surat_model');
		$this->load->model('notif/Notif_model', 'notif_model');
		$this->load->helper('date');
	}

	public function index()
	{
		$data['query'] = $this->surat_model->get_surat_bymahasiswa($this->session->userdata('user_id'));
		$data['title'] = 'Surat Saya';
		$data['view'] = 'surat/index';
		$this->load->view('layout/layout', $data);
	}
	
	public function detail($id_surat = 0)
	{
		$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
		$data['title'] = $data['surat']['id_mahasiswa'];
		$data['view'] = 'surat/detail';
		$this->load->view('layout/layout', $data);
	}

	public function ajukan($id_kategori = 0)
	{
		$data['kategori_surat'] = $this->surat_model->get_kategori_surat('m');
		$data['title'] = 'Ajukan Surat';
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
		$this->db->set('id_surat', $insert_id)
			->set('id_status', 1)
			->set('pic', $this->session->userdata('user_id'))
			->set('date', 'NOW()', FALSE)
			->insert('surat_status');

		//ambil id surat berdasarkan last id status surat
		$insert_id2 = $this->db->select('id_surat')->from('surat_status')->where('id=', $this->db->insert_id())->get()->row_array();
		// ambil keterangan surat berdasar kategori surat
		$kat_surat = $this->db->select('*')->from('kat_keterangan_surat')->where('id_kategori_surat=', $id)->get()->result_array();

		if ($kat_surat) {

			foreach ($kat_surat as $row) {

				$this->db->insert(
					'keterangan_surat',
					array(
						'value' => '',
						'id_surat' =>  $insert_id2['id_surat'],
						'id_kat_keterangan_surat' => $row['id'],
					)
				);
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
			redirect(base_url('mahasiswa/surat/tambah/' . encrypt_url($insert_id)));
		}
	}

	public function tambah($id_surat = 0)
	{
		$id_surat = decrypt_url($id_surat);

		$id_notif = $this->input->post('id_notif');

		if ($this->input->post('submit')) {

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
				$data['kategori_surat'] = $this->surat_model->get_kategori_surat('m');
				$data['fields'] = $this->surat_model->get_fields_by_id_kat_surat($data['surat']['id_kategori_surat']);
				$data['surat'] = $this->surat_model->get_detail_surat($id_surat);
				$data['timeline'] = $this->surat_model->get_timeline($id_surat);

				$data['title'] = 'Ajukan Surat';
				$data['view'] = 'surat/tambah';
				$this->load->view('layout/layout', $data);
			} else {

				//cek dulu apakah ini surat baru atau surat revisi
				if ($this->input->post('revisi')) {
					$id_status = 5;
				} else {
					$id_status = 2;
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
						'id_status' => 2,
						'kepada' => $_SESSION['user_id'],
						'role' => array(2) // harus dalam bentuk array
					);

					//sendmail & notif
					$this->mailer->send_mail($data_notif);

					// hapus notifikasi "Lengkapi dokumen"
					$set_status = $this->db->set('status', 1)
						->set('dibaca', 'NOW()', FALSE)
						->where(array('id' => $id_notif, 'status' => 0))
						->update('notif');

					if ($set_status) {
						redirect(base_url('mahasiswa/surat/tambah/' . encrypt_url($id_surat)));
					}
				}
			}
		} else {

			if ($id_surat) {
				$data['kategori_surat'] = $this->surat_model->get_kategori_surat('m');
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

	public function edit()
	{
		$data['query'] = $this->surat_model->get_surat();
		$data['title'] = 'Ajukan Surat';
		$data['view'] = 'surat/tambah';
		$this->load->view('layout/layout', $data);
	}

	public function hapus($id_surat = 0)
	{
		$surat_exist = $this->surat_model->get_detail_surat($id_surat);
		if ($surat_exist['id_status'] == 4) {
			$this->db->delete('surat', array('id' => $id_surat));
			$this->session->set_flashdata('msg', 'Surat berhasil dihapus');
			redirect(base_url('mahasiswa/surat'));
		} else {
			$this->session->set_flashdata('msg', 'Surat Gagal dihapus');
			redirect(base_url('mahasiswa/surat'));
		}
	}

	public function hapus_file()
	{
		$id = $_POST['id'];
		$media = $this->db->get_where('media', array('id' => $id))->row_array();
		$exist = is_file($media['thumb']);

		if ($media['thumb']) {
			if (is_file($media['thumb'])) {
				unlink($media['thumb']);
				$thumb = 'deleted';
			}
		}
		if ($media['file']) {
			if (is_file($media['file'])) {
				unlink($media['file']);
				$file = 'deleted';
			}
		}

		$hapus = $this->db->delete('media', array('id' => $id));
		// if ($hapus) {
		echo json_encode(array(
			"statusCode" => 200,
			"id" => $file,
			'thumb' => ($media['thumb']) ? $thumb : 'gada',
			'file' => ($media['file']) ? $file : 'gada',
			// 'hapus' => $hapus
		));
		//}
	}

	public function doupload()
	{
		header('Content-type:application/json;charset=utf-8');
		$upload_path = 'uploads/dokumen'; // folder tempat menyimpan file yang diupload

		// cek, jika upload path belum ada, maka buat folder
		if (!is_dir($upload_path)) {
			mkdir($upload_path, 0777, TRUE);
		}

		// konfigurasi upload
		$config = array(
			'upload_path' => $upload_path,
			'allowed_types' => "jpg|png|jpeg|pdf",
			'overwrite' => FALSE,
		);
		//panggil library upload
		$this->load->library('upload', $config);

		//cek jika file gagal diupload
		if (!$this->upload->do_upload('file')) {
			//tampilkan pesan error
			$error = array('error' => $this->upload->display_errors());

			//kirim pesan error dalam format json
			echo json_encode([
				'status' => 'error',
				'message' => $error
			]);

			// jika file berhasil diupload
		} else {
			//masukkan hasil upload ke variabel $data
			$data = $this->upload->data();

			//cek file type apakah image atau bukan image
			//format file_type, contoh 'image/jpeg', 'application/pdf'
			$ext = explode('/', $data['file_type']);
			if ($ext[0] == 'image') {
				//jika image, maka file akan dibuatkan thumbnailnya
				$thumb = $this->_create_thumbs($data['file_name']);
				$thumb = $upload_path . '/' . $data['raw_name'] . '_thumb' . $data['file_ext'];
			} else {
				//jika bukan gambar, maka $thumb = '' (kosong)
				$thumb = '';
			}

			// insert file ke table 'media'
			$result = $this->db->insert(
				'media',
				array(
					'id_user' => $this->session->userdata('user_id'),
					'file' =>  $upload_path . '/' . $data['file_name'],
					'thumb' =>  $thumb,
					'extension' =>  $data['file_ext']
				)
			);

			//output dalam bentuk json
			echo json_encode([
				'status' => 'Ok',
				'id' => $this->db->insert_id(),
				'extension' =>  $data['file_ext'],
				'thumb' =>  $thumb,
				'orig' => $upload_path . '/' . $data['file_name']
			]);
		}
	}

	function _create_thumbs($upload_data)
	{
		// Image resizing config
		$upload_data = $this->upload->data();
		$image_config["image_library"] = "gd2";
		$image_config["source_image"] = $upload_data["full_path"];
		$image_config['create_thumb'] = true;
		$image_config['maintain_ratio'] = TRUE;
		$image_config['thumb_marker'] = "_thumb";
		$image_config['new_image'] = $upload_data["file_path"];
		$image_config['quality'] = "90%";
		$image_config['width'] = 100;
		$image_config['height'] = 100;
		$dim = (intval($upload_data["image_width"]) / intval($upload_data["image_height"])) - ($image_config['width'] / $image_config['height']);
		$image_config['master_dim'] = ($dim > 0) ? "height" : "width";

		$this->load->library('image_lib');
		$this->image_lib->initialize($image_config);

		if (!$this->image_lib->resize()) { //Resize image
			redirect("errorhandler"); //If error, redirect to an error page
		}
	}

	public function getPembimbing()
	{
		$search = $this->input->post('search');
		$result_anggota = $this->surat_model->getPembimbing($search);

		foreach ($result_anggota as $anggota) {
			$selectajax[] = [
				'value' => $anggota['id'],
				'id' => $anggota['id'],
				'text' => $anggota['fullname']
			];
			$this->output->set_content_type('application/json')->set_output(json_encode($selectajax));
		}
	}
}
